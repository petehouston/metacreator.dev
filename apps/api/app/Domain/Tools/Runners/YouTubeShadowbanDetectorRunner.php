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
 * The visibility settings that actually suppress a video, checked one by one.
 *
 * YouTube has no feature called a shadowban, and a tool that pretended to detect
 * one would be reading tea leaves. What does exist is a short list of states that
 * quietly remove a video from search, recommendations, the subscriber feed or the
 * public RSS feed — age restriction, limited-audience, made-for-kids, embedding
 * turned off, unlisted-by-accident. Every one of them is publicly checkable, and
 * every one of them is a real answer to "why did this video die".
 *
 * A "no" on all five is not proof that nothing is wrong. It is proof that the
 * cause is not a setting, which redirects the search to retention and packaging —
 * where it almost always belongs.
 */
final class YouTubeShadowbanDetectorRunner implements Cacheable, ToolRunner, UsesProvider
{
    /** Beyond this, a video is old enough that the 15-slot RSS feed proves nothing. */
    private const FEED_WINDOW_DAYS = 30;

    public static function key(): string
    {
        return 'youtube.shadowban-detector';
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
            'required' => ['url'],
            'additionalProperties' => false,
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'x-control' => 'text',
                    'title' => 'YouTube video URL or ID',
                    'description' => 'The video that is not getting the reach you expected.',
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

        $sections = [
            $this->publicSection($oEmbed, $html),
            $this->audienceSection($html),
            $this->kidsSection($html),
            $this->embedSection($html),
            $this->feedSection($html, $videoId),
        ];

        $overall = (int) round(array_sum(array_map(
            fn (array $section) => $section['score'] * $section['weight'],
            $sections,
        )));

        $fixes = [];

        foreach ($sections as $section) {
            foreach ($section['fixes'] as $fix) {
                $fixes[] = $fix;
            }
        }

        usort($fixes, fn (array $a, array $b) => $this->rank($b['severity']) <=> $this->rank($a['severity']));

        return ToolResult::score(
            overall: $overall,
            sections: array_map(fn (array $section) => [
                'key' => $section['key'],
                'label' => $section['label'],
                'score' => $section['score'],
                'weight' => $section['weight'],
                'notes' => $section['notes'],
            ], $sections),
            fixes: $fixes,
            summary: match (true) {
                $overall >= 95 => 'Nothing is suppressing this video. Every public visibility signal is clear, '
                    .'which points the investigation at the thumbnail, title and first thirty seconds instead.',
                $overall >= 70 => 'Mostly clear, but at least one setting is costing this video reach — '
                    .'see the fixes below.',
                default => 'This video is restricted. The settings below remove it from places viewers '
                    .'would otherwise have found it.',
            },
        )->withMeta([
            'video_id' => $videoId,
            'title' => $oEmbed['title'] ?? YouTubePage::og($html, 'title'),
            'published_at' => YouTubePage::itemprop($html, 'datePublished')
                ?? YouTubePage::field($html, 'publishDate'),
        ])->withWarnings([
            'YouTube does not shadowban. It restricts, and restrictions are visible — which is what this '
            .'checks. Low views on an unrestricted video is a packaging or retention problem, not a penalty.',
        ]);
    }

    /**
     * oEmbed answers only for public videos, so a refusal here is the single
     * strongest signal available without logging in.
     *
     * @param  array<string, mixed>|null  $oEmbed
     * @return array<string, mixed>
     */
    private function publicSection(?array $oEmbed, string $html): array
    {
        $unlisted = YouTubePage::flag($html, 'isUnlisted') ?? false;

        if ($unlisted) {
            return ['key' => 'public', 'label' => 'Publicly listed', 'score' => 0, 'weight' => 0.3,
                'notes' => ['This video is unlisted.'], 'fixes' => [[
                    'severity' => 'high',
                    'title' => 'The video is unlisted, not public',
                    'detail' => 'Unlisted videos are reachable by link only. They never appear in search, '
                        .'recommendations, your channel page or subscriber feeds. This is the most common '
                        .'cause of a video that "gets no views" and by far the easiest to fix.',
                ]]];
        }

        if ($oEmbed === null) {
            return ['key' => 'public', 'label' => 'Publicly listed', 'score' => 20, 'weight' => 0.3,
                'notes' => ['YouTube’s public oEmbed endpoint will not describe this video.'], 'fixes' => [[
                    'severity' => 'high',
                    'title' => 'The video is not publicly available',
                    'detail' => 'It is private, scheduled, region-blocked or removed. Signed-out viewers '
                        .'cannot reach it at all, so nothing downstream of this matters until it is fixed.',
                ]]];
        }

        return ['key' => 'public', 'label' => 'Publicly listed', 'score' => 100, 'weight' => 0.3,
            'notes' => ['Public, and visible to signed-out viewers.'], 'fixes' => []];
    }

    /** @return array<string, mixed> */
    private function audienceSection(string $html): array
    {
        $ageRestricted = YouTubePage::og($html, 'restrictions:age') !== null
            || (YouTubePage::flag($html, 'isFamilySafe') === false);

        if (! $ageRestricted) {
            return ['key' => 'audience', 'label' => 'Age restriction', 'score' => 100, 'weight' => 0.25,
                'notes' => ['No age restriction — the video is family safe.'], 'fixes' => []];
        }

        return ['key' => 'audience', 'label' => 'Age restriction', 'score' => 0, 'weight' => 0.25,
            'notes' => ['Age-restricted (18+).'], 'fixes' => [[
                'severity' => 'high',
                'title' => 'Age restriction is removing this video from most surfaces',
                'detail' => 'An age-restricted video is hidden from signed-out and under-18 viewers, cannot be '
                    .'embedded anywhere, is excluded from recommendations, and earns limited or no ad revenue. '
                    .'If the restriction was applied automatically, appeal it from YouTube Studio — the '
                    .'automated classifier is wrong often enough to be worth contesting.',
            ]]];
    }

    /** @return array<string, mixed> */
    private function kidsSection(string $html): array
    {
        $madeForKids = YouTubePage::flag($html, 'isMadeForKids') ?? false;

        if (! $madeForKids) {
            return ['key' => 'kids', 'label' => 'Made for kids', 'score' => 100, 'weight' => 0.2,
                'notes' => ['Not marked as made for kids.'], 'fixes' => []];
        }

        return ['key' => 'kids', 'label' => 'Made for kids', 'score' => 30, 'weight' => 0.2,
            'notes' => ['Marked as made for kids.'], 'fixes' => [[
                'severity' => 'high',
                'title' => 'The "made for kids" flag disables the features that drive reach',
                'detail' => 'It turns off comments, notifications, end screens, saving to playlists and '
                    .'personalised advertising — which also cuts CPM sharply. Correct if the audience is not '
                    .'actually children; leave it if it is, because misdeclaring is a COPPA problem, not a '
                    .'growth one.',
            ]]];
    }

    /** @return array<string, mixed> */
    private function embedSection(string $html): array
    {
        $embeddable = YouTubePage::flag($html, 'playableInEmbed') ?? true;

        return ['key' => 'embed', 'label' => 'Embedding', 'score' => $embeddable ? 100 : 50, 'weight' => 0.1,
            'notes' => [$embeddable ? 'Can be embedded on other sites.' : 'Embedding is turned off.'],
            'fixes' => $embeddable ? [] : [[
                'severity' => 'medium',
                'title' => 'Turn embedding back on',
                'detail' => 'With embedding off, nobody can put the video in a blog post, a newsletter or a '
                    .'forum reply — and those views still count. There is almost no upside to leaving it off.',
            ]]];
    }

    /**
     * A recent upload missing from its own channel's public RSS feed is a genuine
     * distribution failure. An older one proves nothing: the feed holds 15 entries.
     *
     * @return array<string, mixed>
     */
    private function feedSection(string $html, string $videoId): array
    {
        $channelId = YouTubePage::channelId($html);

        if ($channelId === null) {
            return ['key' => 'feed', 'label' => 'Public feed', 'score' => 100, 'weight' => 0.15,
                'notes' => ['Channel could not be resolved, so the feed was not checked.'], 'fixes' => []];
        }

        $published = $this->publishedAt($html);
        $recent = $published !== null && $published > strtotime('-'.self::FEED_WINDOW_DAYS.' days');

        if (! $recent) {
            return ['key' => 'feed', 'label' => 'Public feed', 'score' => 100, 'weight' => 0.15,
                'notes' => ['Older than '.self::FEED_WINDOW_DAYS.' days — the 15-entry feed cannot say anything '
                    .'useful about it.'], 'fixes' => []];
        }

        $uploads = YouTubePage::recentUploads($channelId);

        if ($uploads === []) {
            return ['key' => 'feed', 'label' => 'Public feed', 'score' => 100, 'weight' => 0.15,
                'notes' => ['The channel feed could not be read.'], 'fixes' => []];
        }

        $present = in_array($videoId, $uploads, true);

        return ['key' => 'feed', 'label' => 'Public feed', 'score' => $present ? 100 : 0, 'weight' => 0.15,
            'notes' => [$present
                ? 'Present in the channel’s public RSS feed.'
                : 'Missing from the channel’s public RSS feed despite being recent.'],
            'fixes' => $present ? [] : [[
                'severity' => 'high',
                'title' => 'This video is not in your channel’s public feed',
                'detail' => 'Everything YouTube distributes automatically — subscriber feeds, RSS readers, '
                    .'cross-posting integrations — reads that feed. A recent public upload missing from it is '
                    .'usually unlisted, a Short filtered from the uploads playlist, or still processing.',
            ]]];
    }

    private function publishedAt(string $html): ?int
    {
        $date = YouTubePage::itemprop($html, 'datePublished')
            ?? YouTubePage::itemprop($html, 'uploadDate')
            ?? YouTubePage::field($html, 'publishDate');

        if ($date === null) {
            return null;
        }

        $timestamp = strtotime($date);

        return $timestamp === false ? null : $timestamp;
    }

    private function rank(string $severity): int
    {
        return ['high' => 3, 'medium' => 2, 'low' => 1][$severity] ?? 0;
    }
}
