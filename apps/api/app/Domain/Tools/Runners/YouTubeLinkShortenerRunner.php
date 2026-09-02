<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Exceptions\ToolExecutionException;
use App\Support\Social\SocialUrl;
use App\Support\Social\YouTubeUrl;

/**
 * The short link YouTube itself hands out, built from any link shape.
 *
 * `youtu.be` is not a third-party shortener: it is YouTube's own domain, so the
 * link never expires, never needs an account, and cannot be taken down by a service
 * going out of business — which is the entire problem with running a share link
 * through bit.ly. The share sheet only offers it on a watch page, so anybody
 * holding a Shorts link, an embed URL or a bare id has no way to produce one.
 *
 * The interesting work is the parameters. `youtu.be` takes `t` in **seconds** while
 * a watch page takes `t=90s`, `list` survives the move but `index` does not mean
 * the same thing, and the `si` parameter YouTube appends to a shared link is a
 * per-share tracking id — pasting a link somebody sent you tells YouTube who
 * forwarded it. All three are handled here rather than left to the visitor.
 */
final class YouTubeLinkShortenerRunner implements Cacheable, ToolRunner
{
    public static function key(): string
    {
        return 'youtube.link-shortener';
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
                    'title' => 'YouTube link',
                    'description' => 'A watch page, Shorts link, embed URL, mobile link or a bare video ID.',
                    'minLength' => 11,
                    'maxLength' => 800,
                    'examples' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=90s'],
                ],
                'start' => [
                    'type' => 'string',
                    'title' => 'Start at',
                    'description' => 'Seconds, or a timestamp like 1:23. Leave blank to keep whatever the '
                        .'pasted link already had.',
                    'maxLength' => 12,
                    'default' => '',
                    'examples' => ['1:23'],
                ],
                'keep_playlist' => [
                    'type' => 'boolean',
                    'title' => 'Keep the playlist',
                    'description' => 'Off gives you a link to the video on its own, which is what most '
                        .'people actually want when they share from a playlist.',
                    'default' => false,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $raw = trim($input->string('url'));

        $videoId = YouTubeUrl::videoId($raw) ?? throw ToolExecutionException::invalidInput(
            "That doesn't look like a YouTube video link.",
            ['url' => 'Unrecognised YouTube URL or video ID.'],
        );

        $original = SocialUrl::normalise($raw);
        $query = $this->query($original);

        // `t` is a timestamp on YouTube, not a tracker, so it is kept here even
        // though the shared stripper treats it as one everywhere else.
        $tracking = SocialUrl::stripTracking($original, keep: ['t', 'v', 'list', 'start']);

        $seconds = $this->seconds($input->string('start')) ?? $this->seconds($query['t'] ?? '');
        $playlist = $input->bool('keep_playlist') ? $this->playlistId($query) : null;

        $params = array_filter([
            // youtu.be takes bare seconds; `90s` works on a watch page and is
            // silently ignored here, which is the classic broken-timestamp bug.
            't' => $seconds !== null && $seconds > 0 ? (string) $seconds : null,
            'list' => $playlist,
        ]);

        $short = 'https://youtu.be/'.$videoId.($params === [] ? '' : '?'.http_build_query($params));

        $blocks = [
            ['label' => 'Short link', 'text' => $short],
            ['label' => 'Canonical watch link — for embeds, sitemaps and anything that parses the URL',
                'text' => $this->watchLink($videoId, $seconds, $playlist)],
            ['label' => 'Autoplay-on-open link — starts muted, which is the only autoplay browsers allow',
                'text' => 'https://youtu.be/'.$videoId.'?'.http_build_query([
                    ...$params, 'autoplay' => '1', 'mute' => '1',
                ])],
            ['label' => 'Markdown', 'text' => '[Watch on YouTube]('.$short.')'],
            ['label' => 'HTML link', 'text' => '<a href="'.$this->escape($short).'" rel="noopener">Watch on YouTube</a>'],
        ];

        $saved = mb_strlen($original) - mb_strlen($short);

        return ToolResult::textBlocks($blocks, summary: sprintf(
            'youtu.be/%s%s — %s than the link you pasted.',
            $videoId,
            $seconds !== null && $seconds > 0 ? ', starting at '.$this->clock($seconds) : '',
            $saved > 0 ? $saved.' characters shorter' : 'the same length or longer',
        ))->withMeta([
            'video_id' => $videoId,
            'short_url' => $short,
            'start_seconds' => $seconds,
            'characters_saved' => max(0, $saved),
            'removed_parameters' => $tracking['removed'],
        ])->withWarnings($this->warnings($tracking['removed'], $query, $input));
    }

    /**
     * @param  list<string>  $removed
     * @param  array<string, string>  $query
     * @return list<string>
     */
    private function warnings(array $removed, array $query, ToolInput $input): array
    {
        $warnings = [];

        if ($removed !== []) {
            $warnings[] = 'Dropped '.implode(', ', $removed).' from the link you pasted. `si` in particular '
                .'is a per-share tracking id: forwarding a link that still carries it tells YouTube who '
                .'sent it to you.';
        }

        if (isset($query['list']) && ! $input->bool('keep_playlist')) {
            $warnings[] = 'The link you pasted was inside a playlist. The short link points at the video '
                .'on its own — turn on "Keep the playlist" if you meant to share the queue.';
        }

        if (isset($query['index'])) {
            $warnings[] = '`index` was dropped. It only positions a video inside a playlist page and does '
                .'nothing on a youtu.be link.';
        }

        return $warnings;
    }

    private function watchLink(string $videoId, ?int $seconds, ?string $playlist): string
    {
        $params = array_filter([
            'v' => $videoId,
            'list' => $playlist,
            // A watch page wants the `s` suffix; without it the value still works
            // but YouTube's own share sheet writes it, so the canonical form does too.
            't' => $seconds !== null && $seconds > 0 ? $seconds.'s' : null,
        ]);

        return 'https://www.youtube.com/watch?'.http_build_query($params);
    }

    /** @param  array<string, string>  $query */
    private function playlistId(array $query): ?string
    {
        $list = $query['list'] ?? '';

        if (preg_match('/^[A-Za-z0-9_-]{2,60}$/', $list) !== 1) {
            return null;
        }

        // `RD…` is a generated radio mix and `UL…` a "up next" queue: neither is a
        // playlist anybody can open, so sharing one hands over a dead link.
        return str_starts_with($list, 'RD') || str_starts_with($list, 'UL') ? null : $list;
    }

    /**
     * The URL's query as flat strings.
     *
     * `parse_str` will happily produce nested arrays from `a[b]=c`, which none of
     * the parameters here can legitimately be; flattening once means every reader
     * below can treat a value as a string without re-checking.
     *
     * @return array<string, string>
     */
    private function query(string $url): array
    {
        parse_str((string) (parse_url($url, PHP_URL_QUERY) ?: ''), $parsed);

        $flat = [];

        foreach ($parsed as $name => $value) {
            if (is_string($value)) {
                $flat[(string) $name] = $value;
            }
        }

        return $flat;
    }

    /** Accepts `90`, `90s`, `1:30`, `1:02:03` or `1h2m3s`; anything else is no value. */
    private function seconds(string $value): ?int
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d+)s?$/', $value, $match) === 1) {
            return (int) $match[1];
        }

        if (preg_match('/^(?:(\d+):)?(\d{1,2}):(\d{2})$/', $value, $match) === 1) {
            return ((int) ($match[1] ?: 0)) * 3600 + ((int) $match[2]) * 60 + (int) $match[3];
        }

        if (preg_match('/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/', $value, $match) === 1) {
            $total = ((int) ($match[1] ?? 0)) * 3600 + ((int) ($match[2] ?? 0)) * 60 + (int) ($match[3] ?? 0);

            if ($total > 0) {
                return $total;
            }
        }

        throw ToolExecutionException::invalidInput(
            "“{$value}” is not a time we recognise. Use seconds, or a timestamp like 1:23.",
            ['start' => 'Expected seconds or m:ss.'],
        );
    }

    private function clock(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $seconds % 60)
            : sprintf('%d:%02d', $minutes, $seconds % 60);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
