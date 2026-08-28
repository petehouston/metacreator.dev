<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Support\Social\LinkDisplay;
use App\Support\Social\PostLength;
use App\Support\Social\PreviewFrame;

/**
 * A Pin in the two places it is actually seen: the home feed tile and the closeup.
 *
 * Pinterest is the only major platform where the same Pin renders twice with
 * different rules. In the masonry feed the tile is cropped to 2:3 and only the first
 * line or two of the title survives; the description does not appear at all until
 * someone opens the Pin. Writing for the closeup and hoping is the standard mistake.
 */
final class PinterestPinPreviewRunner implements Cacheable, ToolRunner
{
    private const TITLE_LIMIT = 100;

    private const DESCRIPTION_LIMIT = 500;

    /** Roughly what survives on a feed tile before the title is clipped. */
    private const TITLE_FOLD = 40;

    /** The closeup shows a short lead-in, then "more". */
    private const DESCRIPTION_FOLD = 50;

    /**
     * Aspect ratios, and whether Pinterest crops them in the feed.
     *
     * @var array<string, array{label: string, ratio: string, note: string}>
     */
    private const ASPECTS = [
        '2:3' => ['label' => '2:3 — 1000 × 1500', 'ratio' => '2:3',
            'note' => 'The ratio Pinterest recommends. Nothing is cropped anywhere.'],
        '1:1' => ['label' => '1:1 — 1000 × 1000', 'ratio' => '1:1',
            'note' => 'Safe, but a square tile takes less vertical space in the feed, so it is scrolled past faster.'],
        '9:16' => ['label' => '9:16 — 1080 × 1920', 'ratio' => '9:16',
            'note' => 'Taller than 2:3, so the feed tile is cropped. Keep text away from the top and bottom.'],
        '1.91:1' => ['label' => '1.91:1 — landscape', 'ratio' => '1.91:1',
            'note' => 'A repurposed landscape image. It is legal, and it performs worst of the four.'],
    ];

    public static function key(): string
    {
        return 'pinterest.pin-preview';
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
            'required' => ['title'],
            'additionalProperties' => false,
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'title' => 'Pin title',
                    'description' => 'Pinterest allows 100 characters — the feed shows far fewer.',
                    'minLength' => 1,
                    'maxLength' => 200,
                ],
                'description' => [
                    'type' => 'string',
                    'title' => 'Pin description',
                    'description' => 'Only read on the closeup, but indexed everywhere.',
                    'maxLength' => 800,
                    'default' => '',
                ],
                'aspect' => [
                    'type' => 'string',
                    'title' => 'Image ratio',
                    'enum' => array_keys(self::ASPECTS),
                    'default' => '2:3',
                ],
                'link' => [
                    'type' => 'string',
                    'title' => 'Destination link (optional)',
                    'maxLength' => 300,
                    'default' => '',
                ],
                'board' => [
                    'type' => 'string',
                    'title' => 'Board name (optional)',
                    'maxLength' => 60,
                    'default' => '',
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $title = $input->string('title');
        $description = $input->string('description');
        $aspect = self::ASPECTS[$input->string('aspect', '2:3')] ?? self::ASPECTS['2:3'];
        $link = trim($input->string('link'));
        $board = trim($input->string('board'));

        $titleCount = PostLength::graphemeCount($title);
        $descriptionCount = PostLength::graphemeCount($description);
        $domain = $link === '' ? null : LinkDisplay::domain($link);

        $tile = PreviewFrame::make('pinterest', 'Home feed tile', 'pin')
            ->media($aspect['ratio'], 'Your Pin image')
            ->body($title, self::TITLE_FOLD, '…')
            ->status(
                $titleCount > self::TITLE_LIMIT ? 'danger' : ($titleCount > self::TITLE_FOLD ? 'warn' : 'ok'),
                $titleCount > self::TITLE_LIMIT
                    ? ($titleCount - self::TITLE_LIMIT).' characters over the title limit'
                    : ($titleCount > self::TITLE_FOLD
                        ? 'Title clipped on the tile'
                        : 'Title fits on the tile'),
            )
            ->detail('Title', $titleCount.'/'.self::TITLE_LIMIT.' characters')
            ->detail('Ratio', $aspect['label'])
            ->note('The feed never shows the description — the image and the first '
                .self::TITLE_FOLD.' or so characters of the title do all the work.');

        if ($domain !== null) {
            $tile->link($domain);
        }

        $closeup = PreviewFrame::make('pinterest', 'Pin closeup', 'pin')
            ->media($aspect['ratio'], 'Your Pin image')
            ->author($board !== '' ? $board : 'Your board', 'Saved to board')
            ->body($description !== '' ? $description : '(no description)', self::DESCRIPTION_FOLD, '… more')
            ->status(
                $descriptionCount > self::DESCRIPTION_LIMIT ? 'danger' : ($description === '' ? 'warn' : 'ok'),
                $descriptionCount > self::DESCRIPTION_LIMIT
                    ? ($descriptionCount - self::DESCRIPTION_LIMIT).' characters over the limit'
                    : ($description === ''
                        ? 'No description — nothing for search to read'
                        : $descriptionCount.'/'.self::DESCRIPTION_LIMIT.' characters'),
            )
            ->detail('Description', $descriptionCount.'/'.self::DESCRIPTION_LIMIT.' characters')
            ->note($aspect['note']);

        if ($domain !== null) {
            $closeup->link($domain, $title);
        }

        $warnings = [];

        if ($titleCount > self::TITLE_LIMIT) {
            $warnings[] = 'The title is '.$titleCount.' characters. Pinterest cuts it at '
                .self::TITLE_LIMIT.'.';
        }

        if ($descriptionCount > self::DESCRIPTION_LIMIT) {
            $warnings[] = 'The description is '.$descriptionCount.' characters. Pinterest cuts it at '
                .self::DESCRIPTION_LIMIT.'.';
        }

        if ($description === '') {
            $warnings[] = 'No description. Pinterest is a search engine before it is a feed, and the '
                .'description is most of what it has to index.';
        }

        if ($aspect['ratio'] === '9:16' || $aspect['ratio'] === '1.91:1') {
            $warnings[] = 'This ratio is cropped or shrunk in the feed. 2:3 (1000 × 1500) is the only '
                .'ratio that renders in full everywhere.';
        }

        return ToolResult::socialPreview(
            [$tile->toArray(), $closeup->toArray()],
            summary: 'Title '.$titleCount.'/'.self::TITLE_LIMIT.', description '
                .$descriptionCount.'/'.self::DESCRIPTION_LIMIT.'. '
                .($titleCount > self::TITLE_FOLD
                    ? 'Only the first '.self::TITLE_FOLD.' characters of the title survive the feed tile.'
                    : 'The whole title fits on the feed tile.'),
        )->withWarnings($warnings)->withMeta([
            'title_characters' => $titleCount,
            'description_characters' => $descriptionCount,
            'aspect' => $aspect['ratio'],
        ]);
    }
}
