<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Exceptions\ToolExecutionException;

/**
 * Engagement rate, benchmarked honestly.
 *
 * The number itself is trivial arithmetic; the value is in the benchmark. Rates are
 * not comparable across platforms or account sizes — 2% is excellent on Instagram at
 * 500k followers and mediocre on TikTok at 5k — so this always answers "is that
 * good?" for the specific platform and follower band, which is the question people
 * actually have.
 */
final class EngagementRateCalculatorRunner implements Cacheable, ToolRunner
{
    /**
     * Median engagement rate by platform and follower band, from published industry
     * studies. Bands are upper bounds on follower count.
     *
     * @var array<string, array<int, float>>
     */
    private const BENCHMARKS = [
        'instagram' => [1000 => 5.6, 10000 => 3.8, 50000 => 2.6, 100000 => 2.0, 500000 => 1.6, PHP_INT_MAX => 1.1],
        'tiktok' => [1000 => 12.0, 10000 => 9.6, 50000 => 7.4, 100000 => 6.2, 500000 => 5.1, PHP_INT_MAX => 4.0],
        'youtube' => [1000 => 4.2, 10000 => 3.1, 50000 => 2.4, 100000 => 1.9, 500000 => 1.5, PHP_INT_MAX => 1.2],
        'x' => [1000 => 1.4, 10000 => 0.9, 50000 => 0.6, 100000 => 0.5, 500000 => 0.4, PHP_INT_MAX => 0.3],
        'facebook' => [1000 => 1.2, 10000 => 0.8, 50000 => 0.5, 100000 => 0.4, 500000 => 0.3, PHP_INT_MAX => 0.2],
        'linkedin' => [1000 => 4.8, 10000 => 3.2, 50000 => 2.1, 100000 => 1.6, 500000 => 1.2, PHP_INT_MAX => 0.9],
    ];

    public static function key(): string
    {
        return 'analytics.engagement-rate-calculator';
    }

    public function cacheTtl(): int
    {
        return 3600;
    }

    public function inputSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'required' => ['platform', 'followers', 'likes'],
            'additionalProperties' => false,
            'properties' => [
                'platform' => [
                    'type' => 'string',
                    'title' => 'Platform',
                    'enum' => array_keys(self::BENCHMARKS),
                    'default' => 'instagram',
                ],
                'followers' => [
                    'type' => 'integer',
                    'title' => 'Followers / subscribers',
                    'minimum' => 1,
                    'maximum' => 1_000_000_000,
                    'examples' => [12500],
                ],
                'likes' => [
                    'type' => 'integer',
                    'title' => 'Likes on the post',
                    'minimum' => 0,
                    'default' => 0,
                ],
                'comments' => [
                    'type' => 'integer',
                    'title' => 'Comments',
                    'minimum' => 0,
                    'default' => 0,
                ],
                'shares' => [
                    'type' => 'integer',
                    'title' => 'Shares / retweets',
                    'minimum' => 0,
                    'default' => 0,
                ],
                'saves' => [
                    'type' => 'integer',
                    'title' => 'Saves / bookmarks',
                    'minimum' => 0,
                    'default' => 0,
                ],
                'reach' => [
                    'type' => 'integer',
                    'title' => 'Reach (optional)',
                    'description' => 'If you know how many accounts saw the post, we also calculate engagement by reach — the metric brands ask for.',
                    'minimum' => 0,
                    'default' => 0,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $platform = $input->string('platform', 'instagram');
        $followers = $input->int('followers');

        if ($followers < 1) {
            throw ToolExecutionException::invalidInput(
                'Follower count must be at least 1.',
                ['followers' => 'Enter your follower or subscriber count.'],
            );
        }

        $interactions = $input->int('likes') + $input->int('comments')
            + $input->int('shares') + $input->int('saves');

        $reach = $input->int('reach');

        $byFollowers = round(($interactions / $followers) * 100, 2);
        $byReach = $reach > 0 ? round(($interactions / $reach) * 100, 2) : null;

        $benchmark = $this->benchmark($platform, $followers);
        $verdict = $this->verdict($byFollowers, $benchmark);

        $pairs = [
            [
                'label' => 'Engagement rate (by followers)',
                'value' => "{$byFollowers}%",
                'hint' => "Median for {$platform} accounts your size is {$benchmark}%",
                'tone' => $verdict['tone'],
            ],
            [
                'label' => 'Total interactions',
                'value' => number_format($interactions),
                'hint' => 'Likes + comments + shares + saves',
            ],
            [
                'label' => 'How you compare',
                'value' => $verdict['label'],
                'hint' => $verdict['detail'],
                'tone' => $verdict['tone'],
            ],
        ];

        if ($byReach !== null) {
            // Reach-based ER is the number brands negotiate on, so it goes second.
            array_splice($pairs, 1, 0, [[
                'label' => 'Engagement rate (by reach)',
                'value' => "{$byReach}%",
                'hint' => 'This is the figure most brand deals are measured against.',
                'tone' => $byReach >= 4 ? 'positive' : 'neutral',
            ]]);
        }

        $pairs[] = [
            'label' => 'Interactions needed to hit the median',
            'value' => number_format((int) ceil(($benchmark / 100) * $followers)),
            'hint' => 'On a post with your follower count.',
        ];

        return ToolResult::keyValue($pairs, summary: $verdict['summary'])
            ->withMeta(['platform' => $platform, 'benchmark' => $benchmark]);
    }

    private function benchmark(string $platform, int $followers): float
    {
        $bands = self::BENCHMARKS[$platform] ?? self::BENCHMARKS['instagram'];

        foreach ($bands as $upperBound => $rate) {
            if ($followers <= $upperBound) {
                return $rate;
            }
        }

        return (float) end($bands);
    }

    /** @return array{label: string, tone: string, detail: string, summary: string} */
    private function verdict(float $rate, float $benchmark): array
    {
        $ratio = $benchmark > 0 ? $rate / $benchmark : 0;

        return match (true) {
            $ratio >= 2.0 => [
                'label' => 'Exceptional',
                'tone' => 'positive',
                'detail' => 'More than double the median for accounts your size.',
                'summary' => "Your {$rate}% engagement rate is exceptional — over 2× the benchmark for your follower band.",
            ],
            $ratio >= 1.25 => [
                'label' => 'Above average',
                'tone' => 'positive',
                'detail' => 'Comfortably ahead of similar accounts.',
                'summary' => "Your {$rate}% engagement rate is above average for accounts your size.",
            ],
            $ratio >= 0.75 => [
                'label' => 'On par',
                'tone' => 'neutral',
                'detail' => 'Right around the median for accounts your size.',
                'summary' => "Your {$rate}% engagement rate is typical for accounts your size.",
            ],
            $ratio >= 0.4 => [
                'label' => 'Below average',
                'tone' => 'warning',
                'detail' => 'Worth testing hooks, posting times and formats.',
                'summary' => "Your {$rate}% engagement rate is below the median for your follower band.",
            ],
            default => [
                'label' => 'Well below average',
                'tone' => 'negative',
                'detail' => 'Often a sign of audience quality problems or a format mismatch.',
                'summary' => "Your {$rate}% engagement rate is well below the benchmark — worth auditing your audience quality.",
            ],
        };
    }
}
