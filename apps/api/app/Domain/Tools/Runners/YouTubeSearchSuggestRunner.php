<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Contracts\UsesProvider;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
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
 * The expansion runs on a wall-clock budget rather than a fixed request count. An
 * alphabet sweep is 26 requests, and on a slow day that is a minute of a worker —
 * so it stops when the budget is spent and says how far it got.
 */
final class YouTubeSearchSuggestRunner implements Cacheable, ToolRunner, UsesProvider
{
    private const ENDPOINT = 'https://suggestqueries.google.com/complete/search';

    /** Whole seconds of wall clock the expansion may spend before it stops early. */
    private const BUDGET_SECONDS = 15.0;

    private const REQUEST_TIMEOUT = 2.5;

    private const MODIFIERS = [
        'seed' => ['label' => 'Just the seed', 'terms' => ['']],
        'questions' => ['label' => 'Questions', 'terms' => [
            'how', 'what', 'why', 'when', 'where', 'which', 'who', 'can', 'is',
        ]],
        'commercial' => ['label' => 'Commercial intent', 'terms' => [
            'best', 'cheap', 'free', 'vs', 'review', 'alternative', 'for beginners', 'tutorial',
        ]],
        'alphabet' => ['label' => 'A–Z sweep', 'terms' => [
            'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm',
            'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z',
        ]],
    ];

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
                    'description' => 'Questions finds the how/what/why searches; the A–Z sweep is the '
                        .'broadest and the slowest.',
                    'enum' => array_keys(self::MODIFIERS),
                    'default' => 'questions',
                ],
                'position' => [
                    'type' => 'string',
                    'title' => 'Where the modifier goes',
                    'description' => '“Before” finds “how to make sourdough starter”; “after” finds '
                        .'“sourdough starter a…”.',
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
        $expansion = $input->string('expansion', 'questions');
        $before = $input->string('position', 'before') === 'before';
        $region = mb_strtolower($input->string('region', 'US'));

        $modifier = self::MODIFIERS[$expansion] ?? self::MODIFIERS['questions'];

        $deadline = microtime(true) + self::BUDGET_SECONDS;
        $seen = [];
        $rows = [];
        $queried = 0;
        $stoppedEarly = false;

        foreach ($modifier['terms'] as $term) {
            if (microtime(true) > $deadline) {
                $stoppedEarly = true;
                break;
            }

            $query = $term === ''
                ? $keyword
                : ($before ? "{$term} {$keyword}" : "{$keyword} {$term}");

            $queried++;

            foreach ($this->suggest($query, $region) as $suggestion) {
                $normalised = mb_strtolower($suggestion);

                if (isset($seen[$normalised])) {
                    continue;
                }

                $seen[$normalised] = true;

                $rows[] = [
                    'suggestion' => $suggestion,
                    'modifier' => $term === '' ? '—' : $term,
                    'words' => count(preg_split('/\s+/u', $suggestion, -1, PREG_SPLIT_NO_EMPTY) ?: []),
                    'search' => 'https://www.youtube.com/results?search_query='.rawurlencode($suggestion),
                ];
            }
        }

        if ($rows === []) {
            throw ToolExecutionException::notFound("any suggestions for “{$keyword}”");
        }

        // Longer phrases are the ones worth making a video for: they are specific
        // enough that a small channel can actually rank for them.
        usort($rows, fn (array $a, array $b) => [$b['words'], $a['suggestion']] <=> [$a['words'], $b['suggestion']]);

        return ToolResult::table(
            columns: [
                ['key' => 'suggestion', 'label' => 'Search'],
                ['key' => 'modifier', 'label' => 'From'],
                ['key' => 'words', 'label' => 'Words', 'align' => 'right'],
                ['key' => 'search', 'label' => 'Open on YouTube', 'align' => 'right'],
            ],
            rows: $rows,
            summary: sprintf(
                '%d unique searches for “%s”, from %d %s queries, longest phrases first.',
                count($rows),
                $keyword,
                $queried,
                mb_strtolower($modifier['label']),
            ),
        )->withMeta([
            'keyword' => $keyword,
            'expansion' => $expansion,
            'region' => $region,
            'queries_made' => $queried,
            'suggestions' => count($rows),
        ])->withWarnings($this->warnings($stoppedEarly, $queried, count($modifier['terms'])));
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
     * Google's public, keyless suggestion endpoint — the same one the search box
     * itself calls, with `ds=yt` scoping it to YouTube.
     *
     * A failure returns nothing rather than throwing: one modifier timing out
     * should cost that modifier's suggestions, not the whole run.
     *
     * @return list<string>
     */
    private function suggest(string $query, string $region): array
    {
        $url = self::ENDPOINT.'?'.http_build_query([
            'client' => 'firefox',
            'ds' => 'yt',
            'gl' => $region,
            'q' => $query,
        ]);

        $response = SafeHttpClient::attempt($url, self::REQUEST_TIMEOUT);

        if ($response === null || ! $response->successful()) {
            return [];
        }

        $decoded = json_decode($response->body(), true);

        if (! is_array($decoded) || ! isset($decoded[1]) || ! is_array($decoded[1])) {
            return [];
        }

        return array_values(array_filter(
            $decoded[1],
            fn (mixed $suggestion) => is_string($suggestion) && $suggestion !== '',
        ));
    }
}
