<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Support\Media\ImageCanvas;

/**
 * One cover image that survives both places a Reel is seen.
 *
 * A Reel cover is used twice from a single 9:16 upload: full-bleed on the Reels
 * tab, and centre-cropped to a square-ish tile on your profile grid. Instagram
 * crops the grid tile from the **middle** of the frame, so a cover designed for the
 * full 9:16 loses its top and bottom on the profile — which is where most people
 * put the text. This exports both crops from the same source so you can see the
 * damage before it is permanent.
 */
final class ReelsCoverCropperRunner implements ToolRunner
{
    public static function key(): string
    {
        return 'instagram.reels-cover-cropper';
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
                    'title' => 'Cover image URL',
                    'description' => 'A public link to your cover frame. Leave empty to see it on a sample.',
                    'maxLength' => 500,
                    'default' => '',
                    'examples' => ['https://example.com/cover.jpg'],
                ],
                'grid_focus' => [
                    'type' => 'string',
                    'title' => 'Grid tile keeps',
                    'description' => 'Which band of the cover the square profile tile is taken from.',
                    'enum' => ['center', 'top', 'bottom'],
                    'default' => 'center',
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $canvas = ImageCanvas::open($input->string('image_url'), 'reels-cover', 1080, 1920);

        $focusY = match ($input->string('grid_focus', 'center')) {
            'top' => 0.0,
            'bottom' => 1.0,
            default => 0.5,
        };

        $artifacts = [
            ImageCanvas::artifact(
                $canvas->cover(1080, 1920),
                filename: 'reel-cover-1080x1920.jpg',
                label: 'Reels tab — 1080 × 1920 (9:16)',
            ),
            ImageCanvas::artifact(
                $canvas->cover(1080, 1350, 0.5, $focusY),
                filename: 'grid-tile-1080x1350.jpg',
                label: 'Profile grid tile — 1080 × 1350 (4:5)',
            ),
            ImageCanvas::artifact(
                $canvas->cover(1080, 1080, 0.5, $focusY),
                filename: 'square-tile-1080x1080.jpg',
                label: 'Square crop — 1080 × 1080 (1:1)',
            ),
        ];

        return ToolResult::media(
            $artifacts,
            summary: 'Your cover as the Reels tab shows it, and as the profile grid crops it.',
        )->withWarnings(array_values(array_filter([
            $canvas->isPlaceholder
                ? 'No image URL given, so these crops came from a generated sample.'
                : null,
            'Keep the cover text inside the middle 1080 × 1350 of the frame. Everything above and below '
            .'that band exists only on the Reels tab, and the profile grid is where people browse.',
            'Instagram also draws its own UI over the bottom fifth of the Reels view — check the '
            .'safe-zone guide before you commit to a layout.',
        ])))->withMeta([
            'source_width' => $canvas->width,
            'source_height' => $canvas->height,
        ]);
    }
}
