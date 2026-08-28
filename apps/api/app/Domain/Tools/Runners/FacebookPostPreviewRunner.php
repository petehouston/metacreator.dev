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
 * How a Facebook post reads in the feed before anyone expands it.
 *
 * Facebook's fold moves depending on whether the post carries an image or link,
 * and it is far earlier than the generous 63,206-character limit suggests. The
 * result is drawn as the feed draws it, because "477 characters" means nothing next
 * to seeing your own call to action sitting below "See more".
 */
final class FacebookPostPreviewRunner implements Cacheable, ToolRunner
{
    private const LIMIT = 63206;

    /** Characters shown before "See more", by post shape. */
    private const FOLDS = [
        'none' => ['Desktop feed' => 477, 'Mobile app' => 250],
        'photo' => ['Desktop feed' => 250, 'Mobile app' => 250],
        'link' => ['Desktop feed' => 200, 'Mobile app' => 200],
    ];

    public static function key(): string
    {
        return 'facebook.post-preview';
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
                    'minLength' => 1,
                    'maxLength' => self::LIMIT,
                ],
                'attachment' => [
                    'type' => 'string',
                    'title' => 'What is attached?',
                    'enum' => ['none', 'photo', 'link'],
                    'default' => 'none',
                ],
                'page_name' => [
                    'type' => 'string',
                    'title' => 'Page name',
                    'description' => 'Shown above the post in the preview.',
                    'maxLength' => 60,
                    'default' => 'Your Page',
                ],
                'link_url' => [
                    'type' => 'string',
                    'title' => 'Link URL',
                    'description' => 'Only used when the post attaches a link.',
                    'maxLength' => 300,
                    'default' => '',
                ],
                'link_title' => [
                    'type' => 'string',
                    'title' => 'Link card headline',
                    'maxLength' => 200,
                    'default' => '',
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $text = $input->string('text');
        $attachment = $input->string('attachment', 'none');
        $page = trim($input->string('page_name')) ?: 'Your Page';
        $count = PostLength::graphemeCount($text);

        $frames = [];

        foreach (self::FOLDS[$attachment] as $surface => $fold) {
            $hidden = max(0, $count - $fold);

            $frame = PreviewFrame::make('facebook', $surface)
                ->author($page, 'Just now · 🌐')
                ->body($text, $fold, '… See more')
                ->actions('Like', 'Comment', 'Share')
                ->status(
                    $hidden === 0 ? 'ok' : ($hidden > $fold ? 'danger' : 'warn'),
                    $hidden === 0
                        ? 'Fully visible'
                        : number_format($hidden).' characters behind “See more”',
                )
                ->detail('Fold', number_format($fold).' characters');

            if ($attachment === 'photo') {
                $frame->media('1.91:1', 'Your photo');
            }

            if ($attachment === 'link') {
                $frame->link(
                    domain: mb_strtoupper(LinkDisplay::domain($input->string('link_url'))),
                    title: trim($input->string('link_title')) ?: 'Your link card headline',
                    style: 'large',
                );
            }

            $frames[] = $frame->toArray();
        }

        $mobileFold = self::FOLDS[$attachment]['Mobile app'];

        $warnings = [];

        if ($count > self::LIMIT) {
            $warnings[] = 'Over Facebook’s '.number_format(self::LIMIT).'-character limit by '
                .number_format($count - self::LIMIT).'. The post will be rejected.';
        }

        if ($count > $mobileFold && $this->hasLinkBelowFold($text, $mobileFold)) {
            $warnings[] = 'Your link sits below the fold. Most people never tap “See more”, '
                .'so move it into the first '.$mobileFold.' characters.';
        }

        return ToolResult::socialPreview(
            $frames,
            summary: number_format($count).' characters. '
                .($count > $mobileFold
                    ? 'On mobile the post is cut at '.number_format($mobileFold).' characters — '
                        .number_format($count - $mobileFold).' are hidden.'
                    : 'The whole post is visible without tapping.'),
        )->withWarnings($warnings)->withMeta([
            'characters' => $count,
            'fold' => $mobileFold,
            'limit' => self::LIMIT,
        ]);
    }

    private function hasLinkBelowFold(string $text, int $fold): bool
    {
        return preg_match('#https?://#i', mb_substr($text, $fold)) === 1;
    }
}
