<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Account\AvatarController;
use App\Http\Controllers\Api\V1\Account\DeviceController;
use App\Http\Controllers\Api\V1\Account\EntitlementsController;
use App\Http\Controllers\Api\V1\Account\NotificationController;
use App\Http\Controllers\Api\V1\Account\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\Account\PasswordController;
use App\Http\Controllers\Api\V1\Account\ProfileController;
use App\Http\Controllers\Api\V1\Account\ToolRunHistoryController;
use App\Http\Controllers\Api\V1\Auth\ConfirmPasswordController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\MagicLinkController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\Blog\BlogController;
use App\Http\Controllers\Api\V1\Catalog\ToolCatalogController;
use App\Http\Controllers\Api\V1\Tools\RunToolController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| Conventions are documented in docs/05-api.md. In short: resource-oriented,
| one response envelope, machine-readable error codes, and every admin route
| declaring the permission it requires (asserted by a test).
|
*/

// ── Public ───────────────────────────────────────────────────────────────────

Route::prefix('catalog')->group(function (): void {
    Route::get('tools', [ToolCatalogController::class, 'index'])->name('catalog.tools.index');
    Route::get('tools/{slug}', [ToolCatalogController::class, 'show'])->name('catalog.tools.show');
    Route::get('categories', [ToolCatalogController::class, 'categories'])->name('catalog.categories');
});

// The whole group 404s when an admin turns the blog off (docs/09).
Route::prefix('blog')->middleware('blog.enabled')->group(function (): void {
    Route::get('posts', [BlogController::class, 'index'])->name('blog.posts.index');
    Route::get('categories', [BlogController::class, 'categories'])->name('blog.categories');
    Route::get('tags', [BlogController::class, 'tags'])->name('blog.tags');

    // Last: a bare segment would otherwise swallow `categories` and `tags`.
    Route::get('posts/{slug}', [BlogController::class, 'show'])->name('blog.posts.show');
});

Route::prefix('tools')->group(function (): void {
    // Two throttles: a per-minute burst guard here, and the daily quota inside the
    // action. They solve different problems — abuse versus cost (see docs/08).
    Route::post('{slug}/run', RunToolController::class)
        ->middleware('throttle:tool-runs')
        ->name('tools.run');

    Route::get('runs/{ulid}', [RunToolController::class, 'show'])->name('tools.runs.show');
});

// ── Authentication ───────────────────────────────────────────────────────────
//
// Every credential-accepting endpoint is behind `throttle:auth`, which is keyed by
// email *and* IP — so an attacker spraying one address cannot lock its owner out.

Route::prefix('auth')->group(function (): void {
    Route::get('session', SessionController::class)->name('auth.session');

    Route::middleware('throttle:auth')->group(function (): void {
        Route::post('register', RegisterController::class)->name('auth.register');
        Route::post('login', [LoginController::class, 'store'])->name('auth.login');

        Route::post('magic-link', [MagicLinkController::class, 'store'])->name('auth.magic-link');
        Route::post('magic-link/consume', [MagicLinkController::class, 'consume'])
            ->name('auth.magic-link.consume');

        Route::post('password/forgot', [PasswordResetController::class, 'forgot'])
            ->name('auth.password.forgot');
        Route::post('password/reset', [PasswordResetController::class, 'reset'])
            ->name('auth.password.reset');
    });

    // Signed rather than authenticated: the link is opened from an email client that
    // has no session, and the signature is what proves it came from us.
    Route::get('email/verify/{ulid}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('auth.email.verify');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [LoginController::class, 'destroy'])->name('auth.logout');
        Route::post('password/confirm', ConfirmPasswordController::class)
            ->middleware('throttle:auth')
            ->name('auth.password.confirm');

        Route::post('email/resend', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:6,1')
            ->name('auth.email.resend');
    });
});

// ── Authenticated ────────────────────────────────────────────────────────────

Route::middleware('auth:sanctum')->group(function (): void {
    Route::prefix('account')->group(function (): void {
        Route::get('entitlements', EntitlementsController::class)->name('account.entitlements');

        Route::patch('profile', [ProfileController::class, 'update'])->name('account.profile.update');

        // Changing a password re-authenticates first (docs/06): a borrowed, already
        // signed-in browser must not be enough to take an account over.
        Route::patch('password', [PasswordController::class, 'update'])
            ->middleware('password.confirm')
            ->name('account.password.update');

        Route::post('avatar', [AvatarController::class, 'store'])->name('account.avatar.store');
        Route::delete('avatar', [AvatarController::class, 'destroy'])->name('account.avatar.destroy');

        Route::get('devices', [DeviceController::class, 'index'])->name('account.devices.index');
        Route::delete('devices/{device}', [DeviceController::class, 'destroy'])
            ->whereNumber('device')
            ->name('account.devices.destroy');

        Route::get('tool-runs', [ToolRunHistoryController::class, 'index'])->name('account.tool-runs');

        Route::get('notification-preferences', [NotificationPreferenceController::class, 'index'])
            ->name('account.notification-preferences.index');
        Route::put('notification-preferences', [NotificationPreferenceController::class, 'update'])
            ->name('account.notification-preferences.update');
    });

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])
        ->name('notifications.read-all');
});
