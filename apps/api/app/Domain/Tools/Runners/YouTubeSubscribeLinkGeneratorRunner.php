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

/**
 * The link that opens the subscribe confirmation dialog, in every form worth pasting.
 *
 * `?sub_confirmation=1` is an undocumented but long-standing and entirely public
 * parameter: it opens YouTube with the subscribe prompt already showing, which
 * converts far better than a channel page the visitor has to find the button on.
 * The tool resolves the channel id first, because the id form is the one that keeps
 * working after a handle is changed.
 */
final class YouTubeSubscribeLinkGeneratorRunner implements Cacheable, ToolRunner, UsesProvider
{
    public static function key(): string
    {
        return 'youtube.subscribe-link-generator';
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
            'required' => ['channel'],
            'additionalProperties' => false,
            'properties' => [
                'channel' => [
                    'type' => 'string',
                    'title' => 'Channel handle or URL',
                    'description' => 'A handle like @mkbhd, a channel ID, or any channel URL — old /c/ and '
                        .'/user/ links included.',
                    'minLength' => 2,
                    'maxLength' => 300,
                    'examples' => ['@mkbhd'],
                ],
                'label' => [
                    'type' => 'string',
                    'title' => 'Button text',
                    'maxLength' => 60,
                    'default' => 'Subscribe on YouTube',
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $channel = trim($input->string('channel'));
        $label = trim($input->string('label', 'Subscribe on YouTube')) ?: 'Subscribe on YouTube';

        $html = YouTubePage::channel(YouTubePage::channelUrl($channel));

        $channelId = YouTubePage::channelId($html) ?? throw ToolExecutionException::notFound('that channel');
        $name = YouTubePage::og($html, 'title') ?? 'this channel';
        $handle = $this->handle($html, $channel);

        $link = "https://www.youtube.com/channel/{$channelId}?sub_confirmation=1";
        $handleLink = $handle !== null
            ? "https://www.youtube.com/{$handle}?sub_confirmation=1"
            : null;

        $blocks = [
            ['label' => 'Subscribe link (channel ID — never breaks)', 'text' => $link],
        ];

        if ($handleLink !== null) {
            $blocks[] = [
                'label' => 'Subscribe link (handle — prettier, breaks if you rename)',
                'text' => $handleLink,
            ];
        }

        $blocks[] = [
            'label' => 'HTML button',
            'text' => '<a href="'.$this->escape($link).'" target="_blank" rel="noopener">'
                .$this->escape($label).'</a>',
        ];

        $blocks[] = [
            'label' => 'Markdown',
            'text' => '['.$label.']('.$link.')',
        ];

        $blocks[] = [
            'label' => 'Video description snippet',
            'text' => "👉 Subscribe: {$link}",
        ];

        return ToolResult::textBlocks(
            $blocks,
            summary: "Subscribe links for “{$name}”. The dialog opens as soon as the page loads.",
        )->withMeta([
            'channel_id' => $channelId,
            'channel_name' => $name,
            'handle' => $handle,
            'subscribe_url' => $link,
        ])->withWarnings([
            'Never offer anything in exchange for a subscription — sub4sub, giveaways gated on subscribing '
            .'and incentivised clicks are against YouTube’s spam policy and the subscribers get removed anyway.',
        ]);
    }

    private function handle(string $html, string $input): ?string
    {
        if (str_starts_with($input, '@')) {
            return $input;
        }

        $handle = YouTubePage::field($html, 'canonicalBaseUrl');

        if (is_string($handle) && str_starts_with($handle, '/@')) {
            return ltrim($handle, '/');
        }

        return preg_match('#"(@[A-Za-z0-9._-]{3,30})"#', $html, $match) === 1 ? $match[1] : null;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
