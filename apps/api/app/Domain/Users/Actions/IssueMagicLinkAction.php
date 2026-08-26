<?php

declare(strict_types=1);

namespace App\Domain\Users\Actions;

use App\Domain\Notifications\Notifier;
use App\Domain\Users\Models\MagicLink;
use App\Domain\Users\Models\User;
use Illuminate\Support\Str;

/**
 * Issues a single-use sign-in link (docs/06).
 *
 * Two rules shape this: issuing a link invalidates every outstanding link for that
 * address, and the caller is told nothing about whether the address exists. Callers
 * always get the same answer, so this endpoint cannot be used to enumerate accounts.
 */
final readonly class IssueMagicLinkAction
{
    public const TTL_MINUTES = 15;

    public function __construct(private Notifier $notifier) {}

    public function execute(string $email, ?string $redirectTo = null): void
    {
        $email = mb_strtolower(trim($email));

        $user = User::query()->where('email', $email)->first();

        // No account, no email — but the controller still returns 202. Silence here
        // is the entire anti-enumeration property.
        if ($user === null || ! $user->isActive()) {
            return;
        }

        MagicLink::query()
            ->where('email', $email)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $token = Str::random(64);

        MagicLink::query()->create([
            'email' => $email,
            'token_hash' => MagicLink::hash($token),
            'intent' => 'login',
            'redirect_to' => $this->safeRedirect($redirectTo),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        $this->notifier->send(
            $user,
            'user.magic_link',
            ['minutes' => self::TTL_MINUTES],
            actionUrl: $this->linkUrl($token),
        );
    }

    private function linkUrl(string $token): string
    {
        return config('app.frontend_url').'/auth/magic?token='.$token;
    }

    /**
     * Only same-site paths survive. An open redirect on the one endpoint that ends in
     * an authenticated session is exactly the phishing primitive an attacker wants.
     */
    private function safeRedirect(?string $redirectTo): ?string
    {
        if ($redirectTo === null || ! str_starts_with($redirectTo, '/') || str_starts_with($redirectTo, '//')) {
            return null;
        }

        return Str::limit($redirectTo, 250, '');
    }
}
