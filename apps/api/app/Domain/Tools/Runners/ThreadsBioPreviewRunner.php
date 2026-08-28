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
 * A Threads profile as it actually reads, before you commit to it.
 *
 * The bio is 150 characters — the same budget as Instagram, but a different job:
 * Threads profiles are opened from a reply in a conversation, so the bio is read by
 * people who have seen exactly one of your posts and are deciding on the spot.
 */
final class ThreadsBioPreviewRunner implements Cacheable, ToolRunner
{
    private const BIO_LIMIT = 150;

    private const NAME_LIMIT = 30;

    /** Threads renders the bio in full on the profile, but keeps it to four lines. */
    private const COMFORTABLE_LINES = 4;

    public static function key(): string
    {
        return 'threads.bio-preview';
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
            'required' => ['bio'],
            'additionalProperties' => false,
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'title' => 'Display name',
                    'description' => 'The bold line above the username.',
                    'maxLength' => 60,
                    'default' => '',
                ],
                'handle' => [
                    'type' => 'string',
                    'title' => 'Username',
                    'description' => 'Shared with your Instagram account.',
                    'maxLength' => 30,
                    'default' => '',
                ],
                'bio' => [
                    'type' => 'string',
                    'title' => 'Bio',
                    'description' => 'Line breaks count as characters.',
                    'minLength' => 1,
                    'maxLength' => 300,
                ],
                'link' => [
                    'type' => 'string',
                    'title' => 'Link (optional)',
                    'maxLength' => 300,
                    'default' => '',
                ],
                'followers' => [
                    'type' => 'integer',
                    'title' => 'Followers',
                    'description' => 'Only used to make the preview look like your profile.',
                    'minimum' => 0,
                    'maximum' => 1_000_000_000,
                    'default' => 0,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $name = trim($input->string('name'));
        $handle = ltrim(trim($input->string('handle')), '@') ?: 'yourhandle';
        $bio = $input->string('bio');
        $link = trim($input->string('link'));
        $followers = $input->int('followers', 0);

        $bioCount = PostLength::graphemeCount($bio);
        $nameCount = PostLength::graphemeCount($name);
        $lines = $bio === '' ? 0 : substr_count($bio, "\n") + 1;

        $frame = PreviewFrame::make('threads', 'Profile header', 'profile')
            ->author(
                $nameCount > self::NAME_LIMIT ? mb_substr($name, 0, self::NAME_LIMIT) : ($name ?: 'Display name'),
                meta: number_format($followers).' followers',
                handle: '@'.$handle,
            )
            ->body($bio)
            ->actions('Follow', 'Mention')
            ->status(
                $bioCount > self::BIO_LIMIT ? 'danger' : ($lines > self::COMFORTABLE_LINES ? 'warn' : 'ok'),
                $bioCount > self::BIO_LIMIT
                    ? ($bioCount - self::BIO_LIMIT).' characters over the limit'
                    : $bioCount.'/'.self::BIO_LIMIT.' characters',
            )
            ->detail('Bio', $bioCount.'/'.self::BIO_LIMIT.' characters')
            ->detail('Display name', $nameCount.'/'.self::NAME_LIMIT.' characters')
            ->detail('Lines', (string) $lines)
            ->note('Most people reach this profile from a single reply. The first line has to '
                .'answer “who is this and why should I care” on its own.');

        if ($link !== '') {
            $frame->link(LinkDisplay::domain($link));
        }

        $warnings = [];

        if ($bioCount > self::BIO_LIMIT) {
            $warnings[] = 'Over the 150-character bio limit by '.($bioCount - self::BIO_LIMIT)
                .'. Threads will not save it.';
        }

        if ($nameCount > self::NAME_LIMIT) {
            $warnings[] = 'Display names are capped at 30 characters and yours is '.$nameCount.'.';
        }

        if ($lines > self::COMFORTABLE_LINES) {
            $warnings[] = 'More than four lines makes the profile header scroll on a phone, '
                .'pushing your posts below the fold.';
        }

        if (preg_match('/#\w/u', $bio) === 1) {
            $warnings[] = 'Hashtags in a Threads bio are not searchable and are not links. '
                .'They cost characters and return nothing.';
        }

        return ToolResult::socialPreview([$frame->toArray()], summary: $bioCount.'/'.self::BIO_LIMIT
            .' characters across '.$lines.' line(s).'
            .($bioCount > self::BIO_LIMIT ? ' It will not save as written.' : ''))
            ->withWarnings($warnings)
            ->withMeta(['characters' => $bioCount, 'limit' => self::BIO_LIMIT]);
    }
}
