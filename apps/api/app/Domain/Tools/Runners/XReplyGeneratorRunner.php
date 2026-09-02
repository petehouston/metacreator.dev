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
use App\Support\Social\PostLength;

/**
 * A reply on X, drawn with the post it is replying to.
 *
 * The single-post screenshot tool already draws one card. A reply is a different
 * picture and the more useful one: the joke, the correction, the ratio and the
 * customer-service exchange all only make sense with the parent above them, joined
 * by the thread line that X draws down the left. Cropping that out of a real
 * screenshot is what people spend the time on.
 *
 * Both cards are drawn from text, which makes this a **mock-up tool, not an
 * evidence tool** — twice over, because a fabricated *parent* is the more damaging
 * half. Neither card draws a verified badge, and the warning is on every run.
 */
final class XReplyGeneratorRunner implements Cacheable, ToolRunner
{
    /** @var array<string, array{bg: string, fg: string, muted: string, border: string, accent: string}> */
    private const THEMES = [
        'light' => ['bg' => '#FFFFFF', 'fg' => '#0F1419', 'muted' => '#536471', 'border' => '#EFF3F4', 'accent' => '#1D9BF0'],
        'dim' => ['bg' => '#15202B', 'fg' => '#F7F9F9', 'muted' => '#8B98A5', 'border' => '#38444D', 'accent' => '#1D9BF0'],
        'dark' => ['bg' => '#000000', 'fg' => '#E7E9EA', 'muted' => '#71767B', 'border' => '#2F3336', 'accent' => '#1D9BF0'],
    ];

    private const WIDTHS = ['desktop' => 1100, 'mobile' => 800];

    /** The free-tier post limit. Subscribers get more, which the warning explains. */
    private const LIMIT = 280;

    public static function key(): string
    {
        return 'x.reply-generator';
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
            'required' => ['parent_name', 'parent_handle', 'parent_text', 'reply_name', 'reply_handle', 'reply_text'],
            'additionalProperties' => false,
            'properties' => [
                'parent_name' => [
                    'type' => 'string', 'title' => 'Original — display name',
                    'minLength' => 1, 'maxLength' => 60, 'examples' => ['Riverside Bakery'],
                ],
                'parent_handle' => [
                    'type' => 'string', 'title' => 'Original — handle',
                    'minLength' => 1, 'maxLength' => 30, 'examples' => ['riversidebake'],
                ],
                'parent_text' => [
                    'type' => 'string', 'x-control' => 'textarea', 'title' => 'Original — post',
                    'minLength' => 1, 'maxLength' => 4000,
                    'examples' => ['We are closed for two weeks while the oven is replaced.'],
                ],
                'reply_name' => [
                    'type' => 'string', 'title' => 'Reply — display name',
                    'minLength' => 1, 'maxLength' => 60, 'examples' => ['Sam'],
                ],
                'reply_handle' => [
                    'type' => 'string', 'title' => 'Reply — handle',
                    'minLength' => 1, 'maxLength' => 30, 'examples' => ['samwrites'],
                ],
                'reply_text' => [
                    'type' => 'string', 'x-control' => 'textarea', 'title' => 'Reply — post',
                    'minLength' => 1, 'maxLength' => 4000,
                    'examples' => ['Two weeks without your sourdough is a public health issue.'],
                ],
                'device' => [
                    'type' => 'string', 'title' => 'Layout',
                    'enum' => ['desktop', 'mobile'], 'default' => 'desktop',
                ],
                'theme' => [
                    'type' => 'string', 'title' => 'Theme',
                    'description' => 'X’s own three: Default, Dim and Lights out.',
                    'enum' => ['light', 'dim', 'dark'], 'default' => 'light',
                ],
                'parent_timestamp' => [
                    'type' => 'string', 'title' => 'Original — timestamp', 'maxLength' => 40, 'default' => '4h',
                ],
                'reply_timestamp' => [
                    'type' => 'string', 'title' => 'Reply — timestamp', 'maxLength' => 40, 'default' => '3h',
                ],
                'replies' => [
                    'type' => 'integer', 'title' => 'Replies on the reply', 'minimum' => 0,
                    'maximum' => 99999999, 'default' => 0,
                ],
                'reposts' => [
                    'type' => 'integer', 'title' => 'Reposts', 'minimum' => 0, 'maximum' => 99999999, 'default' => 0,
                ],
                'likes' => [
                    'type' => 'integer', 'title' => 'Likes', 'minimum' => 0, 'maximum' => 99999999, 'default' => 0,
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

        $svg = $this->draw($theme, $width, $input);
        $uri = 'data:image/svg+xml;base64,'.base64_encode($svg);

        $parentLength = PostLength::graphemeCount($input->string('parent_text'));
        $replyLength = PostLength::graphemeCount($input->string('reply_text'));

        $replyHandle = CardSvg::handle($input->string('reply_handle'));

        return ToolResult::media([
            new ResultArtifact(
                key: 'x-reply',
                filename: 'x-reply-'.$device.'-'.$themeName.'.svg',
                mimeType: 'image/svg+xml',
                size: strlen($svg),
                url: $uri,
                width: $width,
                label: 'X reply — '.ucfirst($device).', '.$themeName.' theme',
                previewUrl: $uri,
            ),
        ], summary: "A {$device} reply card: {$replyHandle} answering "
            .CardSvg::handle($input->string('parent_handle')).", {$themeName} theme.")
            ->withWarnings(array_values(array_filter([
                max($parentLength, $replyLength) > self::LIMIT
                    ? 'One of these posts is over '.self::LIMIT.' characters. Only a subscribed account can '
                    .'post that long — the card still draws it, but a free account could not have sent it.'
                    : null,
                'This draws whatever you type, in both cards. It is a mock-up, not proof — and a fabricated '
                .'*original* post is the more damaging half of a fake exchange.',
                'Neither card carries a verified badge on purpose. Presenting a drawn reply as a real one '
                .'from a real account is impersonation, whatever it was drawn with.',
            ])))
            ->withMeta([
                'parent_characters' => $parentLength,
                'reply_characters' => $replyLength,
                'device' => $device,
                'theme' => $themeName,
            ]);
    }

    /** @param  array{bg: string, fg: string, muted: string, border: string, accent: string}  $theme */
    private function draw(array $theme, int $width, ToolInput $input): string
    {
        $pad = 40;
        $avatar = 32;
        $textX = $pad + $avatar * 2 + 24;
        $inner = $width - $textX - $pad;
        $fontSize = $width > 900 ? 30 : 32;
        $lineHeight = $fontSize + 12;

        $parentName = trim($input->string('parent_name'));
        $replyName = trim($input->string('reply_name'));

        $parentLines = CardSvg::wrap($input->string('parent_text'), $inner, $fontSize);
        $replyLines = CardSvg::wrap($input->string('reply_text'), $inner, $fontSize);

        // ── The original ────────────────────────────────────────────────────
        $parentTop = $pad + 30;
        $body = CardSvg::avatar($pad + $avatar, $parentTop + 4, $avatar, $parentName, null,
            $theme['accent'], $theme['bg']);

        $body .= $this->authorRow($theme, $textX, $parentTop, $parentName,
            CardSvg::handle($input->string('parent_handle')), trim($input->string('parent_timestamp', '4h')));

        $bodyTop = $parentTop + 56;
        $body .= CardSvg::lines($parentLines, $textX, $bodyTop, $lineHeight, 'body', 'accent');

        $parentBottom = $bodyTop + (count($parentLines) - 1) * $lineHeight + 28;

        // The thread line X draws from the parent's avatar down to the reply's.
        $replyTop = $parentBottom + 52;
        $body .= '<line x1="'.($pad + $avatar).'" y1="'.($parentTop + $avatar + 12).'" x2="'.($pad + $avatar)
            .'" y2="'.($replyTop - $avatar + 4).'" stroke="'.$theme['border'].'" stroke-width="4"/>';

        $body .= '<text x="'.$textX.'" y="'.($parentBottom + 22).'" class="muted">Replying to '
            .'<tspan class="accent">'.CardSvg::escape(CardSvg::handle($input->string('parent_handle')))
            .'</tspan></text>';

        // ── The reply ───────────────────────────────────────────────────────
        $body .= CardSvg::avatar($pad + $avatar, $replyTop + 4, $avatar, $replyName, null,
            $theme['accent'], $theme['bg']);

        $body .= $this->authorRow($theme, $textX, $replyTop, $replyName,
            CardSvg::handle($input->string('reply_handle')), trim($input->string('reply_timestamp', '3h')));

        $replyBodyTop = $replyTop + 56;
        $body .= CardSvg::lines($replyLines, $textX, $replyBodyTop, $lineHeight, 'body', 'accent');

        $y = $replyBodyTop + (count($replyLines) - 1) * $lineHeight;

        $metrics = array_filter([
            $input->int('replies') > 0 ? CardSvg::compact($input->int('replies')).'  Replies' : null,
            $input->int('reposts') > 0 ? CardSvg::compact($input->int('reposts')).'  Reposts' : null,
            $input->int('likes') > 0 ? CardSvg::compact($input->int('likes')).'  Likes' : null,
        ]);

        if ($metrics !== []) {
            $y += 34;
            $body .= '<line x1="'.$textX.'" y1="'.$y.'" x2="'.($width - $pad).'" y2="'.$y
                .'" stroke="'.$theme['border'].'" stroke-width="2"/>';
            $y += 44;
            $body .= '<text x="'.$textX.'" y="'.$y.'" class="muted">'
                .CardSvg::escape(implode('     ', $metrics)).'</text>';
        }

        $height = $y + $pad;

        $styles = <<<CSS

            .name { font-size: 30px; font-weight: 700; fill: {$theme['fg']}; }
            .muted { font-size: 26px; fill: {$theme['muted']}; }
            .body { font-size: {$fontSize}px; fill: {$theme['fg']}; }
            .accent { fill: {$theme['accent']}; }
        CSS;

        return CardSvg::document($width, $height, $styles,
            '<rect width="100%" height="100%" rx="28" fill="'.$theme['bg'].'" stroke="'.$theme['border']
            .'" stroke-width="2"/>'.$body,
            'Reply on X by '.$replyName.' to '.$parentName);
    }

    /**
     * Name, handle and timestamp on one baseline, the way X draws them.
     *
     * The offsets are estimated from the name's own length rather than measured,
     * for the reason {@see CardSvg::wrap()} gives: SVG has no text metrics here, and
     * a pessimistic estimate leaves a slightly wide gap where a generous one would
     * overlap the handle.
     *
     * @param  array{bg: string, fg: string, muted: string, border: string, accent: string}  $theme
     */
    private function authorRow(array $theme, int $x, int $y, string $name, string $handle, string $stamp): string
    {
        $nameWidth = (int) round(mb_strlen($name) * 30 * 0.58);
        $handleX = $x + $nameWidth + 18;
        $handleWidth = (int) round(mb_strlen($handle) * 26 * 0.55);

        return '<text x="'.$x.'" y="'.$y.'" class="name">'.CardSvg::escape($name).'</text>'
            .'<text x="'.$handleX.'" y="'.$y.'" class="muted">'.CardSvg::escape($handle).'</text>'
            .($stamp === '' ? '' : '<text x="'.($handleX + $handleWidth + 18).'" y="'.$y.'" class="muted">· '
                .CardSvg::escape($stamp).'</text>');
    }
}
