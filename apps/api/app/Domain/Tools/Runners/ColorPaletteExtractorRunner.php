<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Support\Media\ImageCanvas;

/**
 * The colours already in your work, as hex you can paste.
 *
 * Brand consistency across a grid is mostly a matter of reusing the colours that
 * are already there rather than inventing new ones per post. Each swatch comes with
 * its contrast ratio against white and black, because a palette you cannot put text
 * on is a palette you will abandon by the third thumbnail.
 */
final class ColorPaletteExtractorRunner implements ToolRunner
{
    public static function key(): string
    {
        return 'media.color-palette-extractor';
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
                    'description' => 'A public link to the image. Leave empty to see the tool run on a sample.',
                    'maxLength' => 500,
                    'default' => '',
                    'examples' => ['https://example.com/photo.jpg'],
                ],
                'colors' => [
                    'type' => 'integer',
                    'title' => 'How many colours',
                    'minimum' => 3,
                    'maximum' => 10,
                    'default' => 6,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $canvas = ImageCanvas::open($input->string('image_url'), 'palette', 1200, 1200);
        $palette = $canvas->palette($input->int('colors', 6));

        $rows = [];

        foreach ($palette as $index => $swatch) {
            [$r, $g, $b] = $swatch['rgb'];
            $onWhite = self::contrast($swatch['rgb'], [255, 255, 255]);
            $onBlack = self::contrast($swatch['rgb'], [0, 0, 0]);

            $rows[] = [
                'rank' => $index === 0 ? 'Dominant' : '#'.($index + 1),
                'hex' => $swatch['hex'],
                'rgb' => "rgb({$r}, {$g}, {$b})",
                'share' => $swatch['share'].'%',
                'text' => $onWhite >= $onBlack
                    ? 'White text: '.round($onWhite, 1).':1'
                    : 'Black text: '.round($onBlack, 1).':1',
                'usable' => max($onWhite, $onBlack) >= 4.5 ? 'Text-safe' : 'Background only',
            ];
        }

        return ToolResult::table(
            columns: [
                ['key' => 'rank', 'label' => 'Rank'],
                ['key' => 'hex', 'label' => 'Hex'],
                ['key' => 'rgb', 'label' => 'RGB'],
                ['key' => 'share', 'label' => 'Share of image', 'align' => 'right'],
                ['key' => 'text', 'label' => 'Best text colour'],
                ['key' => 'usable', 'label' => 'Verdict'],
            ],
            rows: $rows,
            summary: 'Dominant colour is '.$palette[0]['hex'].', covering '.$palette[0]['share'].'% of the image.',
        )->withWarnings($canvas->isPlaceholder
            ? ['No image URL given, so this palette came from a generated sample.']
            : [])->withMeta([
                'palette' => array_column($palette, 'hex'),
                'css' => ':root {'.implode('', array_map(
                    fn (array $swatch, int $i) => " --brand-{$i}: {$swatch['hex']};",
                    $palette, array_keys($palette),
                )).' }',
            ]);
    }

    /**
     * WCAG contrast ratio between two colours.
     *
     * @param  array{int, int, int}  $a
     * @param  array{int, int, int}  $b
     */
    private static function contrast(array $a, array $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** @param  array{int, int, int}  $rgb */
    private static function luminance(array $rgb): float
    {
        $channels = array_map(function (int $value): float {
            $v = $value / 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
