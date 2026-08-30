<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\ResultArtifact;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;

/**
 * A YouTube comment card, drawn rather than screenshotted.
 *
 * The web app renders this tool with its own client-side canvas UI
 * (`apps/web/src/tools/custom/youtube.comment-generator.tsx`) so that a dropped
 * avatar never leaves the browser. This runner is the headless equivalent: same
 * inputs, same layout, drawn as SVG for anyone hitting the API directly — which
 * also keeps the catalog row's input schema honest, since it is pulled from here.
 *
 * Like the post-screenshot tool it is a **mock-up tool, not an evidence tool**: it
 * draws whatever you type, so a card proves nothing about who commented what. It
 * deliberately draws no verified badge and no channel link.
 */
final class YouTubeCommentGeneratorRunner implements Cacheable, ToolRunner
{
    private const WIDTH = 1000;

    private const PADDING = 40;

    private const AVATAR = 80;

    /** @var array<string, array{bg: string, fg: string, muted: string, icon: string, border: string, heart: string}> */
    private const THEMES = [
        'light' => ['bg' => '#FFFFFF', 'fg' => '#0F0F0F', 'muted' => '#606060', 'icon' => '#0F0F0F', 'border' => '#E5E5E5', 'heart' => '#FF0000'],
        'dark' => ['bg' => '#0F0F0F', 'fg' => '#F1F1F1', 'muted' => '#AAAAAA', 'icon' => '#F1F1F1', 'border' => '#272727', 'heart' => '#FF0000'],
    ];

    /** The units YouTube itself uses under a comment, longest first so "seconds" wins over "second". */
    private const UNITS = ['seconds', 'minutes', 'hours', 'days', 'weeks', 'months', 'years'];

    public static function key(): string
    {
        return 'youtube.comment-generator';
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
            'required' => ['username', 'content'],
            'additionalProperties' => false,
            'properties' => [
                'username' => [
                    'type' => 'string',
                    'title' => 'Username',
                    'description' => 'With or without the @.',
                    'minLength' => 1,
                    'maxLength' => 60,
                    'examples' => ['John_Smith'],
                ],
                'content' => [
                    'type' => 'string',
                    'title' => 'Comment',
                    'description' => 'Line breaks are kept.',
                    'minLength' => 1,
                    'maxLength' => 2000,
                    'examples' => ['This video was very funny, thanks for sharing'],
                ],
                'time' => [
                    'type' => 'integer',
                    'title' => 'Time',
                    'description' => 'How long ago the comment was posted.',
                    'minimum' => 1,
                    'maximum' => 999,
                    'default' => 5,
                ],
                'unit' => [
                    'type' => 'string',
                    'title' => 'Ago',
                    'enum' => self::UNITS,
                    'default' => 'hours',
                ],
                'likes' => [
                    'type' => 'integer',
                    'title' => 'Likes',
                    'minimum' => 0,
                    'maximum' => 99999999,
                    'default' => 0,
                ],
                'reaction' => [
                    'type' => 'string',
                    'title' => 'Commenter reaction',
                    'description' => 'Whether the like or dislike button is drawn as pressed.',
                    'enum' => ['neutral', 'like', 'dislike'],
                    'default' => 'neutral',
                ],
                'creator_liked' => [
                    'type' => 'boolean',
                    'title' => 'Creator liked the comment',
                    'description' => 'Draws the creator heart beside the like button.',
                    'default' => false,
                ],
                'theme' => [
                    'type' => 'string',
                    'title' => 'Theme',
                    'enum' => ['light', 'dark'],
                    'default' => 'light',
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $themeKey = $input->string('theme', 'light');
        $theme = self::THEMES[$themeKey] ?? self::THEMES['light'];

        $handle = '@'.ltrim(trim($input->string('username')), '@');
        $svg = $this->draw($theme, $handle, $input);
        $uri = 'data:image/svg+xml;base64,'.base64_encode($svg);

        $artifact = new ResultArtifact(
            key: 'comment',
            filename: 'youtube-comment.svg',
            mimeType: 'image/svg+xml',
            size: strlen($svg),
            url: $uri,
            label: 'Comment card — '.ucfirst($themeKey).' theme',
            previewUrl: $uri,
        );

        return ToolResult::media(
            [$artifact],
            summary: "A {$themeKey}-theme YouTube comment card for {$handle}, ready to drop into a "
                .'thumbnail, a slide or a video.',
        )->withWarnings([
            'This draws whatever you type, so it is a mock-up, not proof. Do not present a card as a '
            .'screenshot of a comment someone actually left.',
        ]);
    }

    /** @param  array{bg: string, fg: string, muted: string, icon: string, border: string, heart: string}  $theme */
    private function draw(array $theme, string $handle, ToolInput $input): string
    {
        $textX = self::PADDING + self::AVATAR + 28;
        $inner = self::WIDTH - $textX - self::PADDING;
        $lines = self::wrap($input->string('content'), $inner, 30);

        // The author row sits on the same baseline as the top of the avatar, the way
        // YouTube stacks it: handle and age on one line, body underneath.
        $y = self::PADDING + 30;
        $ago = self::escape(self::ago($input));

        $body = '<text x="'.$textX.'" y="'.$y.'" class="author">'.self::escape($handle)
            .'<tspan class="muted" dx="14">'.$ago.'</tspan></text>';

        $y += 40;

        foreach ($lines as $line) {
            $body .= '<text x="'.$textX.'" y="'.$y.'" class="body">'.self::escape($line).'</text>';
            $y += 40;
        }

        $y += 14;
        $body .= $this->actionRow($theme, $textX, $y, $input);
        $y += 24;

        $height = max($y + self::PADDING, self::PADDING * 2 + self::AVATAR);
        $width = self::WIDTH;
        $radius = (int) (self::AVATAR / 2);
        $avatarCx = self::PADDING + $radius;
        $avatarCy = self::PADDING + $radius;
        $initial = self::escape(mb_strtoupper(mb_substr(ltrim($handle, '@'), 0, 1)) ?: '?');

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}" role="img" aria-label="YouTube comment by {$handle}">
          <style>
            text { font-family: Roboto, "Helvetica Neue", Arial, sans-serif; }
            .author { font-size: 26px; font-weight: 500; fill: {$theme['fg']}; }
            .muted { font-size: 24px; font-weight: 400; fill: {$theme['muted']}; }
            .body { font-size: 30px; fill: {$theme['fg']}; }
            .avatar { font-size: 36px; font-weight: 500; fill: #FFFFFF; }
          </style>
          <rect width="100%" height="100%" fill="{$theme['bg']}"/>
          <circle cx="{$avatarCx}" cy="{$avatarCy}" r="{$radius}" fill="#909090"/>
          <text x="{$avatarCx}" y="{$avatarCy}" dy="13" class="avatar" text-anchor="middle">{$initial}</text>
          {$body}
        </svg>
        SVG;
    }

    /**
     * Like, dislike, Reply — and the creator heart when it is on.
     *
     * The icons are drawn as paths rather than glyphs so the card does not depend on
     * an emoji font being installed wherever the SVG is opened.
     *
     * @param  array{bg: string, fg: string, muted: string, icon: string, border: string, heart: string}  $theme
     */
    private function actionRow(array $theme, int $x, int $y, ToolInput $input): string
    {
        $reaction = $input->string('reaction', 'neutral');
        $likes = $input->int('likes');

        $row = self::thumb($x, $y, $theme['icon'], $reaction === 'like');

        if ($likes > 0) {
            $row .= '<text x="'.($x + 42).'" y="'.($y + 8).'" class="muted">'
                .self::escape(self::compact($likes)).'</text>';
        }

        $dislikeX = $x + ($likes > 0 ? 130 : 80);
        $row .= self::thumb($dislikeX, $y, $theme['icon'], $reaction === 'dislike', flip: true);

        $replyX = $dislikeX + 70;
        $row .= '<text x="'.$replyX.'" y="'.($y + 8).'" class="muted" font-weight="500">Reply</text>';

        if ($input->bool('creator_liked')) {
            // The heart YouTube stamps on the commenter's avatar corner when the
            // channel owner likes a comment.
            $heartX = $replyX + 110;
            $row .= '<path transform="translate('.$heartX.','.($y - 8).') scale(1.4)" fill="'.$theme['heart'].'" '
                .'d="M10 17.5 8.55 16.2C3.4 11.6 0 8.6 0 4.9 0 2.1 2.2 0 5 0c1.6 0 3.1.7 4 1.9C9.9.7 11.4 0 13 0c2.8 0 5 2.1 5 4.9 0 3.7-3.4 6.7-8.55 11.3L10 17.5z"/>';
        }

        return $row;
    }

    /** A thumbs-up outline, optionally filled to mean "pressed", optionally flipped into a thumbs-down. */
    private static function thumb(int $x, int $y, string $color, bool $pressed, bool $flip = false): string
    {
        $path = 'M1 9h4v11H1V9zm6.5 11h8.3c1 0 1.9-.6 2.2-1.5l2.5-6a2.4 2.4 0 0 0-2.2-3.3h-5l.8-3.8a1.8 1.8 0 0 0-3.4-1.1L7.5 8.6V20z';
        $transform = $flip
            ? 'translate('.($x + 26).','.($y + 14).') scale(-1.3,-1.3)'
            : 'translate('.$x.','.($y - 12).') scale(1.3)';

        return '<path transform="'.$transform.'" d="'.$path.'" fill="'.($pressed ? $color : 'none')
            .'" stroke="'.$color.'" stroke-width="1.6" stroke-linejoin="round"/>';
    }

    /** "5 hours ago" — singular when the count is one, exactly as YouTube writes it. */
    private static function ago(ToolInput $input): string
    {
        $value = max(1, $input->int('time', 5));
        $unit = $input->string('unit', 'hours');

        if (! in_array($unit, self::UNITS, true)) {
            $unit = 'hours';
        }

        if ($value === 1) {
            $unit = rtrim($unit, 's');
        }

        return "{$value} {$unit} ago";
    }

    /**
     * Greedy word wrap on an estimated glyph width.
     *
     * SVG cannot reflow text, so the wrap happens here. The 0.55em estimate is
     * deliberately pessimistic: a slightly short line costs nothing, a line running
     * off the edge of the card is the only failure anyone would notice.
     *
     * @return list<string>
     */
    private static function wrap(string $text, int $width, int $fontSize): array
    {
        $maxChars = max(10, (int) floor($width / ($fontSize * 0.55)));
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

    private static function compact(int $value): string
    {
        return match (true) {
            $value >= 1_000_000 => rtrim(rtrim(number_format($value / 1_000_000, 1, '.', ''), '0'), '.').'M',
            $value >= 1_000 => rtrim(rtrim(number_format($value / 1_000, 1, '.', ''), '0'), '.').'K',
            default => (string) $value,
        };
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
