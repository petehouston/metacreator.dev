<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;

/**
 * What a channel actually earns, split by where the money comes from.
 *
 * Most "YouTube money calculators" multiply views by a made-up CPM and stop. Ad
 * revenue is usually the smaller half of a working channel's income, so this shows
 * an RPM band for the niche *and* the sponsorship value of the same audience.
 */
final class YouTubeMoneyCalculatorRunner implements Cacheable, ToolRunner
{
    /**
     * Realistic RPM bands (revenue per 1,000 views, after YouTube's 45% share) by
     * niche, and the typical CPM a sponsor pays for an integration in it.
     *
     * @var array<string, array{label: string, low: float, high: float, sponsor_cpm: float}>
     */
    private const NICHES = [
        'finance' => ['label' => 'Finance & investing', 'low' => 8.0, 'high' => 24.0, 'sponsor_cpm' => 35.0],
        'business' => ['label' => 'Business & marketing', 'low' => 6.0, 'high' => 18.0, 'sponsor_cpm' => 30.0],
        'tech' => ['label' => 'Tech & software', 'low' => 5.0, 'high' => 14.0, 'sponsor_cpm' => 25.0],
        'education' => ['label' => 'Education', 'low' => 3.5, 'high' => 9.0, 'sponsor_cpm' => 18.0],
        'health' => ['label' => 'Health & fitness', 'low' => 3.0, 'high' => 8.0, 'sponsor_cpm' => 20.0],
        'lifestyle' => ['label' => 'Lifestyle & vlogs', 'low' => 2.0, 'high' => 6.0, 'sponsor_cpm' => 15.0],
        'gaming' => ['label' => 'Gaming', 'low' => 1.5, 'high' => 5.0, 'sponsor_cpm' => 12.0],
        'entertainment' => ['label' => 'Entertainment & comedy', 'low' => 1.2, 'high' => 4.5, 'sponsor_cpm' => 12.0],
        'shorts' => ['label' => 'Shorts-first channel', 'low' => 0.05, 'high' => 0.25, 'sponsor_cpm' => 8.0],
    ];

    /** Audience geography moves RPM more than almost anything else. */
    private const GEOS = [
        'us_uk' => ['label' => 'Mostly US / UK / CA / AU', 'multiplier' => 1.0],
        'europe' => ['label' => 'Mostly Western Europe', 'multiplier' => 0.8],
        'mixed' => ['label' => 'Mixed worldwide', 'multiplier' => 0.6],
        'emerging' => ['label' => 'Mostly emerging markets', 'multiplier' => 0.3],
    ];

    public static function key(): string
    {
        return 'youtube.money-calculator';
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
            'required' => ['monthly_views'],
            'additionalProperties' => false,
            'properties' => [
                'monthly_views' => [
                    'type' => 'integer',
                    'title' => 'Monthly views',
                    'minimum' => 0,
                    'maximum' => 10_000_000_000,
                    'examples' => [250000],
                ],
                'niche' => [
                    'type' => 'string',
                    'title' => 'Niche',
                    'enum' => array_keys(self::NICHES),
                    'default' => 'lifestyle',
                ],
                'audience' => [
                    'type' => 'string',
                    'title' => 'Where your audience is',
                    'enum' => array_keys(self::GEOS),
                    'default' => 'mixed',
                ],
                'monetised_share' => [
                    'type' => 'integer',
                    'title' => 'Monetised playbacks (%)',
                    'description' => 'Not every view shows an ad. 55–70% is typical.',
                    'minimum' => 0,
                    'maximum' => 100,
                    'default' => 60,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $views = $input->int('monthly_views');
        $niche = self::NICHES[$input->string('niche', 'lifestyle')] ?? self::NICHES['lifestyle'];
        $geo = self::GEOS[$input->string('audience', 'mixed')] ?? self::GEOS['mixed'];
        $monetised = max(0, min(100, $input->int('monetised_share', 60))) / 100;

        $effectiveViews = $views * $monetised;
        $low = $effectiveViews / 1000 * $niche['low'] * $geo['multiplier'];
        $high = $effectiveViews / 1000 * $niche['high'] * $geo['multiplier'];

        // A sponsor pays on total views, not monetised ones — they do not care
        // whether YouTube served an ad.
        $sponsorship = $views / 1000 * $niche['sponsor_cpm'] * $geo['multiplier'];

        return ToolResult::keyValue([
            ['label' => 'Ad revenue (monthly)', 'value' => $this->range($low, $high),
                'hint' => "RPM band for {$niche['label']}, adjusted for audience location.", 'tone' => 'positive'],
            ['label' => 'Ad revenue (yearly)', 'value' => $this->range($low * 12, $high * 12)],
            ['label' => 'Effective RPM', 'value' => '$'.number_format($niche['low'] * $geo['multiplier'], 2)
                .' – $'.number_format($niche['high'] * $geo['multiplier'], 2),
                'hint' => 'Per 1,000 monetised views, your share after YouTube takes 45%.'],
            ['label' => 'One sponsored integration', 'value' => '$'.number_format($sponsorship),
                'hint' => 'At a $'.number_format($niche['sponsor_cpm'], 0).' CPM on '.number_format($views).' views.',
                'tone' => 'positive'],
            ['label' => 'Monetised views used', 'value' => number_format((int) $effectiveViews),
                'hint' => round($monetised * 100).'% of your total views.'],
        ], summary: 'Roughly '.$this->range($low, $high).' a month from ads — and about $'
            .number_format($sponsorship).' for a single sponsored slot at the same audience size.')
            ->withWarnings([
                'These are bands, not predictions. Your real RPM depends on watch time, ad formats and '
                .'seasonality — Q4 routinely pays double what January does.',
            ]);
    }

    private function range(float $low, float $high): string
    {
        return '$'.number_format($low).' – $'.number_format($high);
    }
}
