<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Exceptions\ToolExecutionException;
use App\Support\Social\YouTubeUrl;

/**
 * Returns every thumbnail resolution YouTube publishes for a video.
 *
 * No API quota is consumed: thumbnails live on a predictable public CDN path, so
 * this resolves the video id and builds URLs. That is why the tool is free — it
 * costs us nothing per run.
 */
final class YouTubeThumbnailDownloaderRunner implements Cacheable, ToolRunner
{
    /**
     * Ordered best → worst. `maxres` and `sd` are not generated for every upload,
     * which the result flags rather than silently omitting.
     *
     * @var array<string, array{label: string, width: int, height: int, guaranteed: bool}>
     */
    private const RESOLUTIONS = [
        'maxresdefault' => ['label' => 'Max resolution', 'width' => 1280, 'height' => 720, 'guaranteed' => false],
        'sddefault' => ['label' => 'Standard definition', 'width' => 640, 'height' => 480, 'guaranteed' => false],
        'hqdefault' => ['label' => 'High quality', 'width' => 480, 'height' => 360, 'guaranteed' => true],
        'mqdefault' => ['label' => 'Medium quality', 'width' => 320, 'height' => 180, 'guaranteed' => true],
        'default' => ['label' => 'Thumbnail', 'width' => 120, 'height' => 90, 'guaranteed' => true],
    ];

    public static function key(): string
    {
        return 'youtube.thumbnail-downloader';
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
            'required' => ['url'],
            'additionalProperties' => false,
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'x-control' => 'text',
                    'title' => 'YouTube video URL or ID',
                    'description' => 'Paste any YouTube link — watch, share, embed, Shorts, or just the 11-character video ID.',
                    'minLength' => 11,
                    'maxLength' => 500,
                    'examples' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $videoId = YouTubeUrl::videoId($input->string('url'))
            ?? throw ToolExecutionException::invalidInput(
                "That doesn't look like a YouTube video link. Try a URL like https://youtube.com/watch?v=…",
                ['url' => 'Unrecognised YouTube URL or video ID.'],
            );

        $rows = [];

        foreach (self::RESOLUTIONS as $file => $meta) {
            $rows[] = [
                'resolution' => "{$meta['width']} × {$meta['height']}",
                'label' => $meta['label'],
                'url' => "https://i.ytimg.com/vi/{$videoId}/{$file}.jpg",
                'webp_url' => "https://i.ytimg.com/vi_webp/{$videoId}/{$file}.webp",
                'availability' => $meta['guaranteed'] ? 'Always available' : 'Depends on upload quality',
            ];
        }

        return ToolResult::table(
            columns: [
                ['key' => 'label', 'label' => 'Size'],
                ['key' => 'resolution', 'label' => 'Dimensions'],
                ['key' => 'availability', 'label' => 'Availability'],
                ['key' => 'url', 'label' => 'Download', 'align' => 'right', 'type' => 'download'],
            ],
            rows: $rows,
            summary: "Found 5 thumbnail sizes for video {$videoId}.",
        )->withMeta([
            'video_id' => $videoId,
            'preview_url' => "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg",
        ])->withWarnings([
            'Only download thumbnails you have the right to use. These images belong to the video owner.',
        ]);
    }
}
