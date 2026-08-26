<?php

declare(strict_types=1);

use App\Domain\Notifications\Notifications\CatalogNotification;
use App\Domain\Users\Models\MagicLink;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\Notification;

/**
 * The four sign-in methods from docs/06, plus the properties that make them safe:
 * single use, expiry, and no account enumeration.
 */
beforeEach(function (): void {
    Notification::fake();
});

// ── Registration ─────────────────────────────────────────────────────────────

it('registers an account and signs the visitor straight in', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'email' => 'Creator@Example.com',
        'password' => 'a-long-enough-passphrase',
        'display_name' => 'Ada',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'creator@example.com')
        ->assertJsonPath('data.display_name', 'Ada')
        ->assertJsonPath('data.is_staff', false);

    $this->assertAuthenticated();

    // Email is normalised on the way in, so `Creator@` and `creator@` are one account.
    expect(User::query()->where('email', 'creator@example.com')->exists())->toBeTrue();
});

it('welcomes a new account and asks it to verify the address', function () {
    $this->postJson('/api/v1/auth/register', [
        'email' => 'new@example.com',
        'password' => 'a-long-enough-passphrase',
    ])->assertCreated();

    $user = User::query()->where('email', 'new@example.com')->sole();

    Notification::assertSentTo($user, CatalogNotification::class);
});

it('rejects a duplicate email without leaking a stack trace', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/v1/auth/register', [
        'email' => 'taken@example.com',
        'password' => 'a-long-enough-passphrase',
    ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation.failed');
});

it('refuses a password short enough to brute force', function () {
    $this->postJson('/api/v1/auth/register', [
        'email' => 'short@example.com',
        'password' => 'short',
    ])->assertStatus(422);

    expect(User::query()->where('email', 'short@example.com')->exists())->toBeFalse();
});

it('turns away a bot that fills the honeypot', function () {
    $this->postJson('/api/v1/auth/register', [
        'email' => 'bot@example.com',
        'password' => 'a-long-enough-passphrase',
        'website' => 'http://spam.example',
    ])->assertStatus(422);

    expect(User::query()->where('email', 'bot@example.com')->exists())->toBeFalse();
});

// ── Password sign-in ─────────────────────────────────────────────────────────

it('signs in with email and password', function () {
    $user = User::factory()->create(['password' => 'a-long-enough-passphrase']);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'a-long-enough-passphrase',
    ])
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);

    $this->assertAuthenticatedAs($user);
});

it('gives the same answer for a wrong password and an unknown account', function () {
    $user = User::factory()->create(['password' => 'a-long-enough-passphrase']);

    $wrongPassword = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'not-the-right-one',
    ]);

    $unknownAccount = $this->postJson('/api/v1/auth/login', [
        'email' => 'nobody@example.com',
        'password' => 'not-the-right-one',
    ]);

    // Identical responses are the whole anti-enumeration property.
    expect($wrongPassword->json('error.details'))->toEqual($unknownAccount->json('error.details'));
    $this->assertGuest();
});

it('refuses a suspended account', function () {
    $user = User::factory()->create([
        'password' => 'a-long-enough-passphrase',
        'status' => 'suspended',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'a-long-enough-passphrase',
    ])->assertStatus(422);

    $this->assertGuest();
});

it('signs out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/auth/logout')
        ->assertOk()
        ->assertJsonPath('data.signed_out', true);
});

// ── Magic link ───────────────────────────────────────────────────────────────

it('issues a magic link for an existing account', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/magic-link', ['email' => $user->email])
        ->assertStatus(202)
        ->assertJsonPath('data.sent', true);

    expect(MagicLink::query()->where('email', $user->email)->count())->toBe(1);
    Notification::assertSentTo($user, CatalogNotification::class);
});

it('answers identically for an address with no account', function () {
    $this->postJson('/api/v1/auth/magic-link', ['email' => 'ghost@example.com'])
        ->assertStatus(202)
        ->assertJsonPath('data.sent', true);

    expect(MagicLink::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('invalidates outstanding links when a new one is issued', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/magic-link', ['email' => $user->email]);
    $first = MagicLink::query()->sole();

    $this->postJson('/api/v1/auth/magic-link', ['email' => $user->email]);

    expect($first->refresh()->consumed_at)->not->toBeNull();
});

it('signs in with a magic link exactly once', function () {
    $user = User::factory()->unverified()->create();
    $token = issueMagicLinkFor($user->email);

    $this->postJson('/api/v1/auth/magic-link/consume', ['token' => $token])
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);

    $this->assertAuthenticatedAs($user);

    // Receiving the mail proves the address, so consuming a link verifies it too.
    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();

    $this->postJson('/api/v1/auth/logout');

    $this->postJson('/api/v1/auth/magic-link/consume', ['token' => $token])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'auth.magic_link_invalid');
});

it('refuses an expired magic link', function () {
    $user = User::factory()->create();
    $token = issueMagicLinkFor($user->email);

    MagicLink::query()->update(['expires_at' => now()->subMinute()]);

    $this->postJson('/api/v1/auth/magic-link/consume', ['token' => $token])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'auth.magic_link_invalid');

    $this->assertGuest();
});

it('stores only a hash of the magic link token', function () {
    $user = User::factory()->create();
    $token = issueMagicLinkFor($user->email);

    // A database dump must not yield usable sign-in links.
    expect(MagicLink::query()->sole()->token_hash)
        ->not->toBe($token)
        ->toBe(hash('sha256', $token));
});

it('ignores an off-site redirect on a magic link', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/magic-link', [
        'email' => $user->email,
        'redirect_to' => 'https://evil.example/steal',
    ]);

    expect(MagicLink::query()->sole()->redirect_to)->toBeNull();
});

// ── Password reset ───────────────────────────────────────────────────────────

it('accepts a reset request for any address without confirming it exists', function () {
    $this->postJson('/api/v1/auth/password/forgot', ['email' => 'ghost@example.com'])
        ->assertStatus(202)
        ->assertJsonPath('data.sent', true);

    Notification::assertNothingSent();
});

it('resets a password with a valid token', function () {
    $user = User::factory()->create(['password' => 'the-old-passphrase']);
    $token = Password::broker()->createToken($user);

    $this->postJson('/api/v1/auth/password/reset', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'a-brand-new-passphrase',
        'password_confirmation' => 'a-brand-new-passphrase',
    ])->assertOk()->assertJsonPath('data.reset', true);

    expect(Hash::check('a-brand-new-passphrase', $user->refresh()->password))->toBeTrue();
});

it('refuses a forged reset token', function () {
    $user = User::factory()->create(['password' => 'the-old-passphrase']);

    $this->postJson('/api/v1/auth/password/reset', [
        'token' => 'not-a-real-token',
        'email' => $user->email,
        'password' => 'a-brand-new-passphrase',
        'password_confirmation' => 'a-brand-new-passphrase',
    ])->assertStatus(422);

    expect(Hash::check('the-old-passphrase', $user->refresh()->password))->toBeTrue();
});

// ── Session ──────────────────────────────────────────────────────────────────

it('reports a guest as null rather than as an error', function () {
    // A signed-out visitor on a public page is normal, not exceptional.
    $this->getJson('/api/v1/auth/session')
        ->assertOk()
        ->assertJsonPath('data', null);
});

it('reports the signed-in user with their effective permissions', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/v1/auth/session')
        ->assertOk()
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.permissions', []);
});

it('marks a newly registered account active in the same request', function () {
    // The column default only applies in the database; a model that never learns its
    // own status reads as inactive for the rest of the request that created it.
    $this->postJson('/api/v1/auth/register', [
        'email' => 'fresh@example.com',
        'password' => 'a-long-enough-passphrase',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'active');
});
