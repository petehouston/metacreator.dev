<?php

declare(strict_types=1);

use App\Domain\Notifications\EventCatalog;
use App\Domain\Notifications\Models\EmailSuppression;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Notifications\Notifier;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\Mail;

// ── Catalog integrity ────────────────────────────────────────────────────────

it('gives every catalog entry a template that exists', function () {
    foreach (EventCatalog::all() as $event) {
        expect(view()->exists($event->template))
            ->toBeTrue("Event [{$event->key}] names a missing template [{$event->template}]");
    }
});

it('refuses to dispatch an event that is not declared', function () {
    $notifier = app(Notifier::class);

    expect(fn () => $notifier->send(User::factory()->create(), 'user.invented_on_the_spot'))
        ->toThrow(InvalidArgumentException::class);
});

it('refuses to send an event whose payload is incomplete', function () {
    $notifier = app(Notifier::class);

    // `user.new_device_login` renders :device, :ip and :signed_in_at — sending it
    // without them would mail a user a message full of literal placeholders.
    expect(fn () => $notifier->send(User::factory()->create(), 'user.new_device_login', ['device' => 'Chrome']))
        ->toThrow(InvalidArgumentException::class);
});

it('never offers a toggle for a notification it will send anyway', function () {
    foreach (EventCatalog::optionalByGroup() as $events) {
        foreach ($events as $event) {
            expect($event->optOut)->toBeTrue();
        }
    }
});

// ── Delivery ─────────────────────────────────────────────────────────────────

it('records an in-app notification and mails it', function () {
    Mail::fake();
    $user = User::factory()->create();

    app(Notifier::class)->send($user, 'user.welcome', ['name' => 'Ada']);

    expect($user->notifications()->count())->toBe(1)
        ->and($user->unreadNotifications()->count())->toBe(1);

    $notification = $user->notifications()->sole();
    expect($notification->data['title'])->toBe('Welcome to MetaCreator, Ada');
});

it('honours an opt-out for events that allow one', function () {
    $user = User::factory()->create();

    NotificationPreference::query()->create([
        'user_id' => $user->id,
        'event_key' => 'tool.run_completed',
        'email' => false,
        'in_app' => false,
    ]);

    app(Notifier::class)->send($user, 'tool.run_completed', ['tool' => 'Hashtag Generator', 'duration' => '1.2s']);

    expect($user->notifications()->count())->toBe(0);
});

it('ignores an opt-out for a security notification', function () {
    $user = User::factory()->create();

    NotificationPreference::query()->create([
        'user_id' => $user->id,
        'event_key' => 'user.password_changed',
        'email' => false,
        'in_app' => false,
    ]);

    app(Notifier::class)->send($user, 'user.password_changed', ['changed_at' => 'today']);

    // A user cannot switch off being told their password changed.
    expect($user->notifications()->count())->toBe(1);
});

it('never mails a suppressed address', function () {
    $user = User::factory()->create();

    EmailSuppression::query()->create([
        'email' => $user->email,
        'reason' => 'bounce',
        'suppressed_at' => now(),
    ]);

    $channels = app(Notifier::class)->channelsFor($user, EventCatalog::get('user.welcome'));

    // The in-app copy still lands; only the email is dropped.
    expect($channels)->not->toContain('mail')
        ->and($channels)->toContain('database');
});

it('cannot opt a user into a channel the event does not declare', function () {
    $user = User::factory()->create();

    NotificationPreference::query()->create([
        'user_id' => $user->id,
        'event_key' => 'tool.run_completed',
        'email' => true,
        'in_app' => true,
    ]);

    // `tool.run_completed` is in-app only. Preferences subtract; they never add.
    expect(app(Notifier::class)->channelsFor($user, EventCatalog::get('tool.run_completed')))
        ->toBe(['database']);
});

// ── Endpoints ────────────────────────────────────────────────────────────────

it('lists notifications with an unread count', function () {
    $user = User::factory()->create();
    app(Notifier::class)->send($user, 'user.welcome', ['name' => 'Ada']);

    $this->actingAs($user)
        ->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.unread', 1)
        ->assertJsonPath('data.0.group', 'security');
});

it('marks a batch of notifications read in one request', function () {
    $user = User::factory()->create();
    app(Notifier::class)->send($user, 'user.welcome', ['name' => 'Ada']);
    app(Notifier::class)->send($user, 'user.password_changed', ['changed_at' => 'today']);

    $ids = $user->notifications()->pluck('id')->all();

    $this->actingAs($user)
        ->postJson('/api/v1/notifications/read', ['ids' => $ids])
        ->assertOk()
        ->assertJsonPath('data.marked', 2)
        ->assertJsonPath('data.unread', 0);
});

it('cannot mark another user\'s notification as read', function () {
    $mine = User::factory()->create();
    $theirs = User::factory()->create();
    app(Notifier::class)->send($theirs, 'user.welcome', ['name' => 'Bob']);

    $ids = $theirs->notifications()->pluck('id')->all();

    $this->actingAs($mine)
        ->postJson('/api/v1/notifications/read', ['ids' => $ids])
        ->assertOk()
        ->assertJsonPath('data.marked', 0);

    expect($theirs->unreadNotifications()->count())->toBe(1);
});

it('exposes preferences grouped for humans, not by event key', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->getJson('/api/v1/account/notification-preferences')
        ->assertOk();

    $groups = collect($response->json('data'));

    expect($groups)->not->toBeEmpty()
        ->and($groups->pluck('label')->all())->each->toBeString();

    // Staff-only alerts must never appear on a customer's settings screen.
    $keys = $groups->pluck('events')->flatten(1)->pluck('key');
    expect($keys->contains('staff.new_ticket'))->toBeFalse();
});

it('saves preference changes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/v1/account/notification-preferences', [
            'preferences' => [
                ['event_key' => 'tool.run_completed', 'email' => false, 'in_app' => false],
            ],
        ])
        ->assertOk();

    expect(NotificationPreference::query()
        ->where('user_id', $user->id)
        ->where('event_key', 'tool.run_completed')
        ->value('in_app'))->toBeFalse();
});

it('rejects a preference change for an event that cannot be turned off', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/v1/account/notification-preferences', [
            'preferences' => [
                ['event_key' => 'user.password_changed', 'email' => false, 'in_app' => false],
            ],
        ])
        ->assertStatus(422);
});
