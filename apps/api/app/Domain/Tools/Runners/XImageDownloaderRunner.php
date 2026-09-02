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
use App\Support\Social\PageMeta;
use App\Support\Social\SocialUrl;

/**
 * The image on a post, at the size it was uploaded rather than the size the
 * timeline is showing.
 *
 * X serves every uploaded photo from `pbs.twimg.com/media/<id>` and picks the
 * rendition with a `name` parameter — `thumb`, `small`, `medium`, `large`,
 * `4096x4096`, `orig`. The timeline asks for `small` or `medium`; saving the
 * picture from the timeline saves that. `name=orig` is the file as uploaded, and
 * nothing in the interface ever links to it.
 *
 * That parameter is the whole tool, and it is why this is an X page rather than a
 * row in the general image downloader: the general tool hands back whatever the
 * page named, and on X the page names the medium rendition. The same mechanism
 * also makes the tool useful without X answering at all — paste a `pbs.twimg.com`
 * link straight from a saved image and the ladder is derived from it, no fetch.
 *
 * Only public posts, read from the card tags X publishes for other sites (docs/08).
 * X increasingly answers an unauthenticated request with an interstitial instead of
 * the post; when it does, that is reported as what it is rather than as an empty
 * result, and the direct-CDN route is offered as the way around it.
 */
final class XImageDownloaderRunner implements Cacheable, ToolRunner, UsesProvider
{
    /**
     * X's rendition names, smallest first, with the bound each one fits inside.
     *
     * `orig` is listed separately in {@see self::renditions()} because it is not a
     * bound: it is the file as uploaded, at whatever size that was.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const RENDITIONS = [
        'thumb' => ['150 × 150', 'Square crop — the grid in a multi-photo post'],
        'small' => ['680 px wide', 'Timeline, narrow layouts'],
        'medium' => ['1200 px wide', 'Timeline on a desktop — usually what the card names'],
        'large' => ['2048 px wide', 'The photo viewer, when you click through'],
        '4096x4096' => ['4096 px wide', 'Nothing in the interface — the largest bounded rendition'],
    ];

    public static function key(): string
    {
        return 'x.image-downloader';
    }

    public function providers(): array
    {
        return ['x'];
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
                    'title' => 'Post URL',
                    'description' => 'An x.com or twitter.com post link — or a pbs.twimg.com image link '
                        .'copied straight from the photo.',
                    'minLength' => 8,
                    'maxLength' => 500,
                    'examples' => ['https://x.com/MrBeast/status/2086107642720649428'],
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $raw = trim($input->string('url'));

        // The CDN route first: a pbs.twimg.com link is already the answer, and
        // deriving the ladder from it needs no request and cannot be walled.
        if ($this->isMediaUrl($raw)) {
            return $this->fromMediaUrl($raw);
        }

        if (SocialUrl::platform($raw) !== 'x') {
            throw ToolExecutionException::invalidInput(
                'That is not an X link.',
                ['url' => 'Expected a link such as https://x.com/MrBeast/status/2086107642720649428'],
            );
        }

        $page = PageMeta::fetch($raw);
        $images = array_values(array_filter($page->images(), fn (string $url) => $this->isMediaUrl($url)));

        if ($images === []) {
            throw ToolExecutionException::notFound($page->isLoginWall()
                ? 'that post — X answered with a sign-in page rather than the post. Open the photo in a '
                .'browser where you are signed in, copy its pbs.twimg.com address, and paste that here '
                .'instead: the sizes are derived from the address itself'
                : 'a photo on that post. A text-only post has none, and a video post publishes a poster '
                .'frame on a different CDN that has no size ladder');
        }

        $rows = [];

        foreach ($images as $index => $image) {
            foreach ($this->renditions($image) as $row) {
                $rows[] = ['photo' => count($images) === 1 ? 'Photo' : 'Photo '.($index + 1)] + $row;
            }
        }

        return $this->result($rows, count($images), $page->title(), [
            'post_url' => $page->canonical() ?? SocialUrl::normalise($raw),
            'title' => $page->title(),
            'photo_count' => count($images),
            'preview_url' => $images[0],
        ]);
    }

    /** The ladder derived from a pasted CDN link, with no post to fetch. */
    private function fromMediaUrl(string $url): ToolResult
    {
        $rows = array_map(fn (array $row) => ['photo' => 'Photo'] + $row, $this->renditions($url));

        return $this->result($rows, 1, null, [
            'post_url' => null,
            'photo_count' => 1,
            'preview_url' => $this->rendition($url, 'medium') ?? $url,
        ]);
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @param  array<string, mixed>  $meta
     */
    private function result(array $rows, int $photos, ?string $title, array $meta): ToolResult
    {
        return ToolResult::table(
            columns: [
                ['key' => 'photo', 'label' => 'Photo'],
                ['key' => 'size', 'label' => 'Version'],
                ['key' => 'bound', 'label' => 'Fits inside'],
                ['key' => 'use', 'label' => 'Where X uses it'],
                ['key' => 'url', 'label' => 'Download', 'align' => 'right', 'type' => 'download'],
            ],
            rows: $rows,
            summary: sprintf(
                '%d photo%s%s. Take the original unless you are matching a layout — every other row '
                .'has been resized and re-compressed by X.',
                $photos,
                $photos === 1 ? '' : 's',
                $title !== null ? ' from “'.$this->clip($title, 70).'”' : '',
            ),
        )->withMeta($meta)->withWarnings([
            'The photo belongs to whoever posted it. Downloading one is not a licence to republish it — '
            .'use these for research, reference, moodboards or commentary, and quote the post rather than '
            .'the file when you are writing about it.',
            'A video or GIF post has no still to download; what a card names for one is its poster frame, '
            .'which is served from a different host and has no size ladder.',
        ]);
    }

    /**
     * Every rendition of the same file, derived by rewriting the `name` parameter.
     *
     * Ordered smallest to largest so the table reads as a ladder, with the original
     * last because it is the row the summary points at.
     *
     * @return list<array<string, string>>
     */
    private function renditions(string $url): array
    {
        $rows = [];

        foreach (self::RENDITIONS as $name => [$bound, $use]) {
            $rewritten = $this->rendition($url, $name);

            if ($rewritten !== null) {
                $rows[] = ['size' => 'Resized', 'bound' => $bound, 'use' => $use, 'url' => $rewritten];
            }
        }

        $original = $this->rendition($url, 'orig');

        if ($original !== null) {
            $rows[] = [
                'size' => 'Original upload',
                'bound' => 'As uploaded',
                'use' => 'Nothing — X never serves this one in the interface',
                'url' => $original,
            ];
        }

        return $rows === [] ? [[
            'size' => 'As published',
            'bound' => 'Unknown',
            'use' => 'The image X names in its card tags',
            'url' => $url,
        ]] : $rows;
    }

    /**
     * The same file at one named size.
     *
     * Two URL shapes are in circulation and both are still served: the current
     * `?format=jpg&name=large`, and the older `….jpg:large` suffix that predates
     * it. Normalising the old one onto the new one keeps a single code path, and
     * the new form is the one X itself emits today.
     */
    private function rendition(string $url, string $name): ?string
    {
        $parts = parse_url($url);

        if (! isset($parts['path']) || ! isset($parts['host'])) {
            return null;
        }

        $path = $parts['path'];
        $format = null;

        // `…/media/Abc123.jpg:large` — the legacy form. The extension is the format
        // and the suffix is the rendition, so both move into the query string.
        if (preg_match('#^(.+?)\.(jpg|jpeg|png|webp|gif)(?::\w+)?$#i', $path, $match) === 1) {
            $path = $match[1];
            $format = mb_strtolower($match[2]) === 'jpeg' ? 'jpg' : mb_strtolower($match[2]);
        }

        if ($format === null) {
            parse_str($parts['query'] ?? '', $query);
            $format = isset($query['format']) && is_string($query['format']) ? $query['format'] : 'jpg';
        }

        return 'https://'.$parts['host'].$path.'?format='.$format.'&name='.$name;
    }

    /** Whether a URL points at X's photo CDN, in either of the two shapes it uses. */
    private function isMediaUrl(string $url): bool
    {
        $host = SocialUrl::host($url);

        return $host === 'pbs.twimg.com'
            && str_starts_with((string) (parse_url(SocialUrl::normalise($url), PHP_URL_PATH) ?: ''), '/media/');
    }

    private function clip(string $value, int $length): string
    {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length).'…' : $value;
    }
}
