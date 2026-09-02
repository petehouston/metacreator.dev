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
 * A Pin at the size it was uploaded, rather than the size the grid is showing.
 *
 * Pinterest serves every Pin from a width-named directory — `/236x/`, `/474x/`,
 * `/564x/`, `/736x/` — and keeps the upload itself under `/originals/`. The grid
 * hands you a 236-pixel thumbnail, the closeup a 736, and the original is
 * frequently 1000×1500 or larger. Since all five are the same path under a
 * different prefix, moving between them needs no request at all: the `og:image` on
 * a Pin page names one of them, and the rest follow by substitution.
 *
 * That substitution is the whole tool, and it is why this is a Pinterest page
 * rather than a row in the general image downloader: nobody searching for a Pin
 * downloader wants the 236px version, and the general tool cannot know that
 * `/originals/` is where the good one lives.
 *
 * Only public Pins, read from the tag Pinterest publishes for link cards (docs/08).
 */
final class PinterestImageDownloaderRunner implements Cacheable, ToolRunner, UsesProvider
{
    /**
     * Pinterest's own rendition widths, smallest first.
     *
     * `originals` is listed separately below because it is not a width: it is the
     * file as uploaded, at whatever size that was.
     *
     * @var array<string, string>
     */
    private const RENDITIONS = [
        '236x' => 'Grid thumbnail',
        '474x' => 'Feed',
        '564x' => 'Related Pins',
        '736x' => 'Pin closeup — the largest fixed width',
    ];

    public static function key(): string
    {
        return 'pinterest.image-downloader';
    }

    public function providers(): array
    {
        return ['pinterest'];
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
                    'title' => 'Pin URL',
                    'description' => 'A pinterest.com/pin/… link, or the pin.it short link from the app’s '
                        .'share sheet.',
                    'minLength' => 8,
                    'maxLength' => 500,
                    'examples' => ['https://www.pinterest.com/pin/1234567890123456789/'],
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $raw = trim($input->string('url'));

        if (SocialUrl::platform($raw) !== 'pinterest') {
            throw ToolExecutionException::invalidInput(
                'That is not a Pinterest link.',
                ['url' => 'Expected a pinterest.com/pin/… or pin.it link.'],
            );
        }

        // A pin.it link is a redirect; PageMeta follows it, and the metadata comes
        // back from wherever it lands, so no special-casing is needed here.
        $page = PageMeta::fetch($raw);
        $image = $page->image();

        if ($image === null) {
            throw ToolExecutionException::notFound($page->isLoginWall()
                ? 'that Pin — Pinterest answered with a sign-in page. Pins on secret boards are not public'
                : 'an image on that Pin. Video Pins and Idea Pins publish a cover frame at best');
        }

        $rows = $this->renditions($image);

        return ToolResult::table(
            columns: [
                ['key' => 'size', 'label' => 'Version'],
                ['key' => 'width', 'label' => 'Width', 'align' => 'right'],
                ['key' => 'use', 'label' => 'Where Pinterest uses it'],
                ['key' => 'url', 'label' => 'Download', 'align' => 'right', 'type' => 'download'],
            ],
            rows: $rows,
            summary: count($rows).' sizes of this Pin'
                .($page->title() !== null ? ' — “'.$this->clip($page->title(), 70).'”' : '')
                .'. The original is the one you want unless you are matching a layout.',
        )->withMeta([
            'pin_url' => $page->canonical() ?? SocialUrl::normalise($raw),
            'title' => $page->title(),
            'description' => $page->description(),
            'original_url' => $rows[count($rows) - 1]['url'] ?? $image,
            'preview_url' => $image,
        ])->withWarnings([
            'A Pin belongs to whoever uploaded it, and most Pins point at somebody\'s product photo or '
            .'blog image. Use these for moodboards, reference and research — re-pinning through Pinterest '
            .'itself is what keeps the credit attached.',
            'Idea Pins and video Pins have no still to download; what comes back for one is its cover frame.',
        ]);
    }

    /**
     * Every rendition of the same file, derived by swapping the width directory.
     *
     * The `/originals/` row is listed last and is the one the summary points at:
     * these are ordered smallest to largest so the table reads as a ladder rather
     * than a list of alternatives.
     *
     * @return list<array<string, string>>
     */
    private function renditions(string $image): array
    {
        if (preg_match('#^(https://i\.pinimg\.com/)(\d+x\d*|originals)(/.+)$#', $image, $match) !== 1) {
            // Not a path we recognise — hand back what the page published rather
            // than inventing URLs that would 404.
            return [[
                'size' => 'As published',
                'width' => 'Unknown',
                'use' => 'The image Pinterest names in its link card',
                'url' => $image,
            ]];
        }

        [, $host, , $path] = $match;

        $rows = [];

        foreach (self::RENDITIONS as $directory => $use) {
            $rows[] = [
                'size' => 'Resized',
                'width' => rtrim($directory, 'x').' px',
                'use' => $use,
                'url' => $host.$directory.$path,
            ];
        }

        $rows[] = [
            'size' => 'Original upload',
            'width' => 'As uploaded',
            'use' => 'Nothing — Pinterest never serves this one in the UI',
            'url' => $host.'originals'.$path,
        ];

        return $rows;
    }

    private function clip(string $value, int $length): string
    {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length).'…' : $value;
    }
}
