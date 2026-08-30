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
 * Twenty-five YouTube hashtags, built from what YouTube's own search box completes.
 *
 * Every other hashtag generator glues suffixes onto your topic and prints the
 * result — which is what {@see HashtagGeneratorRunner} does for the cross-platform
 * case, and it is fine as far as it goes. This one starts from real autocomplete
 * phrases instead, so the tags are made of words people demonstrably type into
 * YouTube rather than words a suffix list guessed at. Suffix-built tags are still
 * used, but only to top the list back up to 25 when autocomplete is thin, and they
 * are labelled as what they are.
 *
 * It shows no "used by 1M videos" figures. Nobody outside Google has hashtag
 * volumes, and that is the one number on the page a creator would actually act on,
 * so inventing it would be the whole tool lying. What replaces it is YouTube's real
 * placement rules — first three above the title, fifteen in the description, more
 * than sixty and every hashtag is ignored — which is advice you can check.
 */
final class YouTubeHashtagGeneratorRunner implements Cacheable, ToolRunner, UsesProvider
{
    private const ENDPOINT = 'https://suggestqueries.google.com/complete/search';

    /** The list is always this long: the tool's promise is 25 tags. */
    private const TARGET = 25;

    /** YouTube shows the first three description hashtags above the video title. */
    private const ABOVE_TITLE = 3;

    /** More than fifteen in the description and YouTube keeps only the first fifteen. */
    private const DESCRIPTION_LIMIT = 15;

    /** Past sixty hashtags anywhere on the upload, YouTube ignores all of them. */
    private const IGNORE_ALL_ABOVE = 60;

    private const REQUEST_TIMEOUT = 4.0;

    private const BUDGET_SECONDS = 10.0;

    /**
     * The modifiers to expand the seed against.
     *
     * Kept short on purpose: this is a hashtag tool, not the keyword expander next
     * door, and the phrasings below are the ones that produce compounds a creator
     * would actually tag with. An A–Z sweep would add mostly noise.
     */
    private const MODIFIERS = ['', 'how to', 'best', 'tutorial', 'for beginners', 'tips', 'diy', 'easy'];

    /**
     * Words dropped before a phrase becomes a tag.
     *
     * Nobody tags a video #howtomakeasourdoughstarter. Stripping the connective
     * tissue leaves the words that carry the search.
     */
    private const STOPWORDS = [
        'a', 'an', 'the', 'to', 'of', 'in', 'on', 'at', 'for', 'from', 'with', 'and', 'or',
        'is', 'are', 'was', 'were', 'be', 'do', 'does', 'did', 'how', 'what', 'why', 'when',
        'where', 'which', 'who', 'can', 'you', 'your', 'my', 'i', 'it', 'that', 'this',
    ];

    /** Format staples. A long-form upload gets none — see {@see self::staples()}. */
    private const SHORTS_STAPLES = ['shorts', 'youtubeshorts', 'shortsfeed', 'viralshorts'];

    private const LONG_FORM_STAPLES = ['youtube', 'youtuber', 'newvideo', 'subscribe'];

    /** Only used to top up a thin list, and labelled as guesswork when they are. */
    private const FALLBACK_SUFFIXES = [
        'tips', 'tutorial', 'guide', 'forbeginners', 'ideas', 'hacks', 'daily',
        'community', 'lover', 'life', 'howto', 'diy', 'challenge', 'review',
    ];

    /** A tag longer than this stops being readable and starts being a sentence. */
    private const MAX_TAG_LENGTH = 30;

    public static function key(): string
    {
        return 'youtube.hashtag-generator';
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
            'required' => ['topic'],
            'additionalProperties' => false,
            'properties' => [
                'topic' => [
                    'type' => 'string',
                    'title' => 'Describe your video topic',
                    'description' => 'What is the video about? Two or three words beat one.',
                    'minLength' => 2,
                    'maxLength' => 80,
                    'examples' => ['catching butterflies'],
                ],
                'format' => [
                    'type' => 'string',
                    'title' => 'Video format',
                    'description' => 'Shorts and long-form want different staple tags — #shorts on a '
                        .'ten-minute video is the classic own goal.',
                    'enum' => ['shorts', 'long-form', 'both'],
                    'default' => 'both',
                ],
                'extra_keywords' => [
                    'type' => 'string',
                    'title' => 'Related words (optional)',
                    'description' => 'Comma-separated. More context, sharper tags.',
                    'maxLength' => 200,
                    'default' => '',
                ],
                'region' => [
                    'type' => 'string',
                    'title' => 'Region',
                    'description' => 'Autocomplete differs by country, sometimes completely.',
                    'enum' => ['US', 'GB', 'CA', 'AU', 'IN', 'DE', 'FR', 'ES', 'BR', 'JP'],
                    'default' => 'US',
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $topic = trim($input->string('topic'));
        $format = $input->string('format', 'both');
        $region = mb_strtolower($input->string('region', 'US'));

        $words = self::words($topic.' '.$input->string('extra_keywords'));

        if ($words === []) {
            throw ToolExecutionException::invalidInput(
                'Enter a topic with at least two letters.',
                ['topic' => 'There is nothing here we can build tags from.'],
            );
        }

        // Order matters and is the ranking: the topic itself, then tags built from
        // real searches, then the format staples, then suffix guesses to top up.
        $tags = [];

        $this->add($tags, self::compound($words), 'Your topic', true);

        foreach ($this->fromAutocomplete($topic, $region) as $tag) {
            $this->add($tags, $tag, 'YouTube autocomplete', true);
        }

        foreach ($this->staples($format) as $staple) {
            $this->add($tags, $staple, 'Format staple', true);
        }

        foreach ($this->fallbacks($words) as $tag) {
            if (count($tags) >= self::TARGET) {
                break;
            }

            $this->add($tags, $tag, 'Built from your topic', false);
        }

        $tags = array_slice($tags, 0, self::TARGET);
        $grounded = count(array_filter($tags, fn (array $tag) => $tag['grounded']));

        return (new ToolResult(
            view: ResultView::Table,
            data: [
                'columns' => [
                    ['key' => 'rank', 'label' => '#', 'align' => 'right'],
                    // A hashtag broken across two lines stops reading as a hashtag.
                    // Copied space-separated, because that is how a description
                    // takes them — "#a, #b" is not something anyone pastes.
                    ['key' => 'hashtag', 'label' => 'Hashtag', 'copyable' => true,
                        'copy_all' => true, 'copy_separator' => ' ', 'wrap' => false],
                    ['key' => 'placement', 'label' => 'Where to put it'],
                    ['key' => 'breadth', 'label' => 'Breadth'],
                    ['key' => 'source', 'label' => 'Source'],
                    ['key' => 'link', 'label' => 'Open on YouTube', 'align' => 'right',
                        'type' => 'link', 'text_key' => 'hashtag'],
                ],
                'rows' => $this->rows($tags),
            ],
            summary: sprintf(
                '%d hashtags for “%s”, %d of them built from searches YouTube actually completes. '
                .'The first %d go above your title.',
                count($tags),
                $topic,
                $grounded,
                self::ABOVE_TITLE,
            ),
        ))->withMeta([
            'topic' => $topic,
            'format' => $format,
            'region' => $region,
            'total' => count($tags),
            'from_autocomplete' => $grounded,
        ])->withWarnings($this->warnings($format, count($tags) - $grounded));
    }

    /**
     * Rows in rank order, each carrying where YouTube will actually use it.
     *
     * @param  list<array{tag: string, source: string, grounded: bool}>  $tags
     * @return list<array<string, mixed>>
     */
    private function rows(array $tags): array
    {
        $rows = [];

        foreach ($tags as $index => $tag) {
            $rank = $index + 1;

            $rows[] = [
                'rank' => $rank,
                'hashtag' => '#'.$tag['tag'],
                'placement' => match (true) {
                    $rank <= self::ABOVE_TITLE => 'Above the title',
                    $rank <= self::DESCRIPTION_LIMIT => 'Description',
                    default => 'Spare — swap one in',
                },
                'breadth' => self::breadth($tag['tag']),
                'source' => $tag['source'],
                'link' => 'https://www.youtube.com/hashtag/'.rawurlencode($tag['tag']),
            ];
        }

        return $rows;
    }

    /**
     * How broad a tag is, judged on the only evidence we have: its shape.
     *
     * A one-word tag is a crowded tag and a three-word compound is a quiet one —
     * that much follows from how people search. It is a shape heuristic, not a
     * volume, and the column says "breadth" rather than a number for that reason.
     */
    private static function breadth(string $tag): string
    {
        return match (true) {
            mb_strlen($tag) <= 10 => 'Broad',
            mb_strlen($tag) <= 18 => 'Mid',
            default => 'Niche',
        };
    }

    /**
     * Real autocomplete phrases, turned into tags in the order YouTube ranked them.
     *
     * @return list<string>
     */
    private function fromAutocomplete(string $topic, string $region): array
    {
        $deadline = microtime(true) + self::BUDGET_SECONDS;
        $queries = [];

        foreach (self::MODIFIERS as $modifier) {
            $queries[] = $modifier === '' ? $topic : "{$modifier} {$topic}";
        }

        if (microtime(true) > $deadline) {
            return [];
        }

        $urls = array_map(fn (string $query) => $this->url($query, $region), $queries);
        $tags = [];

        foreach (SafeHttpClient::attemptPool($urls, self::REQUEST_TIMEOUT) as $response) {
            if ($response === null || ! $response->successful()) {
                continue;
            }

            foreach ($this->parse($response->body()) as $suggestion) {
                $tag = self::compound(self::words($suggestion));

                if ($tag !== '') {
                    $tags[] = $tag;
                }
            }
        }

        return array_values(array_unique($tags));
    }

    /**
     * Suffix-built tags, used only to reach 25 when autocomplete came back thin.
     *
     * @param  list<string>  $words
     * @return list<string>
     */
    private function fallbacks(array $words): array
    {
        $tags = [];
        $seeds = array_slice([self::compound($words), ...$words], 0, 4);

        foreach (self::FALLBACK_SUFFIXES as $suffix) {
            foreach ($seeds as $seed) {
                if ($seed !== '') {
                    $tags[] = $seed.$suffix;
                }
            }
        }

        return $tags;
    }

    /**
     * The staples for this format.
     *
     * A long-form upload gets none of the Shorts tags: #shorts on a ten-minute video
     * pulls it in front of an audience that swipes away, and the retention hit is
     * real. "Both" gets the Shorts staples last, so they land in the spare rows
     * rather than above the title.
     *
     * @return list<string>
     */
    private function staples(string $format): array
    {
        return match ($format) {
            'shorts' => [...self::SHORTS_STAPLES, 'youtube'],
            'long-form' => self::LONG_FORM_STAPLES,
            default => [...self::LONG_FORM_STAPLES, ...self::SHORTS_STAPLES],
        };
    }

    /** @return list<string> */
    private function warnings(string $format, int $invented): array
    {
        $warnings = [
            sprintf(
                'YouTube keeps the first %d hashtags in your description and shows the first %d above the '
                .'title. Past %d hashtags on an upload it ignores every one of them, so the extra rows here '
                .'are spares to swap in, not a list to paste whole.',
                self::DESCRIPTION_LIMIT,
                self::ABOVE_TITLE,
                self::IGNORE_ALL_ABOVE,
            ),
            'There are no video or channel counts here. Nobody outside Google has hashtag volumes, and that '
            .'is exactly the number you would act on — so the breadth column is a shape heuristic, honestly '
            .'labelled, rather than a figure we made up.',
        ];

        if ($format === 'both') {
            $warnings[] = 'You picked both formats, so the Shorts tags are ranked last. Drop them from a '
                .'long-form upload — #shorts on a ten-minute video sends it to an audience that swipes away.';
        }

        if ($invented > 0) {
            $warnings[] = sprintf(
                "%d of these were built from your topic rather than from YouTube's autocomplete, which had "
                .'little to say about it. Check those read like something a person would search before you '
                .'use them.',
                $invented,
            );
        }

        return $warnings;
    }

    /**
     * Append a tag if it is usable and new.
     *
     * @param  list<array{tag: string, source: string, grounded: bool}>  $tags
     */
    private function add(array &$tags, string $tag, string $source, bool $grounded): void
    {
        if ($tag === '' || mb_strlen($tag) < 3 || mb_strlen($tag) > self::MAX_TAG_LENGTH) {
            return;
        }

        foreach ($tags as $existing) {
            if ($existing['tag'] === $tag) {
                return;
            }
        }

        $tags[] = ['tag' => $tag, 'source' => $source, 'grounded' => $grounded];
    }

    /**
     * A phrase's meaningful words, lower-cased and stripped to letters and digits.
     *
     * @return list<string>
     */
    private static function words(string $phrase): array
    {
        $words = [];

        foreach (preg_split('/[\s,]+/u', mb_strtolower($phrase), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $clean = preg_replace('/[^\p{L}\p{N}]/u', '', $word) ?? '';

            if ($clean !== '' && ! in_array($clean, self::STOPWORDS, true)) {
                $words[] = $clean;
            }
        }

        return $words;
    }

    /**
     * The words joined into one tag.
     *
     * Capped at three words because a four-word compound is a sentence with the
     * spaces removed, and nobody browses one.
     *
     * @param  list<string>  $words
     */
    private static function compound(array $words): string
    {
        return implode('', array_slice($words, 0, 3));
    }

    /** Google's keyless suggestion endpoint, scoped to YouTube with `ds=yt`. */
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
