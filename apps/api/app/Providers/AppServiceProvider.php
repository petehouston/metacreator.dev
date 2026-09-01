<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Users\Models\User;
use App\Http\Middleware\IdentifyVisitor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Fail loudly in development on N+1 queries and mass-assignment mistakes,
        // rather than discovering them in production.
        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());
        Model::unguard(false);

        $this->configureRateLimiting();
        $this->configurePasswordRules();
    }

    /**
     * One password policy, defined once.
     *
     * Length over composition rules — NIST dropped the character-class advice years
     * ago. The breach-corpus check is what actually stops account takeovers, but it
     * calls an external API, so it is off outside production: tests must not depend
     * on the network, and a local developer must not be blocked by it.
     */
    private function configurePasswordRules(): void
    {
        Password::defaults(function (): Password {
            $rule = Password::min(10);

            return app()->isProduction() ? $rule->uncompromised() : $rule;
        });
    }

    /**
     * Short-window burst protection.
     *
     * This is not the quota — that lives in QuotaService and is about cost. This is
     * about stopping loops and scrapers, so it is keyed per actor and generous enough
     * that a real person never notices it.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('tool-runs', function (Request $request): Limit {
            $user = $request->user();

            if ($user instanceof User) {
                return Limit::perMinute($user->can('tools.bypass_quota') ? 600 : 60)
                    ->by("u:{$user->id}");
            }

            return Limit::perMinute(15)
                ->by('v:'.$request->attributes->get(IdentifyVisitor::ATTRIBUTE, $request->ip()));
        });

        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(5)->by('email:'.$request->input('email')),
            Limit::perMinute(20)->by('ip:'.$request->ip()),
        ]);

        // Two keys, two different abuses: one address being flooded with confirmation
        // mail, and one client seeding the list with addresses it does not own.
        RateLimiter::for('newsletter', fn (Request $request) => [
            Limit::perMinute(3)->by('email:'.$request->input('email')),
            Limit::perMinute(10)->by('ip:'.$request->ip()),
        ]);

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
