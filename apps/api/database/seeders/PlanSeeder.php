<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Billing\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Reference data. Prices live in Stripe; `stripe_price_id` binds them, and changing
 * a price means a NEW plan row so existing subscribers are never silently re-priced.
 */
final class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'key' => 'pass_7d',
                'name' => '7-Day Pass',
                'tagline' => 'Full access for a week. No subscription, no card kept on file.',
                'billing_mode' => 'one_time',
                'interval' => null,
                'amount' => 900,
                'duration_days' => 7,
                'sort_order' => 1,
                'features' => [
                    'Every premium tool for 7 days',
                    '300 runs per day',
                    'Full export and download',
                    'Unlimited run history during the pass',
                ],
                'limits' => ['runs_per_day' => 300, 'export' => true, 'history_days' => null],
            ],
            [
                'key' => 'pro_monthly',
                'name' => 'Pro Monthly',
                'tagline' => 'Everything, every month.',
                'billing_mode' => 'subscription',
                'interval' => 'month',
                'amount' => 1900,
                'sort_order' => 2,
                'features' => [
                    'Every tool, including premium',
                    '1,000 runs per day',
                    'Unlimited run history',
                    'Exports, bulk operations and media kits',
                    'Priority support',
                ],
                'limits' => ['runs_per_day' => 1000, 'export' => true, 'history_days' => null],
            ],
            [
                'key' => 'pro_yearly',
                'name' => 'Pro Yearly',
                'tagline' => 'Two months free, billed once a year.',
                'billing_mode' => 'subscription',
                'interval' => 'year',
                'amount' => 18000,
                'sort_order' => 3,
                'is_highlighted' => true,
                'features' => [
                    'Everything in Pro Monthly',
                    'Two months free versus monthly',
                    'Early access to new tools',
                    'Annual media kit refresh',
                ],
                'limits' => ['runs_per_day' => 1000, 'export' => true, 'history_days' => null],
            ],
        ];

        foreach ($plans as $plan) {
            // `seedRow` rather than `updateOrCreate`: a price or a feature list
            // edited in the console is a business decision, and the next deploy is
            // not allowed to reset it to what this file happened to say.
            Plan::seedRow(
                ['key' => $plan['key']],
                [...$plan, 'currency' => 'USD', 'is_active' => true],
            );
        }
    }
}
