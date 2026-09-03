<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Account\AvatarController;
use App\Http\Controllers\Api\V1\Account\DeviceController;
use App\Http\Controllers\Api\V1\Account\EntitlementsController;
use App\Http\Controllers\Api\V1\Account\NotificationController;
use App\Http\Controllers\Api\V1\Account\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\Account\PasswordController;
use App\Http\Controllers\Api\V1\Account\ProfileController;
use App\Http\Controllers\Api\V1\Account\ToolFavoriteController;
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
use App\Http\Controllers\Api\V1\Changelog\ChangelogController;
use App\Http\Controllers\Api\V1\Newsletter\NewsletterSubscriptionController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\Tools\RunToolController;
use App\Http\Controllers\Api\V1\TopRanking\TopRankingController;
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

// Public settings only — the frontend needs a few of them (blog presentation,
// branding) to render, and they change without a deploy.
Route::get('settings', SettingsController::class)->name('settings.public');

Route::prefix('catalog')->group(function (): void {
    Route::get('tools', [ToolCatalogController::class, 'index'])->name('catalog.tools.index');
    Route::get('categories', [ToolCatalogController::class, 'categories'])->name('catalog.categories');

    // Before the `{slug}` route: a bare segment would otherwise swallow it.
    Route::get('tools/trending', [ToolCatalogController::class, 'trending'])
        ->name('catalog.tools.trending');

    Route::get('tools/{slug}', [ToolCatalogController::class, 'show'])->name('catalog.tools.show');
});

// The public changelog. The whole group 404s when an admin turns it off, and the
// footer link goes with it — the admin screens keep working either way.
Route::prefix('changelog')->middleware('changelog.enabled')->group(function (): void {
    Route::get('/', [ChangelogController::class, 'index'])->name('changelog.index');

    // Before the `{slug}` route: a bare segment would otherwise swallow it.
    Route::get('meta', [ChangelogController::class, 'meta'])->name('changelog.meta');

    Route::get('{slug}', [ChangelogController::class, 'show'])->name('changelog.show');
});

// The top-ranking pages. No feature switch and no pagination: an unpublished page
// is already invisible (`is_published` per row), and the index is what draws the
// header menu, so half of it would be a broken menu rather than a smaller payload.
Route::prefix('top-ranking')->group(function (): void {
    Route::get('/', [TopRankingController::class, 'index'])->name('top-ranking.index');

    // Last: a bare segment would otherwise swallow any sibling added later.
    Route::get('{slug}', [TopRankingController::class, 'show'])->name('top-ranking.show');
});

// The whole group 404s when an admin turns the blog off (docs/09).
Route::prefix('blog')->middleware('blog.enabled')->group(function (): void {
    Route::get('posts', [BlogController::class, 'index'])->name('blog.posts.index');
    Route::get('categories', [BlogController::class, 'categories'])->name('blog.categories');
    Route::get('tags', [BlogController::class, 'tags'])->name('blog.tags');

    // Last: a bare segment would otherwise swallow `categories` and `tags`.
    Route::get('posts/{slug}', [BlogController::class, 'show'])->name('blog.posts.show');
});

// The public newsletter. Throttled by address *and* by IP: the first stops one
// mailbox being flooded with confirmation mail, the second stops a script seeding
// the list. The group 404s when an admin turns the newsletter off.
Route::prefix('newsletter')->middleware('newsletter.enabled')->group(function (): void {
    Route::post('subscribe', [NewsletterSubscriptionController::class, 'store'])
        ->middleware('throttle:newsletter')
        ->name('newsletter.subscribe');

    // Its own limit, keyed by IP alone: the subscribe limiter keys on the submitted
    // address, which a confirmation carries no copy of.
    Route::post('confirm', [NewsletterSubscriptionController::class, 'confirm'])
        ->middleware('throttle:20,1')
        ->name('newsletter.confirm');
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
        Route::get('tool-runs/{ulid}', [ToolRunHistoryController::class, 'show'])
            ->name('account.tool-runs.show');

        // Saved tools. PUT rather than POST because saving is idempotent: the same
        // request twice leaves the same one row.
        Route::get('favorites', [ToolFavoriteController::class, 'index'])->name('account.favorites.index');
        Route::put('favorites/{slug}', [ToolFavoriteController::class, 'store'])
            ->name('account.favorites.store');
        Route::delete('favorites/{slug}', [ToolFavoriteController::class, 'destroy'])
            ->name('account.favorites.destroy');

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

// ── Admin ────────────────────────────────────────────────────────────────────
//
// One file per surface. Every route inside declares the permission it needs, and
// an architecture test fails the build if one does not.

Route::prefix('admin')->middleware('auth:sanctum')->group(base_path('routes/admin.php'));
