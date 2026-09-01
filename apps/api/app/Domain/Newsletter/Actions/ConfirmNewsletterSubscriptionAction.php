<?php

declare(strict_types=1);

namespace App\Domain\Newsletter\Actions;

use App\Domain\Newsletter\Models\NewsletterSubscriber;
use App\Domain\Settings\Settings;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * The second half of double opt-in: the click that turns a pending row into a
 * subscriber, and the moment consent becomes defensible.
 *
 * The token is single-use — confirming clears it — so a forwarded email cannot
 * confirm the same address twice, and a leaked mailbox archive is not a standing
 * key to the list.
 */
final readonly class ConfirmNewsletterSubscriptionAction
{
    /** A link that has sat in an inbox for a season is not consent worth acting on. */
    public const TTL_DAYS = 30;

    public function __construct(private Settings $settings) {}

    public function execute(string $token): NewsletterSubscriber
    {
        $subscriber = NewsletterSubscriber::query()
            ->where('confirm_token_hash', NewsletterSubscriber::hashToken($token))
            ->where('status', 'pending')
            ->where('updated_at', '>=', now()->subDays(self::TTL_DAYS))
            ->first();

        if ($subscriber === null) {
            throw new ModelNotFoundException('That confirmation link is no longer valid.');
        }

        $isLocal = $this->settings->string('newsletter.provider', 'local') === 'local';

        $subscriber->forceFill([
            'status' => 'subscribed',
            'confirmed_at' => now(),
            'confirm_token_hash' => null,
            'sync_status' => $isLocal ? 'synced' : 'pending',
            'synced_at' => $isLocal ? now() : null,
        ])->save();

        return $subscriber;
    }
}
