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
 * Album, artist, playlist and podcast art from Spotify — at the largest size
 * Spotify actually publishes, and no larger.
 *
 * Spotify's oEmbed endpoint is public, documented and needs no token, and it
 * answers with a `thumbnail_url` on Spotify's image CDN. That URL is a fixed
 * prefix followed by the image's own id, and the prefix *is* the size: for album
 * art `…0000b273` is 640 px, `…00001e02` is 300 and `…00004851` is 64. Swapping
 * the prefix therefore moves between renditions with no second request, exactly as
 * Pinterest's width directories do — and the endpoint hands out the 300 by default,
 * so the useful one is always one substitution away.
 *
 * Where this tool differs from the others in the downloads family is what it
 * refuses to promise. Pinterest has `/originals/`, Apple has 3000×3000; **Spotify
 * has no original route at all.** 640 px is the ceiling for album and artist art,
 * and every "HD Spotify cover art" result that claims otherwise is upscaling the
 * 640 and calling it a service. Saying so is the tool.
 */
final class SpotifyCoverArtDownloaderRunner implements Cacheable, ToolRunner, UsesProvider
{
    /**
     * Prefix ladders, per image family, largest first.
     *
     * The families are the ones whose renditions are published and verifiable: an
     * album cover (which a track link resolves to) and an artist portrait. A
     * playlist mosaic and a podcast cover are served as a single rendition, so they
     * fall through to {@see self::published()} and are measured rather than guessed.
     *
     * @var array<string, array{label: string, sizes: array<string, int>}>
     */
    private const FAMILIES = [
        'ab67616d' => [
            'label' => 'Album cover',
            'sizes' => ['ab67616d0000b273' => 640, 'ab67616d00001e02' => 300, 'ab67616d00004851' => 64],
        ],
        'ab676161' => [
            'label' => 'Artist portrait',
            'sizes' => ['ab6761610000e5eb' => 640, 'ab67616100005174' => 320, 'ab6761610000f178' => 160],
        ],
    ];

    /** What each rendition is drawn in, keyed by the pixel size. */
    private const USES = [
        640 => 'The largest Spotify publishes — the now-playing screen',
        320 => 'Artist header on a phone',
        300 => 'What the oEmbed endpoint and the link card hand you',
        160 => 'Search results',
        64 => 'The queue and the now-playing bar',
    ];

    public static function key(): string
    {
        return 'spotify.cover-art-downloader';
    }

    public function providers(): array
    {
        return ['spotify'];
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
                    'title' => 'Spotify link',
                    'description' => 'An open.spotify.com link to a track, album, artist, playlist, '
                        .'show or episode — or the `spotify:album:…` URI the desktop app copies.',
                    'minLength' => 8,
                    'maxLength' => 500,
                    'examples' => ['https://open.spotify.com/album/4aawyAB9vmqN3uQ7FjRGTy'],
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $url = $this->normalise(trim($input->string('url')));
        $oembed = $this->oembed($url);

        $image = is_string($oembed['thumbnail_url'] ?? null) ? $oembed['thumbnail_url'] : null;

        if ($image === null) {
            throw ToolExecutionException::notFound(
                'artwork for that link. Spotify answered without a thumbnail, which happens on '
                .'region-restricted and unpublished items',
            );
        }

        [$rows, $family] = $this->renditions($image);
        $title = is_string($oembed['title'] ?? null) ? $oembed['title'] : null;

        return ToolResult::table(
            columns: [
                ['key' => 'size', 'label' => 'Version'],
                ['key' => 'pixels', 'label' => 'Pixels', 'align' => 'right'],
                ['key' => 'use', 'label' => 'Where Spotify uses it'],
                ['key' => 'url', 'label' => 'Download', 'align' => 'right', 'type' => 'download'],
            ],
            rows: $rows,
            summary: $family !== null
                ? $family.' for '.($title !== null ? '“'.$this->clip($title, 60).'”' : 'that link')
                    .' at '.count($rows).' sizes. 640 × 640 is the largest Spotify serves — there is '
                    .'no original behind it.'
                : 'Spotify publishes this one as a single rendition'
                    .($title !== null ? ' for “'.$this->clip($title, 60).'”' : '')
                    .'. That row is the file itself, measured rather than guessed.',
        )->withMeta(array_filter([
            'spotify_url' => $url,
            'title' => $title,
            'family' => $family,
            'preview_url' => $rows[0]['url'] ?? $image,
            'original_url' => $rows[0]['url'] ?? $image,
        ], fn ($value) => $value !== null))
            ->withWarnings([
                'Cover art is the copyright of the label, the artist or the podcast publisher. '
                .'Use it for a playlist you are describing, a review, a link card or your own '
                .'reference — releasing it as artwork of your own is not a use this tool supports.',
                '640 × 640 is Spotify’s ceiling for album and artist images. Anything advertising a '
                .'larger Spotify cover is upscaling this same file.',
            ]);
    }

    /** `spotify:album:ID` and bare open.spotify.com links both become a canonical URL. */
    private function normalise(string $raw): string
    {
        if (preg_match('/^spotify:([a-z]+):([A-Za-z0-9]+)$/', $raw, $match) === 1) {
            return "https://open.spotify.com/{$match[1]}/{$match[2]}";
        }

        $url = str_contains($raw, '://') ? $raw : "https://{$raw}";
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || ! str_ends_with(mb_strtolower($host), 'spotify.com')) {
            throw ToolExecutionException::invalidInput(
                'That is not a Spotify link.',
                ['url' => 'Expected an open.spotify.com link or a `spotify:…` URI.'],
            );
        }

        // Spotify's share sheet appends `si=`, a per-share id. Nothing here needs it
        // and forwarding it would attach the sharer to our request.
        $path = parse_url($url, PHP_URL_PATH) ?: '/';

        return 'https://open.spotify.com'.$path;
    }

    /**
     * Spotify's own oEmbed record for the link.
     *
     * @return array<string, mixed>
     */
    private function oembed(string $url): array
    {
        $response = SafeHttpClient::get('https://open.spotify.com/oembed?url='.urlencode($url));
        $body = json_decode(SafeHttpClient::body($response), true);

        if (! is_array($body)) {
            throw ToolExecutionException::upstreamFailed(
                'spotify',
                'Spotify answered with something that was not a record we could read.',
            );
        }

        return $body;
    }

    /**
     * Every rendition of the image, by prefix substitution where the family is known.
     *
     * @return array{0: list<array<string, string>>, 1: ?string}
     */
    private function renditions(string $image): array
    {
        if (preg_match('#^(https://[^/]+/image/)([0-9a-f]{16})([0-9a-f]+)$#i', $image, $match) === 1) {
            [, $prefix, $sizeToken, $id] = $match;
            $family = self::FAMILIES[mb_substr($sizeToken, 0, 8)] ?? null;

            if ($family !== null) {
                $rows = [];

                foreach ($family['sizes'] as $token => $pixels) {
                    $rows[] = [
                        'size' => $pixels === 640 ? 'Largest published' : 'Resized',
                        'pixels' => "{$pixels} × {$pixels}",
                        'use' => self::USES[$pixels],
                        'url' => $prefix.$token.$id,
                    ];
                }

                return [$rows, $family['label']];
            }
        }

        return [[$this->published($image)], null];
    }

    /**
     * The one row for an image whose family we do not have a ladder for.
     *
     * Measured with a real request, because "as published" with no number beside it
     * is the kind of answer that sends somebody back to Google.
     *
     * @return array<string, string>
     */
    private function published(string $image): array
    {
        $response = SafeHttpClient::attempt($image);
        $size = $response !== null && $response->successful()
            ? @getimagesizefromstring(SafeHttpClient::body($response))
            : false;

        return [
            'size' => 'As published',
            'pixels' => $size === false ? 'Unknown' : "{$size[0]} × {$size[1]}",
            'use' => 'The only rendition Spotify serves for this item',
            'url' => $image,
        ];
    }

    private function clip(string $value, int $length): string
    {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length).'…' : $value;
    }
}
