<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Support\Media\ImageCanvas;

/**
 * One image, exported at the three shapes Pinterest actually distributes.
 *
 * 2:3 is the only ratio that renders in full everywhere, which is why it is first.
 * The square is for the board cover, and the 9:16 is for Idea Pins — the same
 * artwork, three different crops, and Pinterest picks the crop, not you.
 */
final class PinImageSizerRunner implements ToolRunner
{
    /** @var list<array{key: string, label: string, width: int, height: int, note: string}> */
    private const SIZES = [
        ['key' => 'standard-pin', 'label' => 'Standard Pin (2:3)', 'width' => 1000, 'height' => 1500,
            'note' => 'Never cropped in the feed. Use this one unless you have a reason not to.'],
        ['key' => 'square-pin', 'label' => 'Square Pin (1:1)', 'width' => 1000, 'height' => 1000,
            'note' => 'Board covers and profile tiles.'],
        ['key' => 'idea-pin', 'label' => 'Idea Pin (9:16)', 'width' => 1080, 'height' => 1920,
            'note' => 'Full-screen, with Pinterest chrome over the top and bottom.'],
    ];

    public static function key(): string
    {
        return 'pinterest.pin-image-sizer';
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
                    'description' => 'A public link to your Pin artwork. Leave empty to see it on a sample.',
                    'maxLength' => 500,
                    'default' => '',
                    'examples' => ['https://example.com/pin.jpg'],
                ],
                'focus' => [
                    'type' => 'string',
                    'title' => 'Keep in frame',
                    'description' => 'The part of the artwork every crop must keep — usually where the text sits.',
                    'enum' => ['center', 'top', 'bottom'],
                    'default' => 'center',
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $canvas = ImageCanvas::open($input->string('image_url'), 'pin-sizer', 1000, 1500);

        $focusY = match ($input->string('focus', 'center')) {
            'top' => 0.0,
            'bottom' => 1.0,
            default => 0.5,
        };

        $artifacts = [];

        foreach (self::SIZES as $size) {
            $artifacts[] = ImageCanvas::artifact(
                $canvas->cover($size['width'], $size['height'], 0.5, $focusY),
                filename: "{$size['key']}-{$size['width']}x{$size['height']}.jpg",
                label: "{$size['label']} — {$size['width']} × {$size['height']}",
            );
        }

        return ToolResult::media(
            $artifacts,
            summary: 'Three Pinterest-native exports from one '.$canvas->width.' × '.$canvas->height.' source.',
        )->withWarnings(array_values(array_filter([
            $canvas->isPlaceholder
                ? 'No image URL given, so these exports came from a generated sample.'
                : null,
            $canvas->width > $canvas->height
                ? 'Your source is landscape. Pinterest is a vertical surface: a landscape Pin takes so '
                .'little height in the feed that it is scrolled past. Start from a portrait image.'
                : null,
            'Text on a Pin has to be legible at about 236 px wide, which is the size of a feed tile. '
            .'If you cannot read it at thumbnail size, nobody will.',
        ])));
    }
}
