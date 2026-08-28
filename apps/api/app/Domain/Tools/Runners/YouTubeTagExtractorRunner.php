<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Contracts\UsesProvider;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Exceptions\ToolExecutionException;
use App\Support\Http\SafeHttpClient;
use App\Support\Social\YouTubeUrl;

/**
 * The tags on a public video, read from the page's own metadata.
 *
 * No API quota is spent: YouTube publishes the tags in a `keywords` meta tag on the
 * watch page, which is public information about a public video (see docs/08 on
 * compliance).
 */
final class YouTubeTagExtractorRunner implements Cacheable, ToolRunner, UsesProvider
{
    public static function key(): string
    {
        return 'youtube.tag-extractor';
    }

    public function providers(): array
    {
        return ['youtube'];
    }

    public function cacheTtl(): int
    {
        return 3600;
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
                "That doesn't look like a YouTube video link.",
                ['url' => 'Unrecognised YouTube URL or video ID.'],
            );

        $html = SafeHttpClient::body(SafeHttpClient::get(YouTubeUrl::watchUrl($videoId)));

        $tags = $this->tags($html);
        $title = $this->meta($html, 'title');

        if ($tags === []) {
            return ToolResult::table(
                columns: [['key' => 'tag', 'label' => 'Tag']],
                rows: [],
                summary: 'This video has no tags — which is increasingly common. '
                    .'Tags carry very little ranking weight now; the title, description and thumbnail do the work.',
            )->withMeta(['video_id' => $videoId, 'title' => $title]);
        }

        $rows = array_map(fn (string $tag) => [
            'tag' => $tag,
            'words' => count(preg_split('/\s+/u', $tag, -1, PREG_SPLIT_NO_EMPTY) ?: []),
            'characters' => mb_strlen($tag),
        ], $tags);

        $totalChars = array_sum(array_column($rows, 'characters')) + count($rows) - 1;

        return ToolResult::table(
            columns: [
                ['key' => 'tag', 'label' => 'Tag'],
                ['key' => 'words', 'label' => 'Words', 'align' => 'right'],
                ['key' => 'characters', 'label' => 'Characters', 'align' => 'right'],
            ],
            rows: $rows,
            summary: count($rows).' tags on “'.($title ?? $videoId).'”, '
                .number_format($totalChars).' of YouTube’s 500-character tag budget used.',
        )->withMeta([
            'video_id' => $videoId,
            'title' => $title,
            'tag_string' => implode(', ', $tags),
        ]);
    }

    /** @return list<string> */
    private function tags(string $html): array
    {
        if (preg_match('/<meta name="keywords" content="([^"]*)"/i', $html, $match) !== 1) {
            return [];
        }

        $tags = array_map('trim', explode(',', html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5)));

        return array_values(array_filter($tags, fn (string $tag) => $tag !== ''));
    }

    private function meta(string $html, string $property): ?string
    {
        return preg_match('/<meta property="og:'.$property.'" content="([^"]*)"/i', $html, $match) === 1
            ? html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5)
            : null;
    }
}
