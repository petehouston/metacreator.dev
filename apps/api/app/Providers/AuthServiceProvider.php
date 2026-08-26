<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Super Admins bypass every check. Returning null (rather than false) for
        // everyone else lets the normal policy chain run.
        Gate::before(fn (User $user) => $user->hasRole('super-admin') ? true : null);

        // Horizon and other dashboards are staff-only.
        Gate::define('viewHorizon', fn (User $user) => $user->hasAnyRole(['super-admin', 'admin']));
    }
}
