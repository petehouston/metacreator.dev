<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Support\Social\PreviewFrame;

/**
 * Where each platform's own UI will cover your frame.
 *
 * Vertical video is designed on a clean canvas and then published under a caption,
 * a profile row and a stack of buttons. This draws each canvas with that chrome
 * shaded over it, and gives the exact margins to keep clear in pixels at the size
 * you work in.
 */
final class SafeZoneGuideRunner implements Cacheable, ToolRunner
{
    /**
     * Margins in pixels on a 1080×1920 canvas, measured against each app's chrome.
     *
     * @var array<string, array{label: string, platform: string, width: int, height: int, top: int, bottom: int, left: int, right: int, note: string}>
     */
    private const SURFACES = [
        'tiktok' => ['label' => 'TikTok', 'platform' => 'tiktok', 'width' => 1080, 'height' => 1920,
            'top' => 130, 'bottom' => 500, 'left' => 60, 'right' => 260,
            'note' => 'The right rail (avatar, likes, sound) is the widest intrusion on any platform.'],
        'reels' => ['label' => 'Instagram Reels', 'platform' => 'instagram', 'width' => 1080, 'height' => 1920,
            'top' => 130, 'bottom' => 420, 'left' => 60, 'right' => 220,
            'note' => 'Caption and audio row sit over the bottom fifth.'],
        'stories' => ['label' => 'Instagram / Facebook Stories', 'platform' => 'instagram', 'width' => 1080, 'height' => 1920,
            'top' => 250, 'bottom' => 250, 'left' => 60, 'right' => 60,
            'note' => 'Progress bar and profile row at the top; reply box at the bottom.'],
        'shorts' => ['label' => 'YouTube Shorts', 'platform' => 'youtube', 'width' => 1080, 'height' => 1920,
            'top' => 120, 'bottom' => 380, 'left' => 60, 'right' => 200,
            'note' => 'Title, channel name and the subscribe button all sit low-left.'],
        'youtube_thumb' => ['label' => 'YouTube thumbnail', 'platform' => 'youtube', 'width' => 1280, 'height' => 720,
            'top' => 0, 'bottom' => 60, 'left' => 0, 'right' => 120,
            'note' => 'The duration badge covers the bottom-right corner on every surface.'],
        'feed_square' => ['label' => 'Instagram feed (square)', 'platform' => 'instagram', 'width' => 1080, 'height' => 1080,
            'top' => 0, 'bottom' => 0, 'left' => 0, 'right' => 0,
            'note' => 'No chrome over the image, but the grid tile centre-crops a 4:5 post.'],
    ];

    public static function key(): string
    {
        return 'media.safe-zone-guide';
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
            'required' => [],
            'additionalProperties' => false,
            'properties' => [
                'surface' => [
                    'type' => 'string',
                    'title' => 'Designing for',
                    'enum' => ['all', ...array_keys(self::SURFACES)],
                    'default' => 'all',
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $chosen = $input->string('surface', 'all');

        $rows = [];
        $frames = [];

        foreach (self::SURFACES as $key => $surface) {
            if ($chosen !== 'all' && $chosen !== $key) {
                continue;
            }

            $safeWidth = $surface['width'] - $surface['left'] - $surface['right'];
            $safeHeight = $surface['height'] - $surface['top'] - $surface['bottom'];
            $covered = 1 - (($safeWidth * $safeHeight) / ($surface['width'] * $surface['height']));

            $rows[] = [
                'surface' => $surface['label'],
                'canvas' => "{$surface['width']} × {$surface['height']}",
                'safe_area' => "{$safeWidth} × {$safeHeight}",
                'margins' => "top {$surface['top']} · bottom {$surface['bottom']} · "
                    ."left {$surface['left']} · right {$surface['right']}",
                'note' => $surface['note'],
            ];

            $frames[] = PreviewFrame::make($surface['platform'], $surface['label'], 'safe-zone')
                ->safeZone(
                    $surface['width'], $surface['height'],
                    $surface['top'], $surface['bottom'], $surface['left'], $surface['right'],
                )
                ->status(
                    $covered > 0.35 ? 'danger' : ($covered > 0.15 ? 'warn' : 'ok'),
                    round($covered * 100).'% of the frame is covered',
                )
                ->detail('Canvas', "{$surface['width']} × {$surface['height']} px")
                ->detail('Safe area', "{$safeWidth} × {$safeHeight} px")
                ->detail('Keep clear', "top {$surface['top']} · bottom {$surface['bottom']} · "
                    ."left {$surface['left']} · right {$surface['right']} px")
                ->note($surface['note'])
                ->toArray();
        }

        return ToolResult::socialPreview(
            $frames,
            summary: 'Keep every word and face inside the clear area — anything in the shaded margins is '
                .'covered by the app’s own buttons on at least one device.',
            table: [
                'columns' => [
                    ['key' => 'surface', 'label' => 'Surface'],
                    ['key' => 'canvas', 'label' => 'Canvas'],
                    ['key' => 'safe_area', 'label' => 'Safe area'],
                    ['key' => 'margins', 'label' => 'Keep clear (px)'],
                    ['key' => 'note', 'label' => 'Why'],
                ],
                'rows' => $rows,
            ],
        )->withWarnings([
            'Margins are measured on current app versions and drift when a platform redesigns. '
            .'Leave a little more room than the minimum on anything you cannot re-export.',
        ]);
    }
}
