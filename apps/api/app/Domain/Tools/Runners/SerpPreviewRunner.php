<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Support\Social\PreviewFrame;
use App\Support\Social\SocialUrl;
use App\Support\Text\TextWidth;

/**
 * Your title and meta description drawn as a Google result, on a desktop and on a
 * phone, with the part that gets cut greyed out in place.
 *
 * Google truncates a snippet on **pixel width**, not on a character count, and it
 * does it in a fixed column: a desktop result is drawn in roughly 600 CSS pixels
 * and a phone in roughly 372. That is why every "60 characters" rule of thumb is
 * wrong in both directions — "Will It Fit?" is 12 characters wider than
 * "illinois trivia" despite being three characters shorter.
 *
 * So the fold here is measured rather than counted ({@see TextWidth}), at the font
 * sizes Google draws, and reported as a width against a budget. The house standard
 * in docs/16 — warn a title at 580 px — falls out of the desktop row below rather
 * than being asserted separately.
 *
 * What this cannot know, and says so: Google rewrites a title it dislikes, and it
 * ignores a meta description whenever the page contains a passage that answers the
 * query better. This shows what you *submitted*, which is the half you control.
 */
final class SerpPreviewRunner implements Cacheable, ToolRunner
{
    /**
     * The two surfaces, in the geometry Google draws them in.
     *
     * `column` is the width of the result column in CSS pixels; `title_lines` and
     * `description_lines` are how many lines of that column each element is allowed
     * before the ellipsis. Multiplying the two is the budget, which is the honest
     * way to express a clamp that is really "N lines of a fixed column" — a single
     * character count cannot express it at all.
     *
     * @var array<string, array{label: string, device: int, column: int, title_size: float, description_size: float, title_lines: int, description_lines: int, note: string}>
     */
    private const SURFACES = [
        'desktop' => [
            'label' => 'Desktop search',
            'device' => 600,
            'column' => 600,
            'title_size' => 20.0,
            'description_size' => 14.0,
            'title_lines' => 1,
            'description_lines' => 2,
            'note' => 'One line for the title on a desktop result, and two for the snippet under it.',
        ],
        'mobile' => [
            'label' => 'Mobile search',
            'device' => 372,
            'column' => 372,
            'title_size' => 20.0,
            'description_size' => 14.0,
            'title_lines' => 2,
            'description_lines' => 3,
            'note' => 'A phone gives the title a second line and the snippet a third — narrower, '
                .'but more total room. Most of your clicks are here.',
        ],
    ];

    /** The ellipsis itself occupies part of the last line. */
    private const ELLIPSIS_ALLOWANCE = 0.97;

    public static function key(): string
    {
        return 'seo.serp-preview';
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
            'required' => ['title'],
            'additionalProperties' => false,
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'x-control' => 'text',
                    'title' => 'Title tag',
                    'description' => 'The <title> of the page, including any brand suffix you append.',
                    'minLength' => 1,
                    'maxLength' => 300,
                    'examples' => ['YouTube Thumbnail Downloader — Get Any Video Thumbnail in HD (Free)'],
                ],
                'description' => [
                    'type' => 'string',
                    'x-control' => 'textarea',
                    'title' => 'Meta description',
                    'maxLength' => 600,
                    'default' => '',
                ],
                'url' => [
                    'type' => 'string',
                    'x-control' => 'text',
                    'title' => 'Page URL',
                    'description' => 'Only used to draw the site name and the crumb trail Google shows '
                        .'above the title.',
                    'maxLength' => 500,
                    'default' => '',
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $title = trim($input->string('title'));
        $description = trim($input->string('description'));
        $url = trim($input->string('url'));

        $site = $this->siteName($url);
        $crumbs = $this->crumbs($url);

        $frames = [];
        $rows = [];
        $anyCut = false;

        foreach (self::SURFACES as $surface) {
            $titleBudget = $surface['column'] * $surface['title_lines'] * self::ELLIPSIS_ALLOWANCE;
            $descriptionBudget = $surface['column'] * $surface['description_lines'] * self::ELLIPSIS_ALLOWANCE;

            $fittedTitle = TextWidth::fit($title, $titleBudget, $surface['title_size']);
            $fittedDescription = $description === ''
                ? ['visible' => '', 'hidden' => '', 'width' => 0.0, 'truncated' => false]
                : TextWidth::fit($description, $descriptionBudget, $surface['description_size']);

            $cut = $fittedTitle['truncated'] || $fittedDescription['truncated'];
            $anyCut = $anyCut || $cut;

            $frames[] = PreviewFrame::make('google', $surface['label'], 'serp')
                ->device($surface['device'])
                ->search($site, $crumbs)
                ->headline($fittedTitle['visible'], $fittedTitle['hidden'])
                ->bodyParts($fittedDescription['visible'], $fittedDescription['hidden'])
                ->status(
                    $fittedTitle['truncated'] ? 'warn' : 'ok',
                    $fittedTitle['truncated'] ? 'Title is cut' : 'Title fits',
                )
                ->detail('Title', round($fittedTitle['width']).' / '.round($titleBudget).' px')
                ->detail('Description', round($fittedDescription['width']).' / '
                    .round($descriptionBudget).' px')
                ->note($surface['note'])
                ->toArray();

            $rows[] = [
                'surface' => $surface['label'],
                'element' => 'Title',
                'width' => round($fittedTitle['width']).' px',
                'budget' => round($titleBudget).' px',
                'verdict' => $fittedTitle['truncated']
                    ? 'Cut — '.mb_strlen($fittedTitle['hidden']).' characters hidden'
                    : 'Fits, with '.round($titleBudget - $fittedTitle['width']).' px to spare',
            ];

            $rows[] = [
                'surface' => $surface['label'],
                'element' => 'Description',
                'width' => round($fittedDescription['width']).' px',
                'budget' => round($descriptionBudget).' px',
                'verdict' => $description === ''
                    ? 'Empty — Google will write one from the page'
                    : ($fittedDescription['truncated']
                        ? 'Cut — '.mb_strlen($fittedDescription['hidden']).' characters hidden'
                        : 'Fits'),
            ];
        }

        return ToolResult::socialPreview(
            $frames,
            summary: $anyCut
                ? 'Something is cut on at least one surface. Move the words that earn the click in '
                    .'front of the fold — the phone result is the one most of your traffic sees.'
                : 'Both surfaces render your snippet in full. The desktop title has '
                    .round(600 * self::ELLIPSIS_ALLOWANCE - TextWidth::px($title, 20.0))
                    .' px of headroom left.',
            table: [
                'columns' => [
                    ['key' => 'surface', 'label' => 'Surface'],
                    ['key' => 'element', 'label' => 'Element'],
                    ['key' => 'width', 'label' => 'Drawn width', 'align' => 'right'],
                    ['key' => 'budget', 'label' => 'Budget', 'align' => 'right'],
                    ['key' => 'verdict', 'label' => 'Verdict'],
                ],
                'rows' => $rows,
            ],
        )->withMeta([
            'title_px_desktop' => round(TextWidth::px($title, 20.0)),
            'description_px_desktop' => round(TextWidth::px($description, 14.0)),
        ])->withWarnings([
            'Google rewrites the title on a large share of results, and replaces the meta '
            .'description whenever a passage on the page answers the query better. This is what you '
            .'submitted, which is the half you control — not a promise about what gets drawn.',
            'Widths are measured with Arial metrics at the sizes Google renders. Your visitor’s '
            .'font, zoom level and window width all move the fold a little; treat the last few '
            .'pixels of the budget as a margin rather than a line.',
        ]);
    }

    /** The site name Google draws above the title, taken from the host. */
    private function siteName(string $url): string
    {
        $host = $url === '' ? null : SocialUrl::host($url);

        if ($host === null) {
            return 'Your site';
        }

        $label = explode('.', $host)[0];

        return ucfirst($label);
    }

    /**
     * The crumb trail Google draws in place of the raw URL.
     *
     * Google has not shown a full URL in a result for years: it shows the host, then
     * the path segments separated by rules. Drawing the raw URL would make the
     * preview wrong in the one place it is easiest to be right.
     */
    private function crumbs(string $url): string
    {
        if ($url === '') {
            return 'example.com';
        }

        $host = SocialUrl::host($url) ?? 'example.com';
        $path = trim((string) (parse_url(SocialUrl::normalise($url), PHP_URL_PATH) ?: ''), '/');

        if ($path === '') {
            return $host;
        }

        $segments = array_slice(array_filter(explode('/', $path)), 0, 3);

        return $host.' › '.implode(' › ', $segments);
    }
}
