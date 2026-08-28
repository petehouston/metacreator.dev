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
 * Deep links to exact moments, and a chapter list built from the same input.
 *
 * Paste a list of `mm:ss Label` lines and you get one shareable link per moment,
 * plus a description block that YouTube will turn into real chapters — which needs
 * the first entry to be 0:00 and at least three entries, both checked here.
 */
final class YouTubeTimestampLinkBuilderRunner implements Cacheable, ToolRunner
{
    public static function key(): string
    {
        return 'youtube.timestamp-link-builder';
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
            'required' => ['url', 'timestamps'],
            'additionalProperties' => false,
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'x-control' => 'text',
                    'title' => 'YouTube video URL or ID',
                    'minLength' => 11,
                    'maxLength' => 500,
                    'examples' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
                ],
                'timestamps' => [
                    'type' => 'string',
                    'title' => 'Moments, one per line',
                    'description' => 'Format: “0:00 Intro”. Hours are fine too — “1:04:20 The twist”.',
                    'minLength' => 1,
                    'maxLength' => 10000,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $videoId = YouTubeUrl::videoId($input->string('url'))
            ?? throw ToolExecutionException::invalidInput(
                "That doesn't look like a YouTube video link.",
                ['url' => 'Unrecognised YouTube URL or video ID.'],
            );

        $rows = [];
        $chapterLines = [];

        foreach (preg_split('/\r?\n/', $input->string('timestamps')) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || preg_match('/^(\d{1,2}:)?\d{1,2}:\d{2}/', $line, $match) !== 1) {
                continue;
            }

            $stamp = $match[0];
            $label = trim(mb_substr($line, mb_strlen($stamp))) ?: 'Untitled';
            $seconds = $this->seconds($stamp);

            $rows[] = [
                'time' => $stamp,
                'label' => $label,
                'seconds' => $seconds,
                'link' => YouTubeUrl::watchUrl($videoId, $seconds),
                'short_link' => "https://youtu.be/{$videoId}?t={$seconds}",
            ];

            $chapterLines[] = "{$stamp} {$label}";
        }

        if ($rows === []) {
            throw ToolExecutionException::invalidInput(
                'No timestamps recognised. Each line needs to start with a time, like “1:23 The point”.',
                ['timestamps' => 'Expected lines such as “0:00 Intro”.'],
            );
        }

        $warnings = [];

        // YouTube only renders chapters when these three conditions all hold.
        if ($rows[0]['seconds'] !== 0) {
            $warnings[] = 'Chapters need a 0:00 entry first. Without it YouTube shows plain links, not chapters.';
        }

        if (count($rows) < 3) {
            $warnings[] = 'Chapters need at least three timestamps. You have '.count($rows).'.';
        }

        foreach ($rows as $index => $row) {
            if ($index > 0 && $row['seconds'] - $rows[$index - 1]['seconds'] < 10) {
                $warnings[] = 'Chapters must be at least 10 seconds apart — “'.$row['label'].'” is too close to the one before it.';

                break;
            }
        }

        return ToolResult::table(
            columns: [
                ['key' => 'time', 'label' => 'Time'],
                ['key' => 'label', 'label' => 'Moment'],
                ['key' => 'link', 'label' => 'Deep link'],
                ['key' => 'short_link', 'label' => 'Short link'],
            ],
            rows: $rows,
            summary: count($rows).' timestamp links built for video '.$videoId.'.',
        )->withWarnings($warnings)->withMeta([
            'video_id' => $videoId,
            'chapter_block' => implode("\n", $chapterLines),
        ]);
    }

    private function seconds(string $stamp): int
    {
        $parts = array_reverse(array_map('intval', explode(':', $stamp)));
        $seconds = 0;

        foreach ($parts as $index => $part) {
            $seconds += $part * (60 ** $index);
        }

        return $seconds;
    }
}
