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
 * Podcast artwork at the size it was submitted, which is 3000×3000 — not the 600
 * the page hands you.
 *
 * Apple's Search API is public, unauthenticated and documented, and its lookup
 * endpoint answers with `artworkUrl600` for any show or episode. That URL ends in
 * a literal `600x600bb.jpg`, and the size segment is part of the path rather than a
 * signature, so the same file is served at every other size by substitution —
 * including `3000x3000bb.jpg`, which is the artwork as the publisher uploaded it.
 * Apple's own submission rules require 3000×3000, so that rendition exists for
 * every show in the directory.
 *
 * That substitution is the whole tool, and it is why this is an Apple Podcasts page
 * rather than a row in the general image downloader: the general tool reads
 * `og:image` off a page, and on `podcasts.apple.com` that is the 600 — five times
 * smaller in each dimension than the file sitting one URL away.
 *
 * Episode links are handled too. A link copied from an episode carries `?i=<id>`,
 * and an episode that ships its own cover has different artwork from the show it
 * belongs to; asking Apple about the episode id rather than the show id is the
 * difference between the right picture and its parent.
 */
final class ApplePodcastArtworkDownloaderRunner implements Cacheable, ToolRunner, UsesProvider
{
    /**
     * The size segments Apple serves, smallest first.
     *
     * 3000 is listed last and is the one the summary points at: it is the upload,
     * and every other row is a resize of it.
     *
     * @var array<int, string>
     */
    private const SIZES = [
        100 => 'Directory list thumbnail',
        300 => 'Search result',
        600 => 'What the web page and the API hand you',
        1200 => 'Show page on a large display',
        3000 => 'The artwork as submitted — Apple requires this size',
    ];

    public static function key(): string
    {
        return 'podcasts.apple-artwork-downloader';
    }

    public function providers(): array
    {
        return ['itunes'];
    }

    public function cacheTtl(): int
    {
        return 86400;
    }

    public function inputSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'required' => ['url'],
            'additionalProperties' => false,
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'x-control' => 'text',
                    'title' => 'Apple Podcasts link or ID',
                    'description' => 'A podcasts.apple.com link to a show or an episode, or the bare '
                        .'numeric ID from one.',
                    'minLength' => 3,
                    'maxLength' => 500,
                    'examples' => ['https://podcasts.apple.com/us/podcast/the-daily/id1200361736'],
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $raw = trim($input->string('url'));
        [$id, $isEpisode] = $this->identify($raw);

        $entry = $this->lookup($id);
        $artwork = $this->artwork($entry);

        if ($artwork === null) {
            throw ToolExecutionException::notFound(
                'artwork for that entry. Apple answered, but with no image URL on the record',
            );
        }

        $rows = $this->renditions($artwork);

        $name = $this->text($entry['trackName'] ?? $entry['collectionName'] ?? null);
        $author = $this->text($entry['artistName'] ?? null);

        return ToolResult::table(
            columns: [
                ['key' => 'size', 'label' => 'Size'],
                ['key' => 'pixels', 'label' => 'Pixels', 'align' => 'right'],
                ['key' => 'use', 'label' => 'Where Apple uses it'],
                ['key' => 'url', 'label' => 'Download', 'align' => 'right', 'type' => 'download'],
            ],
            rows: $rows,
            summary: count($rows).' sizes of the artwork for '
                .($name !== null ? '“'.$this->clip($name, 60).'”' : 'that entry')
                .($author !== null ? ' by '.$this->clip($author, 40) : '')
                .'. Take the 3000 — everything else on this list is a resize of it.',
        )->withMeta(array_filter([
            'apple_id' => $id,
            'kind' => $isEpisode ? 'episode' : 'show',
            'title' => $name,
            'author' => $author,
            'feed_url' => $entry['feedUrl'] ?? null,
            'genre' => $entry['primaryGenreName'] ?? null,
            'episodes' => $entry['trackCount'] ?? null,
            'original_url' => $rows[count($rows) - 1]['url'] ?? null,
            'preview_url' => $artwork,
        ], fn ($value) => $value !== null))
            ->withWarnings([
                'Podcast artwork belongs to the show. Use it for a review, a directory listing, a '
                .'link card or your own reference — not as the face of something you publish.',
                $isEpisode
                    ? 'Episode covers are often the show’s own. When they are, the URLs below are the '
                        .'show’s artwork, which is Apple answering honestly rather than the tool '
                        .'falling back.'
                    : 'This is the show’s cover. If you wanted a single episode’s art, paste the '
                        .'episode link — it carries an `?i=` id and often has its own picture.',
            ]);
    }

    /**
     * The Apple id in the input, and whether it names an episode.
     *
     * `?i=` wins over the `id…` in the path, because a link copied from an episode
     * carries both and the episode is the thing the visitor was looking at.
     *
     * @return array{0: string, 1: bool}
     */
    private function identify(string $raw): array
    {
        if (preg_match('/^\d{6,15}$/', $raw) === 1) {
            return [$raw, false];
        }

        $host = parse_url($raw, PHP_URL_HOST);

        if (! is_string($host) || ! str_contains($host, 'apple.com')) {
            throw ToolExecutionException::invalidInput(
                'That is not an Apple Podcasts link.',
                ['url' => 'Expected a podcasts.apple.com link, or the numeric ID from one.'],
            );
        }

        parse_str((string) (parse_url($raw, PHP_URL_QUERY) ?: ''), $query);

        $episode = $query['i'] ?? null;

        if (is_string($episode) && preg_match('/^\d{6,15}$/', $episode) === 1) {
            return [$episode, true];
        }

        if (preg_match('#/id(\d{6,15})#', $raw, $match) === 1) {
            return [$match[1], false];
        }

        throw ToolExecutionException::invalidInput(
            'That Apple Podcasts link has no ID in it.',
            ['url' => 'The link should contain an `/id1234567890` segment.'],
        );
    }

    /**
     * Apple's public lookup record for an id.
     *
     * @return array<string, mixed>
     */
    private function lookup(string $id): array
    {
        $response = SafeHttpClient::get("https://itunes.apple.com/lookup?id={$id}&entity=podcast");
        $body = json_decode(SafeHttpClient::body($response), true);

        $results = is_array($body) ? ($body['results'] ?? []) : [];

        if (! is_array($results) || $results === []) {
            throw ToolExecutionException::notFound(
                "anything in Apple's directory with the ID {$id}. Shows are removed from the "
                .'directory when a feed goes dead, and the link keeps working',
            );
        }

        $first = $results[0];

        return is_array($first) ? $first : [];
    }

    /** @param  array<string, mixed>  $entry */
    private function artwork(array $entry): ?string
    {
        foreach (['artworkUrl600', 'artworkUrl100', 'artworkUrl60', 'artworkUrl30'] as $field) {
            $value = $entry[$field] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * The same file at every size, by rewriting the size segment.
     *
     * A URL that does not carry the segment is handed back untouched rather than
     * rewritten into a guess: inventing five URLs that 404 is worse than returning
     * the one that works.
     *
     * @return list<array<string, string>>
     */
    private function renditions(string $artwork): array
    {
        if (preg_match('#^(.*/)(\d+)x(\d+)(bb[^/]*\.(?:jpg|png|webp))$#i', $artwork, $match) !== 1) {
            return [[
                'size' => 'As published',
                'pixels' => 'Unknown',
                'use' => 'The URL Apple’s own API returned',
                'url' => $artwork,
            ]];
        }

        [, $prefix, , , $suffix] = $match;

        $rows = [];

        foreach (self::SIZES as $size => $use) {
            $rows[] = [
                'size' => $size === 3000 ? 'Original' : 'Resized',
                'pixels' => "{$size} × {$size}",
                'use' => $use,
                'url' => "{$prefix}{$size}x{$size}{$suffix}",
            ];
        }

        return $rows;
    }

    /** A field from Apple's record, when it is a string and not something else. */
    private function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function clip(string $value, int $length): string
    {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length).'…' : $value;
    }
}
