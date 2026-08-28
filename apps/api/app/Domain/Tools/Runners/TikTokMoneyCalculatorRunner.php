<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;

/**
 * What TikTok pays, and why brand deals are the real number.
 *
 * The Creator Rewards programme pays a fraction of a cent per view, so a viral
 * video earns lunch money. The honest answer to "how much can I make on TikTok"
 * is a sponsorship rate, which is what this leads with.
 */
final class TikTokMoneyCalculatorRunner implements Cacheable, ToolRunner
{
    /** Creator Rewards RPM band, in dollars per 1,000 qualified views. */
    private const REWARDS_LOW = 0.4;

    private const REWARDS_HIGH = 1.0;

    /** Sponsorship CPM by niche — what a brand pays per 1,000 views of an integration. */
    private const NICHES = [
        'finance' => ['label' => 'Finance & business', 'cpm' => 30.0],
        'tech' => ['label' => 'Tech & apps', 'cpm' => 25.0],
        'beauty' => ['label' => 'Beauty & fashion', 'cpm' => 20.0],
        'fitness' => ['label' => 'Health & fitness', 'cpm' => 18.0],
        'food' => ['label' => 'Food & cooking', 'cpm' => 15.0],
        'lifestyle' => ['label' => 'Lifestyle & vlogs', 'cpm' => 12.0],
        'comedy' => ['label' => 'Comedy & entertainment', 'cpm' => 10.0],
    ];

    public static function key(): string
    {
        return 'tiktok.money-calculator';
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
            'required' => ['monthly_views', 'followers'],
            'additionalProperties' => false,
            'properties' => [
                'monthly_views' => [
                    'type' => 'integer',
                    'title' => 'Monthly views',
                    'minimum' => 0,
                    'maximum' => 10_000_000_000,
                    'examples' => [800000],
                ],
                'followers' => [
                    'type' => 'integer',
                    'title' => 'Followers',
                    'minimum' => 0,
                    'maximum' => 1_000_000_000,
                    'examples' => [45000],
                ],
                'niche' => [
                    'type' => 'string',
                    'title' => 'Niche',
                    'enum' => array_keys(self::NICHES),
                    'default' => 'lifestyle',
                ],
                'qualified_share' => [
                    'type' => 'integer',
                    'title' => 'Views over 1 minute (%)',
                    'description' => 'Creator Rewards only pays on videos longer than a minute.',
                    'minimum' => 0,
                    'maximum' => 100,
                    'default' => 30,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $views = $input->int('monthly_views');
        $followers = $input->int('followers');
        $niche = self::NICHES[$input->string('niche', 'lifestyle')] ?? self::NICHES['lifestyle'];
        $qualified = max(0, min(100, $input->int('qualified_share', 30))) / 100;

        $rewardsViews = $views * $qualified;
        $rewardsLow = $rewardsViews / 1000 * self::REWARDS_LOW;
        $rewardsHigh = $rewardsViews / 1000 * self::REWARDS_HIGH;

        $perPost = $views > 0 ? $views / 1000 * $niche['cpm'] : 0;

        // Brands that price on followers rather than views land around $25–50 per
        // 1,000 followers for a mid-size account.
        $byFollowers = $followers / 1000 * 35;

        return ToolResult::keyValue([
            ['label' => 'Brand deal (per video)', 'value' => '$'.number_format(min($perPost, $byFollowers * 3))
                .' – $'.number_format(max($perPost, $byFollowers)),
                'hint' => "Priced two ways: on views at a \${$niche['cpm']} CPM, and on followers.",
                'tone' => 'positive'],
            ['label' => 'Creator Rewards (monthly)', 'value' => '$'.number_format($rewardsLow)
                .' – $'.number_format($rewardsHigh),
                'hint' => 'Only videos over one minute qualify — you set that at '
                    .round($qualified * 100).'%.'],
            ['label' => 'Qualified views', 'value' => number_format((int) $rewardsViews)],
            ['label' => 'Creator Rewards yearly', 'value' => '$'.number_format($rewardsLow * 12)
                .' – $'.number_format($rewardsHigh * 12)],
            ['label' => 'Three brand deals a month', 'value' => '$'.number_format($perPost * 3),
                'hint' => 'A realistic cadence once you have a media kit.', 'tone' => 'positive'],
        ], summary: 'One sponsored video is worth roughly $'.number_format($perPost)
            .' — about '.($rewardsHigh > 0 ? round($perPost / max($rewardsHigh, 0.01), 1) : 0)
            .'× what a whole month of Creator Rewards pays.')
            ->withWarnings([
                'Creator Rewards rates vary by country and change frequently. Treat the ad-style '
                .'figures as a floor and brand deals as the real business.',
            ]);
    }
}
