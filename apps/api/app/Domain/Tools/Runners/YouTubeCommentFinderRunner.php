<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Settings\Settings;
use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Contracts\UsesProvider;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Exceptions\ToolExecutionException;
use App\Support\Http\SafeHttpClient;
use App\Support\Social\YouTubeUrl;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Finds a comment on a video by what it said.
 *
 * The one tool here that genuinely needs the YouTube Data API: comments are not in
 * the page metadata, and the internal endpoint that serves them to the browser is
 * not a public API, so scraping them would be exactly the trade docs/08 says we do
 * not make. `commentThreads.list` is the official, documented route, and it takes
 * the search terms server-side so we fetch one page instead of paging the lot.
 *
 * With no API key configured the tool says so plainly rather than failing obscurely.
 */
final class YouTubeCommentFinderRunner implements Cacheable, ToolRunner, UsesProvider
{
    private const ENDPOINT = 'https://www.googleapis.com/youtube/v3/commentThreads';

    public const API_KEY_SETTING = 'providers.youtube.api_key';

    public function __construct(private readonly Settings $settings) {}

    public static function key(): string
    {
        return 'youtube.comment-finder';
    }

    public function providers(): array
    {
        return ['youtube'];
    }

    public function cacheTtl(): int
    {
        return 900;
    }

    public function inputSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'required' => ['url', 'query'],
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
                'query' => [
                    'type' => 'string',
                    'title' => 'Words in the comment',
                    'description' => 'Matched against the comment text. A phrase in quotes matches exactly.',
                    'minLength' => 2,
                    'maxLength' => 120,
                    'examples' => ['timestamp'],
                ],
                'order' => [
                    'type' => 'string',
                    'title' => 'Order',
                    'enum' => ['relevance', 'time'],
                    'default' => 'relevance',
                ],
                'limit' => [
                    'type' => 'integer',
                    'title' => 'How many to return',
                    'minimum' => 5,
                    'maximum' => 100,
                    'default' => 50,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $apiKey = $this->settings->string(self::API_KEY_SETTING);

        if ($apiKey === '') {
            throw new ToolExecutionException(
                'Comment search is not available on this site yet — it needs a YouTube Data API key, '
                .'and the site owner has not added one.',
                'tool.unavailable',
            );
        }

        $videoId = YouTubeUrl::videoId($input->string('url'))
            ?? throw ToolExecutionException::invalidInput(
                "That doesn't look like a YouTube video link.",
                ['url' => 'Unrecognised YouTube URL or video ID.'],
            );

        $query = trim($input->string('query'));
        $limit = max(5, min(100, $input->int('limit', 50)));

        $threads = $this->fetch($apiKey, $videoId, $query, $input->string('order', 'relevance'), $limit);

        $rows = [];

        foreach ($threads as $thread) {
            $comment = $thread['snippet']['topLevelComment'] ?? null;

            if (! is_array($comment)) {
                continue;
            }

            $snippet = $comment['snippet'] ?? [];
            $commentId = is_string($comment['id'] ?? null) ? $comment['id'] : null;

            $publishedAt = $this->text($snippet, 'publishedAt');

            $rows[] = [
                'author' => $this->text($snippet, 'authorDisplayName'),
                // The card lays the comment out over as many lines as it needs, so
                // unlike a table row it keeps the author's own line breaks.
                'body' => trim($this->text($snippet, 'textDisplay')),
                'avatar' => $this->httpsUrl($this->text($snippet, 'authorProfileImageUrl')),
                'likes' => (int) ($snippet['likeCount'] ?? 0),
                'replies' => (int) ($thread['snippet']['totalReplyCount'] ?? 0),
                'published_at' => $publishedAt,
                'published' => $this->date($publishedAt),
                'link' => $commentId !== null
                    ? YouTubeUrl::watchUrl($videoId)."&lc={$commentId}"
                    : '',
            ];
        }

        // Relevance ordering is the API's; within it, the loudest comment first is
        // what people actually want when hunting for a half-remembered one.
        usort($rows, fn (array $a, array $b) => $b['likes'] <=> $a['likes']);

        return ToolResult::comments(
            comments: $rows,
            summary: $rows === []
                ? "No comment on this video matches “{$query}”."
                : count($rows)." comment(s) matching “{$query}”, most-liked first.",
        )->withMeta(['video_id' => $videoId, 'query' => $query, 'matches' => count($rows)]);
    }

    /**
     * The endpoint is ours, not the user's, so it goes straight through the HTTP
     * client — {@see SafeHttpClient} guards user-supplied URLs and
     * flattens every failure into "not found", which would hide the two statuses
     * that need their own message here.
     *
     * @return list<array<string, mixed>>
     */
    private function fetch(string $apiKey, string $videoId, string $query, string $order, int $limit): array
    {
        $parameters = [
            'part' => 'snippet',
            'videoId' => $videoId,
            'searchTerms' => $query,
            'maxResults' => $limit,
            'textFormat' => 'plainText',
            'key' => $apiKey,
        ];

        // `order=relevance` alongside `searchTerms` makes the API answer 400
        // processingFailure — a long-standing quirk of commentThreads.list. Only
        // `time` is safe to send; relevance falls back to the API default and the
        // most-liked-first sort we apply to the rows anyway.
        if ($order === 'time') {
            $parameters['order'] = 'time';
        }

        try {
            $response = Http::timeout(8.0)->connectTimeout(3.0)->get(self::ENDPOINT, $parameters);
        } catch (Throwable) {
            throw ToolExecutionException::upstreamFailed('youtube');
        }

        if ($response->status() === 403) {
            throw new ToolExecutionException(
                'Comments are turned off for this video, or the API key has run out of quota for today.',
                'tool.upstream_failed',
                ['provider' => 'youtube'],
            );
        }

        if ($response->status() === 404) {
            throw ToolExecutionException::notFound('that video');
        }

        if ($response->status() === 400) {
            throw ToolExecutionException::upstreamFailed(
                'youtube',
                'YouTube rejected the comment search. Try different search words, or a shorter phrase.',
            );
        }

        if ($response->failed()) {
            throw ToolExecutionException::upstreamFailed('youtube');
        }

        $items = $response->json('items');

        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    /** @param  array<string, mixed>  $snippet */
    private function text(array $snippet, string $key): string
    {
        $value = $snippet[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * The avatar URL comes from the API, not the user, but it is still rendered into
     * a `src`: anything that is not plain https is dropped rather than passed through.
     */
    private function httpsUrl(string $value): string
    {
        return str_starts_with($value, 'https://') ? $value : '';
    }

    private function date(string $value): string
    {
        $timestamp = strtotime($value);

        return $timestamp === false ? $value : gmdate('j M Y', $timestamp);
    }
}
