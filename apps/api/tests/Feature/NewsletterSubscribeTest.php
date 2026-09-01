<?php

declare(strict_types=1);

use App\Domain\Newsletter\Mail\NewsletterConfirmationMail;
use App\Domain\Newsletter\Models\NewsletterSubscriber;
use App\Domain\Notifications\Models\EmailSuppression;
use App\Domain\Settings\Setting;
use App\Domain\Settings\Settings;
use Illuminate\Support\Facades\Mail;

/**
 * The public signup path (docs/14).
 *
 * Two properties carry most of the weight: a signup is never lost, and the response
 * says nothing about whether an address is already on the list — a form that
 * answers differently per address is a membership oracle for anyone who asks.
 */
function setNewsletterSetting(string $key, mixed $value, string $type = 'string'): void
{
    Setting::query()->updateOrCreate(
        ['key' => $key],
        ['value' => $value, 'type' => $type, 'group' => 'newsletter'],
    );

    app(Settings::class)->flush();
}

it('accepts a signup and sends a confirmation under double opt-in', function (): void {
    Mail::fake();

    $response = $this->postJson('/api/v1/newsletter/subscribe', [
        'email' => 'Reader@Example.com',
        'source' => 'footer',
        'source_url' => '/blog/some-post',
    ]);

    $response->assertStatus(202)
        ->assertJsonPath('data.requires_confirmation', true);

    $subscriber = NewsletterSubscriber::query()->firstOrFail();

    expect($subscriber->email)->toBe('reader@example.com')
        ->and($subscriber->status)->toBe('pending')
        ->and($subscriber->source)->toBe('footer')
        ->and($subscriber->consent_text)->not->toBeNull()
        ->and($subscriber->consent_ip_hash)->not->toBeNull()
        ->and($subscriber->confirm_token_hash)->not->toBeNull();

    Mail::assertQueued(NewsletterConfirmationMail::class);
});

it('subscribes immediately when double opt-in is off', function (): void {
    Mail::fake();
    setNewsletterSetting('newsletter.double_opt_in', false, 'bool');

    $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'reader@example.com'])
        ->assertStatus(202)
        ->assertJsonPath('data.requires_confirmation', false);

    $subscriber = NewsletterSubscriber::query()->firstOrFail();

    expect($subscriber->status)->toBe('subscribed')
        ->and($subscriber->confirmed_at)->not->toBeNull()
        ->and($subscriber->sync_status)->toBe('synced');

    Mail::assertNothingQueued();
});

it('confirms a pending subscriber and burns the token', function (): void {
    Mail::fake();

    $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'reader@example.com'])
        ->assertStatus(202);

    $token = null;

    Mail::assertQueued(NewsletterConfirmationMail::class, function ($mail) use (&$token): bool {
        $token = (new ReflectionProperty($mail, 'token'))->getValue($mail);

        return true;
    });

    $this->postJson('/api/v1/newsletter/confirm', ['token' => $token])
        ->assertOk()
        ->assertJsonPath('data.confirmed', true);

    $subscriber = NewsletterSubscriber::query()->firstOrFail();

    expect($subscriber->status)->toBe('subscribed')
        ->and($subscriber->confirmed_at)->not->toBeNull()
        ->and($subscriber->confirm_token_hash)->toBeNull();

    // Single use: the same link cannot confirm twice.
    $this->postJson('/api/v1/newsletter/confirm', ['token' => $token])->assertNotFound();
});

it('does not resurrect an unsubscribed address, and says so to nobody', function (): void {
    Mail::fake();

    NewsletterSubscriber::query()->create([
        'email' => 'gone@example.com',
        'status' => 'unsubscribed',
        'unsubscribed_at' => now(),
    ]);

    $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'gone@example.com'])
        ->assertStatus(202)
        ->assertJsonPath('data.requires_confirmation', false);

    expect(NewsletterSubscriber::query()->firstOrFail()->status)->toBe('unsubscribed');

    Mail::assertNothingQueued();
});

it('never mails a suppressed address', function (): void {
    Mail::fake();

    EmailSuppression::query()->create([
        'email' => 'bounced@example.com',
        'reason' => 'hard_bounce',
        'suppressed_at' => now(),
    ]);

    $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'bounced@example.com'])
        ->assertStatus(202);

    expect(NewsletterSubscriber::query()->count())->toBe(0);

    Mail::assertNothingQueued();
});

it('re-sends a confirmation for an address that never confirmed', function (): void {
    Mail::fake();

    $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'reader@example.com'])->assertStatus(202);
    $first = NewsletterSubscriber::query()->firstOrFail()->confirm_token_hash;

    $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'reader@example.com'])->assertStatus(202);

    expect(NewsletterSubscriber::query()->count())->toBe(1)
        ->and(NewsletterSubscriber::query()->firstOrFail()->confirm_token_hash)->not->toBe($first);

    Mail::assertQueued(NewsletterConfirmationMail::class, 2);
});

it('rejects a bad address and a filled honeypot', function (): void {
    $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'not-an-email'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation.failed');

    $this->postJson('/api/v1/newsletter/subscribe', [
        'email' => 'reader@example.com',
        'company' => 'Acme Bots',
    ])->assertStatus(422);

    expect(NewsletterSubscriber::query()->count())->toBe(0);
});

it('404s the whole group when the newsletter is switched off', function (): void {
    Setting::query()->updateOrCreate(
        ['key' => 'features.newsletter_enabled'],
        ['value' => false, 'type' => 'bool', 'group' => 'features', 'is_public' => true],
    );
    app(Settings::class)->flush();

    $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'reader@example.com'])
        ->assertNotFound();
});
