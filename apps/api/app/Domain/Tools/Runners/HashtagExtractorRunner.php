<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Support\Social\PageMeta;
use App\Support\Social\SocialUrl;

/**
 * The hashtags on somebody else's post, pulled from the post itself.
 *
 * The tag extractor already answers this for YouTube's hidden `keywords` metadata,
 * which is a different thing: tags are invisible to viewers, hashtags are part of
 * the caption. Wanting to know which hashtags a competitor put on a post that did
 * well is the more common question by a wide margin, and copying them out of a
 * screenshot by hand is how people do it today.
 *
 * The read is Open Graph, not the rendered feed: every platform publishes the
 * caption in `og:description` so that link cards render elsewhere, which makes it
 * public metadata about a public post rather than scraping (docs/08). The
 * consequence worth being honest about is that a platform serving us a login wall
 * gets reported as a login wall — the tool has no logged-in session and will not
 * pretend to have one.
 *
 * Pasted text is accepted too, because half the time the caption is already on the
 * clipboard and a round trip to fetch it back is pure ceremony.
 */
final class HashtagExtractorRunner implements Cacheable, ToolRunner
{
    /**
     * A hashtag is `#` followed by letters, digits or underscore, in any script.
     *
     * `\p{L}` rather than `[a-z]` matters: a Japanese or Arabic hashtag is a
     * hashtag, and an extractor that silently drops them is worse than useless to
     * anyone outside the anglosphere. A tag of digits alone is excluded because
     * every platform rejects one.
     */
    private const PATTERN = '/(?<![\w&])#([\p{L}\p{M}][\p{L}\p{M}\p{N}_]{0,138}|[\p{N}_]*[\p{L}][\p{L}\p{M}\p{N}_]{0,138})/u';

    public static function key(): string
    {
        return 'utility.hashtag-extractor';
    }

    public function cacheTtl(): int
    {
        return 3600;
    }

    public function inputSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'required' => ['source'],
            'additionalProperties' => false,
            'properties' => [
                'source' => [
                    'type' => 'string',
                    'x-control' => 'textarea',
                    'title' => 'Post URL, or the caption itself',
                    'description' => 'Paste a link to a public post, video or Pin — or paste the caption '
                        .'text straight in and skip the fetch.',
                    'minLength' => 2,
                    'maxLength' => 20000,
                    'examples' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
                ],
                'lowercase' => [
                    'type' => 'boolean',
                    'title' => 'Lower-case the results',
                    'description' => 'Hashtags are case-insensitive on every platform, so #TravelTips and '
                        .'#traveltips reach the same feed. Case only affects readability.',
                    'default' => false,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $source = trim($input->string('source'));
        $isUrl = $this->looksLikeUrl($source);

        [$haystacks, $meta] = $isUrl ? $this->fromUrl($source) : [['Caption' => $source], []];

        $found = $this->extract($haystacks, $input->bool('lowercase'));

        if ($found === []) {
            return ToolResult::table(
                columns: [['key' => 'hashtag', 'label' => 'Hashtag']],
                rows: [],
                summary: $isUrl
                    ? 'No hashtags in the metadata that page publishes. Either the post has none, or the '
                    .'platform did not give us the caption — see the notes below.'
                    : 'No hashtags in that text.',
            )->withMeta($meta)->withWarnings($this->warnings($isUrl, $meta, 0));
        }

        $rows = array_map(fn (array $tag) => [
            'hashtag' => $tag['tag'],
            'characters' => mb_strlen($tag['tag']),
            'found_in' => implode(', ', $tag['where']),
        ], $found);

        $tags = array_column($found, 'tag');

        return ToolResult::table(
            columns: [
                ['key' => 'hashtag', 'label' => 'Hashtag', 'copyable' => true, 'copy_all' => true,
                    'copy_separator' => ' '],
                ['key' => 'characters', 'label' => 'Characters', 'align' => 'right'],
                ['key' => 'found_in', 'label' => 'Found in'],
            ],
            rows: $rows,
            summary: count($rows).' hashtag'.(count($rows) === 1 ? '' : 's')
                .($meta['title'] ?? null ? ' on “'.$meta['title'].'”' : '')
                .', '.mb_strlen(implode(' ', $tags)).' characters if you paste them all.',
        )->withMeta([
            ...$meta,
            'hashtags' => $tags,
            'hashtag_string' => implode(' ', $tags),
            'count' => count($tags),
        ])->withWarnings($this->warnings($isUrl, $meta, count($tags)));
    }

    /**
     * Fetch the page and hand back every field a caption can hide in.
     *
     * Keeping them separate rather than concatenating is what lets the result say
     * *where* a tag came from — a hashtag in the title is doing a different job
     * from one buried at the end of a description.
     *
     * @return array{0: array<string, string>, 1: array<string, mixed>}
     */
    private function fromUrl(string $url): array
    {
        $identity = SocialUrl::identify($url);
        $page = PageMeta::fetch($url);

        $meta = [
            'url' => $identity['url'],
            'platform' => $identity['platform'],
            'kind' => $identity['kind'],
            'title' => $page->title(),
            'login_wall' => $page->isLoginWall(),
        ];

        return [array_filter([
            'Title' => $page->title(),
            'Description' => $page->description(),
            'Keywords' => $page->named('keywords'),
        ], fn (?string $value) => $value !== null && $value !== ''), $meta];
    }

    /**
     * De-duplicate case-insensitively while keeping the first spelling seen.
     *
     * `#Travel` and `#travel` reach the same feed, so listing both as separate
     * findings would overstate how many tags the post actually used.
     *
     * @param  array<string, string>  $haystacks
     * @return list<array{tag: string, where: list<string>}>
     */
    private function extract(array $haystacks, bool $lowercase): array
    {
        $found = [];

        foreach ($haystacks as $field => $text) {
            if (preg_match_all(self::PATTERN, $text, $matches) === 0) {
                continue;
            }

            foreach ($matches[0] as $raw) {
                $tag = $lowercase ? mb_strtolower($raw) : $raw;
                $key = mb_strtolower($raw);

                if (! isset($found[$key])) {
                    $found[$key] = ['tag' => $tag, 'where' => []];
                }

                if (! in_array($field, $found[$key]['where'], true)) {
                    $found[$key]['where'][] = $field;
                }
            }
        }

        return array_values($found);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<string>
     */
    private function warnings(bool $isUrl, array $meta, int $count): array
    {
        $warnings = [];

        if ($isUrl && ($meta['login_wall'] ?? false) === true) {
            $warnings[] = ucfirst((string) ($meta['platform'] ?? 'that platform')).' answered with a sign-in '
                .'page rather than the post. That happens on private accounts, age-restricted posts, and '
                .'increasingly on Instagram and Facebook even for public ones. Paste the caption text '
                .'instead and the extractor works on it directly.';
        }

        if ($isUrl && ($meta['platform'] ?? null) === 'youtube') {
            $warnings[] = 'YouTube only publishes the first ~160 characters of a description in its '
                .'metadata, so hashtags right at the end of a long description may not appear here. The '
                .'three that show above the title always do.';
        }

        if ($count > 30) {
            $warnings[] = 'That is '.$count.' hashtags. Instagram caps a post at 30 and silently ignores '
                .'the rest; X and LinkedIn have no cap but engagement falls off well before this.';
        }

        return $warnings;
    }

    private function looksLikeUrl(string $value): bool
    {
        if (str_contains($value, "\n") || preg_match('/\s/u', trim($value)) === 1) {
            return false;
        }

        if (SocialUrl::host($value) === null) {
            return false;
        }

        // A bare word with a dot in it is not a URL worth a network round trip;
        // requiring either a scheme or a slashed path keeps "hello.world" as text.
        return str_contains($value, '://') || str_contains(ltrim($value, '/'), '/');
    }
}
