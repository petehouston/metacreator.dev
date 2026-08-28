<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;

/**
 * Distance from the YouTube Partner Program, measured against both thresholds.
 *
 * Nothing here is fetched: watch hours and Shorts views are private analytics that
 * only the channel owner can read, so asking for them is more honest than scraping
 * a number we cannot see. What the tool adds is the arithmetic people get wrong —
 * that the two watch-time routes are alternatives rather than a sum, that the fan
 * funding tier arrives long before ads do, and that the checkbox requirements
 * disqualify a channel outright however large it is.
 */
final class YouTubePartnerProgramCheckerRunner implements Cacheable, ToolRunner
{
    /** Fan funding first, ads second — the order a channel actually reaches them. */
    private const TIERS = [
        'fan_funding' => [
            'label' => 'Fan funding (memberships, Super Thanks, Shopping)',
            'subscribers' => 500,
            'watch_hours' => 3_000.0,
            'shorts_views' => 3_000_000,
            'uploads_90d' => 3,
        ],
        'ads' => [
            'label' => 'Ad revenue and YouTube Premium share',
            'subscribers' => 1_000,
            'watch_hours' => 4_000.0,
            'shorts_views' => 10_000_000,
            'uploads_90d' => 0,
        ],
    ];

    public static function key(): string
    {
        return 'youtube.partner-program-checker';
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
                    'title' => 'Subscribers',
                    'minimum' => 0,
                    'maximum' => 500_000_000,
                    'examples' => [740],
                ],
                'watch_hours' => [
                    'type' => 'number',
                    'title' => 'Valid public watch hours (last 12 months)',
                    'description' => 'YouTube Studio → Analytics → Overview, with the date range set to the last 365 days.',
                    'minimum' => 0,
                    'maximum' => 100_000_000,
                    'default' => 0,
                ],
                'shorts_views' => [
                    'type' => 'integer',
                    'title' => 'Valid public Shorts views (last 90 days)',
                    'description' => 'The alternative route to the same threshold — you need one or the other, not both.',
                    'minimum' => 0,
                    'maximum' => 10_000_000_000,
                    'default' => 0,
                ],
                'uploads_90d' => [
                    'type' => 'integer',
                    'title' => 'Public uploads in the last 90 days',
                    'description' => 'Only the fan funding tier requires these. Shorts count.',
                    'minimum' => 0,
                    'maximum' => 1000,
                    'default' => 0,
                ],
                'country_eligible' => [
                    'type' => 'boolean',
                    'title' => 'You live in a country or region where the Partner Program is available',
                    'default' => true,
                ],
                'no_active_strikes' => [
                    'type' => 'boolean',
                    'title' => 'No active Community Guidelines strike on the channel',
                    'default' => true,
                ],
                'original_content' => [
                    'type' => 'boolean',
                    'title' => 'The channel is original content, not reused or mass-produced',
                    'default' => true,
                ],
                'two_step_verification' => [
                    'type' => 'boolean',
                    'title' => '2-Step Verification is on and an AdSense account is ready',
                    'default' => true,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $subscribers = $input->int('subscribers');
        $watchHours = $input->float('watch_hours');
        $shortsViews = $input->int('shorts_views');
        $uploads = $input->int('uploads_90d');

        $blockers = $this->blockers($input);

        $tiers = [];
        foreach (self::TIERS as $key => $tier) {
            $tiers[$key] = $this->assess($tier, $subscribers, $watchHours, $shortsViews, $uploads);
        }

        // The nearest tier the channel has not reached is the one worth scoring
        // against; a channel past both is measured against the harder one.
        $target = $tiers['fan_funding']['met'] ? 'ads' : 'fan_funding';
        $assessment = $tiers[$target];

        $sections = [
            [
                'key' => 'subscribers',
                'label' => 'Subscribers',
                'score' => $this->percentage($subscribers, $assessment['tier']['subscribers']),
                'weight' => 0.3,
                'notes' => [number_format($subscribers).' of '.number_format($assessment['tier']['subscribers']).' needed'],
            ],
            [
                'key' => 'watch',
                'label' => 'Watch time or Shorts views',
                'score' => $assessment['watch_score'],
                'weight' => 0.45,
                'notes' => $assessment['watch_notes'],
            ],
            [
                'key' => 'activity',
                'label' => 'Recent uploads',
                'score' => $assessment['tier']['uploads_90d'] === 0
                    ? 100
                    : $this->percentage($uploads, $assessment['tier']['uploads_90d']),
                'weight' => 0.05,
                'notes' => $assessment['tier']['uploads_90d'] === 0
                    ? ['Not required for this tier.']
                    : [$uploads.' of '.$assessment['tier']['uploads_90d'].' public uploads in 90 days'],
            ],
            [
                'key' => 'eligibility',
                'label' => 'Account requirements',
                'score' => $blockers === [] ? 100 : 0,
                'weight' => 0.2,
                'notes' => $blockers === []
                    ? ['All four account requirements are met.']
                    : array_map(fn (array $blocker) => $blocker['title'], $blockers),
            ],
        ];

        $overall = (int) round(array_sum(array_map(
            fn (array $section) => $section['score'] * $section['weight'],
            $sections,
        )));

        return ToolResult::score(
            overall: $overall,
            sections: $sections,
            fixes: array_slice([...$blockers, ...$assessment['fixes']], 0, 6),
            summary: $this->summary($tiers, $blockers !== []),
        )->withMeta([
            'fan_funding_eligible' => $tiers['fan_funding']['met'] && $blockers === [],
            'ads_eligible' => $tiers['ads']['met'] && $blockers === [],
            'target_tier' => $target,
        ])->withWarnings([
            'Meeting every threshold makes a channel eligible to apply. Acceptance is a manual review '
            .'of the whole channel against YouTube’s monetization policies, and it is not automatic.',
        ]);
    }

    /**
     * @param  array{label: string, subscribers: int, watch_hours: float, shorts_views: int, uploads_90d: int}  $tier
     * @return array{
     *     tier: array{label: string, subscribers: int, watch_hours: float, shorts_views: int, uploads_90d: int},
     *     met: bool,
     *     watch_score: int,
     *     watch_notes: list<string>,
     *     fixes: list<array{severity: string, title: string, detail: string}>,
     * }
     */
    private function assess(array $tier, int $subscribers, float $watchHours, int $shortsViews, int $uploads): array
    {
        $hoursScore = $this->percentage($watchHours, $tier['watch_hours']);
        $shortsScore = $this->percentage($shortsViews, $tier['shorts_views']);

        // The two routes are alternatives, so progress is the better of them —
        // adding them together is the mistake this tool exists to prevent.
        $viaShorts = $shortsScore > $hoursScore;
        $watchScore = max($hoursScore, $shortsScore);

        $met = $subscribers >= $tier['subscribers']
            && $watchScore >= 100
            && $uploads >= $tier['uploads_90d'];

        $fixes = [];

        if ($subscribers < $tier['subscribers']) {
            $fixes[] = [
                'severity' => 'high',
                'title' => number_format($tier['subscribers'] - $subscribers).' more subscribers',
                'detail' => "{$tier['label']} needs ".number_format($tier['subscribers']).' subscribers.',
            ];
        }

        if ($watchScore < 100) {
            $fixes[] = $viaShorts
                ? [
                    'severity' => 'high',
                    'title' => number_format($tier['shorts_views'] - $shortsViews).' more Shorts views in 90 days',
                    'detail' => 'You are closer on the Shorts route. It is a rolling 90-day window, so views '
                        .'expire out of it — the count has to be reached, not accumulated.',
                ]
                : [
                    'severity' => 'high',
                    'title' => number_format($tier['watch_hours'] - $watchHours, 0).' more watch hours',
                    'detail' => 'Watch hours count over a rolling 12 months and only from public, long-form '
                        .'videos. Shorts views, live premieres before they end and deleted videos do not count.',
                ];
        }

        if ($uploads < $tier['uploads_90d']) {
            $fixes[] = [
                'severity' => 'medium',
                'title' => 'Publish '.($tier['uploads_90d'] - $uploads).' more public video(s)',
                'detail' => 'The fan funding tier requires 3 public uploads in the last 90 days. '
                    .'Shorts count towards it.',
            ];
        }

        return [
            'tier' => $tier,
            'met' => $met,
            'watch_score' => $watchScore,
            'watch_notes' => [
                number_format($watchHours, 0).' of '.number_format($tier['watch_hours'], 0).' watch hours',
                number_format($shortsViews).' of '.number_format($tier['shorts_views']).' Shorts views',
                $viaShorts ? 'Shorts are your faster route.' : 'Long-form watch time is your faster route.',
            ],
            'fixes' => $fixes,
        ];
    }

    /** @return list<array{severity: string, title: string, detail: string}> */
    private function blockers(ToolInput $input): array
    {
        $requirements = [
            'country_eligible' => [
                'The Partner Program is not available where you are',
                'YouTube only accepts applications from countries and regions where the programme has launched. '
                .'Nothing about the channel changes this.',
            ],
            'no_active_strikes' => [
                'An active Community Guidelines strike blocks the application',
                'Strikes expire 90 days after they are issued. Applications are refused while one is live, '
                .'however far past the thresholds the channel is.',
            ],
            'original_content' => [
                'Reused or mass-produced content is refused',
                'Compilations, reuploads and templated bulk content are rejected at review. Commentary, '
                .'editing and original narration over third-party footage are what make it pass.',
            ],
            'two_step_verification' => [
                'Turn on 2-Step Verification and connect AdSense',
                'Both are hard requirements checked at application time, and both take minutes to set up.',
            ],
        ];

        $blockers = [];

        foreach ($requirements as $key => [$title, $detail]) {
            if (! $input->bool($key, true)) {
                $blockers[] = ['severity' => 'high', 'title' => $title, 'detail' => $detail];
            }
        }

        return $blockers;
    }

    /** @param  array<string, array{met: bool, ...}>  $tiers */
    private function summary(array $tiers, bool $blocked): string
    {
        if ($blocked) {
            return 'The thresholds are only half of it — an account requirement below is not met, '
                .'and that is a hard no whatever the numbers say.';
        }

        return match (true) {
            $tiers['ads']['met'] => 'You meet the bar for ad revenue. Apply from YouTube Studio → Earn.',
            $tiers['fan_funding']['met'] => 'You meet the fan funding bar — apply now for memberships and Super Thanks, '
                .'and keep going for ads.',
            default => 'Not eligible yet. The fan funding tier is the nearer target, and it is worth applying for '
                .'on the day you reach it.',
        };
    }

    private function percentage(float $have, float $need): int
    {
        return $need <= 0 ? 100 : (int) min(100, round($have / $need * 100));
    }
}
