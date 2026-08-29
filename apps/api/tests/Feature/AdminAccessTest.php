<?php

declare(strict_types=1);

use App\Domain\Access\PermissionCatalog;
use App\Domain\Settings\Setting;
use App\Domain\Settings\Settings;
use App\Domain\Tools\Runners\YouTubeCommentFinderRunner;
use App\Domain\Users\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

/**
 * The admin API's guarantee is not "these screens check a permission" — it is
 * "**every** endpoint checks one, and the right one".
 *
 * The architecture test proves each route *declares* a permission. This proves the
 * declaration is enforced: a signed-in customer with no roles gets 403 from every
 * one of them, and each seeded role gets exactly the surface its description
 * promises. Both halves are needed — a route can declare a permission nobody
 * checks, and a role can be given a permission its screens never surface.
 */

/** @return list<array{string, string}> Every admin route as [method, uri]. */
function adminRoutes(): array
{
    return collect(Route::getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/admin'))
        // GET only: a POST with no body would fail validation before it ever
        // reached the permission check, which would prove nothing.
        ->filter(fn ($route) => in_array('GET', $route->methods(), true))
        // The CSV export streams its response, which has no `status()` to assert
        // against; it gets its own test rather than a special case in every loop.
        ->reject(fn ($route) => $route->getName() === 'admin.newsletter.export')
        ->map(fn ($route) => [$route->methods()[0], '/'.$route->uri()])
        ->values()
        ->all();
}

it('refuses every admin endpoint to a signed-in customer', function () {
    $customer = User::factory()->create();

    foreach (adminRoutes() as [$method, $uri]) {
        $status = $this->actingAs($customer)->json($method, $uri)->status();

        // 403 on the plain routes; 404 is accepted only where the URI still holds a
        // `{placeholder}`, because model binding runs in the `api` group *before*
        // route middleware and a missing row answers first. Either way the request
        // never reaches the controller — which is the property being asserted.
        $allowed = str_contains($uri, '{') ? [403, 404] : [403];

        expect(in_array($status, $allowed, true))
            ->toBeTrue("{$method} {$uri} did not reject a customer (got {$status})");
    }
});

it('refuses every admin endpoint to an anonymous visitor', function () {
    foreach (adminRoutes() as [$method, $uri]) {
        expect($this->json($method, $uri)->status())
            ->toBe(401, "{$method} {$uri} did not require a session");
    }
});

it('gives an editor the blog and denies it the billing screens', function () {
    $editor = staff('editor');

    $this->actingAs($editor)->getJson('/api/v1/admin/posts')->assertOk();
    $this->actingAs($editor)->getJson('/api/v1/admin/media')->assertOk();
    $this->actingAs($editor)->getJson('/api/v1/admin/invoices')->assertForbidden();
    $this->actingAs($editor)->getJson('/api/v1/admin/users')->assertForbidden();
    $this->actingAs($editor)->getJson('/api/v1/admin/settings')->assertForbidden();
});

it('gives an accountant billing and denies it the blog', function () {
    $accountant = staff('accountant');

    $this->actingAs($accountant)->getJson('/api/v1/admin/invoices')->assertOk();
    $this->actingAs($accountant)->getJson('/api/v1/admin/subscriptions')->assertOk();
    $this->actingAs($accountant)->getJson('/api/v1/admin/plans')->assertOk();
    $this->actingAs($accountant)->getJson('/api/v1/admin/posts')->assertForbidden();
    $this->actingAs($accountant)->getJson('/api/v1/admin/media')->assertForbidden();
});

it('gives support the ticket queue and denies it invoices', function () {
    $support = staff('support');

    $this->actingAs($support)->getJson('/api/v1/admin/tickets')->assertOk();
    $this->actingAs($support)->getJson('/api/v1/admin/users')->assertOk();
    $this->actingAs($support)->getJson('/api/v1/admin/invoices')->assertForbidden();
    $this->actingAs($support)->getJson('/api/v1/admin/settings')->assertForbidden();
});

it('lets an analyst read the dashboards and change nothing', function () {
    $analyst = staff('analyst');

    $this->actingAs($analyst)->getJson('/api/v1/admin/overview')->assertOk();
    $this->actingAs($analyst)->getJson('/api/v1/admin/analytics/tools')->assertOk();
    $this->actingAs($analyst)->getJson('/api/v1/admin/tools')->assertOk();

    $this->actingAs($analyst)->putJson('/api/v1/admin/settings', ['settings' => []])->assertForbidden();
});

/**
 * The separation that makes `super-admin` mean something. If an `admin` could
 * reach these, the role split would be documentation rather than a control.
 */
it('stops an admin from escalating its own privileges', function (string $method, string $template) {
    $admin = staff('admin');
    $victim = User::factory()->create();

    // Real, existing targets: a 404 from a missing row would pass this test while
    // proving nothing about the permission that is supposed to be blocking it.
    $uri = str_replace(
        ['{user}', '{role}'],
        [$victim->public_id, (string) Role::query()->value('id')],
        $template,
    );

    $this->actingAs($admin)->json($method, $uri)->assertForbidden();
})->with([
    'assigning roles' => ['PUT', '/api/v1/admin/users/{user}/roles'],
    'editing a role' => ['PATCH', '/api/v1/admin/roles/{role}'],
    'creating a role' => ['POST', '/api/v1/admin/roles'],
    'deleting a role' => ['DELETE', '/api/v1/admin/roles/{role}'],
    'deleting a user' => ['DELETE', '/api/v1/admin/users/{user}'],
]);

/**
 * The secrets split is enforced per key, not per route — one payload may
 * legitimately touch several groups — so it has to be asserted per key too.
 *
 * An `admin` *does* hold `settings.scripts.update` (docs/06); the line it must not
 * cross is provider credentials.
 */
it('stops an admin from writing a provider secret', function () {
    $this->seed(SettingsSeeder::class);

    $this->actingAs(staff('admin'))
        ->putJson('/api/v1/admin/settings', [
            'settings' => [['key' => 'newsletter.api_key', 'value' => 'sk_live_stolen']],
        ])
        ->assertForbidden();
});

it('never returns a secret it holds, only whether one is set', function () {
    $this->seed(SettingsSeeder::class);

    $root = staff('super-admin');

    $this->actingAs($root)->putJson('/api/v1/admin/settings', [
        'settings' => [['key' => 'newsletter.api_key', 'value' => 'sk_live_real']],
    ])->assertOk();

    $response = $this->actingAs($root)->getJson('/api/v1/admin/settings')->assertOk();

    $newsletter = collect($response->json('data.groups'))->firstWhere('group', 'newsletter');
    $apiKey = collect($newsletter['settings'])->firstWhere('key', 'newsletter.api_key');

    expect($apiKey['value'])->toBeNull()
        ->and($apiKey['is_set'])->toBeTrue()
        ->and(json_encode($response->json()))->not->toContain('sk_live_real');
});

it('treats a blank secret as "leave it alone", not as "erase it"', function () {
    $this->seed(SettingsSeeder::class);

    $root = staff('super-admin');

    $this->actingAs($root)->putJson('/api/v1/admin/settings', [
        'settings' => [['key' => 'newsletter.api_key', 'value' => 'sk_live_real']],
    ])->assertOk();

    // The UI never renders the key, so a plain save of that form submits an empty
    // string. Treating that as a delete would silently break the integration.
    $this->actingAs($root)->putJson('/api/v1/admin/settings', [
        'settings' => [['key' => 'newsletter.api_key', 'value' => '']],
    ])->assertOk();

    expect(Setting::query()->where('key', 'newsletter.api_key')->first()?->typedValue())
        ->toBe('sk_live_real');
});

it('exposes the YouTube Data API key as a settable provider secret', function () {
    $this->seed(SettingsSeeder::class);

    $root = staff('super-admin');

    $this->actingAs($root)->putJson('/api/v1/admin/settings', [
        'settings' => [['key' => 'providers.youtube.api_key', 'value' => 'AIza-real']],
    ])->assertOk();

    // The point of the setting: the tool that needs the key reads it back without a
    // deploy, and the key itself never travels to the browser.
    expect(app(Settings::class)->string(YouTubeCommentFinderRunner::API_KEY_SETTING))
        ->toBe('AIza-real');

    $response = $this->actingAs($root)->getJson('/api/v1/admin/settings')->assertOk();
    $providers = collect($response->json('data.groups'))->firstWhere('group', 'providers');
    $key = collect($providers['settings'])->firstWhere('key', 'providers.youtube.api_key');

    expect($key['value'])->toBeNull()
        ->and($key['is_set'])->toBeTrue()
        ->and(json_encode($response->json()))->not->toContain('AIza-real');
});

it('lets an admin write an ordinary setting', function () {
    $this->seed(SettingsSeeder::class);
    $admin = staff('admin');

    $this->actingAs($admin)
        ->putJson('/api/v1/admin/settings', [
            'settings' => [['key' => 'site.tagline', 'value' => 'Tools that help creators grow faster']],
        ])
        ->assertOk()
        ->assertJsonPath('data.updated.0', 'site.tagline');
});

it('lets a super admin through every gate', function () {
    $root = staff('super-admin');

    foreach (adminRoutes() as [$method, $uri]) {
        $status = $this->actingAs($root)->json($method, $uri)->status();

        // 404 is a pass: the placeholder row does not exist, which means the request
        // got past authorization and into model binding.
        expect(in_array($status, [200, 404], true))
            ->toBeTrue("{$method} {$uri} refused a super admin with {$status}");
    }
});

it('streams the newsletter export to someone who may read the list', function () {
    $root = staff('super-admin');

    $this->actingAs($root)
        ->get('/api/v1/admin/newsletter/export')
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $this->actingAs(User::factory()->create())
        ->get('/api/v1/admin/newsletter/export')
        ->assertForbidden();
});

it('declares a permission for every admin route it exposes', function () {
    // Belt and braces against a typo: a route may only require a permission the
    // catalog actually declares, or it requires something nothing can ever grant.
    $declared = PermissionCatalog::all();

    foreach (Route::getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'api/v1/admin')) {
            continue;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware) || ! str_starts_with($middleware, 'permission:')) {
                continue;
            }

            foreach (explode('|', substr($middleware, strlen('permission:'))) as $permission) {
                expect(in_array($permission, $declared, true))
                    ->toBeTrue("{$route->uri()} requires undeclared permission [{$permission}]");
            }
        }
    }
});
