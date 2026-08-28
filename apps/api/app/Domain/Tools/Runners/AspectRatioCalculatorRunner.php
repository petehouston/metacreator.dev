<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Exceptions\ToolExecutionException;

/**
 * Resize without distorting, and know which platform slot the result fits.
 *
 * Change one dimension and the other is solved for you; the ratio is then matched
 * against the standard social sizes, because "1080 × 1350" means nothing until you
 * know it is the Instagram portrait slot.
 */
final class AspectRatioCalculatorRunner implements Cacheable, ToolRunner
{
    /** @var array<string, array{ratio: float, label: string}> */
    private const KNOWN = [
        '1:1' => ['ratio' => 1.0, 'label' => 'Square — Instagram feed, profile art'],
        '4:5' => ['ratio' => 0.8, 'label' => 'Instagram portrait — the tallest feed post allowed'],
        '9:16' => ['ratio' => 0.5625, 'label' => 'Vertical — Reels, Shorts, TikTok, Stories'],
        '16:9' => ['ratio' => 1.7778, 'label' => 'Widescreen — YouTube, X and LinkedIn video'],
        '1.91:1' => ['ratio' => 1.91, 'label' => 'Link card — og:image, Facebook and X previews'],
        '3:2' => ['ratio' => 1.5, 'label' => 'Classic photo'],
        '4:3' => ['ratio' => 1.3333, 'label' => 'Legacy video and older thumbnails'],
        '2:3' => ['ratio' => 0.6667, 'label' => 'Pinterest standard pin'],
    ];

    public static function key(): string
    {
        return 'utility.aspect-ratio-calculator';
    }

    public function cacheTtl(): int
    {
        return 86400;
    }

    public function inputSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'required' => ['width', 'height'],
            'additionalProperties' => false,
            'properties' => [
                'width' => [
                    'type' => 'integer',
                    'title' => 'Original width (px)',
                    'minimum' => 1,
                    'maximum' => 100000,
                    'examples' => [1920],
                ],
                'height' => [
                    'type' => 'integer',
                    'title' => 'Original height (px)',
                    'minimum' => 1,
                    'maximum' => 100000,
                    'examples' => [1080],
                ],
                'new_width' => [
                    'type' => 'integer',
                    'title' => 'New width (px)',
                    'description' => 'Fill in one of these two and we solve for the other.',
                    'minimum' => 0,
                    'maximum' => 100000,
                    'default' => 0,
                ],
                'new_height' => [
                    'type' => 'integer',
                    'title' => 'New height (px)',
                    'minimum' => 0,
                    'maximum' => 100000,
                    'default' => 0,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $width = $input->int('width');
        $height = $input->int('height');

        if ($width < 1 || $height < 1) {
            throw ToolExecutionException::invalidInput('Width and height must both be at least 1 pixel.');
        }

        $divisor = $this->gcd($width, $height);
        $ratio = $width / $height;
        $simplified = ($width / $divisor).':'.($height / $divisor);

        $newWidth = $input->int('new_width');
        $newHeight = $input->int('new_height');

        if ($newWidth > 0 && $newHeight === 0) {
            $newHeight = (int) round($newWidth / $ratio);
        } elseif ($newHeight > 0 && $newWidth === 0) {
            $newWidth = (int) round($newHeight * $ratio);
        }

        $match = $this->closest($ratio);

        $pairs = [
            ['label' => 'Aspect ratio', 'value' => $simplified,
                'hint' => 'Decimal '.round($ratio, 4), 'tone' => 'positive'],
            ['label' => 'Orientation', 'value' => match (true) {
                $ratio > 1.02 => 'Landscape',
                $ratio < 0.98 => 'Portrait',
                default => 'Square',
            }],
            ['label' => 'Closest standard', 'value' => $match['name'],
                'hint' => $match['label'].($match['exact'] ? '' : ' — you are close but not exact')],
            ['label' => 'Megapixels', 'value' => round($width * $height / 1_000_000, 2).' MP'],
        ];

        if ($newWidth > 0 && $newHeight > 0) {
            array_splice($pairs, 1, 0, [[
                'label' => 'Resized to',
                'value' => "{$newWidth} × {$newHeight}",
                'hint' => 'Same ratio, no distortion.',
                'tone' => 'positive',
            ]]);
        }

        return ToolResult::keyValue($pairs, summary: "{$width} × {$height} is {$simplified} — {$match['label']}.")
            ->withMeta(['ratio' => round($ratio, 4), 'simplified' => $simplified]);
    }

    /** @return array{name: string, label: string, exact: bool} */
    private function closest(float $ratio): array
    {
        $best = null;
        $bestDelta = PHP_FLOAT_MAX;

        foreach (self::KNOWN as $name => $known) {
            $delta = abs($known['ratio'] - $ratio);

            if ($delta < $bestDelta) {
                $bestDelta = $delta;
                $best = ['name' => $name, 'label' => $known['label'], 'exact' => $delta < 0.005];
            }
        }

        return $best ?? ['name' => '—', 'label' => 'Non-standard ratio', 'exact' => false];
    }

    private function gcd(int $a, int $b): int
    {
        return $b === 0 ? $a : $this->gcd($b, $a % $b);
    }
}
