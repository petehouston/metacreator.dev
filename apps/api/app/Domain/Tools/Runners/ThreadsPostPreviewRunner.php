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
 * A Threads post as the feed renders it, and whether it needs to become a chain.
 *
 * Threads caps a single post at 500 characters — comfortably longer than X, short
 * enough that a post written for LinkedIn always overflows. Long posts are also
 * collapsed behind "more" in the feed, so the useful question is not only "does it
 * fit" but "what survives the collapse".
 */
final class ThreadsPostPreviewRunner implements Cacheable, ToolRunner
{
    private const LIMIT = 500;

    /**
     * Threads collapses a long post in the feed. The cut is driven by height rather
     * than by an exact character count, so this is a conservative approximation:
     * whichever comes first, roughly four lines or ~280 characters.
     */
    private const FOLD = 280;

    private const FOLD_LINES = 4;

    public static function key(): string
    {
        return 'threads.post-preview';
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
                    'description' => 'Line breaks count as characters, exactly as they do in the app.',
                    'minLength' => 1,
                    'maxLength' => 2000,
                ],
                'handle' => [
                    'type' => 'string',
                    'title' => 'Username',
                    'maxLength' => 30,
                    'default' => '',
                ],
                'attachment' => [
                    'type' => 'string',
                    'title' => 'What is attached?',
                    'enum' => ['none', 'image', 'link'],
                    'default' => 'none',
                ],
                'link_url' => [
                    'type' => 'string',
                    'title' => 'Link URL',
                    'description' => 'Only used when the post attaches a link.',
                    'maxLength' => 300,
                    'default' => '',
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $text = $input->string('text');
        $handle = ltrim(trim($input->string('handle')), '@') ?: 'yourhandle';
        $attachment = $input->string('attachment', 'none');

        $count = PostLength::graphemeCount($text);
        $lines = substr_count($text, "\n") + 1;
        $fold = $this->fold($text);
        $hidden = max(0, $count - $fold);

        $feed = PreviewFrame::make('threads', 'Feed', 'post')
            ->author($handle, 'now', handle: '@'.$handle)
            ->body($text, $fold, '… more')
            ->actions('Like', 'Reply', 'Repost', 'Share')
            ->status(
                $count > self::LIMIT ? 'danger' : ($hidden > 0 ? 'warn' : 'ok'),
                $count > self::LIMIT
                    ? number_format($count - self::LIMIT).' characters over the 500 limit'
                    : ($hidden > 0 ? $hidden.' characters behind “more”' : 'Fully visible'),
            )
            ->detail('Length', $count.'/'.self::LIMIT.' characters')
            ->detail('Lines', (string) $lines)
            ->note('Threads collapses long posts in the feed. The cut follows height, so treat '
                .'the first four lines as the part everyone reads.');

        match ($attachment) {
            'image' => $feed->media('1:1', 'Your image'),
            'link' => $feed->link(LinkDisplay::domain($input->string('link_url')), 'Your link card headline', style: 'large'),
            default => null,
        };

        $frames = [$feed->toArray()];

        // Over the limit the post cannot be published as one, so show the split the
        // app forces on you rather than a single frame that could never exist.
        if ($count > self::LIMIT) {
            foreach ($this->chain($text) as $index => $part) {
                $frames[] = PreviewFrame::make('threads', 'Chain post '.($index + 1), 'post')
                    ->author($handle, 'now', handle: '@'.$handle)
                    ->body($part)
                    ->status('ok', PostLength::graphemeCount($part).'/'.self::LIMIT.' characters')
                    ->toArray();
            }
        }

        $warnings = [];

        if ($count > self::LIMIT) {
            $warnings[] = 'Over the 500-character limit by '.number_format($count - self::LIMIT)
                .'. Threads will not post it as one — the split above is one way to break it up.';
        }

        if ($lines > self::FOLD_LINES && $count <= self::LIMIT) {
            $warnings[] = 'More than four lines, so the feed will collapse the post. '
                .'Put the point in the first line rather than building to it.';
        }

        return ToolResult::socialPreview($frames, summary: $count.'/'.self::LIMIT.' characters. '
            .($count > self::LIMIT
                ? 'This needs to be a chain of '.count($this->chain($text)).' posts.'
                : ($hidden > 0
                    ? $hidden.' characters sit behind “more” in the feed.'
                    : 'The whole post is visible in the feed.')))
            ->withWarnings($warnings)
            ->withMeta(['characters' => $count, 'limit' => self::LIMIT, 'fold' => $fold]);
    }

    /** Whichever comes first: four lines, or the character fold. */
    private function fold(string $text): int
    {
        $lines = explode("\n", $text);

        if (count($lines) <= self::FOLD_LINES) {
            return self::FOLD;
        }

        $upToFourLines = PostLength::graphemeCount(implode("\n", array_slice($lines, 0, self::FOLD_LINES)));

        return min(self::FOLD, $upToFourLines);
    }

    /**
     * Break an over-long post on sentence boundaries.
     *
     * @return list<string>
     */
    private function chain(string $text): array
    {
        $sentences = preg_split('/(?<=[.!?…])\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [$text];

        $parts = [];
        $current = '';

        foreach ($sentences as $sentence) {
            $candidate = $current === '' ? $sentence : $current.' '.$sentence;

            if (PostLength::graphemeCount($candidate) <= self::LIMIT) {
                $current = $candidate;

                continue;
            }

            if ($current !== '') {
                $parts[] = $current;
            }

            // A single sentence longer than the limit still has to be cut somewhere.
            while (PostLength::graphemeCount($sentence) > self::LIMIT) {
                $parts[] = mb_substr($sentence, 0, self::LIMIT);
                $sentence = mb_substr($sentence, self::LIMIT);
            }

            $current = $sentence;
        }

        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts;
    }
}
