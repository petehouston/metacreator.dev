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
use App\Support\Social\PageMeta;

/**
 * A Twitch profile picture, clip thumbnail or VOD still at every size Twitch keeps
 * — with each size checked before it is offered.
 *
 * Twitch names the dimensions in the path. A profile picture is
 * `…-profile_image-300x300.png`, a clip still is `…-preview-480x272.jpg`, and a VOD
 * thumbnail ends the same way; the number in the URL is the rendition, not a query
 * parameter and not a signature. So the whole ladder is reachable by substitution,
 * and a 300 × 300 avatar on the page is one edit away from the 600 × 600 Twitch
 * stores.
 *
 * That edit is why Twitch gets its own page rather than a row in the general image
 * downloader: `og:image` on a channel is always the 300, and the general tool has
 * no way to know that a larger one exists at a URL nobody advertises.
 *
 * Every candidate is **fetched and measured before it appears in the table**. Twitch
 * stops at 600 for avatars — 900 and 1200 answer 404 — and the difference between a
 * tool that knows that and one that guesses is a download link that works. Nothing
 * here is invented: a row exists because a request for it came back with an image.
 */
final class TwitchImageDownloaderRunner implements Cacheable, ToolRunner, UsesProvider
{
    /**
     * Candidate size segments per asset kind, largest first.
     *
     * These are candidates, not claims. Each is verified in {@see self::verify()}
     * and dropped when Twitch does not serve it, which is what keeps the table
     * honest as Twitch changes what it keeps.
     *
     * @var array<string, list<array{0: int, 1: int, 2: string}>>
     */
    private const LADDERS = [
        'profile' => [
            [600, 600, 'The largest Twitch stores for an avatar'],
            [300, 300, 'The channel page, and what the link card publishes'],
            [150, 150, 'Following list'],
            [70, 70, 'Chat badge row'],
            [50, 50, 'Sidebar'],
            [28, 28, 'Chat'],
        ],
        'clip' => [
            [1280, 720, 'Full-size still, when Twitch kept one'],
            [480, 272, 'The clip page and the share card'],
            [260, 147, 'Clip grid tile'],
            [86, 45, 'Inline suggestion'],
        ],
        'video' => [
            [1280, 720, 'Full-size still'],
            [640, 360, 'VOD page'],
            [320, 180, 'Directory tile'],
        ],
    ];

    public static function key(): string
    {
        return 'twitch.image-downloader';
    }

    public function providers(): array
    {
        return ['twitch'];
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
            'required' => ['url'],
            'additionalProperties' => false,
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'x-control' => 'text',
                    'title' => 'Twitch link or channel name',
                    'description' => 'A channel (twitch.tv/name), a clip, or a VOD — or just the '
                        .'channel name on its own.',
                    'minLength' => 2,
                    'maxLength' => 500,
                    'examples' => ['https://www.twitch.tv/shroud'],
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $url = $this->normalise(trim($input->string('url')));

        $page = PageMeta::fetch($url);
        $image = $page->image();

        if ($image === null || $this->isTwitchChrome($image)) {
            throw ToolExecutionException::notFound($page->isLoginWall()
                ? 'that channel — Twitch answered with a sign-in page'
                : 'an image on that page. Twitch serves its own logo in place of a thumbnail for a '
                    .'deleted VOD, a sub-only VOD and a channel that does not exist');
        }

        $kind = $this->kind($image);
        $rows = $this->rows($image, $kind);

        if ($rows === []) {
            throw ToolExecutionException::notFound('a downloadable rendition of that image');
        }

        return ToolResult::table(
            columns: [
                ['key' => 'size', 'label' => 'Version'],
                ['key' => 'pixels', 'label' => 'Measured', 'align' => 'right'],
                ['key' => 'use', 'label' => 'Where Twitch uses it'],
                ['key' => 'url', 'label' => 'Download', 'align' => 'right', 'type' => 'download'],
            ],
            rows: $rows,
            summary: $this->summary($kind, count($rows), $page->title()),
        )->withMeta(array_filter([
            'twitch_url' => $page->canonical() ?? $url,
            'kind' => $kind,
            'title' => $page->title(),
            'preview_url' => $rows[0]['url'] ?? $image,
            'original_url' => $rows[0]['url'] ?? $image,
        ], fn ($value) => $value !== null))
            ->withWarnings([
                'Every size in this table was fetched and measured before it was listed, so a link '
                .'here is a link that works.',
                $kind === 'profile'
                    ? 'Twitch stops at 600 × 600 for an avatar. Larger URLs answer 404 — there is no '
                        .'bigger file behind them to find.'
                    : 'Twitch keeps clip and VOD stills for a limited time and at whichever sizes it '
                        .'chose for that item, so two clips can offer different ladders.',
                'A channel’s art belongs to the streamer. Use it for a thumbnail credit, a raid '
                .'graphic you have permission for, or your own reference.',
            ]);
    }

    /** A bare channel name becomes the channel URL; everything else must be a Twitch link. */
    private function normalise(string $raw): string
    {
        if (preg_match('/^[A-Za-z0-9_]{3,25}$/', $raw) === 1) {
            return "https://www.twitch.tv/{$raw}";
        }

        $url = str_contains($raw, '://') ? $raw : "https://{$raw}";
        $host = mb_strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));

        if (! str_ends_with($host, 'twitch.tv')) {
            throw ToolExecutionException::invalidInput(
                'That is not a Twitch link.',
                ['url' => 'Expected a twitch.tv or clips.twitch.tv link, or a channel name.'],
            );
        }

        return $url;
    }

    /** Twitch's own furniture, served in place of a thumbnail it does not have. */
    private function isTwitchChrome(string $image): bool
    {
        return str_contains($image, '/ttv-static-metadata/')
            || str_contains($image, 'twitch_logo');
    }

    private function kind(string $image): string
    {
        if (str_contains($image, '-profile_image-')) {
            return 'profile';
        }

        return str_contains($image, 'clips-media-assets') || str_contains($image, '-preview-')
            ? 'clip'
            : 'video';
    }

    /**
     * The verified ladder for this image.
     *
     * @return list<array<string, string>>
     */
    private function rows(string $image, string $kind): array
    {
        if (preg_match('#^(.*?)(\d+)x(\d+)(\.[a-z0-9]+)$#i', $image, $match) !== 1) {
            $published = $this->measure($image, 'As published',
                'The URL Twitch publishes on the page');

            return $published === null ? [] : [$published];
        }

        [, $prefix, , , $extension] = $match;

        $candidates = [];

        foreach (self::LADDERS[$kind] as [$width, $height, $use]) {
            $candidates["{$width}x{$height}"] = [
                'url' => "{$prefix}{$width}x{$height}{$extension}",
                'use' => $use,
            ];
        }

        return $this->verify($candidates);
    }

    /**
     * Fetch every candidate at once and keep the ones that came back as an image.
     *
     * Concurrent rather than sequential because six round trips in series is most of
     * a run's budget spent waiting, and these are small files.
     *
     * @param  array<string, array{url: string, use: string}>  $candidates
     * @return list<array<string, string>>
     */
    private function verify(array $candidates): array
    {
        $responses = SafeHttpClient::attemptPool(
            array_map(fn (array $candidate) => $candidate['url'], $candidates),
        );

        $rows = [];
        $largest = true;

        foreach ($candidates as $key => $candidate) {
            $response = $responses[$key] ?? null;

            if ($response === null || ! $response->successful()) {
                continue;
            }

            $size = @getimagesizefromstring(SafeHttpClient::body($response));

            if ($size === false) {
                continue;
            }

            $rows[] = [
                'size' => $largest ? 'Largest available' : 'Resized',
                'pixels' => "{$size[0]} × {$size[1]}",
                'use' => $candidate['use'],
                'url' => $candidate['url'],
            ];

            $largest = false;
        }

        return $rows;
    }

    /** @return array<string, string>|null */
    private function measure(string $image, string $label, string $use): ?array
    {
        $response = SafeHttpClient::attempt($image);

        if ($response === null || ! $response->successful()) {
            return null;
        }

        $size = @getimagesizefromstring(SafeHttpClient::body($response));

        return [
            'size' => $label,
            'pixels' => $size === false ? 'Unknown' : "{$size[0]} × {$size[1]}",
            'use' => $use,
            'url' => $image,
        ];
    }

    private function summary(string $kind, int $count, ?string $title): string
    {
        $subject = match ($kind) {
            'profile' => 'profile picture',
            'clip' => 'clip thumbnail',
            default => 'VOD thumbnail',
        };

        return $count.' verified size'.($count === 1 ? '' : 's')." of that {$subject}"
            .($title !== null ? ' — '.$this->clip($title, 60) : '')
            .'. The first row is the largest Twitch actually serves.';
    }

    private function clip(string $value, int $length): string
    {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length).'…' : $value;
    }
}
