<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Contracts\UsesProvider;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Exceptions\ToolExecutionException;
use App\Support\Social\YouTubePage;
use App\Support\Social\YouTubeUrl;
use DateTimeImmutable;

/**
 * A YouTube video cited in the five styles students are actually asked for.
 *
 * The fiddly part is not the punctuation, it is that every style treats the
 * uploader differently: APA wants the person with the channel in brackets, MLA
 * wants "uploaded by", Chicago leads with the channel as author. Getting the
 * metadata once and formatting it five ways is the whole job — which is why this
 * reads the video's published metadata rather than asking the user to retype it.
 */
final class YouTubeCitationGeneratorRunner implements Cacheable, ToolRunner, UsesProvider
{
    public static function key(): string
    {
        return 'youtube.citation-generator';
    }

    public function providers(): array
    {
        return ['youtube'];
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
            'required' => ['url'],
            'additionalProperties' => false,
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'x-control' => 'text',
                    'title' => 'YouTube video URL or ID',
                    'minLength' => 11,
                    'maxLength' => 500,
                    'examples' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
                ],
                'author' => [
                    'type' => 'string',
                    'title' => 'Author’s real name (optional)',
                    'description' => 'APA and MLA prefer a person where one is known — “Brownlee, M.” '
                        .'Leave blank to cite the channel alone, which is correct when no person is credited.',
                    'maxLength' => 120,
                    'default' => '',
                    'examples' => ['Brownlee, M.'],
                ],
                'accessed' => [
                    'type' => 'string',
                    'title' => 'Date accessed',
                    'description' => 'Harvard and MLA want this. Leave blank for today.',
                    // Not `format: date`, which would reject the empty string this
                    // field defaults to. The pattern accepts a date or nothing.
                    'pattern' => '^(\\d{4}-\\d{2}-\\d{2})?$',
                    'maxLength' => 10,
                    'default' => '',
                    'examples' => ['2026-08-28'],
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $videoId = YouTubeUrl::videoId($input->string('url'))
            ?? throw ToolExecutionException::invalidInput(
                "That doesn't look like a YouTube video link.",
                ['url' => 'Unrecognised YouTube URL or video ID.'],
            );

        $html = YouTubePage::watch($videoId);
        $oEmbed = YouTubePage::oEmbed($videoId);

        $title = $this->string($oEmbed, 'title')
            ?? YouTubePage::og($html, 'title')
            ?? throw ToolExecutionException::notFound('a title for that video');

        $channel = $this->string($oEmbed, 'author_name')
            ?? YouTubePage::field($html, 'ownerChannelName')
            ?? 'Unknown channel';

        $published = $this->published($html);
        $accessed = $this->accessed($input->string('accessed'));
        $url = YouTubeUrl::watchUrl($videoId);
        $author = trim($input->string('author'));

        // APA and MLA name a person where one is known; with no person credited the
        // channel *is* the author, and inventing one would be worse than citing it.
        $apaAuthor = $author !== '' ? "{$author} [{$channel}]" : $channel;

        $blocks = [
            [
                'label' => 'APA 7th edition',
                'text' => sprintf(
                    '%s. (%s). %s [Video]. YouTube. %s',
                    rtrim($apaAuthor, '.'),
                    $published?->format('Y, F j') ?? 'n.d.',
                    $title,
                    $url,
                ),
            ],
            [
                'label' => 'MLA 9th edition',
                'text' => sprintf(
                    '“%s.” YouTube, uploaded by %s, %s, %s. Accessed %s.',
                    rtrim($title, '.'),
                    $channel,
                    $published?->format('j F Y') ?? 'n.d.',
                    $url,
                    $accessed->format('j F Y'),
                ),
            ],
            [
                'label' => 'Chicago 17th edition (notes-bibliography)',
                'text' => sprintf(
                    '%s. “%s.” YouTube video%s. %s. %s.',
                    rtrim($channel, '.'),
                    rtrim($title, '.'),
                    ($duration = YouTubePage::humanDuration(YouTubePage::itemprop($html, 'duration'))) !== null
                        ? ", {$duration}"
                        : '',
                    $published?->format('F j, Y') ?? 'n.d.',
                    $url,
                ),
            ],
            [
                'label' => 'Harvard',
                'text' => sprintf(
                    '%s (%s) %s. Available at: %s (Accessed: %s).',
                    rtrim($channel, '.'),
                    $published?->format('Y') ?? 'n.d.',
                    $title,
                    $url,
                    $accessed->format('j F Y'),
                ),
            ],
            [
                'label' => 'BibTeX',
                'text' => sprintf(
                    "@misc{%s,\n  author       = {%s},\n  title        = {{%s}},\n  year         = {%s},\n"
                    ."  howpublished = {YouTube video},\n  url          = {%s},\n  urldate      = {%s}\n}",
                    $this->citationKey($channel, $videoId),
                    $author !== '' ? $author : $channel,
                    $title,
                    $published?->format('Y') ?? 'n.d.',
                    $url,
                    $accessed->format('Y-m-d'),
                ),
            ],
        ];

        return ToolResult::textBlocks($blocks, summary: sprintf(
            'Five citation styles for “%s” by %s%s.',
            $title,
            $channel,
            $published !== null ? ' ('.$published->format('Y').')' : '',
        ))->withMeta([
            'video_id' => $videoId,
            'title' => $title,
            'channel' => $channel,
            'published_at' => $published?->format('Y-m-d'),
        ])->withWarnings($published === null
            ? ['No publication date was published for this video, so every style falls back to “n.d.”. '
                .'Check the date on the watch page and edit it in if your marker needs one.']
            : []);
    }

    private function published(string $html): ?DateTimeImmutable
    {
        $value = YouTubePage::itemprop($html, 'datePublished')
            ?? YouTubePage::itemprop($html, 'uploadDate')
            ?? YouTubePage::field($html, 'publishDate');

        if ($value === null) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function accessed(string $value): DateTimeImmutable
    {
        $value = trim($value);

        if ($value === '') {
            return new DateTimeImmutable('today');
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $parsed === false ? new DateTimeImmutable('today') : $parsed;
    }

    /** A stable, collision-resistant BibTeX key: first word of the channel plus the video id. */
    private function citationKey(string $channel, string $videoId): string
    {
        $word = preg_replace('/[^a-z0-9]/', '', mb_strtolower(strtok($channel, ' ') ?: 'youtube'));

        return ($word !== '' ? $word : 'youtube').'_'.$videoId;
    }

    /** @param  array<string, mixed>|null  $oEmbed */
    private function string(?array $oEmbed, string $key): ?string
    {
        $value = $oEmbed[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
