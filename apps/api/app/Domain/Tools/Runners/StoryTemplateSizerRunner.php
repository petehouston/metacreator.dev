<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Support\Media\ImageCanvas;
use GdImage;

/**
 * A story exported at 1080 × 1920, plus the same frame with the app's chrome shaded
 * over it.
 *
 * Two files, and the second one is the point. The clean export is what you upload;
 * the overlay is proof of what survives once the progress bar, profile row, reply
 * box and sticker rail are drawn on top. Designers check this in their head and get
 * it wrong roughly half the time, because the bottom intrusion is much taller than
 * it feels.
 */
final class StoryTemplateSizerRunner implements ToolRunner
{
    private const WIDTH = 1080;

    private const HEIGHT = 1920;

    /** @var array<string, array{label: string, top: int, bottom: int, side: int}> */
    private const SURFACES = [
        'instagram_story' => ['label' => 'Instagram / Facebook story', 'top' => 250, 'bottom' => 250, 'side' => 60],
        'instagram_reel' => ['label' => 'Instagram Reel', 'top' => 130, 'bottom' => 420, 'side' => 60],
        'tiktok' => ['label' => 'TikTok', 'top' => 130, 'bottom' => 500, 'side' => 60],
        'youtube_short' => ['label' => 'YouTube Short', 'top' => 120, 'bottom' => 380, 'side' => 60],
    ];

    public static function key(): string
    {
        return 'instagram.story-sizer';
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
                    'title' => 'Image URL',
                    'description' => 'A public link to the artwork. Leave empty to see the overlay on a sample.',
                    'maxLength' => 500,
                    'default' => '',
                    'examples' => ['https://example.com/story.jpg'],
                ],
                'surface' => [
                    'type' => 'string',
                    'title' => 'Designing for',
                    'enum' => array_keys(self::SURFACES),
                    'default' => 'instagram_story',
                ],
                'focus' => [
                    'type' => 'string',
                    'title' => 'Keep in frame',
                    'description' => 'Which part of a wider image survives the crop to 9:16.',
                    'enum' => ['center', 'top', 'bottom'],
                    'default' => 'center',
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $surface = self::SURFACES[$input->string('surface', 'instagram_story')] ?? self::SURFACES['instagram_story'];

        $focusY = match ($input->string('focus', 'center')) {
            'top' => 0.0,
            'bottom' => 1.0,
            default => 0.5,
        };

        $canvas = ImageCanvas::open($input->string('image_url'), 'story', self::WIDTH, self::HEIGHT);
        $clean = $canvas->cover(self::WIDTH, self::HEIGHT, 0.5, $focusY);

        $safeWidth = self::WIDTH - $surface['side'] * 2;
        $safeHeight = self::HEIGHT - $surface['top'] - $surface['bottom'];

        return ToolResult::media(
            [
                ImageCanvas::artifact(
                    $clean,
                    filename: 'story-1080x1920.jpg',
                    label: 'Upload this — 1080 × 1920, no overlay',
                ),
                ImageCanvas::artifact(
                    self::overlay($clean, $surface),
                    filename: 'story-safe-zone-check.jpg',
                    label: $surface['label'].' — shaded area is covered by the app',
                ),
            ],
            summary: 'Safe area for '.$surface['label'].' is '.$safeWidth.' × '.$safeHeight
                .' px, centred in the 1080 × 1920 frame.',
        )->withWarnings(array_values(array_filter([
            $canvas->isPlaceholder
                ? 'No image URL given, so the overlay was drawn on a generated sample.'
                : null,
            $canvas->width / $canvas->height > 0.75
                ? 'Your source is much wider than 9:16, so most of its width was cropped away. '
                .'Design vertically from the start rather than cropping a landscape image down.'
                : null,
            'Keep every word, logo and face inside the clear band. Margins shift when a platform '
            .'redesigns, so leave a little more room than the minimum.',
        ])))->withMeta([
            'safe_width' => $safeWidth,
            'safe_height' => $safeHeight,
            'margins' => $surface,
        ]);
    }

    /**
     * Shade the margins the app's own UI covers, and outline what is left.
     *
     * @param  array{label: string, top: int, bottom: int, side: int}  $surface
     */
    private static function overlay(GdImage $source, array $surface): GdImage
    {
        $out = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagecopy($out, $source, 0, 0, 0, 0, self::WIDTH, self::HEIGHT);

        $shade = ImageCanvas::colour($out, 0, 0, 0, 75);

        imagefilledrectangle($out, 0, 0, self::WIDTH, $surface['top'], $shade);
        imagefilledrectangle($out, 0, self::HEIGHT - $surface['bottom'], self::WIDTH, self::HEIGHT, $shade);
        imagefilledrectangle($out, 0, $surface['top'], $surface['side'], self::HEIGHT - $surface['bottom'], $shade);
        imagefilledrectangle(
            $out, self::WIDTH - $surface['side'], $surface['top'],
            self::WIDTH, self::HEIGHT - $surface['bottom'], $shade,
        );

        $outline = ImageCanvas::colour($out, 255, 255, 255);
        imagesetthickness($out, 6);
        imagerectangle(
            $out,
            $surface['side'], $surface['top'],
            self::WIDTH - $surface['side'], self::HEIGHT - $surface['bottom'],
            $outline,
        );

        return $out;
    }
}
