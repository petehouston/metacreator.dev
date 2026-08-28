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

/**
 * Handle or custom URL → the `UC…` channel ID everything else needs.
 *
 * Handles are the public identity, but RSS feeds, the Data API and most third-party
 * tools still want the immutable channel ID — and there is no obvious place in the
 * YouTube UI to find it.
 */
final class YouTubeChannelIdFinderRunner implements Cacheable, ToolRunner, UsesProvider
{
    public static function key(): string
    {
        return 'youtube.channel-id-finder';
    }

    public function providers(): array
    {
        return ['youtube'];
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
            'required' => ['channel'],
            'additionalProperties' => false,
            'properties' => [
                'channel' => [
                    'type' => 'string',
                    'title' => 'Channel handle or URL',
                    'description' => 'A handle like @mkbhd, or any channel URL — including old /c/ and /user/ links.',
                    'minLength' => 2,
                    'maxLength' => 300,
                    'examples' => ['@mkbhd'],
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $url = $this->channelUrl(trim($input->string('channel')));

        $html = SafeHttpClient::body(SafeHttpClient::get($url));

        $channelId = $this->channelId($html) ?? throw ToolExecutionException::notFound('that channel');
        $name = preg_match('/<meta property="og:title" content="([^"]*)"/i', $html, $title) === 1
            ? html_entity_decode($title[1], ENT_QUOTES | ENT_HTML5)
            : null;

        return ToolResult::keyValue([
            ['label' => 'Channel ID', 'value' => $channelId, 'tone' => 'positive'],
            ['label' => 'Channel name', 'value' => $name ?? '—'],
            ['label' => 'Canonical URL', 'value' => "https://www.youtube.com/channel/{$channelId}"],
            ['label' => 'RSS feed', 'value' => "https://www.youtube.com/feeds/videos.xml?channel_id={$channelId}",
                'hint' => 'Every upload, no API key, no quota.'],
            ['label' => 'Uploads playlist', 'value' => 'UU'.substr($channelId, 2),
                'hint' => 'The playlist holding every public upload — swap the UC prefix for UU.'],
        ], summary: ($name !== null ? "“{$name}” is " : 'That channel is ')."channel ID {$channelId}.");
    }

    /**
     * The page mentions many `UC…` ids — every recommended channel in the sidebar
     * has one — so the canonical link and the explicit metadata are checked first,
     * and a bare match is only a last resort.
     */
    private function channelId(string $html): ?string
    {
        $patterns = [
            '#<link[^>]+rel=["\']canonical["\'][^>]+href=["\']https://www\.youtube\.com/channel/(UC[A-Za-z0-9_-]{22})#i',
            '#<meta[^>]+itemprop=["\']identifier["\'][^>]+content=["\'](UC[A-Za-z0-9_-]{22})#i',
            '#"externalId"\s*:\s*"(UC[A-Za-z0-9_-]{22})"#',
            '#"channelId"\s*:\s*"(UC[A-Za-z0-9_-]{22})"#',
            '#(UC[A-Za-z0-9_-]{22})#',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $match) === 1) {
                return $match[1];
            }
        }

        return null;
    }

    private function channelUrl(string $channel): string
    {
        if (str_starts_with($channel, '@')) {
            return 'https://www.youtube.com/'.$channel;
        }

        if (preg_match('#^UC[A-Za-z0-9_-]{22}$#', $channel) === 1) {
            return "https://www.youtube.com/channel/{$channel}";
        }

        if (str_contains($channel, 'youtube.com') || str_contains($channel, 'youtu.be')) {
            return str_contains($channel, '://') ? $channel : "https://{$channel}";
        }

        // A bare name: treat it as a handle, which is what people usually paste.
        return 'https://www.youtube.com/@'.ltrim($channel, '@');
    }
}
