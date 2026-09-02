<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;

/**
 * What an Instagram account is worth per post — priced the way a brand actually
 * prices it, which is not "followers × a magic number".
 *
 * Instagram has no ad-revenue share. There is no RPM, no payout per view, and any
 * calculator that quotes one is describing a bonus programme that has been opened
 * and closed several times in several countries. The money on Instagram is brand
 * deals, so the honest calculator is a rate card, and a rate card is built from
 * three things: how many people see a post, how many of them do something, and how
 * much a thousand of them are worth in your niche.
 *
 * Engagement rate is the multiplier that matters, and it cuts both ways: a 25,000
 * follower account at 6% is worth more per post than a 100,000 follower account at
 * 0.8%, and brands with a media buyer know it. The band below is applied against
 * the niche CPM rather than bolted on afterwards, which is why the two accounts do
 * not come out proportional to their follower counts.
 *
 * Format matters too. A Reel reaches beyond the follower graph and is priced above
 * a feed post; a Story is cheap, disappears, and is usually thrown in.
 */
final class InstagramMoneyCalculatorRunner implements Cacheable, ToolRunner
{
    /**
     * What a brand pays per 1,000 people reached, by niche.
     *
     * These are the bands sponsorship marketplaces and agency rate cards cluster
     * around, not a measurement of any one deal. They exist to put a negotiation in
     * the right order of magnitude, which is the thing a creator asking this
     * question is missing.
     *
     * @var array<string, array{label: string, cpm: float}>
     */
    private const NICHES = [
        'finance' => ['label' => 'Finance & business', 'cpm' => 35.0],
        'tech' => ['label' => 'Tech & software', 'cpm' => 28.0],
        'beauty' => ['label' => 'Beauty & skincare', 'cpm' => 24.0],
        'fashion' => ['label' => 'Fashion', 'cpm' => 20.0],
        'fitness' => ['label' => 'Health & fitness', 'cpm' => 20.0],
        'parenting' => ['label' => 'Parenting & family', 'cpm' => 18.0],
        'travel' => ['label' => 'Travel', 'cpm' => 16.0],
        'food' => ['label' => 'Food & drink', 'cpm' => 15.0],
        'lifestyle' => ['label' => 'Lifestyle', 'cpm' => 12.0],
        'entertainment' => ['label' => 'Entertainment & memes', 'cpm' => 9.0],
    ];

    /**
     * Format multipliers against the feed-post rate.
     *
     * @var array<string, array{label: string, multiplier: float, reach: float, note: string}>
     */
    private const FORMATS = [
        'reel' => ['label' => 'Reel', 'multiplier' => 1.35, 'reach' => 0.55,
            'note' => 'Reels are served beyond your followers, so reach — and the rate — runs above '
                .'a feed post.'],
        'feed' => ['label' => 'Feed post or carousel', 'multiplier' => 1.0, 'reach' => 0.35,
            'note' => 'The baseline. A carousel is priced the same but usually holds attention longer.'],
        'story' => ['label' => 'Story frame', 'multiplier' => 0.35, 'reach' => 0.10,
            'note' => 'Cheap, and gone in a day. Sell these in sets of three, or add them to a Reel '
                .'deal rather than pricing them alone.'],
    ];

    /**
     * Engagement bands, as a multiplier on the niche CPM.
     *
     * @var list<array{0: float, 1: float, 2: string}>
     */
    private const ENGAGEMENT_BANDS = [
        [6.0, 1.6, 'Exceptional — lead with this number in the first email'],
        [3.0, 1.25, 'Strong for any follower count'],
        [1.5, 1.0, 'Healthy, and the band most rate cards assume'],
        [0.8, 0.8, 'Below the median — expect to be negotiated down'],
        [0.0, 0.6, 'Low. Fix this before selling; it is the number a media buyer checks first'],
    ];

    public static function key(): string
    {
        return 'instagram.money-calculator';
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
            'required' => ['followers'],
            'additionalProperties' => false,
            'properties' => [
                'followers' => [
                    'type' => 'integer',
                    'title' => 'Followers',
                    'minimum' => 0,
                    'maximum' => 1_000_000_000,
                    'examples' => [24000],
                ],
                'engagement_rate' => [
                    'type' => 'number',
                    'title' => 'Engagement rate (%)',
                    'description' => 'Interactions divided by followers, as a percentage. Work it out '
                        .'with the engagement rate calculator if you do not have it to hand.',
                    'minimum' => 0,
                    'maximum' => 100,
                    'default' => 2.5,
                ],
                'niche' => [
                    'type' => 'string',
                    'title' => 'Niche',
                    'enum' => array_keys(self::NICHES),
                    'default' => 'lifestyle',
                ],
                'format' => [
                    'type' => 'string',
                    'title' => 'Format',
                    'enum' => array_keys(self::FORMATS),
                    'default' => 'reel',
                ],
                'average_reach' => [
                    'type' => 'integer',
                    'title' => 'Average reach per post (optional)',
                    'description' => 'From your own insights. When you have it, it replaces the '
                        .'estimate — and it is the number to quote in a media kit.',
                    'minimum' => 0,
                    'maximum' => 1_000_000_000,
                    'default' => 0,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $followers = max(0, $input->int('followers'));
        $engagement = max(0.0, min(100.0, $input->float('engagement_rate', 2.5)));
        $niche = self::NICHES[$input->string('niche', 'lifestyle')] ?? self::NICHES['lifestyle'];
        $format = self::FORMATS[$input->string('format', 'reel')] ?? self::FORMATS['reel'];
        $statedReach = max(0, $input->int('average_reach'));

        [$multiplier, $bandNote] = $this->band($engagement);

        $reach = $statedReach > 0 ? $statedReach : (int) round($followers * $format['reach']);
        $rate = $reach / 1000 * $niche['cpm'] * $format['multiplier'] * $multiplier;

        // A negotiation is a range, and quoting a single number invites it to be the
        // ceiling. The floor is where you stop; the ask is what you open with.
        $floor = $rate * 0.75;
        $ask = $rate * 1.3;

        return ToolResult::keyValue([
            ['label' => 'Ask this', 'value' => '$'.number_format($ask),
                'hint' => 'Open here. Brands expect to negotiate, and the first number sets the range.',
                'tone' => 'positive'],
            ['label' => 'Fair rate', 'value' => '$'.number_format($rate),
                'hint' => "{$format['label']} · {$niche['label']} at a \${$niche['cpm']} CPM."],
            ['label' => 'Walk away below', 'value' => '$'.number_format($floor),
                'hint' => 'Under this you are paying for the privilege of making the content.'],
            ['label' => 'Reach used', 'value' => number_format($reach),
                'hint' => $statedReach > 0
                    ? 'Your own figure, which is the one to put in a media kit.'
                    : 'Estimated at '.round($format['reach'] * 100).'% of followers for a '
                        .mb_strtolower($format['label']).'. Replace it with your real reach.'],
            ['label' => 'Engagement band', 'value' => number_format($engagement, 2).'%',
                'hint' => $bandNote,
                'tone' => $multiplier >= 1.25 ? 'positive' : ($multiplier < 1.0 ? 'warning' : 'neutral')],
            ['label' => 'Three posts a month', 'value' => '$'.number_format($rate * 3),
                'hint' => 'A retainer at the fair rate — which is where the actual income is.',
                'tone' => 'positive'],
            ['label' => 'Per 1,000 followers', 'value' => '$'.number_format($followers > 0 ? $rate / ($followers / 1000) : 0, 2),
                'hint' => 'The figure people compare, and the one that hides everything that matters.'],
        ], summary: 'A '.mb_strtolower($format['label']).' from this account prices at roughly $'
            .number_format($rate).', with $'.number_format($ask).' as the opening ask. '
            .'Instagram pays nothing for the post itself — this is the whole business.')
            ->withMeta([
                'rate' => round($rate, 2),
                'ask' => round($ask, 2),
                'floor' => round($floor, 2),
                'reach' => $reach,
                'engagement_multiplier' => $multiplier,
            ])
            ->withWarnings(array_filter([
                'These are the bands agency rate cards cluster around, not a quote. Audience country, '
                .'exclusivity, usage rights and whether the brand can run the post as an ad move a '
                .'real deal more than follower count does.',
                'Usage rights are the clause that gets given away. "We may boost this as an ad for '
                .'six months" is a media buy, and it is worth more than the post.',
                $statedReach === 0
                    ? 'Reach was estimated from your follower count. Open Insights and use the real '
                        .'number — an account whose reach beats the estimate is leaving money on the '
                        .'table by not quoting it.'
                    : '',
            ]));
    }

    /**
     * @return array{0: float, 1: string}
     */
    private function band(float $engagement): array
    {
        foreach (self::ENGAGEMENT_BANDS as [$floor, $multiplier, $note]) {
            if ($engagement >= $floor) {
                return [$multiplier, $note];
            }
        }

        return [1.0, 'Healthy'];
    }
}
