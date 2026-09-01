<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\ActivityController;
use App\Http\Controllers\Api\V1\Admin\BillingController;
use App\Http\Controllers\Api\V1\Admin\ChangelogController;
use App\Http\Controllers\Api\V1\Admin\ContactMessageController;
use App\Http\Controllers\Api\V1\Admin\MediaController;
use App\Http\Controllers\Api\V1\Admin\NewsletterController;
use App\Http\Controllers\Api\V1\Admin\OverviewController;
use App\Http\Controllers\Api\V1\Admin\PostController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\SettingsController;
use App\Http\Controllers\Api\V1\Admin\TaxonomyController;
use App\Http\Controllers\Api\V1\Admin\TicketController;
use App\Http\Controllers\Api\V1\Admin\ToolAnalyticsController;
use App\Http\Controllers\Api\V1\Admin\ToolController;
use App\Http\Controllers\Api\V1\Admin\ToolGrantController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin API
|--------------------------------------------------------------------------
|
| Mounted at /api/v1/admin behind `auth:sanctum`.
|
| **Every route here declares the permission it requires**, and an architecture
| test fails the build if one does not (tests/Architecture). That is the rule
| that makes the permission catalog meaningful rather than decorative: a new admin
| endpoint cannot ship open by accident.
|
| Where a permission is scoped — `posts.update.own`, `media.delete.own` — the
| middleware can only prove the actor may touch *something*. The controller checks
| the row.
|
*/

// ── Dashboards ───────────────────────────────────────────────────────────────

Route::get('overview', OverviewController::class)
    ->middleware('permission:analytics.view')
    ->name('admin.overview');

Route::prefix('analytics')->group(function (): void {
    Route::get('tools', [ToolAnalyticsController::class, 'index'])
        ->middleware('permission:tool_analytics.view|analytics.view')
        ->name('admin.analytics.tools');

    Route::get('funnel', [ToolAnalyticsController::class, 'funnel'])
        ->middleware('permission:analytics.view')
        ->name('admin.analytics.funnel');

    Route::get('content', [ToolAnalyticsController::class, 'content'])
        ->middleware('permission:analytics.view')
        ->name('admin.analytics.content');
});

// ── People ───────────────────────────────────────────────────────────────────

Route::prefix('users')->group(function (): void {
    Route::get('/', [UserController::class, 'index'])
        ->middleware('permission:users.view_any')
        ->name('admin.users.index');

    Route::get('{user}', [UserController::class, 'show'])
        ->middleware('permission:users.view')
        ->name('admin.users.show');

    Route::patch('{user}', [UserController::class, 'update'])
        ->middleware('permission:users.update')
        ->name('admin.users.update');

    Route::post('{user}/suspend', [UserController::class, 'suspend'])
        ->middleware('permission:users.suspend')
        ->name('admin.users.suspend');

    Route::delete('{user}', [UserController::class, 'destroy'])
        ->middleware('permission:users.delete')
        ->name('admin.users.destroy');

    // Assigning roles is `roles.manage` — the permission an `admin` deliberately
    // does not hold, so it cannot promote itself (docs/06).
    Route::put('{user}/roles', [RoleController::class, 'assign'])
        ->middleware('permission:roles.manage')
        ->name('admin.users.roles');
});

Route::prefix('roles')->group(function (): void {
    Route::get('/', [RoleController::class, 'index'])
        ->middleware('permission:roles.view_any')
        ->name('admin.roles.index');

    Route::post('/', [RoleController::class, 'store'])
        ->middleware('permission:roles.manage')
        ->name('admin.roles.store');

    Route::patch('{role}', [RoleController::class, 'update'])
        ->middleware('permission:roles.manage')
        ->name('admin.roles.update');

    Route::delete('{role}', [RoleController::class, 'destroy'])
        ->middleware('permission:roles.manage')
        ->name('admin.roles.destroy');
});

Route::get('permissions', [RoleController::class, 'permissions'])
    ->middleware('permission:roles.view_any')
    ->name('admin.permissions');

Route::prefix('changelog')->group(function (): void {
    Route::get('/', [ChangelogController::class, 'index'])
        ->middleware('permission:changelog.view_any')
        ->name('admin.changelog.index');

    Route::post('/', [ChangelogController::class, 'store'])
        ->middleware('permission:changelog.create')
        ->name('admin.changelog.store');

    Route::get('{release}', [ChangelogController::class, 'show'])
        ->middleware('permission:changelog.view')
        ->name('admin.changelog.show');

    Route::patch('{release}', [ChangelogController::class, 'update'])
        ->middleware('permission:changelog.update')
        ->name('admin.changelog.update');

    // Separate from `update` so a writer can draft releases without being able to
    // put one in front of every customer.
    Route::post('{release}/publish', [ChangelogController::class, 'publish'])
        ->middleware('permission:changelog.publish')
        ->name('admin.changelog.publish');

    Route::delete('{release}', [ChangelogController::class, 'destroy'])
        ->middleware('permission:changelog.delete')
        ->name('admin.changelog.destroy');
});

// ── Tools ────────────────────────────────────────────────────────────────────

Route::prefix('tools')->group(function (): void {
    Route::get('/', [ToolController::class, 'index'])
        ->middleware('permission:tools.view_any')
        ->name('admin.tools.index');

    Route::get('{tool}', [ToolController::class, 'show'])
        ->middleware('permission:tools.view')
        ->name('admin.tools.show');

    Route::patch('{tool}', [ToolController::class, 'update'])
        ->middleware('permission:tools.update')
        ->name('admin.tools.update');
});

Route::get('tool-categories', [ToolController::class, 'categories'])
    ->middleware('permission:tool_categories.view_any|tools.view_any')
    ->name('admin.tool-categories.index');

Route::prefix('tool-grants')->group(function (): void {
    Route::get('/', [ToolGrantController::class, 'index'])
        ->middleware('permission:tool_grants.view_any')
        ->name('admin.tool-grants.index');

    Route::post('/', [ToolGrantController::class, 'store'])
        ->middleware('permission:tool_grants.create')
        ->name('admin.tool-grants.store');

    Route::delete('{grant}', [ToolGrantController::class, 'destroy'])
        ->middleware('permission:tool_grants.delete')
        ->name('admin.tool-grants.destroy');
});

// ── Content ──────────────────────────────────────────────────────────────────

Route::prefix('posts')->group(function (): void {
    Route::get('/', [PostController::class, 'index'])
        ->middleware('permission:posts.view_any')
        ->name('admin.posts.index');

    Route::post('/', [PostController::class, 'store'])
        ->middleware('permission:posts.create')
        ->name('admin.posts.store');

    // Before `{post}`: a bare segment would otherwise swallow it.
    Route::post('bulk', [PostController::class, 'bulk'])
        ->middleware('permission:posts.update|posts.update.own')
        ->name('admin.posts.bulk');

    Route::post('{post}/restore', [PostController::class, 'restore'])
        ->middleware('permission:posts.restore')
        ->name('admin.posts.restore');

    Route::get('{post}', [PostController::class, 'show'])
        ->middleware('permission:posts.view|posts.view_any')
        ->name('admin.posts.show');

    Route::patch('{post}', [PostController::class, 'update'])
        ->middleware('permission:posts.update|posts.update.own')
        ->name('admin.posts.update');

    Route::delete('{post}', [PostController::class, 'destroy'])
        ->middleware('permission:posts.delete')
        ->name('admin.posts.destroy');
});

Route::prefix('post-categories')->group(function (): void {
    Route::get('/', [TaxonomyController::class, 'categories'])
        ->middleware('permission:post_categories.view_any')
        ->name('admin.post-categories.index');

    Route::post('/', [TaxonomyController::class, 'storeCategory'])
        ->middleware('permission:post_categories.create')
        ->name('admin.post-categories.store');

    Route::get('{category}', [TaxonomyController::class, 'showCategory'])
        ->middleware('permission:post_categories.view_any')
        ->name('admin.post-categories.show');

    Route::patch('{category}', [TaxonomyController::class, 'updateCategory'])
        ->middleware('permission:post_categories.update')
        ->name('admin.post-categories.update');

    Route::delete('{category}', [TaxonomyController::class, 'destroyCategory'])
        ->middleware('permission:post_categories.delete')
        ->name('admin.post-categories.destroy');
});

Route::prefix('tags')->group(function (): void {
    Route::get('/', [TaxonomyController::class, 'tags'])
        ->middleware('permission:tags.view_any')
        ->name('admin.tags.index');

    Route::post('/', [TaxonomyController::class, 'storeTag'])
        ->middleware('permission:tags.create')
        ->name('admin.tags.store');

    Route::get('{tag}', [TaxonomyController::class, 'showTag'])
        ->middleware('permission:tags.view_any')
        ->name('admin.tags.show');

    Route::patch('{tag}', [TaxonomyController::class, 'updateTag'])
        ->middleware('permission:tags.update')
        ->name('admin.tags.update');

    Route::delete('{tag}', [TaxonomyController::class, 'destroyTag'])
        ->middleware('permission:tags.delete')
        ->name('admin.tags.destroy');
});

Route::prefix('media')->group(function (): void {
    Route::get('/', [MediaController::class, 'index'])
        ->middleware('permission:media.view_any')
        ->name('admin.media.index');

    Route::post('/', [MediaController::class, 'store'])
        ->middleware('permission:media.create')
        ->name('admin.media.store');

    Route::get('{media}', [MediaController::class, 'show'])
        ->middleware('permission:media.view_any')
        ->name('admin.media.show');

    Route::patch('{media}', [MediaController::class, 'update'])
        ->middleware('permission:media.update|media.update.own')
        ->name('admin.media.update');

    Route::delete('{media}', [MediaController::class, 'destroy'])
        ->middleware('permission:media.delete|media.delete.own')
        ->name('admin.media.destroy');
});

// ── Commerce ─────────────────────────────────────────────────────────────────

Route::get('plans', [BillingController::class, 'plans'])
    ->middleware('permission:plans.view_any')
    ->name('admin.plans.index');

Route::post('plans', [BillingController::class, 'storePlan'])
    ->middleware('permission:plans.create')
    ->name('admin.plans.store');

Route::get('plans/{plan}', [BillingController::class, 'showPlan'])
    ->middleware('permission:plans.view_any')
    ->name('admin.plans.show');

Route::patch('plans/{plan}', [BillingController::class, 'updatePlan'])
    ->middleware('permission:plans.update')
    ->name('admin.plans.update');

Route::delete('plans/{plan}', [BillingController::class, 'destroyPlan'])
    ->middleware('permission:plans.delete')
    ->name('admin.plans.destroy');

Route::get('subscriptions', [BillingController::class, 'subscriptions'])
    ->middleware('permission:subscriptions.view_any')
    ->name('admin.subscriptions.index');

// Before `invoices/{invoice}`: a bare segment would otherwise swallow it.
Route::get('invoices/report', [BillingController::class, 'report'])
    ->middleware('permission:invoices.view_any')
    ->name('admin.invoices.report');

Route::get('invoices', [BillingController::class, 'invoices'])
    ->middleware('permission:invoices.view_any')
    ->name('admin.invoices.index');

Route::get('invoices/{invoice}', [BillingController::class, 'invoice'])
    ->middleware('permission:invoices.view')
    ->name('admin.invoices.show');

// ── Support ──────────────────────────────────────────────────────────────────

Route::prefix('tickets')->group(function (): void {
    Route::get('/', [TicketController::class, 'index'])
        ->middleware('permission:tickets.view_any')
        ->name('admin.tickets.index');

    Route::get('{ticket}', [TicketController::class, 'show'])
        ->middleware('permission:tickets.view')
        ->name('admin.tickets.show');

    Route::patch('{ticket}', [TicketController::class, 'update'])
        ->middleware('permission:tickets.update')
        ->name('admin.tickets.update');

    Route::post('{ticket}/messages', [TicketController::class, 'reply'])
        ->middleware('permission:tickets.reply')
        ->name('admin.tickets.reply');
});

Route::get('contact-messages', [ContactMessageController::class, 'index'])
    ->middleware('permission:tickets.view_any')
    ->name('admin.contact-messages.index');

Route::post('contact-messages/{message}/handled', [ContactMessageController::class, 'handled'])
    ->middleware('permission:tickets.update')
    ->name('admin.contact-messages.handled');

// ── Platform ─────────────────────────────────────────────────────────────────

Route::get('settings', [SettingsController::class, 'index'])
    ->middleware('permission:settings.view')
    ->name('admin.settings.index');

// One write endpoint for every group; the controller re-checks per key, because a
// single payload may legitimately span ordinary settings, scripts and secrets.
Route::put('settings', [SettingsController::class, 'update'])
    ->middleware('permission:settings.update|settings.scripts.update|settings.secrets.update')
    ->name('admin.settings.update');

Route::get('newsletter/subscribers', [NewsletterController::class, 'index'])
    ->middleware('permission:newsletter.view')
    ->name('admin.newsletter.index');

Route::get('newsletter/export', [NewsletterController::class, 'export'])
    ->middleware('permission:newsletter.export')
    ->name('admin.newsletter.export');

Route::get('activity', [ActivityController::class, 'index'])
    ->middleware('permission:activity_log.view')
    ->name('admin.activity.index');
