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
 * Embed code with the parameters that still do something, and none that do not.
 *
 * Most embed generators still offer `rel=0` as "hide related videos", which YouTube
 * stopped honouring in 2018 — it now only restricts suggestions to the same
 * channel. They also offer autoplay without mute, which every browser has blocked
 * since 2018 as well. This tool builds the flags that work, explains the two that
 * changed meaning, and defaults to the privacy-enhanced `youtube-nocookie` domain
 * because embedding a video should not drop a tracking cookie on your visitors.
 */
final class YouTubeEmbedCodeGeneratorRunner implements Cacheable, ToolRunner
{
    public static function key(): string
    {
        return 'youtube.embed-code-generator';
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
                    'minLength' => 11,
                    'maxLength' => 500,
                    'examples' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
                ],
                'start' => [
                    'type' => 'string',
                    'title' => 'Start at',
                    'description' => 'Seconds, or a timestamp like 1:23. Leave blank to start at the beginning.',
                    'maxLength' => 12,
                    'default' => '',
                    'examples' => ['1:23'],
                ],
                'end' => [
                    'type' => 'string',
                    'title' => 'End at',
                    'description' => 'Stops playback at this point. Leave blank to play to the end.',
                    'maxLength' => 12,
                    'default' => '',
                ],
                'width' => [
                    'type' => 'integer',
                    'title' => 'Width (px)',
                    'description' => 'Used by the fixed-size embed. The responsive one ignores it.',
                    'minimum' => 120,
                    'maximum' => 3840,
                    'default' => 560,
                ],
                'privacy_mode' => [
                    'type' => 'boolean',
                    'title' => 'Use the privacy-enhanced domain (youtube-nocookie.com)',
                    'default' => true,
                ],
                'autoplay' => [
                    'type' => 'boolean',
                    'title' => 'Autoplay',
                    'description' => 'Browsers only allow this when the video is also muted, so mute is forced on.',
                    'default' => false,
                ],
                'loop' => [
                    'type' => 'boolean',
                    'title' => 'Loop',
                    'default' => false,
                ],
                'controls' => [
                    'type' => 'boolean',
                    'title' => 'Show player controls',
                    'default' => true,
                ],
                'same_channel_suggestions' => [
                    'type' => 'boolean',
                    'title' => 'Limit end-of-video suggestions to this channel',
                    'description' => 'This is what rel=0 does now. It cannot remove suggestions entirely.',
                    'default' => false,
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

        $start = $this->seconds($input->string('start'));
        $end = $this->seconds($input->string('end'));
        $width = max(120, min(3840, $input->int('width', 560)));
        $height = (int) round($width * 9 / 16);
        $autoplay = $input->bool('autoplay');
        $loop = $input->bool('loop');

        $params = [];

        if ($start !== null) {
            $params['start'] = $start;
        }

        if ($end !== null) {
            $params['end'] = $end;
        }

        if ($autoplay) {
            // Muted autoplay is the only autoplay any current browser permits.
            $params['autoplay'] = 1;
            $params['mute'] = 1;
        }

        if ($loop) {
            // Looping a single video requires naming it as a one-video playlist;
            // `loop=1` on its own silently does nothing.
            $params['loop'] = 1;
            $params['playlist'] = $videoId;
        }

        if (! $input->bool('controls', true)) {
            $params['controls'] = 0;
        }

        if ($input->bool('same_channel_suggestions')) {
            $params['rel'] = 0;
        }

        $host = $input->bool('privacy_mode', true) ? 'www.youtube-nocookie.com' : 'www.youtube.com';
        $src = "https://{$host}/embed/{$videoId}".($params === [] ? '' : '?'.http_build_query($params));

        $attributes = 'title="YouTube video player" frameborder="0" '
            .'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" '
            .'referrerpolicy="strict-origin-when-cross-origin" allowfullscreen';

        $blocks = [
            [
                'label' => "Fixed size — {$width} × {$height}",
                'text' => '<iframe width="'.$width.'" height="'.$height.'" src="'.$this->escape($src).'" '
                    .$attributes.'></iframe>',
            ],
            [
                'label' => 'Responsive — scales to its container, no layout shift',
                'text' => '<div style="position:relative;width:100%;aspect-ratio:16/9;">'."\n"
                    .'  <iframe style="position:absolute;inset:0;width:100%;height:100%;" '
                    .'src="'.$this->escape($src).'" '.$attributes.'></iframe>'."\n"
                    .'</div>',
            ],
            [
                'label' => 'Lazy — the player only loads when it scrolls into view',
                'text' => '<iframe width="'.$width.'" height="'.$height.'" src="'.$this->escape($src).'" '
                    .'loading="lazy" '.$attributes.'></iframe>',
            ],
            [
                'label' => 'Embed URL only — for a CMS field that wants the src',
                'text' => $src,
            ],
        ];

        return ToolResult::textBlocks($blocks, summary: sprintf(
            'Embed code for %s, on the %s domain%s.',
            $videoId,
            $input->bool('privacy_mode', true) ? 'privacy-enhanced' : 'standard',
            $params === [] ? '' : ', with '.count($params).' parameter(s) applied',
        ))->withMeta([
            'video_id' => $videoId,
            'embed_url' => $src,
            'aspect_ratio' => '16:9',
        ])->withWarnings($this->warnings($autoplay, $loop, $input));
    }

    /** @return list<string> */
    private function warnings(bool $autoplay, bool $loop, ToolInput $input): array
    {
        $warnings = [];

        if ($autoplay) {
            $warnings[] = 'Autoplay forces mute, because Chrome, Safari and Firefox all block unmuted '
                .'autoplay. An embed that claims otherwise simply fails silently on the visitor’s machine.';
        }

        if ($loop) {
            $warnings[] = 'Looping needs the video listed as its own one-item playlist, which is why '
                .'`playlist=` appears in the URL. Removing it stops the loop working.';
        }

        if ($input->bool('same_channel_suggestions')) {
            $warnings[] = 'rel=0 no longer hides related videos — YouTube changed it in 2018 to mean '
                .'“suggest videos from this channel only”. Nothing can remove the end screen entirely.';
        }

        if (! $input->bool('privacy_mode', true)) {
            $warnings[] = 'The standard domain sets tracking cookies as soon as the page loads, which in '
                .'the EU and UK needs consent before the embed renders. youtube-nocookie.com defers them '
                .'until playback starts.';
        }

        return $warnings;
    }

    /** Accepts `90`, `1:30` or `1:02:03`; anything else is treated as no value. */
    private function seconds(string $value): ?int
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        if (preg_match('/^(?:(\d+):)?(\d{1,2}):(\d{2})$/', $value, $match) !== 1) {
            throw ToolExecutionException::invalidInput(
                "“{$value}” is not a time we recognise. Use seconds, or a timestamp like 1:23.",
                ['start' => 'Expected seconds or m:ss.'],
            );
        }

        return ((int) ($match[1] ?: 0)) * 3600 + ((int) $match[2]) * 60 + (int) $match[3];
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
