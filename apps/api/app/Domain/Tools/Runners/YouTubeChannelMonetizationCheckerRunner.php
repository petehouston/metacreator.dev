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
use App\Support\Social\PreviewFrame;
use App\Support\Social\YouTubePage;

/**
 * Whether somebody else's channel has monetization switched on, read from its own
 * public page.
 *
 * The partner program checker measures your channel against the rules using figures
 * only you can see. This answers the other question people actually ask — "is *that*
 * channel making money?" — and it answers it from evidence rather than a guess.
 *
 * Two features are conclusive when present, because YouTube only offers either one
 * to a channel already in the Partner Program: channel memberships (the Join button)
 * and the Shopping shelf (the Store tab). One fact is conclusive when absent: a
 * channel under 500 subscribers is below the floor of the lowest tier and cannot be
 * monetized at all.
 *
 * Between those two, a channel can be earning ad revenue and publish no public trace
 * of it whatsoever — signed-out ad slots are not served to a crawler, and no
 * scraping trick recovers them. That case is reported as unconfirmed rather than
 * dressed up as a verdict, which is the difference between this and the tools that
 * announce a number they cannot see.
 */
final class YouTubeChannelMonetizationCheckerRunner implements Cacheable, ToolRunner, UsesProvider
{
    /** The lowest Partner Program tier starts here; below it, nothing is possible. */
    private const SUBSCRIBER_FLOOR = 500;

    /**
     * The Join button, in both shapes YouTube currently ships it — an icon name in
     * the new view-model markup, and the modal's title in the older renderer.
     */
    private const MEMBERSHIP_MARKERS = ['SPONSORSHIP_STAR', 'Want to join this channel?'];

    public static function key(): string
    {
        return 'youtube.channel-monetization-checker';
    }

    public function providers(): array
    {
        return ['youtube'];
    }

    public function cacheTtl(): int
    {
        return 21600;
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
                    'x-control' => 'text',
                    'title' => 'Channel username, ID or URL',
                    'description' => 'A handle like @mkbhd, a UC… channel ID, or any channel URL — '
                        .'including old /c/ and /user/ links.',
                    'minLength' => 2,
                    'maxLength' => 500,
                    'examples' => ['@mkbhd'],
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $html = YouTubePage::channel(YouTubePage::channelUrl(trim($input->string('channel'))));

        $channelId = YouTubePage::channelId($html)
            ?? throw ToolExecutionException::notFound('a channel at that username, ID or URL');

        $header = YouTubePage::channelHeader($html);
        $subscribers = YouTubePage::abbreviatedCount($header['subscribers']);

        $memberships = $this->hasMemberships($html);
        $store = $this->hasStore($html);

        $verdict = $this->verdict($memberships, $store, $subscribers);
        $name = YouTubePage::og($html, 'title') ?? 'That channel';
        $url = $header['handle'] !== null
            ? 'https://www.youtube.com/'.$header['handle']
            : "https://www.youtube.com/channel/{$channelId}";

        $frame = $this->frame($html, $name, $header, $url, $verdict);

        return ToolResult::socialPreview(
            frames: [$frame->toArray()],
            summary: $this->summary($name, $verdict, $memberships, $store, $subscribers),
            table: $this->evidence($memberships, $store, $subscribers),
        )->withMeta([
            'channel_id' => $channelId,
            'channel_url' => $url,
            'handle' => $header['handle'],
            'monetization' => $verdict,
            'memberships_enabled' => $memberships,
            'shopping_enabled' => $store,
            'subscribers_approx' => $subscribers,
        ])->withWarnings($this->warnings($verdict));
    }

    /**
     * @param  array{handle: ?string, subscribers: ?string, videos: ?string}  $header
     */
    private function frame(
        string $html,
        string $name,
        array $header,
        string $url,
        string $verdict,
    ): PreviewFrame {
        $avatar = YouTubePage::channelAvatar($html);
        $banner = YouTubePage::channelBanner($html);

        $frame = PreviewFrame::make('youtube', 'Channel page', 'channel')
            ->author($name, handle: $header['handle'])
            ->artwork(
                banner: $banner !== null ? "{$banner}=w2120-fcrop64=1,00000000ffffffff-nd-v1" : null,
                avatar: $avatar !== null ? "{$avatar}=s240-c-k-c0x00ffffff-no-rj" : null,
            )
            ->cta('View channel', $url)
            ->status(...$this->badge($verdict));

        $description = YouTubePage::channelDescription($html);

        if ($description !== null) {
            $frame->body($description);
        }

        foreach (['Subscribers' => $header['subscribers'], 'Videos' => $header['videos']] as $label => $value) {
            if ($value !== null) {
                $frame->detail($label, $value);
            }
        }

        return $frame;
    }

    private function hasMemberships(string $html): bool
    {
        foreach (self::MEMBERSHIP_MARKERS as $marker) {
            if (str_contains($html, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The Store tab, which a channel only gets once a Shopping shelf is connected.
     *
     * Matched as a tab URL on this channel rather than as the bare word "store",
     * which appears in half the merch links in a description.
     */
    private function hasStore(string $html): bool
    {
        return preg_match('#"url":"/(?:@[\w.\-]+|channel/UC[A-Za-z0-9_-]{22})/store"#', $html) === 1;
    }

    /** enabled | disabled | unconfirmed */
    private function verdict(bool $memberships, bool $store, ?int $subscribers): string
    {
        if ($memberships || $store) {
            return 'enabled';
        }

        if ($subscribers !== null && $subscribers < self::SUBSCRIBER_FLOOR) {
            return 'disabled';
        }

        return 'unconfirmed';
    }

    /** @return array{string, string} tone and label for the frame's badge. */
    private function badge(string $verdict): array
    {
        return match ($verdict) {
            'enabled' => ['ok', 'Monetization enabled'],
            'disabled' => ['danger', 'Monetization not enabled'],
            default => ['warn', 'No public monetization features'],
        };
    }

    private function summary(
        string $name,
        string $verdict,
        bool $memberships,
        bool $store,
        ?int $subscribers,
    ): string {
        if ($verdict === 'enabled') {
            $features = match (true) {
                $memberships && $store => 'channel memberships and a Shopping shelf',
                $memberships => 'channel memberships',
                default => 'a Shopping shelf',
            };

            return "{$name} has monetization enabled — it is running {$features}, and YouTube only "
                .'offers either to a channel already accepted into the Partner Program.';
        }

        if ($verdict === 'disabled') {
            return "{$name} is not monetized. At roughly ".number_format((int) $subscribers)
                .' subscribers it is below the 500 the lowest Partner Program tier requires, so no '
                .'monetization feature is available to it yet.';
        }

        return "{$name} publishes no monetization feature anyone can see from outside — no memberships, "
            .'no Shopping shelf. It is past the subscriber floor, so it may well be earning ad revenue: '
            .'signed-out ad slots are not served to a crawler, and nothing public settles it either way.';
    }

    /**
     * @return array{columns: list<array{key: string, label: string, align?: string}>, rows: list<array<string, string>>}
     */
    private function evidence(bool $memberships, bool $store, ?int $subscribers): array
    {
        $rows = [
            [
                'signal' => 'Channel memberships (Join button)',
                'found' => $memberships ? 'Yes' : 'No',
                'means' => $memberships
                    ? 'Conclusive: memberships are only offered to Partner Program channels.'
                    : 'Inconclusive: plenty of monetized channels never switch memberships on.',
            ],
            [
                'signal' => 'Shopping shelf (Store tab)',
                'found' => $store ? 'Yes' : 'No',
                'means' => $store
                    ? 'Conclusive: YouTube Shopping requires monetization to be on.'
                    : 'Inconclusive: most channels sell nothing through YouTube.',
            ],
            [
                'signal' => 'Subscribers vs the 500 floor',
                'found' => $subscribers === null ? 'Not published' : number_format($subscribers).' (approx.)',
                'means' => match (true) {
                    $subscribers === null => 'This channel hides its subscriber count, so the floor cannot be checked.',
                    $subscribers < self::SUBSCRIBER_FLOOR => 'Conclusive the other way: below 500, no tier is available.',
                    default => 'Past the floor, so monetization is possible — not proof that it is on.',
                },
            ],
            [
                'signal' => 'Ads on videos',
                'found' => 'Not checkable',
                'means' => 'YouTube does not serve ad slots to signed-out requests, so no tool can read '
                    .'this from outside. Anything claiming to is guessing.',
            ],
        ];

        return [
            'columns' => [
                ['key' => 'signal', 'label' => 'Signal'],
                ['key' => 'found', 'label' => 'Found'],
                ['key' => 'means', 'label' => 'What it proves'],
            ],
            'rows' => $rows,
        ];
    }

    /** @return list<string> */
    private function warnings(string $verdict): array
    {
        return $verdict === 'unconfirmed'
            ? ['“No public features” is not the same as “not monetized”. A channel earning ad revenue '
                .'and nothing else looks exactly like this from outside.']
            : [];
    }
}
