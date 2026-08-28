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

/**
 * Where LinkedIn's "…see more" lands, and what survives above it.
 *
 * Everything below the fold is invisible until someone taps, and the tap is the
 * signal LinkedIn rewards. So the only text that matters for reach is the first
 * ~140 characters on mobile — this draws the post as the feed draws it, with the
 * fold in the same place, so you can see what the tap decision is being made on.
 */
final class LinkedInPostPreviewRunner implements Cacheable, ToolRunner
{
    private const LIMIT = 3000;

    /** Characters visible before "…see more", by surface. */
    private const FOLDS = ['Mobile app' => 140, 'Desktop feed' => 210];

    public static function key(): string
    {
        return 'linkedin.post-preview';
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
            'required' => ['text'],
            'additionalProperties' => false,
            'properties' => [
                'text' => [
                    'type' => 'string',
                    'title' => 'Your post',
                    'description' => 'Paste the full post, line breaks and all.',
                    'minLength' => 1,
                    'maxLength' => 5000,
                ],
                'author' => [
                    'type' => 'string',
                    'title' => 'Your name',
                    'description' => 'Shown above the post in the preview.',
                    'maxLength' => 60,
                    'default' => 'Your Name',
                ],
                'headline' => [
                    'type' => 'string',
                    'title' => 'Your headline',
                    'description' => 'The line under your name — LinkedIn shows about 60 characters of it.',
                    'maxLength' => 220,
                    'default' => '',
                ],
                'attachment' => [
                    'type' => 'string',
                    'title' => 'What is attached?',
                    'enum' => ['none', 'image', 'document', 'link'],
                    'default' => 'none',
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $text = $input->string('text');
        $author = trim($input->string('author')) ?: 'Your Name';
        $headline = trim($input->string('headline'));
        $attachment = $input->string('attachment', 'none');
        $count = PostLength::graphemeCount($text);

        $frames = [];

        foreach (self::FOLDS as $surface => $fold) {
            $hidden = max(0, $count - $fold);

            $frame = PreviewFrame::make('linkedin', $surface)
                ->author($author, $this->headlineLine($headline).' · 1st')
                ->body($text, $fold, '…see more')
                ->actions('Like', 'Comment', 'Repost', 'Send')
                ->status(
                    $hidden === 0 ? 'ok' : ($hidden > $fold * 2 ? 'danger' : 'warn'),
                    $hidden === 0
                        ? 'Fully visible'
                        : number_format($hidden).' characters behind “see more”',
                )
                ->detail('Fold', $fold.' characters');

            match ($attachment) {
                'image' => $frame->media('1.91:1', 'Your image'),
                'document' => $frame->media('4:3', 'Page 1 of your document'),
                'link' => $frame->link('yoursite.com', 'Your link card headline', style: 'large'),
                default => null,
            };

            $frames[] = $frame->toArray();
        }

        $warnings = [];

        if ($count > self::LIMIT) {
            $warnings[] = 'Over the '.number_format(self::LIMIT).'-character limit by '
                .number_format($count - self::LIMIT).'. LinkedIn will reject the post.';
        }

        $firstLine = trim(strtok($text, "\n") ?: '');

        if (mb_strlen($firstLine) > 140) {
            $warnings[] = 'Your first line runs past the mobile fold. Break it earlier — the first line '
                .'is the only thing most people read before deciding to scroll on.';
        }

        if ($attachment === 'link') {
            $warnings[] = 'Posts with an outbound link card reach fewer people. Consider putting the '
                .'link in the first comment and saying so in the post.';
        }

        return ToolResult::socialPreview($frames, summary: number_format($count).' of '
            .number_format(self::LIMIT).' characters. '
            .($count > 140
                ? number_format($count - 140).' characters sit behind “see more” on mobile.'
                : 'The whole post is visible without tapping.'))
            ->withWarnings($warnings)
            ->withMeta(['characters' => $count, 'limit' => self::LIMIT]);
    }

    /** LinkedIn truncates the headline under your name at roughly 60 characters. */
    private function headlineLine(string $headline): string
    {
        if ($headline === '') {
            return 'Your headline';
        }

        return mb_strlen($headline) > 60 ? mb_substr($headline, 0, 60).'…' : $headline;
    }
}
