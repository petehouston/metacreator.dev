<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Support\Media\ImageCanvas;

/**
 * One wide image, sliced into panels that line up when you swipe.
 *
 * The seam is the whole job. Splitting a panorama by eye leaves a few pixels
 * duplicated or missing at every join, and the mismatch is obvious in the app even
 * when it is invisible in the editor. Panels here are cut from exact integer
 * boundaries of the source, in order, at the aspect ratio the feed will display.
 */
final class CarouselSplitterRunner implements ToolRunner
{
    /** @var array<string, array{label: string, width: int, height: int}> */
    private const RATIOS = [
        '4:5' => ['label' => 'Portrait (recommended)', 'width' => 1080, 'height' => 1350],
        '1:1' => ['label' => 'Square', 'width' => 1080, 'height' => 1080],
        '9:16' => ['label' => 'Full vertical', 'width' => 1080, 'height' => 1920],
    ];

    public static function key(): string
    {
        return 'instagram.carousel-splitter';
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
                    'title' => 'Wide image URL',
                    'description' => 'A public link to the panorama. Leave empty to see the split on a sample.',
                    'maxLength' => 500,
                    'default' => '',
                    'examples' => ['https://example.com/panorama.jpg'],
                ],
                'panels' => [
                    'type' => 'integer',
                    'title' => 'Number of panels',
                    'description' => 'Instagram allows up to 10 slides in one carousel.',
                    'minimum' => 2,
                    'maximum' => 10,
                    'default' => 3,
                ],
                'ratio' => [
                    'type' => 'string',
                    'title' => 'Panel shape',
                    'description' => '4:5 takes the most vertical space in the feed, so it is the default.',
                    'enum' => array_keys(self::RATIOS),
                    'default' => '4:5',
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $panels = $input->int('panels', 3);
        $ratio = self::RATIOS[$input->string('ratio', '4:5')] ?? self::RATIOS['4:5'];

        // The sample is generated at the width the chosen split actually needs, so
        // the demo shows a real seam rather than an upscaled one.
        $canvas = ImageCanvas::open(
            $input->string('image_url'),
            'carousel',
            $ratio['width'] * $panels,
            $ratio['height'],
        );

        // Integer boundaries: the last panel absorbs the remainder rather than
        // every panel drifting by a fraction of a pixel.
        $sliceWidth = intdiv($canvas->width, $panels);

        $artifacts = [];

        for ($i = 0; $i < $panels; $i++) {
            $x = $i * $sliceWidth;
            $width = $i === $panels - 1 ? $canvas->width - $x : $sliceWidth;

            $artifacts[] = ImageCanvas::artifact(
                $canvas->region($x, 0, $width, $canvas->height, $ratio['width'], $ratio['height']),
                filename: sprintf('carousel-%02d.jpg', $i + 1),
                label: 'Slide '.($i + 1).' of '.$panels,
            );
        }

        $sourceRatio = $canvas->width / $canvas->height;
        $wanted = ($ratio['width'] * $panels) / $ratio['height'];

        $warnings = [];

        if ($canvas->isPlaceholder) {
            $warnings[] = 'No image URL given, so the panels were cut from a generated sample.';
        }

        if (! $canvas->isPlaceholder && abs($sourceRatio - $wanted) / $wanted > 0.1) {
            $warnings[] = 'Your image is '.round($sourceRatio, 2).':1 but '.$panels.' panels at '
                .$input->string('ratio', '4:5').' need '.round($wanted, 2).':1. Each panel has been '
                .'stretched to fit — export the source at '.($ratio['width'] * $panels).' × '
                .$ratio['height'].' px for a clean split.';
        }

        $warnings[] = 'Upload the slides in order, one per slot. Instagram does not reorder them for you, '
            .'and a carousel posted out of order cannot be fixed after publishing.';

        return ToolResult::media(
            $artifacts,
            summary: $panels.' seamless panels at '.$ratio['width'].' × '.$ratio['height']
                .' px, cut from a '.$canvas->width.' × '.$canvas->height.' source.',
        )->withWarnings($warnings)->withMeta([
            'panels' => $panels,
            'panel_width' => $ratio['width'],
            'panel_height' => $ratio['height'],
        ]);
    }
}
