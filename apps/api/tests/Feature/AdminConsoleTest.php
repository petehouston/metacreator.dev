<?php

declare(strict_types=1);

use App\Domain\Access\Actions\SyncRolesAndPermissions;
use App\Domain\Users\Models\MagicLink;
use App\Domain\Users\Models\User;

/**
 * The two console commands that can hand someone the keys to the platform.
 *
 * What is worth proving here is not that they print something, but that the
 * privilege they create is the privilege that was asked for, and that the sign-in
 * link they emit carries the same guarantees as the emailed one: single use, short
 * lived, and never an open redirect.
 */
beforeEach(function (): void {
    app(SyncRolesAndPermissions::class)->handle();
});

it('creates a super admin that bypasses every gate', function () {
    $this->artisan('admin:create', ['email' => 'root@metacreator.dev', '--no-interaction' => true])
        ->assertSuccessful();

    $user = User::query()->where('email', 'root@metacreator.dev')->firstOrFail();

    expect($user->hasRole('super-admin'))->toBeTrue()
        ->and($user->isActive())->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->can('settings.secrets.update'))->toBeTrue();
});

it('promotes an existing account without replacing its history', function () {
    $existing = User::factory()->create(['email' => 'customer@metacreator.dev']);
    $existing->assignRole('support');

    $this->artisan('admin:create', [
        'email' => 'customer@metacreator.dev',
        '--role' => 'admin',
        '--no-password' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($existing->refresh()->getRoleNames()->all())->toEqualCanonicalizing(['support', 'admin']);
});

it('refuses a role that does not exist', function () {
    $this->artisan('admin:create', [
        'email' => 'nobody@metacreator.dev',
        '--role' => 'overlord',
        '--no-interaction' => true,
    ])->assertFailed();

    expect(User::query()->where('email', 'nobody@metacreator.dev')->exists())->toBeFalse();
});

it('issues a usable one-time sign-in link for staff', function () {
    $admin = staff('super-admin');

    $this->artisan('admin:login-link', ['email' => $admin->email])->assertSuccessful();

    $link = MagicLink::query()->where('email', $admin->email)->sole();

    expect($link->isUsable())->toBeTrue()
        ->and($link->redirect_to)->toBe('/admin')
        ->and($link->expires_at->diffInMinutes(now()))->toBeLessThanOrEqual(15);
});

it('invalidates outstanding links each time one is issued', function () {
    $admin = staff('admin');
    $first = issueMagicLinkFor($admin->email);

    $this->artisan('admin:login-link', ['email' => $admin->email])->assertSuccessful();

    $superseded = MagicLink::query()->where('token_hash', MagicLink::hash($first))->sole();

    expect($superseded->isUsable())->toBeFalse()
        ->and(MagicLink::query()->usable()->where('email', $admin->email)->count())->toBe(1);
});

it('drops an off-site redirect rather than emitting an open redirect', function () {
    $admin = staff('super-admin');

    $this->artisan('admin:login-link', [
        'email' => $admin->email,
        '--redirect' => 'https://evil.example/admin',
    ])->assertSuccessful();

    expect(MagicLink::query()->where('email', $admin->email)->sole()->redirect_to)->toBeNull();
});

it('refuses to issue a link for a non-staff account unless forced', function () {
    $customer = User::factory()->create();

    $this->artisan('admin:login-link', ['email' => $customer->email])->assertFailed();
    expect(MagicLink::query()->where('email', $customer->email)->exists())->toBeFalse();

    $this->artisan('admin:login-link', ['email' => $customer->email, '--any-user' => true])
        ->assertSuccessful();
    expect(MagicLink::query()->where('email', $customer->email)->exists())->toBeTrue();
});

it('refuses to issue a link for a suspended account', function () {
    $admin = staff('admin');
    $admin->forceFill(['status' => 'suspended'])->save();

    $this->artisan('admin:login-link', ['email' => $admin->email])->assertFailed();
});
