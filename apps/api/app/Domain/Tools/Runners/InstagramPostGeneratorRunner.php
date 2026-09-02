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
 * An Instagram post card, drawn rather than screenshotted.
 *
 * Instagram's card is the one people most often need a clean copy of — for a deck
 * about a campaign, a mock-up shown to a client before anything is shot, a
 * before-and-after of a caption rewrite — and the one hardest to screenshot
 * cleanly, because the web layout wraps it in a sidebar and the app wraps it in a
 * status bar.
 *
 * The photo is a **placeholder**, deliberately. A generator that composited a real
 * image into a real-looking post would be a forgery kit; one that draws a marked
 * frame at Instagram's own aspect ratio answers the question people actually have,
 * which is where the caption falls and where it gets cut.
 *
 * A **mock-up, not evidence**: it draws whatever is typed, there is no verified
 * badge, and every run says so.
 */
final class InstagramPostGeneratorRunner implements Cacheable, ToolRunner
{
    /** @var array<string, array{bg: string, fg: string, muted: string, border: string, accent: string, frame: string}> */
    private const THEMES = [
        'light' => ['bg' => '#FFFFFF', 'fg' => '#000000', 'muted' => '#737373', 'border' => '#DBDBDB',
            'accent' => '#00376B', 'frame' => '#EFEFEF'],
        'dark' => ['bg' => '#000000', 'fg' => '#F5F5F5', 'muted' => '#A8A8A8', 'border' => '#262626',
            'accent' => '#E0F1FF', 'frame' => '#1A1A1A'],
    ];

    private const WIDTHS = ['desktop' => 940, 'mobile' => 780];

    /** Instagram's three post shapes, as height ÷ width. */
    private const RATIOS = ['square' => 1.0, 'portrait' => 1.25, 'landscape' => 0.5625];

    /** Where the feed cuts a caption and shows "… more". */
    private const CAPTION_FOLD = 125;

    public static function key(): string
    {
        return 'instagram.post-generator';
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
            'required' => ['username', 'caption'],
            'additionalProperties' => false,
            'properties' => [
                'username' => [
                    'type' => 'string',
                    'title' => 'Username',
                    'description' => 'With or without the @.',
                    'minLength' => 1,
                    'maxLength' => 40,
                    'examples' => ['riverside.bakery'],
                ],
                'caption' => [
                    'type' => 'string',
                    'x-control' => 'textarea',
                    'title' => 'Caption',
                    'minLength' => 1,
                    'maxLength' => 2200,
                    'examples' => ['New oven, same sourdough. Open from Saturday ☀️ #bakery #sourdough'],
                ],
                'location' => [
                    'type' => 'string',
                    'title' => 'Location',
                    'description' => 'Optional. Drawn under the username, as the app does.',
                    'maxLength' => 60,
                    'default' => '',
                ],
                'shape' => [
                    'type' => 'string',
                    'title' => 'Post shape',
                    'enum' => ['square', 'portrait', 'landscape'],
                    'default' => 'square',
                ],
                'device' => [
                    'type' => 'string',
                    'title' => 'Layout',
                    'enum' => ['desktop', 'mobile'],
                    'default' => 'mobile',
                ],
                'theme' => [
                    'type' => 'string',
                    'title' => 'Theme',
                    'enum' => ['light', 'dark'],
                    'default' => 'light',
                ],
                'avatar_url' => [
                    'type' => 'string',
                    'title' => 'Avatar image URL',
                    'description' => 'Optional. Left blank, the card draws initials.',
                    'maxLength' => 600,
                    'default' => '',
                ],
                'timestamp' => [
                    'type' => 'string',
                    'title' => 'Timestamp',
                    'maxLength' => 40,
                    'default' => '2 hours ago',
                ],
                'likes' => [
                    'type' => 'integer', 'title' => 'Likes', 'minimum' => 0, 'maximum' => 99999999,
                    'default' => 0,
                ],
                'comments' => [
                    'type' => 'integer', 'title' => 'Comments', 'minimum' => 0, 'maximum' => 99999999,
                    'default' => 0,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $themeName = $input->string('theme', 'light');
        $theme = self::THEMES[$themeName] ?? self::THEMES['light'];
        $device = $input->string('device', 'mobile');
        $width = self::WIDTHS[$device] ?? self::WIDTHS['mobile'];

        $username = ltrim(trim($input->string('username')), '@');
        $caption = $input->string('caption');

        $svg = $this->draw($theme, $width, $username, $caption, $input);
        $uri = 'data:image/svg+xml;base64,'.base64_encode($svg);

        $length = mb_strlen($caption);

        return ToolResult::media([
            new ResultArtifact(
                key: 'instagram-post',
                filename: 'instagram-post-'.$device.'-'.$themeName.'.svg',
                mimeType: 'image/svg+xml',
                size: strlen($svg),
                url: $uri,
                width: $width,
                label: 'Instagram post — '.ucfirst($device).', '.$themeName.' theme',
                previewUrl: $uri,
            ),
        ], summary: "A {$device} Instagram post card for @{$username}, in the {$themeName} theme.")
            ->withWarnings(array_values(array_filter([
                $length > self::CAPTION_FOLD
                    ? 'The caption is '.$length.' characters. Instagram cuts it at about '
                    .self::CAPTION_FOLD.' in the feed, so the card draws the rest greyed — that is where '
                    .'"… more" appears on the real thing.'
                    : null,
                'This draws whatever you type, so it is a mock-up, not proof. Do not present a card as a '
                .'screenshot of a post somebody actually made.',
                'The card carries no verified badge on purpose. Passing off a fake post as coming from a '
                .'real account is impersonation, whatever it was drawn with.',
            ])))
            ->withMeta(['characters' => $length, 'device' => $device, 'theme' => $themeName]);
    }

    /** @param  array{bg: string, fg: string, muted: string, border: string, accent: string, frame: string}  $theme */
    private function draw(array $theme, int $width, string $username, string $caption, ToolInput $input): string
    {
        $pad = 28;
        $headerHeight = 108;
        $mediaHeight = (int) round($width * (self::RATIOS[$input->string('shape', 'square')] ?? 1.0));

        $avatarUrl = trim($input->string('avatar_url'));
        $location = trim($input->string('location'));

        // ── Header: avatar, username, optional location ────────────────────
        $body = CardSvg::avatar($pad + 32, 54, 32, $username, $avatarUrl === '' ? null : $avatarUrl,
            $theme['accent'], $theme['bg']);

        $textX = $pad + 82;

        $body .= '<text x="'.$textX.'" y="'.($location === '' ? 64 : 48).'" class="name">'
            .CardSvg::escape($username).'</text>';

        if ($location !== '') {
            $body .= '<text x="'.$textX.'" y="78" class="muted">'.CardSvg::escape($location).'</text>';
        }

        // ── Media placeholder, at the shape's own aspect ────────────────────
        $body .= '<rect x="0" y="'.$headerHeight.'" width="'.$width.'" height="'.$mediaHeight
            .'" fill="'.$theme['frame'].'"/>';
        $body .= '<text x="'.intdiv($width, 2).'" y="'.($headerHeight + intdiv($mediaHeight, 2))
            .'" class="placeholder" text-anchor="middle">'
            .CardSvg::escape(ucfirst($input->string('shape', 'square')).' · your photo goes here').'</text>';

        $y = $headerHeight + $mediaHeight + 52;

        // ── Action row, as words rather than icons ──────────────────────────
        $body .= '<text x="'.$pad.'" y="'.$y.'" class="action">Like   Comment   Share</text>';
        $body .= '<text x="'.($width - $pad).'" y="'.$y.'" class="action" text-anchor="end">Save</text>';

        $likes = $input->int('likes');

        if ($likes > 0) {
            $y += 46;
            $body .= '<text x="'.$pad.'" y="'.$y.'" class="likes">'
                .CardSvg::compact($likes).' like'.($likes === 1 ? '' : 's').'</text>';
        }

        // ── Caption, split where the feed splits it ─────────────────────────
        $y += 44;
        $inner = $width - $pad * 2;
        $visible = mb_substr($caption, 0, self::CAPTION_FOLD);
        $hidden = mb_substr($caption, self::CAPTION_FOLD);

        $body .= '<text x="'.$pad.'" y="'.$y.'" class="name">'.CardSvg::escape($username).'</text>';

        $lines = CardSvg::wrap($visible, $inner, 28);
        $body .= CardSvg::lines($lines, $pad, $y + 40, 40, 'body', 'accent');
        $y += 40 + (count($lines) - 1) * 40;

        if ($hidden !== '') {
            $hiddenLines = CardSvg::wrap($hidden, $inner, 28);
            $body .= CardSvg::lines($hiddenLines, $pad, $y + 40, 40, 'hidden', 'hidden');
            $y += 40 + (count($hiddenLines) - 1) * 40;
        }

        $comments = $input->int('comments');

        if ($comments > 0) {
            $y += 46;
            $body .= '<text x="'.$pad.'" y="'.$y.'" class="muted">View all '
                .CardSvg::compact($comments).' comments</text>';
        }

        $y += 42;
        $body .= '<text x="'.$pad.'" y="'.$y.'" class="stamp">'
            .CardSvg::escape(trim($input->string('timestamp', '2 hours ago'))).'</text>';

        $height = $y + $pad;

        $styles = <<<CSS

            .name { font-size: 28px; font-weight: 600; fill: {$theme['fg']}; }
            .muted { font-size: 24px; fill: {$theme['muted']}; }
            .stamp { font-size: 21px; fill: {$theme['muted']}; letter-spacing: 0.4px; }
            .body { font-size: 28px; fill: {$theme['fg']}; }
            .hidden { font-size: 28px; fill: {$theme['muted']}; opacity: 0.55; }
            .likes { font-size: 27px; font-weight: 600; fill: {$theme['fg']}; }
            .action { font-size: 25px; font-weight: 600; fill: {$theme['fg']}; }
            .placeholder { font-size: 26px; fill: {$theme['muted']}; }
            .accent { fill: {$theme['accent']}; }
        CSS;

        return CardSvg::document($width, $height, $styles,
            '<rect width="100%" height="100%" fill="'.$theme['bg'].'" stroke="'.$theme['border']
            .'" stroke-width="2"/>'.$body,
            'Instagram post by '.$username);
    }
}
