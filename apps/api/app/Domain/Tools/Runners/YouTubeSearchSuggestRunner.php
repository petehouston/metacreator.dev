<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Contracts\UsesProvider;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Enums\ResultView;
use App\Domain\Tools\Exceptions\ToolExecutionException;
use App\Support\Http\SafeHttpClient;

/**
 * What YouTube's own search box suggests, expanded into a keyword list.
 *
 * Every suggestion is a real search other people typed — which makes this the one
 * kind of keyword research that needs no paid data source and no estimate. It does
 * not give volumes, and it deliberately does not pretend to: a tool that invented a
 * number here would be inventing the only figure anybody would act on.
 *
 * The result is grouped the way the search box behaves: what YouTube completes for
 * the bare seed first, then the question and intent phrasings, then the A–Z sweep.
 * Inside a group the order is YouTube's own ranking, because that ranking is the
 * closest thing in the data to popularity — re-sorting it throws away the only
 * signal the endpoint gives.
 */
final class YouTubeSearchSuggestRunner implements Cacheable, ToolRunner, UsesProvider
{
    private const ENDPOINT = 'https://suggestqueries.google.com/complete/search';

    /** Whole seconds of wall clock the expansion may spend before it stops early. */
    private const BUDGET_SECONDS = 15.0;

    private const REQUEST_TIMEOUT = 4.0;

    /** Requests fired at once. Enough to finish the sweep, small enough to be polite. */
    private const POOL_SIZE = 12;

    private const MODIFIERS = [
        'seed' => [
            'label' => 'Direct suggestions',
            'hint' => 'What the search box completes for the seed on its own.',
            'terms' => [''],
        ],
        'questions' => [
            'label' => 'Questions & long-tail',
            'hint' => 'The how/what/why phrasings — the searches a small channel can rank for.',
            'terms' => ['how', 'what', 'why', 'when', 'where', 'which', 'who', 'can', 'is'],
        ],
        'commercial' => [
            'label' => 'Commercial intent',
            'hint' => 'Searches made by someone comparing, choosing or about to buy.',
            'terms' => ['best', 'cheap', 'free', 'vs', 'review', 'alternative', 'for beginners', 'tutorial'],
        ],
        'alphabet' => [
            'label' => 'Alphabet expansion (A–Z)',
            'hint' => 'The seed plus each letter, the way the box fills in as you keep typing.',
            'terms' => [
                'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm',
                'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z',
            ],
        ],
    ];

    /** The one-run default: everything the search box would show, in reading order. */
    private const EVERYTHING = ['seed', 'questions', 'commercial', 'alphabet'];

    public static function key(): string
    {
        return 'youtube.search-suggest';
    }

    public function providers(): array
    {
        return ['youtube'];
    }

    public function cacheTtl(): int
    {
        return 21600;
    }

    public function inputSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'required' => ['keyword'],
            'additionalProperties' => false,
            'properties' => [
                'keyword' => [
                    'type' => 'string',
                    'title' => 'Seed keyword',
                    'description' => 'The topic to expand. Two or three words works better than one.',
                    'minLength' => 2,
                    'maxLength' => 80,
                    'examples' => ['sourdough starter'],
                ],
                'expansion' => [
                    'type' => 'string',
                    'title' => 'Expansion',
                    'description' => 'Everything runs all four groups in one pass; the others narrow the '
                        .'run to a single group.',
                    'enum' => ['everything', ...array_keys(self::MODIFIERS)],
                    'default' => 'everything',
                ],
                'position' => [
                    'type' => 'string',
                    'title' => 'Where the modifier goes',
                    'description' => '“Before” finds “how to make sourdough starter”; “after” finds '
                        .'“sourdough starter a…”, which is what typing into the box does.',
                    'enum' => ['before', 'after'],
                    'default' => 'before',
                ],
                'region' => [
                    'type' => 'string',
                    'title' => 'Region',
                    // Upper case because the form generator title-cases enum values
                    // for its labels, and "Us" is not a country.
                    'description' => 'Suggestions differ by country, often completely.',
                    'enum' => ['US', 'GB', 'CA', 'AU', 'IN', 'DE', 'FR', 'ES', 'BR', 'JP'],
                    'default' => 'US',
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $keyword = trim($input->string('keyword'));
        $expansion = $input->string('expansion', 'everything');
        $before = $input->string('position', 'before') === 'before';
        $region = mb_strtolower($input->string('region', 'US'));

        $wanted = $expansion === 'everything' || ! isset(self::MODIFIERS[$expansion])
            ? self::EVERYTHING
            : [$expansion];

        $deadline = microtime(true) + self::BUDGET_SECONDS;
        // Shared across groups: the alphabet sweep repeats plenty of what the
        // questions already found, and the same phrase twice is noise.
        $seen = [];
        $groups = [];
        $rows = [];
        $queried = 0;
        $planned = 0;
        $stoppedEarly = false;

        foreach ($wanted as $name) {
            $modifier = self::MODIFIERS[$name];
            $planned += count($modifier['terms']);

            if (microtime(true) > $deadline) {
                $stoppedEarly = true;

                continue;
            }

            $queries = [];

            foreach ($modifier['terms'] as $term) {
                $queries[$term] = $term === ''
                    ? $keyword
                    : ($before ? "{$term} {$keyword}" : "{$keyword} {$term}");
            }

            $groupRows = [];

            foreach ($this->suggestMany($queries, $region, $deadline, $queried, $stoppedEarly) as $term => $suggestions) {
                foreach ($suggestions as $suggestion) {
                    $normalised = mb_strtolower($suggestion);

                    if (isset($seen[$normalised])) {
                        continue;
                    }

                    $seen[$normalised] = true;

                    $groupRows[] = [
                        'rank' => count($groupRows) + 1,
                        'suggestion' => $suggestion,
                        'modifier' => $term === '' ? '—' : $term,
                        'words' => count(preg_split('/\s+/u', $suggestion, -1, PREG_SPLIT_NO_EMPTY) ?: []),
                        'search' => 'https://www.youtube.com/results?search_query='.rawurlencode($suggestion),
                    ];
                }
            }

            if ($groupRows === []) {
                continue;
            }

            $groups[] = [
                'label' => $modifier['label'],
                'hint' => $modifier['hint'],
                'count' => count($groupRows),
                'rows' => $groupRows,
            ];

            $rows = [...$rows, ...$groupRows];
        }

        if ($rows === []) {
            throw ToolExecutionException::notFound("any suggestions for “{$keyword}”");
        }

        return (new ToolResult(
            view: ResultView::Table,
            data: [
                'columns' => [
                    ['key' => 'rank', 'label' => '#', 'align' => 'right'],
                    // Suggestions never wrap: a keyword broken across two lines
                    // stops reading like the phrase somebody typed.
                    ['key' => 'suggestion', 'label' => 'Search suggestion',
                        'copyable' => true, 'copy_all' => true, 'wrap' => false],
                    ['key' => 'modifier', 'label' => 'From'],
                    // The link reads as the search it runs, not as the URL.
                    ['key' => 'search', 'label' => 'Open on YouTube', 'align' => 'right',
                        'type' => 'link', 'text_key' => 'suggestion'],
                ],
                'rows' => $rows,
                'groups' => $groups,
            ],
            summary: sprintf(
                '%d suggestions for “%s” across %d %s, in the order YouTube ranks them.',
                count($rows),
                $keyword,
                count($groups),
                count($groups) === 1 ? 'group' : 'groups',
            ),
        ))->withMeta([
            'keyword' => $keyword,
            'expansion' => $expansion,
            'region' => $region,
            'queries_made' => $queried,
            'suggestions' => count($rows),
            'groups' => array_map(
                fn (array $group) => ['label' => $group['label'], 'count' => $group['count']],
                $groups,
            ),
        ])->withWarnings($this->warnings($stoppedEarly, $queried, $planned));
    }

    /** @return list<string> */
    private function warnings(bool $stoppedEarly, int $queried, int $planned): array
    {
        $warnings = [
            'These are real searches, but there are no volumes here — nobody outside Google has them, '
            .'and a made-up number is the one figure you would actually act on.',
        ];

        if ($stoppedEarly) {
            $warnings[] = "The expansion stopped after {$queried} of {$planned} queries to stay inside its "
                .'time budget. Run it again to pick up the rest, or use a narrower expansion.';
        }

        return $warnings;
    }

    /**
     * One group's queries, fetched in batches and returned in the order asked for.
     *
     * @param  array<string, string>  $queries  modifier term => search query
     * @return array<string, list<string>>
     */
    private function suggestMany(
        array $queries,
        string $region,
        float $deadline,
        int &$queried,
        bool &$stoppedEarly,
    ): array {
        $results = [];

        foreach (array_chunk($queries, self::POOL_SIZE, preserve_keys: true) as $batch) {
            if (microtime(true) > $deadline) {
                $stoppedEarly = true;
                break;
            }

            // Keyed by position, not by the modifier term: the seed's term is the
            // empty string, which is not a key an HTTP pool can be asked to use.
            $terms = array_keys($batch);
            $urls = array_map(fn (string $query) => $this->url($query, $region), array_values($batch));
            $queried += count($urls);

            foreach (SafeHttpClient::attemptPool($urls, self::REQUEST_TIMEOUT) as $index => $response) {
                $results[$terms[$index]] = $response === null || ! $response->successful()
                    ? []
                    : $this->parse($response->body());
            }
        }

        return $results;
    }

    /**
     * Google's public, keyless suggestion endpoint — the same one the search box
     * itself calls, with `ds=yt` scoping it to YouTube.
     */
    private function url(string $query, string $region): string
    {
        return self::ENDPOINT.'?'.http_build_query([
            'client' => 'firefox',
            'ds' => 'yt',
            'gl' => $region,
            'q' => $query,
        ]);
    }

    /** @return list<string> */
    private function parse(string $body): array
    {
        $decoded = json_decode($body, true);

        if (! is_array($decoded) || ! isset($decoded[1]) || ! is_array($decoded[1])) {
            return [];
        }

        return array_values(array_filter(
            $decoded[1],
            fn (mixed $suggestion) => is_string($suggestion) && $suggestion !== '',
        ));
    }
}
