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
use App\Support\Social\YouTubePage;

/**
 * The RSS feed YouTube publishes for every channel and playlist, and never links to.
 *
 * The feed is the one way to follow a channel that costs no API quota, needs no key
 * and cannot be reordered by an algorithm — which makes it the backbone of most
 * automation people build around YouTube. Finding it means knowing the channel ID
 * and an undocumented URL, which is the entire reason this tool exists.
 *
 * The feed is fetched once and its newest entries shown, because a feed URL that
 * looks right and returns nothing is worse than no answer at all.
 */
final class YouTubeRssFeedGeneratorRunner implements Cacheable, ToolRunner, UsesProvider
{
    private const FEED_BASE = 'https://www.youtube.com/feeds/videos.xml';

    public static function key(): string
    {
        return 'youtube.rss-feed-generator';
    }

    public function providers(): array
    {
        return ['youtube'];
    }

    public function cacheTtl(): int
    {
        return 1800;
    }

    public function inputSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'required' => ['source'],
            'additionalProperties' => false,
            'properties' => [
                'source' => [
                    'type' => 'string',
                    'title' => 'Channel or playlist',
                    'description' => 'A handle like @mkbhd, a channel ID, a playlist link, or any channel URL.',
                    'minLength' => 2,
                    'maxLength' => 300,
                    'examples' => ['@mkbhd'],
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $source = trim($input->string('source'));

        $playlistId = $this->playlistId($source);

        return $playlistId !== null
            ? $this->playlistFeed($playlistId)
            : $this->channelFeed($source);
    }

    private function channelFeed(string $source): ToolResult
    {
        $html = YouTubePage::channel(YouTubePage::channelUrl($source));

        $channelId = YouTubePage::channelId($html) ?? throw ToolExecutionException::notFound('that channel');
        $name = YouTubePage::og($html, 'title');

        $feed = self::FEED_BASE."?channel_id={$channelId}";
        // Swapping the UC prefix for UU names the playlist holding every public
        // upload — a second feed for the same videos, which some readers prefer
        // because it excludes nothing the channel has hidden from its main tab.
        $uploads = 'UU'.substr($channelId, 2);

        $entries = $this->entries($feed);

        $pairs = [
            ['label' => 'Channel RSS feed', 'value' => $feed, 'tone' => 'positive',
                'hint' => 'Paste this into any reader, Zapier, Make or n8n trigger.'],
            ['label' => 'Uploads playlist feed', 'value' => self::FEED_BASE."?playlist_id={$uploads}",
                'hint' => 'The same videos, read from the uploads playlist instead.'],
            ['label' => 'Channel ID', 'value' => $channelId],
            ['label' => 'Channel', 'value' => $name ?? '—'],
            ['label' => 'Entries in the feed', 'value' => count($entries) === 0 ? 'None' : (string) count($entries),
                'hint' => 'YouTube caps every feed at the 15 most recent items.'],
        ];

        if ($entries !== []) {
            $pairs[] = ['label' => 'Newest entry', 'value' => $entries[0]['title'],
                'hint' => $entries[0]['published']];
        }

        return ToolResult::keyValue($pairs, summary: $entries === []
            ? 'The feed URL is correct, but YouTube returned no entries — the channel has no public uploads.'
            : sprintf('Feed for %s, returning %d entries. Newest: “%s”.',
                $name !== null ? "“{$name}”" : 'that channel', count($entries), $entries[0]['title']),
        )->withMeta([
            'channel_id' => $channelId,
            'feed_url' => $feed,
            'entries' => $entries,
        ]);
    }

    private function playlistFeed(string $playlistId): ToolResult
    {
        $feed = self::FEED_BASE."?playlist_id={$playlistId}";
        $entries = $this->entries($feed);

        if ($entries === []) {
            throw ToolExecutionException::notFound('a public feed for that playlist');
        }

        return ToolResult::keyValue([
            ['label' => 'Playlist RSS feed', 'value' => $feed, 'tone' => 'positive'],
            ['label' => 'Playlist ID', 'value' => $playlistId],
            ['label' => 'Entries in the feed', 'value' => (string) count($entries)],
            ['label' => 'Newest entry', 'value' => $entries[0]['title'], 'hint' => $entries[0]['published']],
        ], summary: sprintf('Feed for playlist %s, returning %d entries.', $playlistId, count($entries)))
            ->withMeta(['playlist_id' => $playlistId, 'feed_url' => $feed, 'entries' => $entries]);
    }

    /**
     * The feed's entries, newest first.
     *
     * @return list<array{title: string, published: string, url: string}>
     */
    private function entries(string $feed): array
    {
        $response = SafeHttpClient::attempt($feed);

        if ($response === null || $response->failed()) {
            return [];
        }

        $xml = SafeHttpClient::body($response);

        preg_match_all(
            '#<entry>.*?<yt:videoId>([A-Za-z0-9_-]{11})</yt:videoId>.*?<title>(.*?)</title>'
            .'.*?<published>(.*?)</published>.*?</entry>#s',
            $xml,
            $matches,
            PREG_SET_ORDER,
        );

        return array_map(fn (array $match) => [
            'title' => html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5),
            'published' => $this->date($match[3]),
            'url' => "https://www.youtube.com/watch?v={$match[1]}",
        ], $matches);
    }

    /** A playlist id, from a link or pasted bare. Uploads playlists (`UU…`) count. */
    private function playlistId(string $source): ?string
    {
        if (preg_match('/^(?:PL|UU|OL|FL|LL|RD)[A-Za-z0-9_-]{10,}$/', $source) === 1) {
            return $source;
        }

        return preg_match('/[?&]list=((?:PL|UU|OL|FL|LL|RD)[A-Za-z0-9_-]{10,})/', $source, $match) === 1
            ? $match[1]
            : null;
    }

    private function date(string $value): string
    {
        $timestamp = strtotime($value);

        return $timestamp === false ? $value : gmdate('j M Y, H:i', $timestamp).' UTC';
    }
}
