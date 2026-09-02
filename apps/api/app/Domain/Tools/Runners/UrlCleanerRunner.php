<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Enums\ResultView;
use App\Domain\Tools\Exceptions\ToolExecutionException;
use App\Support\Social\SocialUrl;

/**
 * The same link without the tracking, and a line on what each parameter you just
 * deleted was telling somebody.
 *
 * Stripping trackers is usually sold as tidiness. It is not: several of these
 * parameters identify a *person*. `igshid` is minted per share, so a link you
 * forward carries the fact that it came from you. `mc_eid` is a Mailchimp
 * subscriber id — paste a newsletter link into a group chat with that attached and
 * every click is attributed to your subscription. `si` does the same job on
 * YouTube and Spotify shares.
 *
 * So the table names each one rather than silently deleting it, and the tool is
 * equally careful in the other direction: YouTube's `t` is a timestamp and its
 * `list` is a playlist, and a cleaner that drops those has broken the link it was
 * asked to fix. Those are kept, listed, and the reason is given.
 *
 * Works on any URL, not only a social one — a link from an ad, an email or a
 * newsletter is where most of these come from.
 */
final class UrlCleanerRunner implements Cacheable, ToolRunner
{
    /**
     * What each tracking parameter is, and what it hands over.
     *
     * The vocabulary is {@see SocialUrl::TRACKING_PARAMS}; this is the explanation
     * beside it. A parameter in that list with no entry here still gets removed —
     * it simply gets the generic line — so the two can never disagree about *what*
     * is stripped, only about how well it is explained.
     *
     * @var array<string, string>
     */
    private const EXPLANATIONS = [
        'utm_source' => 'Campaign tag. Names the site or channel that sent the click.',
        'utm_medium' => 'Campaign tag. Names the channel type — email, social, cpc.',
        'utm_campaign' => 'Campaign tag. Names the campaign, which is often the internal name for it.',
        'utm_term' => 'Campaign tag. The paid keyword the click was bought on.',
        'utm_content' => 'Campaign tag. Which creative or link position was clicked.',
        'utm_id' => 'Campaign tag. The campaign’s own id in the advertiser’s tool.',
        'fbclid' => 'Meta click id, minted per click. Ties the visit back to a Facebook session.',
        'gclid' => 'Google Ads click id. Ties the visit to the ad click and its cost.',
        'gbraid' => 'Google Ads click id used where the app-to-web id cannot be set.',
        'wbraid' => 'Google Ads click id for web-to-app journeys.',
        'dclid' => 'Google Display click id.',
        'msclkid' => 'Microsoft Advertising click id.',
        'twclid' => 'X advertising click id.',
        'ttclid' => 'TikTok advertising click id.',
        'igshid' => 'Instagram share id, minted per share. Identifies the account that shared it.',
        'igsh' => 'Instagram share id, the newer name for `igshid`. Same meaning.',
        'si' => 'Share id on YouTube and Spotify. Identifies the session the link was copied from.',
        'ref' => 'Referral source, read by the destination site.',
        'ref_src' => 'Referral surface — which widget or embed the click came from.',
        'ref_url' => 'The full page the click came from, handed to the destination.',
        'feature' => 'YouTube share surface — which button produced the link.',
        'pp' => 'YouTube player parameters attached by the share sheet.',
        's' => 'X share source. Names the app the link was copied from.',
        't' => 'Share timestamp on X. On YouTube the same name means a video timestamp — see below.',
        'mc_cid' => 'Mailchimp campaign id.',
        'mc_eid' => 'Mailchimp **subscriber** id. Identifies the person the email was sent to.',
        'yclid' => 'Yandex click id.',
        'li_fat_id' => 'LinkedIn ad attribution id.',
        'trk' => 'LinkedIn tracking token naming the surface you clicked from.',
        'trkCampaign' => 'LinkedIn campaign token.',
        'rdt_cid' => 'Reddit advertising click id.',
        '_branch_match_id' => 'Branch deep-link id, used to match an app install to a click.',
        'share_id' => 'TikTok share id, minted per share.',
        'share_app_id' => 'TikTok share surface id.',
        'is_from_webapp' => 'TikTok flag recording that the link came from the web app.',
        'sender_device' => 'TikTok flag recording the device that shared the link.',
    ];

    /**
     * Parameters that look like trackers but carry meaning on a given platform.
     *
     * A cleaner is only trusted if it never breaks a link, and these are the four
     * ways a naive one does. YouTube's `t` is where the video starts; drop it and
     * you have sent somebody to the beginning of a two-hour stream.
     *
     * @var array<string, array<string, string>>
     */
    private const LOAD_BEARING = [
        'youtube' => [
            't' => 'The timestamp the video starts at. Removing it sends the viewer to 0:00.',
            'start' => 'Start time in seconds, used by embeds.',
            'end' => 'End time in seconds, used by embeds.',
            'list' => 'The playlist the video is being watched in.',
            'index' => 'Position in that playlist.',
        ],
    ];

    public static function key(): string
    {
        return 'utility.url-cleaner';
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
                    'title' => 'The link you were sent',
                    'minLength' => 4,
                    'maxLength' => 2000,
                    'examples' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s&si=aBcDeFgH'],
                ],
                'keep_timestamps' => [
                    'type' => 'boolean',
                    'title' => 'Keep timestamps and playlists',
                    'description' => 'YouTube’s `t`, `list`, `start` and `end` change what the link '
                        .'does. On by default, because removing them breaks the link.',
                    'default' => true,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $raw = trim($input->string('url'));
        $identity = SocialUrl::identify($raw);

        if ($identity['host'] === null) {
            throw ToolExecutionException::invalidInput(
                'That is not a URL we can read. Paste the whole link, including the domain.',
                ['url' => 'Expected a link such as https://example.com/page?utm_source=newsletter'],
            );
        }

        $platform = $identity['platform'];
        $keepTimestamps = $input->bool('keep_timestamps', true);
        $loadBearing = $keepTimestamps ? (self::LOAD_BEARING[$platform] ?? []) : [];

        parse_str((string) (parse_url($identity['url'], PHP_URL_QUERY) ?: ''), $query);

        $removed = [];
        $kept = [];

        foreach ($query as $name => $value) {
            $name = (string) $name;
            $printable = $this->clip(is_scalar($value) ? (string) $value : '…', 40);

            if (isset($loadBearing[$name])) {
                $kept[] = [
                    'parameter' => $name,
                    'value' => $printable,
                    'what' => $loadBearing[$name],
                    'verdict' => 'Kept — it changes what the link does',
                ];

                continue;
            }

            if (in_array($name, SocialUrl::TRACKING_PARAMS, true)) {
                $removed[] = [
                    'parameter' => $name,
                    'value' => $printable,
                    'what' => $this->explain($name),
                    'verdict' => 'Removed',
                ];

                continue;
            }

            $kept[] = [
                'parameter' => $name,
                'value' => $printable,
                'what' => 'Not a parameter we recognise as tracking.',
                'verdict' => 'Kept — the page may need it',
            ];
        }

        $clean = SocialUrl::stripTracking($identity['url'], keep: array_keys($loadBearing));
        $saved = mb_strlen($raw) - mb_strlen($clean['url']);

        $groups = [[
            'label' => 'Your clean link',
            'hint' => $saved > 0 ? $saved.' characters shorter' : 'Nothing needed removing',
            'rows' => [[
                'parameter' => 'Cleaned URL',
                'value' => $clean['url'],
                'what' => 'Paste this one instead.',
                'verdict' => $removed === [] ? 'Already clean' : count($removed).' removed',
            ]],
        ]];

        if ($removed !== []) {
            $groups[] = ['label' => 'Removed', 'rows' => $removed];
        }

        if ($kept !== []) {
            $groups[] = ['label' => 'Kept', 'rows' => $kept];
        }

        return (new ToolResult(
            view: ResultView::Table,
            data: [
                'columns' => [
                    ['key' => 'parameter', 'label' => 'Parameter'],
                    ['key' => 'value', 'label' => 'Value', 'copyable' => true, 'wrap' => true],
                    ['key' => 'what', 'label' => 'What it is'],
                    ['key' => 'verdict', 'label' => 'Verdict'],
                ],
                'groups' => $groups,
            ],
            summary: $this->summary($removed, $identity['label']),
        ))->withMeta([
            'clean_url' => $clean['url'],
            'platform' => $platform,
            'removed_parameters' => array_column($removed, 'parameter'),
            'characters_saved' => max(0, $saved),
        ])->withWarnings($this->warnings($removed, $platform, $keepTimestamps));
    }

    /** @param  list<array<string, string>>  $removed */
    private function summary(array $removed, ?string $label): string
    {
        if ($removed === []) {
            return 'That link carries no tracking parameters we recognise — it is already the short '
                .'version of itself.';
        }

        $names = array_column($removed, 'parameter');
        $identifying = array_intersect($names, ['igshid', 'igsh', 'si', 'mc_eid', 'share_id', 'fbclid']);

        return count($removed).' tracking parameter'.(count($removed) === 1 ? '' : 's').' removed'
            .($label !== null ? " from that {$label} link" : '').'. '
            .($identifying !== []
                ? 'One of them — `'.reset($identifying).'` — identifies the person the link came '
                    .'from, so this is the version to share.'
                : 'The rest of the link is untouched, so it still goes exactly where it did.');
    }

    /**
     * @param  list<array<string, string>>  $removed
     * @return list<string>
     */
    private function warnings(array $removed, ?string $platform, bool $keepTimestamps): array
    {
        $warnings = [];

        if (! $keepTimestamps && $platform === 'youtube') {
            $warnings[] = 'You turned off "keep timestamps", so a `t` or `list` on this link was '
                .'treated as tracking and removed. The link now starts the video at the beginning.';
        }

        if (in_array('mc_eid', array_column($removed, 'parameter'), true)) {
            $warnings[] = 'This link carried a Mailchimp subscriber id. Anyone you had forwarded the '
                .'original to would have had their clicks recorded against your subscription.';
        }

        $warnings[] = 'Removing a campaign tag does not hide the visit from the destination site — '
            .'it only stops the link naming which campaign, share or subscriber produced it.';

        return $warnings;
    }

    /**
     * What a parameter is, or the generic line for one this list has not caught up
     * with — {@see self::EXPLANATIONS} explains the vocabulary, it does not define it.
     */
    private function explain(string $name): string
    {
        $explanations = self::EXPLANATIONS;

        return is_string($explanations[$name] ?? null)
            ? $explanations[$name]
            : 'A tracking parameter added by whoever shared the link.';
    }

    private function clip(string $value, int $length): string
    {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length).'…' : $value;
    }
}
