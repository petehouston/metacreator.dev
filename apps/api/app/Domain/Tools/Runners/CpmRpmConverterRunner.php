<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;

/**
 * The two numbers in YouTube Studio that nobody can keep straight, converted in
 * both directions with the arithmetic shown.
 *
 * They are not two versions of the same figure. **CPM** is what an advertiser pays
 * for a thousand ad impressions — gross, before YouTube's cut, and measured against
 * *monetized playbacks* rather than views. **RPM** is what lands in your account
 * per thousand **views**, after the cut, across every revenue source. A channel
 * with a $14 CPM and a $4 RPM has not been cheated; it has had two thirds of its
 * views go unmonetized and then paid YouTube its 45%.
 *
 * So the conversion needs two facts beyond the number itself: the share of views
 * that carried an ad, and the revenue split. YouTube publishes the split for
 * long-form watch-page ads — the creator keeps 55% — and the monetized-playback
 * rate is in your own analytics. Everything else follows:
 *
 *     RPM = CPM × monetized playback rate × revenue share
 *
 * That identity is the whole tool, run forwards or backwards.
 */
final class CpmRpmConverterRunner implements Cacheable, ToolRunner
{
    /** YouTube's published creator share of watch-page ad revenue on long-form video. */
    private const LONG_FORM_SHARE = 55.0;

    public static function key(): string
    {
        return 'youtube.cpm-rpm-converter';
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
            'required' => ['direction', 'amount'],
            'additionalProperties' => false,
            'properties' => [
                'direction' => [
                    'type' => 'string',
                    'title' => 'Convert',
                    'enum' => ['cpm_to_rpm', 'rpm_to_cpm'],
                    'default' => 'cpm_to_rpm',
                ],
                'amount' => [
                    'type' => 'number',
                    'title' => 'The figure from Studio ($)',
                    'description' => 'Your playback-based CPM, or your RPM — whichever you are '
                        .'converting from.',
                    'minimum' => 0,
                    'maximum' => 1000,
                    'examples' => [14.5],
                ],
                'monetized_rate' => [
                    'type' => 'integer',
                    'title' => 'Monetized playbacks (% of views)',
                    'description' => 'Studio → Revenue → “Monetized playbacks” divided by views. '
                        .'For most channels it lands between 30% and 60%.',
                    'minimum' => 1,
                    'maximum' => 100,
                    'default' => 40,
                ],
                'revenue_share' => [
                    'type' => 'integer',
                    'title' => 'Your revenue share (%)',
                    'description' => '55% for watch-page ads on long-form video, which is YouTube’s '
                        .'published split.',
                    'minimum' => 1,
                    'maximum' => 100,
                    'default' => 55,
                ],
                'monthly_views' => [
                    'type' => 'integer',
                    'title' => 'Monthly views (optional)',
                    'description' => 'Adds the monthly figure the two rates imply.',
                    'minimum' => 0,
                    'maximum' => 10_000_000_000,
                    'default' => 0,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $direction = $input->string('direction', 'cpm_to_rpm');
        $amount = max(0.0, $input->float('amount'));
        $monetized = max(1, min(100, $input->int('monetized_rate', 40))) / 100;
        $share = max(1, min(100, $input->int('revenue_share', 55))) / 100;
        $views = max(0, $input->int('monthly_views'));

        $factor = $monetized * $share;

        if ($direction === 'cpm_to_rpm') {
            $cpm = $amount;
            $rpm = $cpm * $factor;
        } else {
            $rpm = $amount;
            $cpm = $factor > 0 ? $rpm / $factor : 0.0;
        }

        $pairs = [
            ['label' => 'CPM (what advertisers pay)', 'value' => '$'.number_format($cpm, 2),
                'hint' => 'Gross, per 1,000 monetized playbacks.',
                'tone' => $direction === 'rpm_to_cpm' ? 'positive' : null],
            ['label' => 'RPM (what you keep)', 'value' => '$'.number_format($rpm, 2),
                'hint' => 'Net, per 1,000 views — including the views that carried no ad.',
                'tone' => $direction === 'cpm_to_rpm' ? 'positive' : null],
            ['label' => 'The conversion', 'value' => '× '.number_format($factor, 3),
                'hint' => round($monetized * 100).'% monetized playbacks × '
                    .round($share * 100).'% revenue share.'],
            ['label' => 'YouTube’s cut of the ad', 'value' => '$'.number_format($cpm * $monetized * (1 - $share), 2),
                'hint' => 'Per 1,000 views, at this CPM.'],
            ['label' => 'Lost to unmonetized views', 'value' => '$'
                .number_format($cpm * (1 - $monetized) * $share, 2),
                'hint' => 'Per 1,000 views. This is usually the larger of the two gaps, and unlike '
                    .'the split it is one you can move.'],
        ];

        if ($views > 0) {
            $pairs[] = ['label' => 'Monthly revenue at '.number_format($views).' views',
                'value' => '$'.number_format($rpm * $views / 1000, 2), 'tone' => 'positive'];
            $pairs[] = ['label' => 'A year at that rate',
                'value' => '$'.number_format($rpm * $views / 1000 * 12, 2)];
        }

        return ToolResult::keyValue(
            array_map(fn (array $pair) => array_filter($pair, fn ($v) => $v !== null), $pairs),
            summary: 'A $'.number_format($cpm, 2).' CPM is a $'.number_format($rpm, 2)
                .' RPM at this channel’s numbers — '.round((1 - $factor) * 100).'% of the advertiser’s '
                .'money does not reach you, and most of that gap is unmonetized views rather than '
                .'YouTube’s split.',
        )->withMeta([
            'cpm' => round($cpm, 2),
            'rpm' => round($rpm, 2),
            'factor' => round($factor, 4),
        ])->withWarnings([
            'This is the identity behind the two figures, not a forecast. Both move with season, '
            .'geography and niche — the same channel’s CPM in January is not its CPM in November.',
            $share !== self::LONG_FORM_SHARE / 100
                ? 'You changed the revenue share from the published 55%. That figure is right for '
                    .'watch-page ads on long-form video; Shorts are paid from a pool after music '
                    .'licensing and do not use this identity at all.'
                : 'Shorts are not covered by this. Their revenue comes from a pool split after music '
                    .'licensing, so a Shorts RPM cannot be reached from a CPM this way.',
        ]);
    }
}
