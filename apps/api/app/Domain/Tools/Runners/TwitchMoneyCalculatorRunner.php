<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;

/**
 * What a Twitch channel earns in a month, from the four sources that actually pay
 * — with the split applied, because the split is the part people forget.
 *
 * Twitch is the rare platform where most of the arithmetic is published. A Tier 1
 * subscription is $4.99. The standard Affiliate and Partner split is 50/50, and a
 * Partner on the older premium terms takes 70. A Bit is worth exactly one cent to
 * the streamer. Prime subscriptions pay the same as a Tier 1 and cost the viewer
 * nothing extra.
 *
 * Which leaves two figures that genuinely vary — the ad CPM, and how many ad
 * minutes you run per hour — and those are inputs rather than assumptions, because
 * a calculator that buries them is inventing your income.
 *
 * The number this is really for is the comparison at the end: three hundred subs
 * against a full month of ads. Ads look like the easy revenue and are almost never
 * the larger half, which is the finding that changes what a streamer does on
 * Monday.
 */
final class TwitchMoneyCalculatorRunner implements Cacheable, ToolRunner
{
    /** The published price of a Tier 1 subscription, in dollars. */
    private const TIER_1 = 4.99;

    /** Tier 2 and Tier 3 prices, for the mix input. */
    private const TIER_2 = 9.99;

    private const TIER_3 = 24.99;

    /** A Bit is worth one cent to the channel it is cheered in. */
    private const BIT_VALUE = 0.01;

    public static function key(): string
    {
        return 'twitch.money-calculator';
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
            'required' => ['subscribers'],
            'additionalProperties' => false,
            'properties' => [
                'subscribers' => [
                    'type' => 'integer',
                    'title' => 'Subscribers (all tiers, including Prime)',
                    'minimum' => 0,
                    'maximum' => 1_000_000,
                    'examples' => [180],
                ],
                'split' => [
                    'type' => 'integer',
                    'title' => 'Your subscription share (%)',
                    'description' => '50 for Affiliates and most Partners; 70 for Partners on the '
                        .'older premium terms.',
                    'enum' => [50, 70],
                    'default' => 50,
                ],
                'tier_mix' => [
                    'type' => 'string',
                    'title' => 'Subscription mix',
                    'description' => 'Most channels are overwhelmingly Tier 1 and Prime.',
                    'enum' => ['mostly_prime', 'typical', 'high_tier'],
                    'default' => 'typical',
                ],
                'average_viewers' => [
                    'type' => 'integer',
                    'title' => 'Average concurrent viewers',
                    'minimum' => 0,
                    'maximum' => 1_000_000,
                    'default' => 0,
                ],
                'hours_per_month' => [
                    'type' => 'integer',
                    'title' => 'Hours streamed per month',
                    'minimum' => 0,
                    'maximum' => 744,
                    'default' => 80,
                ],
                'ad_minutes_per_hour' => [
                    'type' => 'integer',
                    'title' => 'Ad minutes per hour',
                    'description' => 'Three minutes an hour is the common setting for a channel '
                        .'running Twitch’s ad incentive.',
                    'minimum' => 0,
                    'maximum' => 15,
                    'default' => 3,
                ],
                'ad_cpm' => [
                    'type' => 'number',
                    'title' => 'Ad CPM ($ per 1,000 impressions)',
                    'description' => 'Your own figure from the payout dashboard if you have it. It '
                        .'moves with audience country and time of year.',
                    'minimum' => 0,
                    'maximum' => 100,
                    'default' => 3.5,
                ],
                'bits_per_month' => [
                    'type' => 'integer',
                    'title' => 'Bits cheered per month',
                    'minimum' => 0,
                    'maximum' => 100_000_000,
                    'default' => 0,
                ],
                'donations_per_month' => [
                    'type' => 'number',
                    'title' => 'Direct tips per month ($)',
                    'description' => 'Off-platform tips, which Twitch takes no cut of.',
                    'minimum' => 0,
                    'maximum' => 1_000_000,
                    'default' => 0,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $subscribers = max(0, $input->int('subscribers'));
        $share = ($input->int('split', 50) === 70 ? 70 : 50) / 100;
        $viewers = max(0, $input->int('average_viewers'));
        $hours = max(0, $input->int('hours_per_month', 80));
        $adMinutes = max(0, min(15, $input->int('ad_minutes_per_hour', 3)));
        $cpm = max(0.0, $input->float('ad_cpm', 3.5));
        $bits = max(0, $input->int('bits_per_month'));
        $tips = max(0.0, $input->float('donations_per_month'));

        $averageSubPrice = $this->averageSubPrice($input->string('tier_mix', 'typical'));

        $subRevenue = $subscribers * $averageSubPrice * $share;

        // One ad minute served to one viewer is one impression. Impressions are
        // therefore viewers × ad minutes, and revenue is that over a thousand at the
        // CPM — which is already the streamer's share on Twitch's ad product.
        $impressions = $viewers * $hours * $adMinutes;
        $adRevenue = $impressions / 1000 * $cpm;

        $bitRevenue = $bits * self::BIT_VALUE;
        $total = $subRevenue + $adRevenue + $bitRevenue + $tips;

        $pairs = [
            ['label' => 'Monthly total', 'value' => '$'.number_format($total, 2),
                'hint' => 'Before tax, and before Twitch’s $100 payout threshold.',
                'tone' => 'positive'],
            ['label' => 'Subscriptions', 'value' => '$'.number_format($subRevenue, 2),
                'hint' => $subscribers.' subs at an average $'.number_format($averageSubPrice, 2)
                    .', your '.round($share * 100).'% share applied.'],
            ['label' => 'Ads', 'value' => '$'.number_format($adRevenue, 2),
                'hint' => number_format($impressions).' impressions at a $'.number_format($cpm, 2)
                    .' CPM.'],
            ['label' => 'Bits', 'value' => '$'.number_format($bitRevenue, 2),
                'hint' => number_format($bits).' Bits at one cent each.'],
        ];

        if ($tips > 0) {
            $pairs[] = ['label' => 'Direct tips', 'value' => '$'.number_format($tips, 2),
                'hint' => 'Twitch takes no cut of these, which is why so many channels route them '
                    .'off-platform.'];
        }

        $pairs[] = ['label' => 'Twitch’s cut of your subs', 'value' => '$'
            .number_format($subscribers * $averageSubPrice * (1 - $share), 2),
            'hint' => 'The same subscribers on the other side of the split.'];

        $pairs[] = ['label' => 'Per hour streamed', 'value' => $hours > 0
            ? '$'.number_format($total / $hours, 2)
            : '—',
            'hint' => 'The figure to compare against anything else you could do with the hour.'];

        $pairs[] = ['label' => 'A year at this rate', 'value' => '$'.number_format($total * 12, 2)];

        return ToolResult::keyValue($pairs, summary: $this->summary($subRevenue, $adRevenue, $total))
            ->withMeta([
                'total' => round($total, 2),
                'subscriptions' => round($subRevenue, 2),
                'ads' => round($adRevenue, 2),
                'bits' => round($bitRevenue, 2),
                'impressions' => $impressions,
            ])
            ->withWarnings([
                'Subscription prices, the 50/50 split and the one-cent Bit are Twitch’s own published '
                .'figures. The ad CPM is not published and is the one number here that is genuinely '
                .'an estimate — replace the default with yours.',
                'Prime subscriptions renew only when the viewer re-subscribes each month, so a '
                .'Prime-heavy channel’s sub count is more volatile than the total suggests.',
                'Sub counts are not steady income. Most channels lose a visible share of their subs '
                .'in any month they do not stream, which is why the per-hour figure above matters '
                .'more than the monthly one.',
            ]);
    }

    private function averageSubPrice(string $mix): float
    {
        return match ($mix) {
            'mostly_prime' => self::TIER_1,
            'high_tier' => self::TIER_1 * 0.7 + self::TIER_2 * 0.22 + self::TIER_3 * 0.08,
            default => self::TIER_1 * 0.88 + self::TIER_2 * 0.10 + self::TIER_3 * 0.02,
        };
    }

    private function summary(float $subs, float $ads, float $total): string
    {
        if ($total <= 0.0) {
            return 'Nothing to add up yet. Put in a subscriber count and an average viewer figure — '
                .'those two carry almost all of a Twitch income.';
        }

        $ratio = $ads > 0 ? $subs / $ads : null;

        return '$'.number_format($total, 2).' a month before tax. '
            .($ratio === null
                ? 'Subscriptions are the whole of it; ads are worth adding once you stream regular hours.'
                : ($ratio >= 1
                    ? 'Subscriptions are worth '.number_format($ratio, 1).'× your ad revenue, which '
                        .'is the usual shape: the ad break is the visible income and the smaller half.'
                    : 'Ads are outrunning your subscriptions — unusual, and normally a sign of a big '
                        .'audience that has not been asked to subscribe.'));
    }
}
