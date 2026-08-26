<?php

declare(strict_types=1);

use App\Domain\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Notification::fake();
});

// ── Profile ──────────────────────────────────────────────────────────────────

it('updates the profile fields a user owns', function () {
    $user = User::factory()->create(['display_name' => 'Old', 'timezone' => 'UTC']);

    $this->actingAs($user)
        ->patchJson('/api/v1/account/profile', [
            'display_name' => 'New Name',
            'timezone' => 'Europe/Lisbon',
            'marketing_opt_in' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.display_name', 'New Name')
        ->assertJsonPath('data.timezone', 'Europe/Lisbon')
        ->assertJsonPath('data.marketing_opt_in', true);
});

it('refuses to change the email, which is the account identity', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/api/v1/account/profile', ['email' => 'somewhere-else@example.com'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation.failed');

    expect($user->refresh()->email)->not->toBe('somewhere-else@example.com');
});

it('rejects a display name longer than the column', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/api/v1/account/profile', ['display_name' => str_repeat('a', 61)])
        ->assertStatus(422);
});

// ── Password ─────────────────────────────────────────────────────────────────

it('demands recent authentication before changing a password', function () {
    $user = User::factory()->create(['password' => 'the-old-passphrase']);

    // A borrowed, already signed-in browser must not be enough.
    $this->actingAs($user)
        ->patchJson('/api/v1/account/password', [
            'current_password' => 'the-old-passphrase',
            'password' => 'a-brand-new-passphrase',
            'password_confirmation' => 'a-brand-new-passphrase',
        ])
        ->assertStatus(423);
});

it('changes a password once re-authenticated', function () {
    $user = User::factory()->create(['password' => 'the-old-passphrase']);

    $this->actingAs($user)
        ->postJson('/api/v1/auth/password/confirm', ['password' => 'the-old-passphrase'])
        ->assertOk()
        ->assertJsonPath('data.confirmed', true);

    $this->patchJson('/api/v1/account/password', [
        'current_password' => 'the-old-passphrase',
        'password' => 'a-brand-new-passphrase',
        'password_confirmation' => 'a-brand-new-passphrase',
    ])->assertOk();

    expect(Hash::check('a-brand-new-passphrase', $user->refresh()->password))->toBeTrue();
});

it('will not confirm with the wrong password', function () {
    $user = User::factory()->create(['password' => 'the-old-passphrase']);

    $this->actingAs($user)
        ->postJson('/api/v1/auth/password/confirm', ['password' => 'a-guess'])
        ->assertStatus(422);
});

it('lets a passwordless account set one without confirming a password it does not have', function () {
    // Magic-link and Google accounts have no password; requiring the current one
    // would lock them out of ever setting one.
    $user = User::factory()->create(['password' => null]);

    $this->actingAs($user)
        ->postJson('/api/v1/auth/password/confirm', ['password' => 'anything'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'auth.password_not_set');
});

// ── Avatar ───────────────────────────────────────────────────────────────────

it('stores an avatar and replaces the previous one', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $first = $this->actingAs($user)
        ->postJson('/api/v1/account/avatar', [
            'avatar' => UploadedFile::fake()->image('me.png', 256, 256),
        ])
        ->assertOk()
        ->json('data.avatar_url');

    expect($first)->not->toBeNull();
    $originalPath = $user->refresh()->avatar_path;

    $this->postJson('/api/v1/account/avatar', [
        'avatar' => UploadedFile::fake()->image('me-again.png', 256, 256),
    ])->assertOk();

    // The old file is cleaned up rather than orphaned in the bucket.
    Storage::disk('local')->assertMissing($originalPath);
});

it('refuses a file that is not really an image', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/account/avatar', [
            'avatar' => UploadedFile::fake()->create('payload.php', 16, 'application/x-php'),
        ])
        ->assertStatus(422);
});

it('removes an avatar', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/account/avatar', [
        'avatar' => UploadedFile::fake()->image('me.png', 256, 256),
    ])->assertOk();

    $this->deleteJson('/api/v1/account/avatar')
        ->assertOk()
        ->assertJsonPath('data.avatar_url', null);
});

// ── Devices ──────────────────────────────────────────────────────────────────

it('lists the devices a user is signed in from', function () {
    $user = User::factory()->create(['password' => 'a-long-enough-passphrase']);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'a-long-enough-passphrase',
    ])->assertOk();

    $this->getJson('/api/v1/account/devices')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.is_current', true);
});

it('will not let one user revoke another user\'s device', function () {
    $mine = User::factory()->create();
    $theirs = User::factory()->create();

    $device = $theirs->devices()->create([
        'fingerprint' => str_repeat('a', 64),
        'label' => 'Chrome on macOS',
        'last_seen_at' => now(),
    ]);

    $this->actingAs($mine)
        ->deleteJson("/api/v1/account/devices/{$device->id}")
        ->assertNotFound();

    expect($device->refresh()->revoked_at)->toBeNull();
});

// ── Run history ──────────────────────────────────────────────────────────────

it('requires a session to read run history', function () {
    $this->getJson('/api/v1/account/tool-runs')
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'auth.unauthenticated');
});
