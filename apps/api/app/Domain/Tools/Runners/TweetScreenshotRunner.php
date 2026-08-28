<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\ResultArtifact;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Support\Social\PostLength;

/**
 * A clean image of a post, drawn rather than screenshotted.
 *
 * Reposting a screenshot means cropping a phone frame, a notification bar and
 * whatever else was on screen. This draws the card itself as SVG — sharp at any
 * size, a fraction of the weight of a PNG, and with no chrome to crop.
 *
 * It is a **mock-up tool**, not an evidence tool: it renders whatever text you type,
 * so a card it produces proves nothing about who said what. The warning says so on
 * every run, and the tool deliberately draws no verification badge.
 */
final class TweetScreenshotRunner implements Cacheable, ToolRunner
{
    private const WIDTH = 1000;

    private const PADDING = 48;

    /** @var array<string, array{bg: string, fg: string, muted: string, border: string, accent: string}> */
    private const THEMES = [
        'light' => ['bg' => '#FFFFFF', 'fg' => '#0F1419', 'muted' => '#536471', 'border' => '#EFF3F4', 'accent' => '#1D9BF0'],
        'dim' => ['bg' => '#15202B', 'fg' => '#F7F9F9', 'muted' => '#8B98A5', 'border' => '#38444D', 'accent' => '#1D9BF0'],
        'dark' => ['bg' => '#000000', 'fg' => '#E7E9EA', 'muted' => '#71767B', 'border' => '#2F3336', 'accent' => '#1D9BF0'],
    ];

    public static function key(): string
    {
        return 'x.tweet-screenshot';
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
            'required' => ['name', 'handle', 'text'],
            'additionalProperties' => false,
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'title' => 'Display name',
                    'minLength' => 1,
                    'maxLength' => 50,
                    'examples' => ['Ada Lovelace'],
                ],
                'handle' => [
                    'type' => 'string',
                    'title' => 'Handle',
                    'description' => 'With or without the @.',
                    'minLength' => 1,
                    'maxLength' => 30,
                    'examples' => ['ada'],
                ],
                'text' => [
                    'type' => 'string',
                    'title' => 'Post text',
                    'description' => 'Line breaks are kept. Links, @mentions and #hashtags are coloured as X colours them.',
                    'minLength' => 1,
                    'maxLength' => 2000,
                    'examples' => ['The engine does not originate anything. It can do whatever we know how to order it to perform.'],
                ],
                'theme' => [
                    'type' => 'string',
                    'title' => 'Theme',
                    'enum' => ['light', 'dim', 'dark'],
                    'default' => 'light',
                ],
                'timestamp' => [
                    'type' => 'string',
                    'title' => 'Timestamp',
                    'description' => 'Shown under the post, exactly as typed.',
                    'maxLength' => 60,
                    'default' => '',
                    'examples' => ['2:14 PM · Jul 10, 2025'],
                ],
                'replies' => ['type' => 'integer', 'title' => 'Replies', 'minimum' => 0, 'maximum' => 99999999, 'default' => 0],
                'reposts' => ['type' => 'integer', 'title' => 'Reposts', 'minimum' => 0, 'maximum' => 99999999, 'default' => 0],
                'likes' => ['type' => 'integer', 'title' => 'Likes', 'minimum' => 0, 'maximum' => 99999999, 'default' => 0],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $theme = self::THEMES[$input->string('theme', 'light')] ?? self::THEMES['light'];
        $name = trim($input->string('name'));
        $handle = '@'.ltrim(trim($input->string('handle')), '@');
        $text = $input->string('text');

        $svg = $this->draw($theme, $name, $handle, $text, $input);
        $uri = 'data:image/svg+xml;base64,'.base64_encode($svg);

        $artifact = new ResultArtifact(
            key: 'tweet',
            filename: 'post-card.svg',
            mimeType: 'image/svg+xml',
            size: strlen($svg),
            url: $uri,
            label: 'Post card — '.ucfirst($input->string('theme', 'light')).' theme',
            previewUrl: $uri,
        );

        $characters = PostLength::graphemeCount($text);

        return ToolResult::media(
            [$artifact],
            summary: "A {$input->string('theme', 'light')}-theme card for {$handle}, ready to drop into a "
                .'thread, a slide or a newsletter.',
        )->withWarnings(array_values(array_filter([
            $characters > 280
                ? "That is {$characters} characters. A real post caps at 280 unless the account is "
                .'subscribed — the card still draws it, but it would not have posted.'
                : null,
            'This draws whatever you type, so it is a mock-up, not proof. Do not present a card as a '
            .'screenshot of something someone actually posted.',
        ])))->withMeta(['characters' => $characters]);
    }

    /** @param  array{bg: string, fg: string, muted: string, border: string, accent: string}  $theme */
    private function draw(array $theme, string $name, string $handle, string $text, ToolInput $input): string
    {
        $inner = self::WIDTH - self::PADDING * 2;
        $lines = self::wrap($text, $inner, 34);

        $y = self::PADDING + 96;
        $body = '';

        foreach ($lines as $line) {
            $body .= '<text x="'.self::PADDING.'" y="'.$y.'" class="body">'.self::runs($line, $theme).'</text>';
            $y += 46;
        }

        $timestamp = trim($input->string('timestamp'));

        if ($timestamp !== '') {
            $y += 12;
            $body .= '<text x="'.self::PADDING.'" y="'.$y.'" class="muted">'.self::escape($timestamp).'</text>';
            $y += 30;
        }

        $metrics = array_filter([
            $input->int('replies') > 0 ? self::compact($input->int('replies')).' replies' : null,
            $input->int('reposts') > 0 ? self::compact($input->int('reposts')).' reposts' : null,
            $input->int('likes') > 0 ? self::compact($input->int('likes')).' likes' : null,
        ]);

        if ($metrics !== []) {
            $y += 20;
            $body .= '<line x1="'.self::PADDING.'" y1="'.($y - 34).'" x2="'.(self::WIDTH - self::PADDING)
                .'" y2="'.($y - 34).'" stroke="'.$theme['border'].'" stroke-width="2"/>';
            $body .= '<text x="'.self::PADDING.'" y="'.$y.'" class="muted">'
                .self::escape(implode('   ·   ', $metrics)).'</text>';
            $y += 16;
        }

        $height = $y + self::PADDING;
        $width = self::WIDTH;
        $avatarX = self::PADDING + 34;
        $initials = $this->initials($name);
        $safeName = self::escape($name);
        $safeHandle = self::escape($handle);

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}" role="img" aria-label="Social post by {$safeName}">
          <style>
            text { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
            .name { font-size: 30px; font-weight: 700; fill: {$theme['fg']}; }
            .muted { font-size: 26px; fill: {$theme['muted']}; }
            .body { font-size: 34px; fill: {$theme['fg']}; }
            .accent { fill: {$theme['accent']}; }
            .avatar { font-size: 34px; font-weight: 700; fill: {$theme['bg']}; }
          </style>
          <rect width="100%" height="100%" rx="28" fill="{$theme['bg']}" stroke="{$theme['border']}" stroke-width="2"/>
          <circle cx="{$avatarX}" cy="76" r="34" fill="{$theme['accent']}"/>
          <text x="{$avatarX}" y="88" class="avatar" text-anchor="middle">{$initials}</text>
          <text x="130" y="60" class="name">{$safeName}</text>
          <text x="130" y="96" class="muted">{$safeHandle}</text>
          {$body}
        </svg>
        SVG;
    }

    /**
     * Colour the parts X colours: links, mentions and hashtags.
     *
     * @param  array{bg: string, fg: string, muted: string, border: string, accent: string}  $theme
     */
    private static function runs(string $line, array $theme): string
    {
        $parts = preg_split('/(\s+)/u', $line, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $out = '';

        foreach ($parts as $part) {
            $highlight = preg_match('/^(https?:\/\/|@\w|#\w)/u', $part) === 1;
            $escaped = str_replace(' ', '&#160;', self::escape($part));

            $out .= $highlight
                ? '<tspan class="accent">'.$escaped.'</tspan>'
                : '<tspan>'.$escaped.'</tspan>';
        }

        return $out;
    }

    /**
     * Greedy word wrap on an estimated glyph width.
     *
     * SVG cannot reflow text, so the wrap has to happen here. `0.60em` is a
     * deliberately pessimistic estimate of the average glyph advance in the system
     * sans stack: overrunning the edge of the card is the only failure anyone would
     * see, and a slightly short line costs nothing.
     *
     * @return list<string>
     */
    private static function wrap(string $text, int $width, int $fontSize): array
    {
        $maxChars = max(10, (int) floor($width / ($fontSize * 0.60)));
        $lines = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $paragraph) {
            if (trim($paragraph) === '') {
                $lines[] = '';

                continue;
            }

            $current = '';

            foreach (preg_split('/\s+/u', trim($paragraph), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                $candidate = $current === '' ? $word : "{$current} {$word}";

                if (mb_strlen($candidate) <= $maxChars) {
                    $current = $candidate;

                    continue;
                }

                if ($current !== '') {
                    $lines[] = $current;
                }

                // A single word longer than the line (a URL) is cut rather than
                // allowed to run off the edge of the card.
                while (mb_strlen($word) > $maxChars) {
                    $lines[] = mb_substr($word, 0, $maxChars);
                    $word = mb_substr($word, $maxChars);
                }

                $current = $word;
            }

            if ($current !== '') {
                $lines[] = $current;
            }
        }

        return $lines === [] ? [''] : $lines;
    }

    private function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return '?';
        }

        $initials = mb_strtoupper(mb_substr($words[0], 0, 1));

        if (count($words) > 1) {
            $initials .= mb_strtoupper(mb_substr((string) end($words), 0, 1));
        }

        return self::escape($initials);
    }

    private static function compact(int $value): string
    {
        return match (true) {
            $value >= 1_000_000 => round($value / 1_000_000, 1).'M',
            $value >= 1_000 => round($value / 1_000, 1).'K',
            default => (string) $value,
        };
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
