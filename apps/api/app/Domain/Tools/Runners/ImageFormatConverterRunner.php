<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Support\Media\ImageCanvas;

/**
 * PNG ⇄ JPEG ⇄ WebP, with the transparency question answered out loud.
 *
 * The conversion itself is trivial. The part people get wrong is that JPEG has no
 * alpha channel, so a logo with a transparent background converted to JPEG comes
 * back with a white box around it — silently, in whatever tool they used. This one
 * says so before you download it.
 */
final class ImageFormatConverterRunner implements ToolRunner
{
    public static function key(): string
    {
        return 'media.image-format-converter';
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
                    'description' => 'A public link to the image to convert. Leave empty to try it on a sample.',
                    'maxLength' => 500,
                    'default' => '',
                    'examples' => ['https://example.com/logo.png'],
                ],
                'format' => [
                    'type' => 'string',
                    'title' => 'Convert to',
                    'description' => 'WebP for the web, PNG when you need transparency, JPEG for photos everywhere.',
                    'enum' => ['webp', 'png', 'jpeg', 'gif'],
                    'default' => 'webp',
                ],
                'quality' => [
                    'type' => 'integer',
                    'title' => 'Quality',
                    'description' => 'Ignored for PNG and GIF, which are lossless.',
                    'minimum' => 40,
                    'maximum' => 100,
                    'default' => 88,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $format = $input->string('format', 'webp');
        $quality = $input->int('quality', 88);

        $canvas = ImageCanvas::open($input->string('image_url'), 'converter', 1400, 1400);
        $image = $canvas->fit(2400, 2400);

        $artifact = ImageCanvas::artifact(
            $image,
            filename: "converted.{$format}",
            format: $format,
            quality: $quality,
            label: strtoupper($format).' — '.imagesx($image).' × '.imagesy($image),
        );

        $warnings = [];

        if ($canvas->isPlaceholder) {
            $warnings[] = 'No image URL given, so this converted a generated sample.';
        }

        if ($format === 'jpeg' && in_array($canvas->sourceFormat, ['png', 'webp', 'gif'], true)) {
            $warnings[] = 'JPEG has no transparency. Any transparent area in the source has been filled '
                .'with white — if that matters, convert to PNG or WebP instead.';
        }

        if ($format === 'gif') {
            $warnings[] = 'GIF is limited to 256 colours, so photographs band visibly. It is only the right '
                .'choice for flat graphics and animation, and animation is not preserved here.';
        }

        return ToolResult::media(
            [$artifact],
            summary: strtoupper($canvas->sourceFormat).' → '.strtoupper($format).', '
                .round($artifact->size / 1024).' KB at '.imagesx($image).' × '.imagesy($image).'.',
        )->withWarnings($warnings)->withMeta([
            'source_format' => $canvas->sourceFormat,
            'output_format' => $format,
            'output_bytes' => $artifact->size,
        ]);
    }
}
