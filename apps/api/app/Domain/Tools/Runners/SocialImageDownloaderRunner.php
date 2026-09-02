<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Exceptions\ToolExecutionException;
use App\Support\Social\PageMeta;
use App\Support\Social\SocialUrl;

/**
 * The full-size image behind a post, from any platform that publishes one.
 *
 * Right-clicking a photo in a feed saves the resized copy the feed is showing —
 * 640 pixels wide where the upload was 2000, and re-encoded. Every one of these
 * platforms also publishes the larger version in its `og:image` tag, because that
 * is what a link card renders, and that copy is the one worth having.
 *
 * Reading Open Graph rather than the rendered feed is deliberate and is what keeps
 * this inside the compliance line in docs/08: the tags exist to be read by other
 * sites, no session is used, and nothing that requires being logged in is touched.
 * The cost of that choice is honesty about where it stops — a platform that answers
 * an unauthenticated request with a sign-in page is reported as exactly that,
 * rather than as "no images found".
 *
 * A carousel publishes one `og:image` per slide, so all of them come back. Where
 * the CDN's own sizing convention is known — Pinterest's `/originals/`, Google's
 * `=s0` — the upgraded URL is offered alongside the one the page named.
 */
final class SocialImageDownloaderRunner implements Cacheable, ToolRunner
{
    public static function key(): string
    {
        return 'utility.social-image-downloader';
    }

    public function cacheTtl(): int
    {
        // Short: several of these CDNs sign their URLs and expire them within the
        // hour, and handing somebody a cached link that 403s is worse than a refetch.
        return 900;
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
                    'title' => 'Post URL',
                    'description' => 'A public post, video, Pin, article or profile from any platform — '
                        .'or any web page at all.',
                    'minLength' => 6,
                    'maxLength' => 900,
                    'examples' => ['https://www.pinterest.com/pin/1234567890123456789/'],
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $raw = trim($input->string('url'));
        $identity = SocialUrl::identify($raw);

        if ($identity['host'] === null) {
            throw ToolExecutionException::invalidInput(
                'That is not a URL we can read. Paste the whole link, including the domain.',
                ['url' => 'Expected a link such as https://www.pinterest.com/pin/123/'],
            );
        }

        $page = PageMeta::fetch($identity['url']);
        $images = $page->images();

        if ($images === []) {
            throw ToolExecutionException::notFound($page->isLoginWall()
                ? 'any images — '.($identity['label'] ?? 'that site').' answered with a sign-in page rather '
                .'than the post. That happens on private accounts and, increasingly, on public Instagram '
                .'and Facebook posts too'
                : 'any images published on that page. Not every post publishes one: a text-only post, or a '
                .'video with no poster frame, genuinely has nothing to hand back');
        }

        $rows = [];

        foreach ($images as $index => $image) {
            $rows[] = [
                'image' => count($images) === 1 ? 'Post image' : 'Image '.($index + 1),
                'source' => 'As published (og:image)',
                'dimensions' => $this->declaredSize($page, $index),
                'url' => $image,
            ];

            $upgraded = $this->upgrade($image);

            if ($upgraded !== null) {
                $rows[] = [
                    'image' => count($images) === 1 ? 'Post image' : 'Image '.($index + 1),
                    'source' => $upgraded['label'],
                    'dimensions' => $upgraded['dimensions'],
                    'url' => $upgraded['url'],
                ];
            }
        }

        return ToolResult::table(
            columns: [
                ['key' => 'image', 'label' => 'Image'],
                ['key' => 'source', 'label' => 'Version'],
                ['key' => 'dimensions', 'label' => 'Dimensions'],
                ['key' => 'url', 'label' => 'Download', 'align' => 'right', 'type' => 'download'],
            ],
            rows: $rows,
            summary: sprintf(
                '%d image%s published by %s%s.',
                count($images),
                count($images) === 1 ? '' : 's',
                $identity['label'] ?? (SocialUrl::host($identity['url']) ?? 'that page'),
                $page->title() !== null ? ' for “'.$this->clip($page->title(), 70).'”' : '',
            ),
        )->withMeta([
            'platform' => $identity['platform'],
            'kind' => $identity['kind'],
            'title' => $page->title(),
            'image_count' => count($images),
            'preview_url' => $images[0],
        ])->withWarnings($this->warnings($identity, count($images)));
    }

    /**
     * A larger version of the same file, where the CDN's convention is documented.
     *
     * These are the two conventions worth encoding: Pinterest serves every Pin from
     * a width-named directory with `/originals/` holding the upload, and Google's
     * image CDN takes a `=s0` suffix meaning "as uploaded". Guessing at any others
     * would hand back 404s dressed as options.
     *
     * @return array{url: string, label: string, dimensions: string}|null
     */
    private function upgrade(string $url): ?array
    {
        if (preg_match('#^(https://i\.pinimg\.com/)(\d+x\d*|originals)(/.+)$#', $url, $match) === 1
            && $match[2] !== 'originals') {
            return [
                'url' => $match[1].'originals'.$match[3],
                'label' => 'Original upload',
                'dimensions' => 'As uploaded',
            ];
        }

        if (preg_match('#^(https://[a-z0-9]+\.(?:ggpht|googleusercontent)\.com/[^=]+)=#', $url, $match) === 1) {
            return [
                'url' => $match[1].'=s0',
                'label' => 'Original upload',
                'dimensions' => 'As uploaded',
            ];
        }

        return null;
    }

    /** `og:image:width`/`height`, when the page bothered to publish them. */
    private function declaredSize(PageMeta $page, int $index): string
    {
        if ($index > 0) {
            // The width/height tags are not repeated per slide, so quoting the
            // first image's size against the fourth would be a guess.
            return 'Unknown';
        }

        $width = $page->og('image:width');
        $height = $page->og('image:height');

        return $width !== null && $height !== null ? "{$width} × {$height}" : 'Unknown';
    }

    /**
     * @param  array{platform: ?string, label: ?string, kind: ?string, host: ?string, path: string, url: string}  $identity
     * @return list<string>
     */
    private function warnings(array $identity, int $count): array
    {
        $warnings = [
            'These images belong to whoever posted them. Downloading one is not a licence to republish it — '
            .'use them for research, moodboards, reference or commentary.',
        ];

        if (in_array($identity['platform'], ['instagram', 'facebook'], true)) {
            $warnings[] = 'Meta\'s image URLs are signed and expire, usually within a few hours. Save the '
                .'file now rather than bookmarking the link.';
        }

        if ($identity['kind'] === 'video' || $identity['kind'] === 'reel') {
            $warnings[] = 'That link points at a video, so what comes back is its cover frame rather than '
                .'the video itself. This tool downloads images only.';
        }

        if ($count === 1 && $identity['platform'] === 'instagram') {
            $warnings[] = 'Instagram publishes only the first slide of a carousel in its metadata when it '
                .'answers without a session, so a carousel may come back as a single image.';
        }

        return $warnings;
    }

    private function clip(string $value, int $length): string
    {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length).'…' : $value;
    }
}
