<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Support\Media\ImageCanvas;

/**
 * How small the file gets, and what it costs you to get there.
 *
 * Compression tools usually show one number: the new file size. That is the half of
 * the trade that flatters the tool. This encodes the image at several qualities in
 * one pass so the saving and the damage sit next to each other, and you pick the
 * point where the image still looks like itself.
 */
final class ImageCompressorRunner implements ToolRunner
{
    /** The ladder worth showing: below 55 the artefacts are visible on flat colour. */
    private const STEPS = [
        ['quality' => 90, 'label' => 'Near-original'],
        ['quality' => 80, 'label' => 'Recommended'],
        ['quality' => 70, 'label' => 'Aggressive'],
        ['quality' => 55, 'label' => 'Maximum saving'],
    ];

    public static function key(): string
    {
        return 'media.image-compressor';
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
                    'description' => 'A public link to the image you want to shrink. Leave empty to try it on a sample.',
                    'maxLength' => 500,
                    'default' => '',
                    'examples' => ['https://example.com/hero.jpg'],
                ],
                'format' => [
                    'type' => 'string',
                    'title' => 'Compress as',
                    'description' => 'WebP is usually 25–35% smaller than JPEG at the same visual quality.',
                    'enum' => ['jpeg', 'webp'],
                    'default' => 'webp',
                ],
                'max_width' => [
                    'type' => 'integer',
                    'title' => 'Max width (px)',
                    'description' => 'Resizing first is where the real saving is: a 4000 px photo displayed at '
                        .'1080 px is carrying four times the pixels it needs.',
                    'minimum' => 200,
                    'maximum' => 4000,
                    'default' => 1600,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $format = $input->string('format', 'webp');
        $maxWidth = $input->int('max_width', 1600);

        $canvas = ImageCanvas::open($input->string('image_url'), 'compressor', 2000, 1250);
        $resized = $canvas->fit($maxWidth, $maxWidth * 4);

        // The honest baseline is the source file when there is one. For a generated
        // sample it is the same pixels at quality 100 — a lossless PNG baseline
        // would report a 99% "saving" that says more about PNG than about the tool.
        $original = $canvas->sourceBytes > 0
            ? $canvas->sourceBytes
            : strlen(ImageCanvas::encode($resized, $format, 100));

        $artifacts = [];
        $rows = [];

        foreach (self::STEPS as $step) {
            $artifact = ImageCanvas::artifact(
                $resized,
                filename: "compressed-q{$step['quality']}.{$format}",
                format: $format,
                quality: $step['quality'],
                label: "{$step['label']} — quality {$step['quality']}",
            );

            $artifacts[] = $artifact;

            $rows[] = [
                'label' => $step['label'],
                'quality' => $step['quality'],
                'size' => self::bytes($artifact->size),
                'saving' => $original > 0 ? round((1 - $artifact->size / $original) * 100).'%' : '—',
            ];
        }

        $recommended = $artifacts[1];

        return ToolResult::media(
            $artifacts,
            summary: 'Original '.self::bytes($original).' → '.self::bytes($recommended->size)
                .' at the recommended setting, a '
                .($original > 0 ? round((1 - $recommended->size / $original) * 100) : 0).'% saving.',
        )->withWarnings(array_values(array_filter([
            $canvas->isPlaceholder
                ? 'No image URL given, so this ran on a generated sample. Paste a link to compress your own.'
                : null,
            'Every step re-encodes from your source once. Compressing an already-compressed JPEG a second '
            .'time loses quality without saving much — always start from the original export.',
        ])))->withMeta([
            'original_bytes' => $original,
            'steps' => $rows,
            'source_format' => $canvas->sourceFormat,
        ]);
    }

    private static function bytes(int $bytes): string
    {
        return $bytes >= 1_048_576
            ? round($bytes / 1_048_576, 2).' MB'
            : round($bytes / 1024).' KB';
    }
}
