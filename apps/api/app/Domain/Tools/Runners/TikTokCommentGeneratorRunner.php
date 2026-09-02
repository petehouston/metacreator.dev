<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\ResultArtifact;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Support\Social\CardSvg;

/**
 * A TikTok comment card, drawn rather than screenshotted.
 *
 * The pattern is now a format of its own: a comment, screenshotted, used as the
 * hook on the next video. Doing it for real means catching the comment on a phone,
 * cropping the status bar and the keyboard out, and living with whatever the
 * compression did. Drawing it gives a sharp card at any size, with the counts and
 * the timestamp set to whatever the story needs.
 *
 * The creator's reply badge is the detail that sells it and the one every generator
 * gets wrong: TikTok marks the video's own author with a "Creator" chip, not a
 * heart, and it sits after the handle rather than in the action row.
 *
 * A **mock-up, not evidence**: it draws whatever is typed. There is no verified
 * badge, and the warning is on every run.
 */
final class TikTokCommentGeneratorRunner implements Cacheable, ToolRunner
{
    /** @var array<string, array{bg: string, fg: string, muted: string, border: string, accent: string, chip: string}> */
    private const THEMES = [
        // TikTok's own two: the app is dark by default, the web comment panel light.
        'dark' => ['bg' => '#121212', 'fg' => '#FFFFFF', 'muted' => '#8A8B91', 'border' => '#2A2A2C',
            'accent' => '#20D5EC', 'chip' => '#2A2A2C'],
        'light' => ['bg' => '#FFFFFF', 'fg' => '#161823', 'muted' => '#86878B', 'border' => '#E3E3E4',
            'accent' => '#00B4CC', 'chip' => '#F1F1F2'],
    ];

    private const WIDTHS = ['desktop' => 1000, 'mobile' => 820];

    private const UNITS = ['s', 'm', 'h', 'd', 'w'];

    public static function key(): string
    {
        return 'tiktok.comment-generator';
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
                    'maxLength' => 40,
                    'examples' => ['sam.bakes'],
                ],
                'content' => [
                    'type' => 'string',
                    'x-control' => 'textarea',
                    'title' => 'Comment',
                    'minLength' => 1,
                    'maxLength' => 300,
                    'examples' => ['no because the oven reveal actually made me gasp'],
                ],
                'age' => [
                    'type' => 'string',
                    'title' => 'Posted',
                    'description' => 'TikTok’s own shorthand: 5m, 3h, 2d, 1w. “3 hours ago” is accepted '
                        .'too and shortened for you.',
                    'maxLength' => 24,
                    'default' => '3h',
                ],
                'is_creator' => [
                    'type' => 'boolean',
                    'title' => 'Commenter is the video’s creator',
                    'description' => 'Adds the “Creator” chip TikTok puts after the handle.',
                    'default' => false,
                ],
                'liked_by_creator' => [
                    'type' => 'boolean',
                    'title' => 'Liked by creator',
                    'default' => false,
                ],
                'pinned' => [
                    'type' => 'boolean',
                    'title' => 'Pinned comment',
                    'default' => false,
                ],
                'device' => [
                    'type' => 'string', 'title' => 'Layout',
                    'enum' => ['desktop', 'mobile'], 'default' => 'mobile',
                ],
                'theme' => [
                    'type' => 'string', 'title' => 'Theme',
                    'enum' => ['dark', 'light'], 'default' => 'dark',
                ],
                'avatar_url' => [
                    'type' => 'string', 'title' => 'Avatar image URL',
                    'description' => 'Optional. Left blank, the card draws initials.',
                    'maxLength' => 600, 'default' => '',
                ],
                'likes' => [
                    'type' => 'integer', 'title' => 'Likes', 'minimum' => 0, 'maximum' => 99999999,
                    'default' => 0,
                ],
                'replies' => [
                    'type' => 'integer', 'title' => 'Replies', 'minimum' => 0, 'maximum' => 99999999,
                    'default' => 0,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $themeName = $input->string('theme', 'dark');
        $theme = self::THEMES[$themeName] ?? self::THEMES['dark'];
        $device = $input->string('device', 'mobile');
        $width = self::WIDTHS[$device] ?? self::WIDTHS['mobile'];

        $username = ltrim(trim($input->string('username')), '@');
        $svg = $this->draw($theme, $width, $username, $input);
        $uri = 'data:image/svg+xml;base64,'.base64_encode($svg);

        return ToolResult::media([
            new ResultArtifact(
                key: 'tiktok-comment',
                filename: 'tiktok-comment-'.$device.'-'.$themeName.'.svg',
                mimeType: 'image/svg+xml',
                size: strlen($svg),
                url: $uri,
                width: $width,
                label: 'TikTok comment — '.ucfirst($device).', '.$themeName.' theme',
                previewUrl: $uri,
            ),
        ], summary: "A {$device} TikTok comment card for @{$username}, in the {$themeName} theme.")
            ->withWarnings([
                'This draws whatever you type, so it is a mock-up, not proof. Do not present a card as a '
                .'screenshot of a comment somebody actually left.',
                'Using a real person\'s handle on a comment they did not write is impersonation, and a card '
                .'that ends up as the hook on a video reaches further than the correction ever will.',
            ])
            ->withMeta([
                'characters' => mb_strlen($input->string('content')),
                'device' => $device,
                'theme' => $themeName,
            ]);
    }

    /** @param  array{bg: string, fg: string, muted: string, border: string, accent: string, chip: string}  $theme */
    private function draw(array $theme, int $width, string $username, ToolInput $input): string
    {
        $pad = 36;
        $avatar = 38;
        $textX = $pad + $avatar * 2 + 22;
        // The like column on the right is reserved before the body is wrapped, or
        // long comments run under the heart.
        $inner = $width - $textX - $pad - 90;

        $body = CardSvg::avatar($pad + $avatar, $pad + $avatar, $avatar, $username,
            ($url = trim($input->string('avatar_url'))) === '' ? null : $url, $theme['accent'], $theme['bg']);

        $y = $pad + 30;

        if ($input->bool('pinned')) {
            $body .= '<text x="'.$textX.'" y="'.$y.'" class="pin">Pinned</text>';
            $y += 36;
        }

        $handle = CardSvg::handle($username);
        $body .= '<text x="'.$textX.'" y="'.$y.'" class="handle">'.CardSvg::escape($handle).'</text>';

        if ($input->bool('is_creator')) {
            // The chip TikTok stamps on the video author's own comments.
            $chipX = $textX + (int) round(mb_strlen($handle) * 26 * 0.56) + 16;

            $body .= '<rect x="'.$chipX.'" y="'.($y - 24).'" width="118" height="34" rx="8" fill="'
                .$theme['chip'].'"/>';
            $body .= '<text x="'.($chipX + 59).'" y="'.($y - 1).'" class="chip" text-anchor="middle">Creator</text>';
        }

        $lines = CardSvg::wrap($input->string('content'), $inner, 30);
        $body .= CardSvg::lines($lines, $textX, $y + 46, 42, 'body', 'accent');

        $y += 46 + (count($lines) - 1) * 42;

        // ── Meta row: age, Reply, and the creator-liked note ─────────────────
        $meta = [$this->age(trim($input->string('age', '3h'))), 'Reply'];

        if ($input->int('replies') > 0) {
            $meta[] = 'View '.CardSvg::compact($input->int('replies'))
                .' repl'.($input->int('replies') === 1 ? 'y' : 'ies');
        }

        $y += 46;
        $body .= '<text x="'.$textX.'" y="'.$y.'" class="muted">'
            .CardSvg::escape(implode('     ', $meta)).'</text>';

        if ($input->bool('liked_by_creator')) {
            $y += 40;
            $body .= '<text x="'.$textX.'" y="'.$y.'" class="muted">♥ Liked by creator</text>';
        }

        // ── The heart column, right-aligned against the card edge ───────────
        $heartY = $pad + 44;
        $body .= '<text x="'.($width - $pad).'" y="'.$heartY.'" class="heart" text-anchor="end">♥</text>';

        if ($input->int('likes') > 0) {
            $body .= '<text x="'.($width - $pad).'" y="'.($heartY + 38).'" class="muted" text-anchor="end">'
                .CardSvg::compact($input->int('likes')).'</text>';
        }

        $height = max($y + $pad, $pad * 2 + $avatar * 2);

        $styles = <<<CSS

            .handle { font-size: 26px; font-weight: 600; fill: {$theme['muted']}; }
            .body { font-size: 30px; fill: {$theme['fg']}; }
            .muted { font-size: 24px; fill: {$theme['muted']}; }
            .chip { font-size: 21px; font-weight: 600; fill: {$theme['muted']}; }
            .pin { font-size: 21px; font-weight: 700; fill: {$theme['accent']}; letter-spacing: 0.5px; }
            .heart { font-size: 34px; fill: {$theme['muted']}; }
            .accent { fill: {$theme['accent']}; }
        CSS;

        return CardSvg::document($width, $height, $styles,
            '<rect width="100%" height="100%" rx="20" fill="'.$theme['bg'].'" stroke="'.$theme['border']
            .'" stroke-width="2"/>'.$body,
            'TikTok comment by '.$handle);
    }

    /**
     * Normalise the age to TikTok's shorthand.
     *
     * TikTok writes "3h", never "3 hours ago", and a card that spells it out is the
     * fastest tell there is. Anything unrecognised is passed through rather than
     * rejected — somebody typing a date knows what they are doing.
     */
    private function age(string $value): string
    {
        if (preg_match('/^(\d+)\s*(second|minute|hour|day|week)s?\b/i', $value, $match) === 1) {
            return $match[1].mb_strtolower(mb_substr($match[2], 0, 1));
        }

        if (preg_match('/^(\d+)\s*([smhdw])$/i', $value, $match) === 1
            && in_array(mb_strtolower($match[2]), self::UNITS, true)) {
            return $match[1].mb_strtolower($match[2]);
        }

        return $value === '' ? '3h' : $value;
    }
}
