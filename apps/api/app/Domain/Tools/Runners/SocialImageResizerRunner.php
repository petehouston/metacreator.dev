<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Support\Media\ImageCanvas;

/**
 * One image, every size a platform actually asks for.
 *
 * The sizes are the point. Almost every "social media sizes" list on the web is
 * years stale, and a post cropped by the platform instead of by you is the most
 * common avoidable mistake in the whole catalog. Each output is a cover crop at the
 * exact pixel dimensions the surface uses, taken around a focal point you choose —
 * because a square crop of a 16:9 photo throws away two thirds of it, and which two
 * thirds is a decision, not a default.
 */
final class SocialImageResizerRunner implements ToolRunner
{
    /**
     * Target surfaces, grouped by platform.
     *
     * @var array<string, array{platform: string, label: string, width: int, height: int}>
     */
    private const SIZES = [
        'instagram_square' => ['platform' => 'instagram', 'label' => 'Instagram feed (square)', 'width' => 1080, 'height' => 1080],
        'instagram_portrait' => ['platform' => 'instagram', 'label' => 'Instagram feed (portrait)', 'width' => 1080, 'height' => 1350],
        'instagram_story' => ['platform' => 'instagram', 'label' => 'Instagram story / Reel', 'width' => 1080, 'height' => 1920],
        'facebook_post' => ['platform' => 'facebook', 'label' => 'Facebook feed post', 'width' => 1200, 'height' => 630],
        'facebook_cover' => ['platform' => 'facebook', 'label' => 'Facebook page cover', 'width' => 1640, 'height' => 664],
        'x_post' => ['platform' => 'x', 'label' => 'X in-stream image', 'width' => 1600, 'height' => 900],
        'x_header' => ['platform' => 'x', 'label' => 'X profile header', 'width' => 1500, 'height' => 500],
        'linkedin_post' => ['platform' => 'linkedin', 'label' => 'LinkedIn feed post', 'width' => 1200, 'height' => 627],
        'linkedin_cover' => ['platform' => 'linkedin', 'label' => 'LinkedIn page cover', 'width' => 1128, 'height' => 191],
        'youtube_thumbnail' => ['platform' => 'youtube', 'label' => 'YouTube thumbnail', 'width' => 1280, 'height' => 720],
        'youtube_banner' => ['platform' => 'youtube', 'label' => 'YouTube channel banner', 'width' => 2048, 'height' => 1152],
        'pinterest_pin' => ['platform' => 'pinterest', 'label' => 'Pinterest standard Pin', 'width' => 1000, 'height' => 1500],
        'tiktok_video' => ['platform' => 'tiktok', 'label' => 'TikTok video frame', 'width' => 1080, 'height' => 1920],
    ];

    public static function key(): string
    {
        return 'media.image-resizer';
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
                    'description' => 'A public link to your image. Leave it empty to see the crops on a sample image.',
                    'maxLength' => 500,
                    'default' => '',
                    'examples' => ['https://example.com/hero.jpg'],
                ],
                'platform' => [
                    'type' => 'string',
                    'title' => 'Platform',
                    'description' => 'Resize for one network, or all of them at once.',
                    'enum' => ['all', 'instagram', 'facebook', 'x', 'linkedin', 'youtube', 'pinterest', 'tiktok'],
                    'default' => 'all',
                ],
                'focus' => [
                    'type' => 'string',
                    'title' => 'Keep in frame',
                    'description' => 'Which part of the image every crop must keep. Centre suits most photos; '
                        .'pick top for portraits so faces survive the square crop.',
                    'enum' => ['center', 'top', 'bottom', 'left', 'right'],
                    'default' => 'center',
                ],
                'format' => [
                    'type' => 'string',
                    'title' => 'Output format',
                    'enum' => ['jpeg', 'png', 'webp'],
                    'default' => 'jpeg',
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $platform = $input->string('platform', 'all');
        $format = $input->string('format', 'jpeg');
        [$focusX, $focusY] = self::focus($input->string('focus', 'center'));

        $canvas = ImageCanvas::open($input->string('image_url'), 'resizer', 1600, 1600);

        $artifacts = [];
        $upscaled = [];

        foreach (self::SIZES as $key => $size) {
            if ($platform !== 'all' && $size['platform'] !== $platform) {
                continue;
            }

            if ($size['width'] > $canvas->width || $size['height'] > $canvas->height) {
                $upscaled[] = $size['label'];
            }

            $artifacts[] = ImageCanvas::artifact(
                $canvas->cover($size['width'], $size['height'], $focusX, $focusY),
                filename: str_replace('_', '-', $key)."-{$size['width']}x{$size['height']}.{$format}",
                format: $format,
                label: "{$size['label']} — {$size['width']} × {$size['height']}",
            );
        }

        $warnings = [];

        if ($canvas->isPlaceholder) {
            $warnings[] = 'No image URL given, so these crops are cut from a generated sample. '
                .'Paste a link to your own image to resize it.';
        }

        if ($upscaled !== []) {
            $warnings[] = 'Your image is smaller than '.count($upscaled).' of these targets ('
                .implode(', ', array_slice($upscaled, 0, 3)).'), so those were enlarged and will look soft. '
                .'Start from an image at least 2048 px wide.';
        }

        return ToolResult::media(
            $artifacts,
            summary: count($artifacts).' crops from a '.$canvas->width.' × '.$canvas->height
                .' source, each at the exact size the platform uses.',
        )->withWarnings($warnings)->withMeta([
            'source_width' => $canvas->width,
            'source_height' => $canvas->height,
        ]);
    }

    /** @return array{float, float} */
    private static function focus(string $focus): array
    {
        return match ($focus) {
            'top' => [0.5, 0.0],
            'bottom' => [0.5, 1.0],
            'left' => [0.0, 0.5],
            'right' => [1.0, 0.5],
            default => [0.5, 0.5],
        };
    }
}
