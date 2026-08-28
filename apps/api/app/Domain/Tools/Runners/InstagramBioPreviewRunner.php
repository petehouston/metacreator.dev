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
 * What an Instagram bio really looks like in the app.
 *
 * The bio field is 150 characters, but only the first ~80 show before "more", and
 * line breaks are counted as characters. Both facts routinely surprise people whose
 * carefully written bio arrives clipped — so the bio is drawn inside a profile
 * header, clipped exactly where the app clips it.
 */
final class InstagramBioPreviewRunner implements Cacheable, ToolRunner
{
    private const BIO_LIMIT = 150;

    private const NAME_LIMIT = 30;

    private const FOLD = 80;

    public static function key(): string
    {
        return 'instagram.bio-preview';
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
                    'description' => 'The bold line. It is searchable — put your keyword here, not just your name.',
                    'maxLength' => 60,
                    'default' => '',
                ],
                'handle' => [
                    'type' => 'string',
                    'title' => 'Username',
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
                    'title' => 'Link in bio (optional)',
                    'maxLength' => 300,
                    'default' => '',
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $name = $input->string('name');
        $bio = $input->string('bio');
        $link = trim($input->string('link'));
        $handle = trim($input->string('handle'));

        $bioCount = PostLength::graphemeCount($bio);
        $nameCount = PostLength::graphemeCount($name);
        $lines = $bio === '' ? 0 : substr_count($bio, "\n") + 1;
        $hidden = max(0, $bioCount - self::FOLD);

        $frame = PreviewFrame::make('instagram', 'Profile header', 'profile')
            ->author(
                $nameCount > self::NAME_LIMIT ? mb_substr($name, 0, self::NAME_LIMIT) : ($name ?: 'Display name'),
                handle: '@'.ltrim($handle !== '' ? $handle : 'yourhandle', '@'),
            )
            ->body($bio, self::FOLD, '… more')
            ->actions('Follow', 'Message')
            ->status(
                $bioCount > self::BIO_LIMIT ? 'danger' : ($hidden > 0 ? 'warn' : 'ok'),
                $bioCount > self::BIO_LIMIT
                    ? number_format($bioCount - self::BIO_LIMIT).' characters over the limit'
                    : ($hidden > 0 ? $hidden.' characters behind “more”' : 'Fully visible'),
            )
            ->detail('Bio', $bioCount.'/'.self::BIO_LIMIT.' characters')
            ->detail('Display name', $nameCount.'/'.self::NAME_LIMIT.' characters')
            ->detail('Lines', (string) $lines)
            ->note('Instagram shows about '.self::FOLD.' characters before “… more”. '
                .'Line breaks count against the 150-character limit.');

        if ($link !== '') {
            // Instagram shows the host and truncates the path, so long tracked links
            // read as noise rather than as a destination.
            $frame->link(LinkDisplay::domain($link));
        }

        $warnings = [];

        if ($bioCount > self::BIO_LIMIT) {
            $warnings[] = 'Over the 150-character bio limit by '.($bioCount - self::BIO_LIMIT)
                .'. Instagram will not save it.';
        }

        if ($nameCount > self::NAME_LIMIT) {
            $warnings[] = 'Display names are capped at 30 characters and yours is '.$nameCount.'.';
        }

        if ($lines > 5) {
            $warnings[] = 'More than five lines rarely renders the way you expect across app versions.';
        }

        return ToolResult::socialPreview([$frame->toArray()], summary: "{$bioCount}/".self::BIO_LIMIT
            .' characters across '.$lines.' line(s). '
            .($bioCount > self::FOLD
                ? 'The last '.($bioCount - self::FOLD).' characters are hidden behind “more”.'
                : 'The whole bio is visible at a glance.'))
            ->withWarnings($warnings)
            ->withMeta(['characters' => $bioCount, 'limit' => self::BIO_LIMIT, 'fold' => self::FOLD]);
    }
}
