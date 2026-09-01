<?php

declare(strict_types=1);

namespace App\Domain\Newsletter\Actions;

use App\Domain\Newsletter\Mail\NewsletterConfirmationMail;
use App\Domain\Newsletter\Models\NewsletterSubscriber;
use App\Domain\Notifications\Models\EmailSuppression;
use App\Domain\Settings\Settings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * A signup from any public capture placement (docs/14).
 *
 * The list is written locally first, always — whichever provider is configured
 * syncs afterwards, so a provider outage or a wrong API key can never lose a
 * signup. With `local` there is nothing to sync and the row is the list.
 *
 * The result is deliberately the same shape whatever the address's history:
 * already subscribed, previously unsubscribed and suppressed, or brand new. A
 * signup form that answers differently per address is a list-membership oracle.
 */
final readonly class SubscribeToNewsletterAction
{
    public function __construct(private Settings $settings) {}

    /**
     * @return array{requires_confirmation: bool}
     */
    public function execute(
        string $email,
        ?string $name = null,
        ?string $source = null,
        ?string $sourceUrl = null,
        ?string $ipHash = null,
        ?string $consentText = null,
    ): array {
        $email = mb_strtolower(trim($email));
        $doubleOptIn = $this->settings->bool('newsletter.double_opt_in', true);

        $subscriber = NewsletterSubscriber::query()->where('email', $email)->first();

        // Bounces and complaints stay off the list, and an unsubscribe is a decision
        // the person already made — neither may be undone by a form post (docs/14).
        // Silence, not an error: the caller is told nothing either way.
        if ($this->isSuppressed($email, $subscriber)) {
            return ['requires_confirmation' => false];
        }

        if ($subscriber?->status === 'subscribed') {
            return ['requires_confirmation' => false];
        }

        $token = $doubleOptIn ? Str::random(64) : null;

        $attributes = [
            'name' => $name ?: $subscriber?->name,
            'status' => $doubleOptIn ? 'pending' : 'subscribed',
            'source' => $source ?: $subscriber?->source,
            'source_url' => $sourceUrl ?: $subscriber?->source_url,
            'consent_text' => $consentText,
            'consent_ip_hash' => $ipHash,
            'confirm_token_hash' => $token === null ? null : NewsletterSubscriber::hashToken($token),
            'confirmed_at' => $doubleOptIn ? null : now(),
            'provider' => $this->settings->string('newsletter.provider', 'local'),
        ];

        // Nothing to sync when the local table *is* the list; any other provider
        // leaves the row queued for the sync worker.
        $attributes += $this->syncStateFor((bool) $attributes['confirmed_at']);

        $subscriber = NewsletterSubscriber::query()->updateOrCreate(['email' => $email], $attributes);

        if ($token !== null) {
            $this->sendConfirmation($subscriber, $token);
        }

        return ['requires_confirmation' => $doubleOptIn];
    }

    private function isSuppressed(string $email, ?NewsletterSubscriber $subscriber): bool
    {
        return in_array($subscriber?->status, ['unsubscribed', 'bounced'], true)
            || EmailSuppression::suppresses($email);
    }

    /** @return array<string, mixed> */
    private function syncStateFor(bool $confirmed): array
    {
        $isLocal = $this->settings->string('newsletter.provider', 'local') === 'local';

        return $isLocal && $confirmed
            ? ['sync_status' => 'synced', 'synced_at' => now(), 'sync_error' => null]
            : ['sync_status' => 'pending', 'sync_error' => null];
    }

    private function sendConfirmation(NewsletterSubscriber $subscriber, string $token): void
    {
        try {
            Mail::to($subscriber->email)->send(new NewsletterConfirmationMail($token));
        } catch (Throwable $e) {
            // Under `QUEUE_CONNECTION=sync` an unreachable SMTP host would otherwise
            // turn a stored signup into a 500. The row is already safe; the person can
            // submit the form again to get a fresh link.
            Log::error('Newsletter confirmation email failed', [
                'subscriber' => $subscriber->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
