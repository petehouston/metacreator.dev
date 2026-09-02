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
use App\Support\Social\LinkDisplay;

/**
 * A Pinterest Pin card, drawn rather than screenshotted.
 *
 * The Pin preview tool answers "will my title survive the feed"; this answers the
 * neighbouring question, which is "what does the finished Pin look like to somebody
 * scrolling past" — for a deck, a client mock-up, a blog post about Pinterest, or a
 * before-and-after of a rewritten description.
 *
 * The Pin image is a **placeholder** at Pinterest's own 2:3, on purpose. What people
 * are checking is the title, the description and the source domain — and a
 * generator that composited a real photograph into a real-looking Pin would be a
 * forgery kit rather than a mock-up tool.
 *
 * A **mock-up, not evidence**: it draws whatever is typed, and every run says so.
 */
final class PinterestPinGeneratorRunner implements Cacheable, ToolRunner
{
    /** @var array<string, array{bg: string, fg: string, muted: string, border: string, accent: string, frame: string, brand: string}> */
    private const THEMES = [
        'light' => ['bg' => '#FFFFFF', 'fg' => '#111111', 'muted' => '#767676', 'border' => '#E9E9E9',
            'accent' => '#0074E8', 'frame' => '#EFEFEF', 'brand' => '#E60023'],
        'dark' => ['bg' => '#111111', 'fg' => '#F5F5F5', 'muted' => '#B5B5B5', 'border' => '#2A2A2A',
            'accent' => '#7FB9FF', 'frame' => '#1E1E1E', 'brand' => '#E60023'],
    ];

    private const WIDTHS = ['desktop' => 900, 'mobile' => 760];

    /** Where the closeup cuts a Pin title. */
    private const TITLE_FOLD = 40;

    public static function key(): string
    {
        return 'pinterest.pin-generator';
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
            'required' => ['title', 'account'],
            'additionalProperties' => false,
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'title' => 'Pin title',
                    'minLength' => 1,
                    'maxLength' => 100,
                    'examples' => ['15 sourdough mistakes to stop making'],
                ],
                'description' => [
                    'type' => 'string',
                    'x-control' => 'textarea',
                    'title' => 'Pin description',
                    'maxLength' => 500,
                    'default' => '',
                    'examples' => ['Every one of these cost me a loaf before I worked it out.'],
                ],
                'account' => [
                    'type' => 'string',
                    'title' => 'Account name',
                    'minLength' => 1,
                    'maxLength' => 60,
                    'examples' => ['Riverside Bakery'],
                ],
                'source_url' => [
                    'type' => 'string',
                    'title' => 'Source link',
                    'description' => 'Pinterest shows the domain only, never the path.',
                    'maxLength' => 400,
                    'default' => '',
                    'examples' => ['https://riversidebakery.example/sourdough'],
                ],
                'shape' => [
                    'type' => 'string',
                    'title' => 'Pin shape',
                    'description' => 'Standard is 2:3, which is what Pinterest recommends. Long is 1:2.1, '
                        .'the tallest it will show without cropping.',
                    'enum' => ['standard', 'square', 'long'],
                    'default' => 'standard',
                ],
                'device' => [
                    'type' => 'string', 'title' => 'Layout',
                    'enum' => ['desktop', 'mobile'], 'default' => 'desktop',
                ],
                'theme' => [
                    'type' => 'string', 'title' => 'Theme',
                    'enum' => ['light', 'dark'], 'default' => 'light',
                ],
                'avatar_url' => [
                    'type' => 'string', 'title' => 'Avatar image URL',
                    'description' => 'Optional. Left blank, the card draws initials.',
                    'maxLength' => 600, 'default' => '',
                ],
                'saves' => [
                    'type' => 'integer', 'title' => 'Saves', 'minimum' => 0, 'maximum' => 99999999,
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

        $title = trim($input->string('title'));
        $svg = $this->draw($theme, $width, $title, $input);
        $uri = 'data:image/svg+xml;base64,'.base64_encode($svg);

        return ToolResult::media([
            new ResultArtifact(
                key: 'pinterest-pin',
                filename: 'pinterest-pin-'.$device.'-'.$themeName.'.svg',
                mimeType: 'image/svg+xml',
                size: strlen($svg),
                url: $uri,
                width: $width,
                label: 'Pinterest Pin — '.ucfirst($device).', '.$themeName.' theme',
                previewUrl: $uri,
            ),
        ], summary: "A {$device} Pin card for “{$title}”, in the {$themeName} theme.")
            ->withWarnings(array_values(array_filter([
                mb_strlen($title) > self::TITLE_FOLD
                    ? 'The title is '.mb_strlen($title).' characters. Pinterest cuts it around '
                    .self::TITLE_FOLD.' in the closeup, which the card draws.'
                    : null,
                'This draws whatever you type, so it is a mock-up, not proof. Do not present a card as a '
                .'screenshot of a Pin somebody actually published.',
            ])))
            ->withMeta([
                'title_characters' => mb_strlen($title),
                'description_characters' => mb_strlen($input->string('description')),
                'device' => $device,
                'theme' => $themeName,
            ]);
    }

    /** @param  array{bg: string, fg: string, muted: string, border: string, accent: string, frame: string, brand: string}  $theme */
    private function draw(array $theme, int $width, string $title, ToolInput $input): string
    {
        $pad = 32;
        $inner = $width - $pad * 2;

        $ratio = match ($input->string('shape', 'standard')) {
            'square' => 1.0,
            'long' => 2.1,
            default => 1.5,
        };

        // The Pin image itself is inset with a rounded corner, the way the closeup
        // draws it — a full-bleed rectangle reads as a blog card, not a Pin.
        $mediaHeight = (int) round($inner * $ratio);
        $mediaTop = $pad;

        $body = '<rect x="'.$pad.'" y="'.$mediaTop.'" width="'.$inner.'" height="'.$mediaHeight
            .'" rx="32" fill="'.$theme['frame'].'"/>';
        $body .= '<text x="'.intdiv($width, 2).'" y="'.($mediaTop + intdiv($mediaHeight, 2))
            .'" class="placeholder" text-anchor="middle">'
            .CardSvg::escape($this->shapeLabel($input->string('shape', 'standard'))).'</text>';

        // Pinterest's Save button, top right of the image.
        $body .= '<rect x="'.($width - $pad - 132).'" y="'.($mediaTop + 24).'" width="108" height="56" rx="28" fill="'
            .$theme['brand'].'"/>';
        $body .= '<text x="'.($width - $pad - 78).'" y="'.($mediaTop + 60)
            .'" class="save" text-anchor="middle">Save</text>';

        $y = $mediaTop + $mediaHeight + 62;

        $titleLines = CardSvg::wrap($this->fold($title, self::TITLE_FOLD), $inner, 36);
        $body .= CardSvg::lines($titleLines, $pad, $y, 48, 'title');
        $y += (count($titleLines) - 1) * 48;

        $description = trim($input->string('description'));

        if ($description !== '') {
            $y += 52;
            $descriptionLines = array_slice(CardSvg::wrap($description, $inner, 27), 0, 4);
            $body .= CardSvg::lines($descriptionLines, $pad, $y, 38, 'body', 'accent');
            $y += (count($descriptionLines) - 1) * 38;
        }

        $source = trim($input->string('source_url'));

        if ($source !== '') {
            $y += 50;
            $body .= '<text x="'.$pad.'" y="'.$y.'" class="muted">'
                .CardSvg::escape(LinkDisplay::domain($source)).'</text>';
        }

        // ── Account row ─────────────────────────────────────────────────────
        $account = trim($input->string('account'));
        $avatarUrl = trim($input->string('avatar_url'));

        $y += 72;
        $body .= CardSvg::avatar($pad + 28, $y - 10, 28, $account, $avatarUrl === '' ? null : $avatarUrl,
            $theme['brand'], '#FFFFFF');
        $body .= '<text x="'.($pad + 76).'" y="'.($y - 16).'" class="account">'
            .CardSvg::escape($account).'</text>';

        $saves = $input->int('saves');

        $body .= '<text x="'.($pad + 76).'" y="'.($y + 14).'" class="muted">'
            .CardSvg::escape($saves > 0 ? CardSvg::compact($saves).' saves' : 'Saved by nobody yet')
            .'</text>';

        $height = $y + 52;

        $styles = <<<CSS

            .title { font-size: 36px; font-weight: 700; fill: {$theme['fg']}; }
            .body { font-size: 27px; fill: {$theme['fg']}; }
            .muted { font-size: 24px; fill: {$theme['muted']}; }
            .account { font-size: 27px; font-weight: 600; fill: {$theme['fg']}; }
            .placeholder { font-size: 26px; fill: {$theme['muted']}; }
            .save { font-size: 26px; font-weight: 700; fill: #FFFFFF; }
            .accent { fill: {$theme['accent']}; }
        CSS;

        return CardSvg::document($width, $height, $styles,
            '<rect width="100%" height="100%" rx="24" fill="'.$theme['bg'].'" stroke="'.$theme['border']
            .'" stroke-width="2"/>'.$body,
            'Pinterest Pin: '.$title);
    }

    /** Cut the title where Pinterest cuts it, so the card is honest about the crop. */
    private function fold(string $value, int $length): string
    {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length).'…' : $value;
    }

    private function shapeLabel(string $shape): string
    {
        return match ($shape) {
            'square' => '1:1 · your Pin image goes here',
            'long' => '1:2.1 · your Pin image goes here',
            default => '2:3 · your Pin image goes here',
        };
    }
}
