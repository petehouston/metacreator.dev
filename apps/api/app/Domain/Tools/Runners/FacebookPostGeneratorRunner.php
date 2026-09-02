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
 * A Facebook post card, drawn rather than screenshotted.
 *
 * The use is mundane and constant: a slide about something somebody posted, a
 * mock-up of a post that has not been written yet, a teaching example, a card in a
 * newsletter. Screenshotting the real thing drags in a browser chrome, a sidebar,
 * a cookie banner and whoever else was in the feed — and if the post is somebody
 * else's, their photograph too.
 *
 * It is a **mock-up tool, not an evidence tool**. It draws whatever is typed, so a
 * card proves nothing about who posted what, and every run says so. There is
 * deliberately no verified badge: the one thing a fake card must not be able to
 * claim is that it came from a verified account.
 *
 * The web app renders this with a canvas UI that also does mobile and desktop
 * widths and exports PNG/JPG/WebP/AVIF; this runner is the headless equivalent,
 * which is what keeps the catalog's input schema honest (docs/08).
 */
final class FacebookPostGeneratorRunner implements Cacheable, ToolRunner
{
    /** @var array<string, array{bg: string, fg: string, muted: string, border: string, accent: string, chip: string}> */
    private const THEMES = [
        'light' => ['bg' => '#FFFFFF', 'fg' => '#050505', 'muted' => '#65676B', 'border' => '#CED0D4',
            'accent' => '#0866FF', 'chip' => '#F0F2F5'],
        'dark' => ['bg' => '#242526', 'fg' => '#E4E6EB', 'muted' => '#B0B3B8', 'border' => '#3E4042',
            'accent' => '#2E89FF', 'chip' => '#3A3B3C'],
    ];

    /** Desktop feed card and the narrower phone card, at 2× for a sharp export. */
    private const WIDTHS = ['desktop' => 1120, 'mobile' => 780];

    public static function key(): string
    {
        return 'facebook.post-generator';
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
            'required' => ['name', 'text'],
            'additionalProperties' => false,
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'title' => 'Page or person name',
                    'minLength' => 1,
                    'maxLength' => 80,
                    'examples' => ['Riverside Bakery'],
                ],
                'text' => [
                    'type' => 'string',
                    'x-control' => 'textarea',
                    'title' => 'Post text',
                    'minLength' => 1,
                    'maxLength' => 3000,
                    'examples' => ['We are open again from Saturday. Same sourdough, new oven. 🥖'],
                ],
                'timestamp' => [
                    'type' => 'string',
                    'title' => 'Timestamp',
                    'description' => 'Whatever Facebook would show: “2h”, “Yesterday at 14:20”, “12 March”.',
                    'maxLength' => 40,
                    'default' => '2h',
                ],
                'audience' => [
                    'type' => 'string',
                    'title' => 'Audience',
                    'enum' => ['public', 'friends', 'private'],
                    'default' => 'public',
                ],
                'device' => [
                    'type' => 'string',
                    'title' => 'Layout',
                    'description' => 'Desktop draws the wider feed card; mobile draws the phone width, '
                        .'where the text wraps sooner.',
                    'enum' => ['desktop', 'mobile'],
                    'default' => 'desktop',
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
                    'description' => 'Optional. Left blank, the card draws the name’s initials — which is '
                        .'usually the better choice for a mock-up.',
                    'maxLength' => 600,
                    'default' => '',
                ],
                'reactions' => [
                    'type' => 'integer', 'title' => 'Reactions', 'minimum' => 0, 'maximum' => 99999999,
                    'default' => 0,
                ],
                'comments' => [
                    'type' => 'integer', 'title' => 'Comments', 'minimum' => 0, 'maximum' => 99999999,
                    'default' => 0,
                ],
                'shares' => [
                    'type' => 'integer', 'title' => 'Shares', 'minimum' => 0, 'maximum' => 99999999,
                    'default' => 0,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $themeName = $input->string('theme', 'light');
        $theme = self::THEMES[$themeName] ?? self::THEMES['light'];
        $device = $input->string('device', 'desktop');
        $width = self::WIDTHS[$device] ?? self::WIDTHS['desktop'];

        $name = trim($input->string('name'));
        $svg = $this->draw($theme, $width, $name, $input);
        $uri = 'data:image/svg+xml;base64,'.base64_encode($svg);

        return ToolResult::media([
            new ResultArtifact(
                key: 'facebook-post',
                filename: 'facebook-post-'.$device.'-'.$themeName.'.svg',
                mimeType: 'image/svg+xml',
                size: strlen($svg),
                url: $uri,
                width: $width,
                label: 'Facebook post — '.ucfirst($device).', '.$themeName.' theme',
                previewUrl: $uri,
            ),
        ], summary: "A {$device} Facebook post card for {$name}, in the {$themeName} theme.")
            ->withWarnings([
                'This draws whatever you type, so it is a mock-up, not proof. Do not present a card as a '
                .'screenshot of a post somebody actually made.',
                'The card carries no verified badge on purpose. Passing off a fake post as coming from a '
                .'real page or person is impersonation, whatever it was drawn with.',
            ])
            ->withMeta([
                'characters' => mb_strlen($input->string('text')),
                'device' => $device,
                'theme' => $themeName,
            ]);
    }

    /** @param  array{bg: string, fg: string, muted: string, border: string, accent: string, chip: string}  $theme */
    private function draw(array $theme, int $width, string $name, ToolInput $input): string
    {
        $pad = 36;
        $inner = $width - $pad * 2;
        $bodySize = $width > 900 ? 30 : 32;

        $lines = CardSvg::wrap($input->string('text'), $inner, $bodySize);

        $avatarUrl = trim($input->string('avatar_url'));
        $body = CardSvg::avatar($pad + 34, $pad + 34, 34, $name, $avatarUrl === '' ? null : $avatarUrl,
            $theme['accent'], $theme['bg']);

        $textX = $pad + 88;

        $body .= '<text x="'.$textX.'" y="'.($pad + 26).'" class="name">'.CardSvg::escape($name).'</text>';
        $body .= '<text x="'.$textX.'" y="'.($pad + 58).'" class="muted">'
            .CardSvg::escape(trim($input->string('timestamp', '2h')).'  ·  '
                .$this->audienceLabel($input->string('audience', 'public'))).'</text>';

        $bodyTop = $pad + 118;
        $lineHeight = $bodySize + 12;

        $body .= CardSvg::lines($lines, $pad, $bodyTop, $lineHeight, 'body', 'accent');

        $y = $bodyTop + (count($lines) - 1) * $lineHeight + 34;

        $reactions = $input->int('reactions');
        $comments = $input->int('comments');
        $shares = $input->int('shares');

        if ($reactions > 0 || $comments > 0 || $shares > 0) {
            // Facebook puts reactions on the left and comments/shares on the right,
            // and that asymmetry is one of the things the eye recognises.
            $body .= $this->reactionPills($theme, $pad, $y + 8);

            if ($reactions > 0) {
                $body .= '<text x="'.($pad + 76).'" y="'.($y + 18).'" class="muted">'
                    .CardSvg::compact($reactions).'</text>';
            }

            $right = array_filter([
                $comments > 0 ? CardSvg::compact($comments).' comment'.($comments === 1 ? '' : 's') : null,
                $shares > 0 ? CardSvg::compact($shares).' share'.($shares === 1 ? '' : 's') : null,
            ]);

            if ($right !== []) {
                $body .= '<text x="'.($width - $pad).'" y="'.($y + 18).'" class="muted" text-anchor="end">'
                    .CardSvg::escape(implode('  ', $right)).'</text>';
            }

            $y += 44;
        }

        // The action row, drawn as labels rather than icons: a wrong icon reads as
        // fake far faster than a missing one.
        $y += 16;
        $body .= '<line x1="'.$pad.'" y1="'.$y.'" x2="'.($width - $pad).'" y2="'.$y
            .'" stroke="'.$theme['border'].'" stroke-width="2"/>';

        $y += 42;
        $third = intdiv($width, 3);

        foreach (['Like', 'Comment', 'Share'] as $index => $label) {
            $body .= '<text x="'.($third * $index + intdiv($third, 2)).'" y="'.$y
                .'" class="action" text-anchor="middle">'.$label.'</text>';
        }

        $height = $y + $pad;

        $styles = <<<CSS

            .name { font-size: 30px; font-weight: 600; fill: {$theme['fg']}; }
            .muted { font-size: 24px; fill: {$theme['muted']}; }
            .body { font-size: {$bodySize}px; fill: {$theme['fg']}; }
            .action { font-size: 26px; font-weight: 600; fill: {$theme['muted']}; }
            .accent { fill: {$theme['accent']}; }
        CSS;

        return CardSvg::document($width, $height, $styles,
            '<rect width="100%" height="100%" rx="16" fill="'.$theme['bg']
            .'" stroke="'.$theme['border'].'" stroke-width="2"/>'.$body,
            'Facebook post by '.$name);
    }

    /**
     * The overlapping like and love discs Facebook stacks above the counts.
     *
     * @param  array{bg: string, fg: string, muted: string, border: string, accent: string, chip: string}  $theme
     */
    private function reactionPills(array $theme, int $x, int $y): string
    {
        return '<circle cx="'.($x + 16).'" cy="'.$y.'" r="16" fill="'.$theme['accent'].'"/>'
            .'<circle cx="'.($x + 44).'" cy="'.$y.'" r="16" fill="#F3425F" stroke="'.$theme['bg'].'" stroke-width="3"/>';
    }

    private function audienceLabel(string $audience): string
    {
        return match ($audience) {
            'friends' => 'Friends',
            'private' => 'Only me',
            default => 'Public',
        };
    }
}
