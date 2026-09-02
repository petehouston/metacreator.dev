<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Support\Http\SafeHttpClient;
use App\Support\Social\PreviewFrame;
use App\Support\Social\PreviewImage;

/**
 * One banner, four crops: the only YouTube asset where most of the file is thrown
 * away on the device most people use.
 *
 * Channel art is uploaded at 2560×1440 and then cropped differently by every
 * surface. A television shows the whole thing. A desktop browser shows a 2560×423
 * strip through the middle. A phone shows 1546×423 — which is 1,014 pixels
 * narrower than the desktop strip and **17% of the uploaded image**. That is why
 * so many channels have a banner whose wordmark is perfect on the designer's
 * monitor and cut in half on the device the audience is holding.
 *
 * The four numbers below are YouTube's own published channel-art dimensions, and
 * every frame here is the same 2560×1440 canvas with everything that device throws
 * away shaded out — so the question the tool answers is not "what size is the
 * banner" but "is my name inside the smallest window".
 *
 * With a URL, the real banner is drawn under the crops and measured; without one,
 * the frames are drawn over a generated placeholder so the geometry still reads.
 */
final class YouTubeBannerSafeAreaRunner implements Cacheable, ToolRunner
{
    /** YouTube's upload canvas for channel art. */
    private const CANVAS_WIDTH = 2560;

    private const CANVAS_HEIGHT = 1440;

    /**
     * What each surface actually shows, centred on the canvas.
     *
     * @var array<string, array{label: string, width: int, height: int, note: string}>
     */
    private const SURFACES = [
        'tv' => ['label' => 'TV', 'width' => 2560, 'height' => 1440,
            'note' => 'The only surface that shows the whole file. Design here last, not first.'],
        'desktop' => ['label' => 'Desktop', 'width' => 2560, 'height' => 423,
            'note' => 'A full-width strip through the middle. Nothing above or below it is ever seen '
                .'in a browser.'],
        'tablet' => ['label' => 'Tablet', 'width' => 1855, 'height' => 423,
            'note' => 'The desktop strip with 352 px trimmed from each side.'],
        'mobile' => ['label' => 'Mobile', 'width' => 1546, 'height' => 423,
            'note' => 'The smallest window, and the one that decides the design: 1546×423 is what '
                .'every phone shows, and it is where your name has to be.'],
    ];

    public static function key(): string
    {
        return 'youtube.banner-safe-area';
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
            'required' => [],
            'additionalProperties' => false,
            'properties' => [
                'image_url' => [
                    'type' => 'string',
                    'x-control' => 'text',
                    'title' => 'Banner image URL',
                    'description' => 'A direct link to the image you plan to upload, or to a channel’s '
                        .'existing banner. Leave it blank to see the crops on their own.',
                    'maxLength' => 500,
                    'default' => '',
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $url = trim($input->string('image_url'));
        $measured = $url === '' ? null : $this->measure($url);
        $banner = $url === '' ? PreviewImage::placeholder('youtube-banner', '16:9') : $url;

        $frames = [];
        $rows = [];

        foreach (self::SURFACES as $surface) {
            $left = intdiv(self::CANVAS_WIDTH - $surface['width'], 2);
            $right = self::CANVAS_WIDTH - $surface['width'] - $left;
            $top = intdiv(self::CANVAS_HEIGHT - $surface['height'], 2);
            $bottom = self::CANVAS_HEIGHT - $surface['height'] - $top;

            $shown = ($surface['width'] * $surface['height'])
                / (self::CANVAS_WIDTH * self::CANVAS_HEIGHT);

            $frames[] = PreviewFrame::make('youtube', $surface['label'], 'safe-zone')
                ->safeZone(self::CANVAS_WIDTH, self::CANVAS_HEIGHT, $top, $bottom, $left, $right)
                ->artwork(banner: $banner)
                ->status(
                    $shown > 0.9 ? 'ok' : ($shown > 0.2 ? 'warn' : 'danger'),
                    round($shown * 100).'% of the file is shown',
                )
                ->detail('Shows', "{$surface['width']} × {$surface['height']} px")
                ->detail('Cropped away', 'top '.$top.' · bottom '.$bottom
                    .' · left '.$left.' · right '.$right.' px')
                ->note($surface['note'])
                ->toArray();

            $rows[] = [
                'surface' => $surface['label'],
                'visible' => "{$surface['width']} × {$surface['height']}",
                'crop' => "top {$top}\nbottom {$bottom}\nleft {$left}\nright {$right}",
                'share' => round($shown * 100).'%',
                'note' => $surface['note'],
            ];
        }

        return ToolResult::socialPreview(
            $frames,
            summary: $this->summary($measured),
            table: [
                'columns' => [
                    ['key' => 'surface', 'label' => 'Surface'],
                    ['key' => 'visible', 'label' => 'Shows (px)'],
                    ['key' => 'crop', 'label' => 'Cropped away (px)'],
                    ['key' => 'share', 'label' => 'Of the file', 'align' => 'right'],
                    ['key' => 'note', 'label' => 'Why'],
                ],
                'rows' => $rows,
            ],
        )->withMeta(array_filter([
            'canvas' => self::CANVAS_WIDTH.'×'.self::CANVAS_HEIGHT,
            'measured_width' => $measured['width'] ?? null,
            'measured_height' => $measured['height'] ?? null,
        ], fn ($value) => $value !== null))
            ->withWarnings($this->warnings($measured, $url));
    }

    /** @param  array{width: int, height: int}|null  $measured */
    private function summary(?array $measured): string
    {
        $base = 'Everything that matters — your name, your face, your tagline — belongs inside the '
            .'1546×423 mobile window in the centre. The rest of the canvas is décor for a television.';

        if ($measured === null) {
            return $base;
        }

        if ($measured['width'] === self::CANVAS_WIDTH && $measured['height'] === self::CANVAS_HEIGHT) {
            return "That image is 2560×1440 — exactly YouTube's canvas. {$base}";
        }

        return "That image is {$measured['width']}×{$measured['height']}, not 2560×1440, so YouTube "
            ."will scale it before it crops. {$base}";
    }

    /**
     * @param  array{width: int, height: int}|null  $measured
     * @return list<string>
     */
    private function warnings(?array $measured, string $url): array
    {
        $warnings = [
            'The 6 MB upload ceiling is on the file, not the canvas: a photographic banner at '
            .'2560×1440 often needs to be exported as a JPEG rather than a PNG to clear it.',
        ];

        if ($url !== '' && $measured === null) {
            $warnings[] = 'That URL did not come back as an image we could measure, so the crops are '
                .'drawn over it without a verdict on its size. A link to a page rather than to the '
                .'file itself is the usual cause.';
        }

        if ($measured !== null && $measured['width'] < self::CANVAS_WIDTH) {
            $warnings[] = 'That image is narrower than 2560 px. YouTube will upscale it, and upscaling '
                .'is what makes a banner look soft on a television while looking fine on a phone.';
        }

        return $warnings;
    }

    /**
     * The image's real dimensions, or null when the URL is not an image we can read.
     *
     * @return array{width: int, height: int}|null
     */
    private function measure(string $url): ?array
    {
        $response = SafeHttpClient::attempt($url);

        if ($response === null || $response->failed()) {
            return null;
        }

        $size = @getimagesizefromstring(SafeHttpClient::body($response));

        if ($size === false || $size[0] < 1 || $size[1] < 1) {
            return null;
        }

        return ['width' => $size[0], 'height' => $size[1]];
    }
}
