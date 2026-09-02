<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Support\Social\PostLength;
use App\Support\Social\PreviewFrame;
use App\Support\Text\TextWidth;

/**
 * A subject line drawn as an inbox row, in the four clients that account for most
 * of the opens — with the preheader beside it, which is the half nobody previews.
 *
 * An inbox is a list of fixed-width rows, so a subject is clamped by the width of
 * the column it lands in and not by a character count. The same subject survives on
 * an iPhone and is cut on a Gmail desktop row, because Gmail spends the width on
 * the sender and the date first and gives the subject what is left.
 *
 * The preheader is drawn in the same row for a reason: in every client here it
 * shares the subject's line or the one under it, so a subject that fits and a
 * preheader that starts with "View this email in your browser" have together
 * wasted the whole preview. Seeing them in one row is the point.
 */
final class EmailSubjectPreviewRunner implements Cacheable, ToolRunner
{
    /**
     * The clients, as geometry.
     *
     * `subject_px` is the width the subject is drawn in, measured on a default
     * window at the default density; `preheader_px` is what the preview text gets
     * after it. `layout` tells the renderer whether the client stacks the preheader
     * under the subject (a phone row) or runs it on the same line (a desktop list).
     *
     * @var array<string, array{label: string, device: int, layout: string, subject_size: float, subject_px: float, preheader_size: float, preheader_px: float, note: string}>
     */
    private const CLIENTS = [
        'gmail_desktop' => [
            'label' => 'Gmail — desktop',
            'device' => 720,
            'layout' => 'inline',
            'subject_size' => 14.0,
            'subject_px' => 340.0,
            'preheader_size' => 14.0,
            'preheader_px' => 300.0,
            'note' => 'Gmail pays the sender column and the date first, then runs the subject and the '
                .'preheader together on one line. It is the tightest surface for a subject and the '
                .'most generous for a preheader.',
        ],
        'gmail_mobile' => [
            'label' => 'Gmail — Android & iOS',
            'device' => 375,
            'layout' => 'stacked',
            'subject_size' => 15.0,
            'subject_px' => 300.0,
            'preheader_size' => 14.0,
            'preheader_px' => 300.0,
            'note' => 'Three stacked lines — sender, subject, preheader — each clamped to one line of '
                .'the phone’s width.',
        ],
        'apple_mail' => [
            'label' => 'Apple Mail — iPhone',
            'device' => 375,
            'layout' => 'stacked',
            'subject_size' => 15.0,
            'subject_px' => 290.0,
            'preheader_size' => 14.0,
            'preheader_px' => 580.0,
            'note' => 'The most generous preview on any phone: Apple Mail defaults to two lines of '
                .'preheader under the subject, so the second sentence is doing real work here.',
        ],
        'outlook_desktop' => [
            'label' => 'Outlook — desktop list',
            'device' => 420,
            'layout' => 'stacked',
            'subject_size' => 14.0,
            'subject_px' => 250.0,
            'preheader_size' => 13.0,
            'preheader_px' => 250.0,
            'note' => 'A narrow reading-pane list. If a subject survives Outlook it survives '
                .'everywhere.',
        ],
    ];

    public static function key(): string
    {
        return 'content.email-subject-preview';
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
            'required' => ['subject'],
            'additionalProperties' => false,
            'properties' => [
                'subject' => [
                    'type' => 'string',
                    'x-control' => 'text',
                    'title' => 'Subject line',
                    'minLength' => 1,
                    'maxLength' => 300,
                    'examples' => ['The three metrics I actually watch'],
                ],
                'preheader' => [
                    'type' => 'string',
                    'x-control' => 'text',
                    'title' => 'Preheader (preview text)',
                    'description' => 'The first text in the email body, which every client draws '
                        .'beside or under the subject.',
                    'maxLength' => 300,
                    'default' => '',
                ],
                'sender' => [
                    'type' => 'string',
                    'x-control' => 'text',
                    'title' => 'From name',
                    'maxLength' => 80,
                    'default' => 'MetaCreator',
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $subject = trim($input->string('subject'));
        $preheader = trim($input->string('preheader'));
        $sender = trim($input->string('sender', 'MetaCreator')) ?: 'MetaCreator';

        $frames = [];
        $rows = [];
        $cutIn = [];

        foreach (self::CLIENTS as $client) {
            $fittedSubject = TextWidth::fit($subject, $client['subject_px'], $client['subject_size']);
            $fittedPreheader = $preheader === ''
                ? ['visible' => '', 'hidden' => '', 'width' => 0.0, 'truncated' => false]
                : TextWidth::fit($preheader, $client['preheader_px'], $client['preheader_size']);

            if ($fittedSubject['truncated']) {
                $cutIn[] = $client['label'];
            }

            $frames[] = PreviewFrame::make('email', $client['label'], 'inbox')
                ->device($client['device'])
                ->variant($client['layout'])
                ->author($sender, $client['layout'] === 'inline' ? 'now' : null)
                ->headline($fittedSubject['visible'], $fittedSubject['hidden'])
                ->bodyParts($fittedPreheader['visible'], $fittedPreheader['hidden'], ' — ')
                ->status(
                    $fittedSubject['truncated'] ? 'warn' : 'ok',
                    $fittedSubject['truncated'] ? 'Subject is cut' : 'Subject fits',
                )
                ->detail('Subject', round($fittedSubject['width']).' / '
                    .round($client['subject_px']).' px')
                ->detail('Preheader', round($fittedPreheader['width']).' / '
                    .round($client['preheader_px']).' px')
                ->note($client['note'])
                ->toArray();

            $rows[] = [
                'client' => $client['label'],
                'subject' => round($fittedSubject['width']).' / '.round($client['subject_px']).' px',
                'subject_verdict' => $fittedSubject['truncated']
                    ? 'Cut after “'.$this->tail($fittedSubject['visible']).'”'
                    : 'Fits',
                'preheader' => $preheader === ''
                    ? 'Not set'
                    : round($fittedPreheader['width']).' / '.round($client['preheader_px']).' px',
            ];
        }

        $characters = PostLength::graphemeCount($subject);
        $words = count(array_filter(preg_split('/\s+/u', $subject) ?: []));

        return ToolResult::socialPreview(
            $frames,
            summary: $cutIn === []
                ? "“{$subject}” survives all four clients intact."
                : 'Cut in '.count($cutIn).' of 4 clients — '.implode(', ', $cutIn)
                    .'. Front-load the promise: the first ~30 characters are the only part every '
                    .'inbox agrees to show.',
            table: [
                'columns' => [
                    ['key' => 'client', 'label' => 'Client'],
                    ['key' => 'subject', 'label' => 'Subject width', 'align' => 'right'],
                    ['key' => 'subject_verdict', 'label' => 'Verdict'],
                    ['key' => 'preheader', 'label' => 'Preheader width', 'align' => 'right'],
                ],
                'rows' => $rows,
            ],
        )->withMeta([
            'characters' => $characters,
            'words' => $words,
            'preheader_set' => $preheader !== '',
        ])->withWarnings(array_values(array_filter([
            $preheader === ''
                ? 'No preheader. With the field empty, every client here fills the space with the '
                    .'first text in your email — usually "View this email in your browser", which is '
                    .'a quarter of the inbox real estate you get, spent on nothing.'
                : null,
            'Column widths are measured on each client’s default window and density. A maximised '
            .'desktop window gives the subject more room; a split reading pane gives it less.',
            'Emoji are counted at full width here because that is how they are drawn. Some corporate '
            .'filters strip them from the subject entirely, so never let one carry meaning your '
            .'words do not repeat.',
        ])));
    }

    /** The last few words of the visible part, for the "cut after" verdict. */
    private function tail(string $visible): string
    {
        $words = preg_split('/\s+/u', trim($visible)) ?: [];

        return implode(' ', array_slice($words, -3));
    }
}
