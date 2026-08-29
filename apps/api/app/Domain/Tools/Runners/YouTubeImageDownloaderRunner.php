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
 * Every image a YouTube page publishes, not just the thumbnail that was chosen.
 *
 * The thumbnail downloader answers "give me this video's thumbnail in every size".
 * This answers the wider question: a video also publishes the three frames YouTube
 * generated automatically and offered the creator, and a channel publishes an
 * avatar and a banner that have no download button anywhere in the YouTube UI.
 * Both are what people are actually hunting for when they search for a YouTube
 * image downloader.
 */
final class YouTubeImageDownloaderRunner implements Cacheable, ToolRunner, UsesProvider
{
    private const OWNERSHIP_WARNING = 'These images belong to the channel owner. '
        .'Downloading one is not a licence to republish it.';

    /** Google's image CDN resizes an avatar to any square on request. */
    private const AVATAR_SIZES = [88, 176, 240, 800, 900];

    /** A banner is a 16:9 upload; these are the widths worth handing out. */
    private const BANNER_WIDTHS = [1060, 1707, 2120, 2560];

    /**
     * The frames YouTube generates from the quarter, half and three-quarter marks.
     * They survive a custom thumbnail replacing them.
     */
    private const AUTO_FRAMES = [1 => '25%', 2 => '50%', 3 => '75%'];

    public static function key(): string
    {
        return 'youtube.image-downloader';
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
            'required' => ['url'],
            'additionalProperties' => false,
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'x-control' => 'text',
                    'title' => 'YouTube video or channel URL',
                    'description' => 'A video link gives you its thumbnail and the three auto-generated frames. '
                        .'A channel link or @handle gives you the avatar and banner.',
                    'minLength' => 2,
                    'maxLength' => 500,
                    'examples' => ['https://www.youtube.com/@mkbhd'],
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $url = trim($input->string('url'));

        $videoId = YouTubeUrl::videoId($url);

        return $videoId !== null ? $this->videoImages($videoId) : $this->channelImages($url);
    }

    /**
     * Video images sit on a predictable CDN path, so no request is needed.
     *
     * Each auto-generated frame is offered at the HD size, which exists only if the
     * upload was HD, and at 480×360, which always does — hence the availability
     * column rather than a row that sometimes 404s with no explanation.
     */
    private function videoImages(string $videoId): ToolResult
    {
        $rows = [
            $this->videoRow($videoId, 'maxresdefault', 'Chosen thumbnail', 1280, 720, false),
            $this->videoRow($videoId, 'hqdefault', 'Chosen thumbnail', 480, 360, true),
        ];

        foreach (self::AUTO_FRAMES as $frame => $mark) {
            $label = "Auto frame {$frame} — {$mark} mark";

            $rows[] = $this->videoRow($videoId, "maxres{$frame}", $label, 1280, 720, false);
            $rows[] = $this->videoRow($videoId, "hq{$frame}", $label, 480, 360, true);
        }

        return ToolResult::table(
            columns: [
                ['key' => 'image', 'label' => 'Image'],
                ['key' => 'dimensions', 'label' => 'Dimensions'],
                ['key' => 'availability', 'label' => 'Availability'],
                ['key' => 'url', 'label' => 'JPG', 'align' => 'right', 'type' => 'download'],
                ['key' => 'webp_url', 'label' => 'WebP', 'align' => 'right', 'type' => 'download'],
            ],
            rows: $rows,
            summary: 'Eight images published for video '.$videoId.' — the chosen thumbnail, plus the '
                .'three auto-generated frames at two sizes each.',
        )->withMeta([
            'source' => 'video',
            'video_id' => $videoId,
            'preview_url' => "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg",
        ])->withWarnings([self::OWNERSHIP_WARNING]);
    }

    /** @return array<string, string> */
    private function videoRow(
        string $videoId,
        string $file,
        string $label,
        int $width,
        int $height,
        bool $guaranteed,
    ): array {
        return [
            'image' => $label,
            'dimensions' => "{$width} × {$height}",
            'availability' => $guaranteed ? 'Always available' : 'HD uploads only',
            'url' => "https://i.ytimg.com/vi/{$videoId}/{$file}.jpg",
            'webp_url' => "https://i.ytimg.com/vi_webp/{$videoId}/{$file}.webp",
        ];
    }

    /**
     * Channel images need the page, because the avatar and banner ids are not
     * derivable from the handle.
     *
     * No WebP column here: unlike video thumbnails, which have a real `vi_webp`
     * path, `yt3` serves JPEG whatever suffix is asked for. A WebP heading over
     * JPEG links would be a lie.
     */
    private function channelImages(string $channel): ToolResult
    {
        $html = YouTubePage::channel(YouTubePage::channelUrl($channel));

        $avatar = YouTubePage::channelAvatar($html);
        $banner = YouTubePage::channelBanner($html);

        if ($avatar === null && $banner === null) {
            throw ToolExecutionException::notFound('any images on that channel');
        }

        $rows = [];

        foreach ($avatar !== null ? self::AVATAR_SIZES : [] as $size) {
            $rows[] = [
                'image' => $size >= 900 ? 'Avatar — largest served' : 'Avatar',
                'dimensions' => "{$size} × {$size}",
                'url' => "{$avatar}=s{$size}-c-k-c0x00ffffff-no-rj",
            ];
        }

        if ($banner !== null) {
            // `s0` asks the CDN for the file as it was uploaded, at its own size.
            $rows[] = [
                'image' => 'Banner — original upload',
                'dimensions' => 'As uploaded',
                'url' => "{$banner}=s0",
            ];

            foreach (self::BANNER_WIDTHS as $width) {
                $rows[] = [
                    'image' => 'Banner',
                    'dimensions' => $width.' × '.(int) round($width * 9 / 16),
                    'url' => "{$banner}=w{$width}-fcrop64=1,00000000ffffffff-nd-v1",
                ];
            }
        }

        $name = YouTubePage::og($html, 'title');

        $result = ToolResult::table(
            columns: [
                ['key' => 'image', 'label' => 'Image'],
                ['key' => 'dimensions', 'label' => 'Dimensions'],
                ['key' => 'url', 'label' => 'Download', 'align' => 'right', 'type' => 'download'],
            ],
            rows: $rows,
            summary: 'Avatar'.($banner !== null ? ' and banner' : '').' for '
                .($name !== null ? "“{$name}”" : 'that channel').', at every size the CDN will serve.',
        )->withMeta([
            'source' => 'channel',
            'channel_name' => $name,
            'channel_id' => YouTubePage::channelId($html),
            'preview_url' => $avatar !== null ? "{$avatar}=s176-c-k-c0x00ffffff-no-rj" : null,
        ])->withWarnings([self::OWNERSHIP_WARNING]);

        return $banner === null
            ? $result->withWarnings(['This channel has no banner set, so only the avatar is listed.'])
            : $result;
    }
}
