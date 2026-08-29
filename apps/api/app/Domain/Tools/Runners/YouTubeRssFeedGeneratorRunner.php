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

    /** The two columns every result in this tool is drawn in. */
    private const COLUMNS = [
        ['key' => 'label', 'label' => 'Field', 'copyable' => false],
        ['key' => 'value', 'label' => 'Value', 'copyable' => true],
    ];

    private function channelFeed(string $source): ToolResult
    {
        $html = YouTubePage::channel(YouTubePage::channelUrl($source));

        $channelId = YouTubePage::channelId($html) ?? throw ToolExecutionException::notFound('that channel');
        $name = YouTubePage::og($html, 'title');

        $feed = self::FEED_BASE."?channel_id={$channelId}";
        $result = $this->fetch($feed);

        $rows = [['label' => 'Channel RSS feed', 'value' => $feed]];

        // Swapping the UC prefix for UU names the playlist holding every public
        // upload — a second feed for the same videos, which some readers prefer.
        // It is offered only once it has been seen to return a feed, because
        // YouTube serves it for some channels and 404s it for others with nothing
        // on the channel page to say which.
        $uploads = self::FEED_BASE.'?playlist_id=UU'.substr($channelId, 2);

        if ($this->fetch($uploads) !== null) {
            $rows[] = ['label' => 'Uploads playlist feed', 'value' => $uploads];
        }

        $rows[] = ['label' => 'Channel ID', 'value' => $channelId];
        $rows[] = ['label' => 'Channel', 'value' => $name ?? '—'];

        $who = $name !== null ? "“{$name}”" : 'that channel';

        if ($result === null) {
            return $this->unreadable($rows, sprintf(
                'The feed URL for %s is correct — it is built from the channel id on the channel\'s '
                .'own page — but YouTube would not return the feed itself.',
                $who,
            ));
        }

        $entries = $result['entries'];

        $rows[] = ['label' => 'Entries in the feed', 'value' => $entries === [] ? 'None' : (string) count($entries)];

        if ($entries !== []) {
            $rows[] = ['label' => 'Newest entry', 'value' => $entries[0]['title']];
            $rows[] = ['label' => 'Published', 'value' => $entries[0]['published']];
        }

        return ToolResult::table(self::COLUMNS, $rows, summary: $entries === []
            ? 'The feed URL is correct, but YouTube returned no entries — the channel has no public uploads.'
            : sprintf('Feed for %s, returning %s. Newest: “%s”.', $who,
                $this->plural(count($entries), 'entry', 'entries'), $entries[0]['title']),
        )->withMeta([
            'channel_id' => $channelId,
            'feed_url' => $feed,
            'feed_reachable' => true,
            'entries' => $entries,
            'code' => ['label' => 'RSS feed', 'text' => $result['xml']],
        ]);
    }

    private function playlistFeed(string $playlistId): ToolResult
    {
        $feed = self::FEED_BASE."?playlist_id={$playlistId}";
        $result = $this->fetch($feed);

        $rows = [
            ['label' => 'Playlist RSS feed', 'value' => $feed],
            ['label' => 'Playlist ID', 'value' => $playlistId],
        ];

        // YouTube's playlist feeds are the flakiest corner of this endpoint, so a
        // run of errors is not evidence the playlist is missing. The playlist page
        // is the reliable witness: if that loads, the id is real and the feed URL
        // is worth handing back with a caveat rather than refusing outright.
        if ($result === null) {
            $page = SafeHttpClient::attempt("https://www.youtube.com/playlist?list={$playlistId}");

            if ($page === null || $page->failed()) {
                throw ToolExecutionException::notFound('a public feed for that playlist');
            }

            return $this->unreadable(
                $rows,
                'The playlist exists and this is its feed URL, but YouTube would not return the feed itself.',
            );
        }

        $entries = $result['entries'];

        if ($entries === []) {
            throw ToolExecutionException::notFound('any videos in that playlist\'s feed');
        }

        $rows[] = ['label' => 'Entries in the feed', 'value' => (string) count($entries)];
        $rows[] = ['label' => 'Newest entry', 'value' => $entries[0]['title']];
        $rows[] = ['label' => 'Published', 'value' => $entries[0]['published']];

        return ToolResult::table(self::COLUMNS, $rows, summary: sprintf(
            'Feed for playlist %s, returning %s.', $playlistId,
            $this->plural(count($entries), 'entry', 'entries'),
        ))->withMeta([
            'playlist_id' => $playlistId,
            'feed_url' => $feed,
            'feed_reachable' => true,
            'entries' => $entries,
            'code' => ['label' => 'RSS feed', 'text' => $result['xml']],
        ]);
    }

    /**
     * The result for a feed whose URL is right but which YouTube would not serve.
     *
     * No feed card is attached: there is no document to show, and an empty or
     * half-written code block reads as "the feed is broken" when the URL is fine.
     * The warning carries the explanation instead, because the one thing the reader
     * needs is to know the URL is worth keeping.
     *
     * @param  list<array{label: string, value: string}>  $rows
     */
    private function unreadable(array $rows, string $why): ToolResult
    {
        $rows[] = ['label' => 'Entries in the feed', 'value' => 'Could not read'];

        return ToolResult::table(self::COLUMNS, $rows, summary: $why)
            ->withWarnings([
                'YouTube serves this endpoint from backends that disagree about which feeds they '
                .'hold, so the same URL can answer 200, 404 and 500 within a few seconds. We '
                .'retried and got an error every time, which is why there is no feed shown below. '
                .'Save the URL and use it anyway: feed readers and automation tools retry on a '
                .'schedule, and it will pick the feed up on a later attempt.',
            ])
            ->withMeta(['feed_reachable' => false, 'entries' => []]);
    }

    /**
     * A feed document and its entries, or null when YouTube would not serve it.
     *
     * The retry is the point of this method. YouTube answers this endpoint from a
     * set of backends that disagree about which feeds they hold: for some channels
     * every request succeeds, and for others the same URL returns 200, 404 and 500
     * in turn, seconds apart. One attempt therefore measures luck rather than
     * whether the feed exists, which is what made this tool report a correct URL as
     * broken. Attempts are cheap — these responses come back in about 200ms.
     *
     * An empty entry list still means a real feed with nothing in it; only null
     * means the URL would not serve.
     *
     * @return array{xml: string, entries: list<array{title: string, published: string, url: string}>}|null
     */
    private function fetch(string $feed, int $attempts = 4): ?array
    {
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            if ($attempt > 1) {
                usleep(250_000);
            }

            $response = SafeHttpClient::attempt($feed);

            if ($response === null || $response->failed()) {
                continue;
            }

            $xml = SafeHttpClient::body($response);

            // A 200 carrying Google's HTML error page is the other way this fails.
            if (! str_contains($xml, '<feed')) {
                continue;
            }

            return ['xml' => $xml, 'entries' => $this->parse($xml)];
        }

        return null;
    }

    /** @return list<array{title: string, published: string, url: string}> */
    private function parse(string $xml): array
    {
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

    private function plural(int $count, string $one, string $many): string
    {
        return $count.' '.($count === 1 ? $one : $many);
    }

    private function date(string $value): string
    {
        $timestamp = strtotime($value);

        return $timestamp === false ? $value : gmdate('j M Y, H:i', $timestamp).' UTC';
    }
}
