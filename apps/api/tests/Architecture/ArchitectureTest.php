<?php

declare(strict_types=1);

use App\Domain\Access\PermissionCatalog;
use App\Domain\Tools\Models\Tool;
use App\Providers\ToolServiceProvider;
use Database\Seeders\ToolCatalogSeeder;
use Database\Seeders\ToolCategorySeeder;
use Illuminate\Support\Facades\Route;

/**
 * Structure asserted, not merely documented.
 *
 * The layering rules in docs/02-architecture.md are only real if something enforces
 * them; a convention nobody checks is a convention that decays.
 */
arch('no debugging statements survive review')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die'])
    ->not->toBeUsed();

arch('controllers stay thin')
    ->expect('App\Http\Controllers')
    // Business logic belongs in an Action; a controller that reaches for the query
    // builder is a controller that has started doing domain work.
    ->not->toUse(['Illuminate\Support\Facades\DB', 'Illuminate\Support\Facades\Schema']);

arch('domains do not reach across each other')
    ->expect('App\Domain\Blog')
    ->not->toUse(['App\Domain\Billing', 'App\Domain\Support', 'App\Domain\Newsletter']);

arch('tool runners cannot check access themselves')
    ->expect('App\Domain\Tools\Runners')
    // Access, quota and telemetry live in RunToolAction. A runner that could check
    // access is a runner that could get it wrong.
    ->not->toUse([
        'App\Domain\Tools\Services\ToolAccessService',
        'App\Domain\Tools\Services\QuotaService',
        'App\Domain\Billing\Services\EntitlementService',
        'Illuminate\Support\Facades\Auth',
    ]);

arch('models carry no business rules')
    ->expect('App\Domain\Tools\Models')
    ->not->toUse(['Illuminate\Support\Facades\Http', 'Illuminate\Support\Facades\Mail']);

arch('value objects are immutable')
    ->expect('App\Domain\Tools\Data')
    ->toBeReadonly();

arch('enums are backed, so they survive a database round trip')
    ->expect('App\Domain\Tools\Enums')
    ->toBeStringBackedEnums();

arch('everything declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

it('registers a runner for every published tool, and a tool for every runner', function () {
    // Seeded here rather than skipped when empty: this is exactly the assertion that
    // must run on a fresh checkout, because drift in either direction is a 500
    // waiting to happen — a catalog row with no runner throws on its first run, and
    // a runner with no row is dead code nobody notices.
    $this->seed(ToolCategorySeeder::class);
    $this->seed(ToolCatalogSeeder::class);

    $registryKeys = array_map(fn (string $class) => $class::key(), ToolServiceProvider::runners());
    $catalogKeys = Tool::query()->pluck('key')->all();

    expect(array_diff($catalogKeys, $registryKeys))
        ->toBeEmpty('Catalog rows exist with no registered runner')
        ->and(array_diff($registryKeys, $catalogKeys))
        ->toBeEmpty('Runners are registered with no catalog row');
});

it('declares every permission a role references', function () {
    $declared = PermissionCatalog::all();

    foreach (array_keys(PermissionCatalog::ROLES) as $role) {
        $undeclared = array_diff(PermissionCatalog::permissionsFor($role), $declared);

        expect($undeclared)->toBeEmpty(
            "Role [{$role}] references undeclared permissions: ".implode(', ', $undeclared)
        );
    }
});

it('never lets an admin grant itself more power', function () {
    $adminPermissions = PermissionCatalog::permissionsFor('admin');

    foreach (PermissionCatalog::ADMIN_EXCLUSIONS as $excluded) {
        expect(in_array($excluded, $adminPermissions, true))
            ->toBeFalse("Admin must not hold [{$excluded}]");
    }
});

it('gives super-admin every declared permission', function () {
    expect(PermissionCatalog::permissionsFor('super-admin'))
        ->toHaveCount(count(PermissionCatalog::all()));
});

it('protects every admin route with a permission', function () {
    $unprotected = collect(Route::getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/admin'))
        ->reject(fn ($route) => collect($route->gatherMiddleware())
            ->contains(fn ($middleware) => str_starts_with((string) $middleware, 'permission:')
                || str_starts_with((string) $middleware, 'can:')))
        ->map(fn ($route) => $route->methods()[0].' '.$route->uri())
        ->values()
        ->all();

    expect($unprotected)->toBeEmpty(
        'Admin routes without permission middleware: '.implode(', ', $unprotected)
    );
});
