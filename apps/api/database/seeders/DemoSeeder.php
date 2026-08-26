<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Billing\Models\AccessPass;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Tools\Models\Tool;
use App\Domain\Users\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local development data: one account per role, plus the billing states that are
 * otherwise tedious to reproduce by hand.
 *
 * Never runs in production — see {@see DatabaseSeeder}.
 */
final class DemoSeeder extends Seeder
{
    private const PASSWORD = 'password';

    public function run(): void
    {
        $accounts = [
            ['email' => 'admin@metacreator.dev', 'name' => 'Ada Admin', 'role' => 'super-admin'],
            ['email' => 'editor@metacreator.dev', 'name' => 'Eli Editor', 'role' => 'editor'],
            ['email' => 'junior@metacreator.dev', 'name' => 'Jun Junior', 'role' => 'editor-restricted'],
            ['email' => 'support@metacreator.dev', 'name' => 'Sam Support', 'role' => 'support'],
            ['email' => 'accountant@metacreator.dev', 'name' => 'Ash Accounts', 'role' => 'accountant'],
        ];

        foreach ($accounts as $account) {
            $user = $this->user($account['email'], $account['name']);
            $user->syncRoles([$account['role']]);
        }

        // A free user, for testing the account wall and the paywall.
        $this->user('free@metacreator.dev', 'Frankie Free');

        // A subscriber, for testing everything behind the paywall.
        $pro = $this->user('pro@metacreator.dev', 'Robin Pro');
        $this->giveSubscription($pro, 'pro_monthly');

        // Someone mid-pass, for testing the expiry and upgrade prompts.
        $passHolder = $this->user('pass@metacreator.dev', 'Pat Pass');
        $this->givePass($passHolder, days: 3);

        // A comped grant, for testing the `grant` access reason end to end.
        $premiumTool = Tool::query()->where('tier', 'account')->first();

        if ($premiumTool !== null) {
            $free = User::query()->where('email', 'free@metacreator.dev')->first();

            $free?->toolGrants()->updateOrCreate(
                ['tool_id' => $premiumTool->id],
                ['reason' => 'Seeded demo grant', 'expires_at' => now()->addMonth()],
            );
        }

        $this->command->info('Demo accounts seeded — password for all of them is "'.self::PASSWORD.'".');
    }

    private function user(string $email, string $name): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);

        $user->fill([
            'name' => $name,
            'display_name' => $name,
            'password' => Hash::make(self::PASSWORD),
            'marketing_opt_in' => true,
        ]);

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()]);
        }
        $user->save();

        return $user;
    }

    private function giveSubscription(User $user, string $planKey): void
    {
        $plan = Plan::query()->where('key', $planKey)->firstOrFail();

        Subscription::query()->updateOrCreate(
            ['user_id' => $user->id, 'plan_id' => $plan->id],
            [
                'stripe_id' => 'sub_demo_'.$user->id,
                'stripe_status' => 'active',
                'current_period_start' => now()->subDays(5),
                'current_period_end' => now()->addDays(25),
            ],
        );
    }

    private function givePass(User $user, int $days): void
    {
        $plan = Plan::query()->where('key', 'pass_7d')->firstOrFail();

        AccessPass::query()->updateOrCreate(
            ['user_id' => $user->id, 'stripe_payment_intent' => 'pi_demo_'.$user->id],
            [
                'plan_id' => $plan->id,
                'source' => 'purchase',
                'starts_at' => now()->subDays(7 - $days),
                'expires_at' => now()->addDays($days),
            ],
        );
    }
}
