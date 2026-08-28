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
use App\Support\Social\YouTubePage;
use App\Support\Social\YouTubeUrl;

/**
 * Everything a video declares about itself, in one table.
 *
 * Half the value is the fields nobody surfaces in the UI — the exact publish
 * timestamp, the category, whether the video is licensed for reuse, how many
 * countries it is available in. The other half is that they are all in one place,
 * which is what makes this the tool people reach for when auditing somebody else's
 * upload rather than their own.
 */
final class YouTubeMetadataViewerRunner implements Cacheable, ToolRunner, UsesProvider
{
    public static function key(): string
    {
        return 'youtube.metadata-viewer';
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
                    'description' => 'Watch, share, embed, Shorts or live links all work — as does a bare video ID.',
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

        $html = YouTubePage::watch($videoId);
        $oEmbed = YouTubePage::oEmbed($videoId);

        $title = $this->oEmbed($oEmbed, 'title') ?? YouTubePage::og($html, 'title');
        $description = YouTubePage::og($html, 'description') ?? '';
        $channelId = YouTubePage::channelId($html);
        $duration = YouTubePage::humanDuration(YouTubePage::itemprop($html, 'duration'));
        $views = YouTubePage::itemprop($html, 'interactionCount');
        $keywords = YouTubePage::named($html, 'keywords');

        $rows = [
            $this->row('Video', 'Title', $title),
            $this->row('Video', 'Video ID', $videoId),
            $this->row('Video', 'Watch URL', YouTubeUrl::watchUrl($videoId)),
            $this->row('Video', 'Duration', $duration),
            $this->row('Video', 'Category', YouTubePage::field($html, 'category')),
            // Shown in full: the value cell wraps, and this is the field people came
            // to read.
            $this->row('Video', 'Description', $description === '' ? null : $description),
            $this->row('Video', 'Description length', $description === ''
                ? null
                : number_format(mb_strlen($description)).' characters of 5,000'),

            $this->row('Channel', 'Channel name', $this->oEmbed($oEmbed, 'author_name')
                ?? YouTubePage::field($html, 'ownerChannelName')),
            $this->row('Channel', 'Channel ID', $channelId),
            $this->row('Channel', 'Channel URL', $channelId !== null
                ? "https://www.youtube.com/channel/{$channelId}"
                : null),

            $this->row('Publishing', 'Published', $this->date(YouTubePage::itemprop($html, 'datePublished')
                ?? YouTubePage::field($html, 'publishDate'))),
            $this->row('Publishing', 'Uploaded', $this->date(YouTubePage::itemprop($html, 'uploadDate')
                ?? YouTubePage::field($html, 'uploadDate'))),
            $this->row('Publishing', 'Views', $views !== null ? number_format((int) $views) : null),
            $this->row('Publishing', 'Live broadcast', $this->yesNo(YouTubePage::flag($html, 'isLiveContent'))),

            $this->row('Settings', 'Listed publicly', $this->yesNo(
                ($unlisted = YouTubePage::flag($html, 'isUnlisted')) === null ? null : ! $unlisted,
            )),
            $this->row('Settings', 'Family safe', $this->yesNo(YouTubePage::flag($html, 'isFamilySafe'))),
            $this->row('Settings', 'Made for kids', $this->yesNo(YouTubePage::flag($html, 'isMadeForKids'))),
            $this->row('Settings', 'Embeddable', $this->yesNo(YouTubePage::flag($html, 'playableInEmbed'))),
            $this->row('Settings', 'Available countries', $this->countries($html)),

            $this->row('Media', 'Thumbnail', "https://i.ytimg.com/vi/{$videoId}/maxresdefault.jpg"),
            $this->row('Media', 'Tags', $keywords ?? 'None — most uploads no longer set any'),
        ];

        // The table is the readable view; this is the payload people copy into their
        // own code, so it carries the untruncated fields and the raw flags.
        $json = [
            'video_id' => $videoId,
            'title' => $title,
            'description' => $description === '' ? null : $description,
            'description_length' => $description === '' ? 0 : mb_strlen($description),
            'watch_url' => YouTubeUrl::watchUrl($videoId),
            'duration' => $duration,
            'category' => YouTubePage::field($html, 'category'),
            'channel' => [
                'name' => $this->oEmbed($oEmbed, 'author_name') ?? YouTubePage::field($html, 'ownerChannelName'),
                'id' => $channelId,
                'url' => $channelId !== null ? "https://www.youtube.com/channel/{$channelId}" : null,
            ],
            'published_at' => YouTubePage::itemprop($html, 'datePublished')
                ?? YouTubePage::field($html, 'publishDate'),
            'uploaded_at' => YouTubePage::itemprop($html, 'uploadDate')
                ?? YouTubePage::field($html, 'uploadDate'),
            'views' => $views !== null ? (int) $views : null,
            'is_live_content' => YouTubePage::flag($html, 'isLiveContent'),
            'is_unlisted' => YouTubePage::flag($html, 'isUnlisted'),
            'is_family_safe' => YouTubePage::flag($html, 'isFamilySafe'),
            'is_made_for_kids' => YouTubePage::flag($html, 'isMadeForKids'),
            'playable_in_embed' => YouTubePage::flag($html, 'playableInEmbed'),
            'available_countries' => $this->countries($html),
            'thumbnail_url' => "https://i.ytimg.com/vi/{$videoId}/maxresdefault.jpg",
            'tags' => $keywords === null
                ? []
                : array_values(array_filter(array_map('trim', explode(',', $keywords)), fn (string $t) => $t !== '')),
        ];

        return ToolResult::table(
            columns: [
                ['key' => 'group', 'label' => 'Group'],
                ['key' => 'field', 'label' => 'Field'],
                ['key' => 'value', 'label' => 'Value'],
            ],
            rows: array_values(array_filter($rows)),
            summary: $title !== null
                ? "Metadata for “{$title}”."
                : "Metadata for video {$videoId}.",
        )->withMeta([
            'video_id' => $videoId,
            'title' => $title,
            'channel_id' => $channelId,
            // The full description is worth carrying even though the table shows a
            // preview: it is the field people came to copy.
            'description' => $description,
            'preview_url' => "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg",
            // Rendered as a copyable card under the table by the shared renderer.
            'json' => $json,
        ])->withWarnings($oEmbed === null
            ? ['YouTube’s public oEmbed endpoint refused this video, so some fields may be missing. '
                .'That usually means the video is unlisted, private or region-blocked.']
            : []);
    }

    /** @param  array<string, mixed>|null  $oEmbed */
    private function oEmbed(?array $oEmbed, string $key): ?string
    {
        $value = $oEmbed[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return array<string, string>|null */
    private function row(string $group, string $field, ?string $value): ?array
    {
        return $value === null || $value === ''
            ? null
            : ['group' => $group, 'field' => $field, 'value' => $value];
    }

    private function countries(string $html): ?string
    {
        if (preg_match('/"availableCountries"\s*:\s*\[([^\]]*)\]/', $html, $match) !== 1) {
            return null;
        }

        $count = preg_match_all('/"[A-Z]{2}"/', $match[1]);

        return match (true) {
            $count === 0 => null,
            // 249 is the full ISO 3166-1 list YouTube ships for an unrestricted video.
            $count >= 240 => "{$count} — available worldwide",
            default => "{$count} — blocked in ".(249 - $count).' countries',
        };
    }

    private function date(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? $value : gmdate('j M Y, H:i', $timestamp).' UTC';
    }

    private function yesNo(?bool $value): ?string
    {
        return $value === null ? null : ($value ? 'Yes' : 'No');
    }
}
