<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Access\Services\AuditLogger;
use App\Domain\Notifications\Mail\MailProvider;
use App\Domain\Notifications\Mail\TestMail;
use App\Domain\Settings\Setting;
use App\Domain\Settings\Settings;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Readiness and test sends for the transactional mail configuration.
 *
 * Mail settings are the one group whose breakage is silent — nothing on the site
 * looks wrong while every password reset vanishes — so saving them is not enough on
 * its own. These two endpoints close that gap: one says what the stored settings add
 * up to without sending anything, and one actually pushes a message through the
 * configured transport and reports the provider's own error verbatim when it fails.
 */
final class MailController extends Controller
{
    public function __construct(
        private readonly Settings $settings,
        private readonly AuditLogger $audit,
    ) {}

    /** What the stored settings resolve to, without touching the provider. */
    public function status(): JsonResource
    {
        return new JsonResource($this->currentStatus());
    }

    /**
     * Send one real message through the configured transport.
     *
     * Queued delivery is deliberately bypassed: the entire point is the transport's
     * answer, and a queued send would return 200 whatever happened, leaving the
     * failure in a worker log the person configuring the screen is not watching.
     */
    public function test(Request $request): JsonResource
    {
        $validated = $request->validate([
            'email' => ['nullable', 'email:rfc', 'max:255'],
        ]);

        $actor = $request->user();

        // Defaults to the actor's own address: a test send is a way to reach a
        // provider, and letting an arbitrary address through by default would make
        // this an open relay for anyone who reaches the settings screen.
        $recipient = $validated['email'] ?? (string) $actor?->email;

        if ($recipient === '') {
            return new JsonResource([
                'sent' => false,
                'error' => 'No recipient: give an address to send the test to.',
                'status' => $this->currentStatus(),
            ]);
        }

        $provider = MailProvider::fromSettings($this->settings);

        if (! $provider->isConfigured($this->settings)) {
            return new JsonResource([
                'sent' => false,
                'error' => $this->missingMessage($provider),
                'status' => $this->currentStatus(),
            ]);
        }

        try {
            Mail::to($recipient)->sendNow(new TestMail(
                providerLabel: $provider->label(),
                sentBy: (string) ($actor->email ?? 'an administrator'),
                sentAt: now()->toDayDateTimeString().' UTC',
            ));
        } catch (Throwable $e) {
            // The provider's own words, not a generic failure: "domain not found" and
            // "invalid API key" need different fixes, and paraphrasing loses that.
            return new JsonResource([
                'sent' => false,
                'error' => $e->getMessage(),
                'status' => $this->currentStatus(),
            ]);
        }

        // A successful test proves working credentials to whoever ran it, so it is
        // recorded with the actor the same way a settings change is. The provider
        // row is the subject: it is the setting the test actually exercised, so the
        // entry lands in the same history an admin reads when mail stops working.
        $subject = Setting::query()->where('key', 'mail.provider')->first();

        if ($subject !== null) {
            $this->audit->record(
                event: 'mail.test_sent',
                subject: $subject,
                causer: $actor,
                description: "Test email sent to {$recipient} via {$provider->value}",
            );
        }

        return new JsonResource([
            'sent' => true,
            'recipient' => $recipient,
            'status' => $this->currentStatus(),
        ]);
    }

    /** @return array<string, mixed> */
    private function currentStatus(): array
    {
        $provider = MailProvider::fromSettings($this->settings);

        return [
            'provider' => $provider->value,
            'provider_label' => $provider->label(),
            'configured' => $provider->isConfigured($this->settings),
            'missing' => $this->missing($provider),
            'from_address' => $this->settings->string('mail.from_address'),
            'reply_to_address' => $this->settings->string('mail.reply_to_address'),
            // Klaviyo delivers through a flow the operator has to build, so a green
            // "configured" there means the key is valid, not that mail arrives.
            'delivers_via_flow' => $provider === MailProvider::Klaviyo,
        ];
    }

    /** @return list<string> */
    private function missing(MailProvider $provider): array
    {
        $missing = array_values(array_filter(
            $provider->requiredSettings(),
            fn (string $key): bool => trim((string) $this->settings->get($key, '')) === '',
        ));

        if ($this->settings->string('mail.from_address') === '') {
            array_unshift($missing, 'mail.from_address');
        }

        return $missing;
    }

    private function missingMessage(MailProvider $provider): string
    {
        return $provider->label().' is selected but not fully configured. Missing: '
            .implode(', ', $this->missing($provider)).'.';
    }
}
