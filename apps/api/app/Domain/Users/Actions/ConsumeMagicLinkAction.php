<?php

declare(strict_types=1);

namespace App\Domain\Users\Actions;

use App\Domain\Users\Exceptions\InvalidMagicLink;
use App\Domain\Users\Models\MagicLink;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Exchanges a magic-link token for an authenticated session.
 *
 * The row is claimed inside a locking transaction: two tabs opening the same emailed
 * link a millisecond apart must not both succeed, or "single use" is a comment
 * rather than a guarantee.
 */
final readonly class ConsumeMagicLinkAction
{
    /** @return array{user: User, redirect_to: string|null} */
    public function execute(string $token): array
    {
        return DB::transaction(function () use ($token): array {
            $link = MagicLink::query()
                ->where('token_hash', MagicLink::hash($token))
                ->lockForUpdate()
                ->first();

            if ($link === null || ! $link->isUsable()) {
                throw new InvalidMagicLink;
            }

            $user = User::query()->where('email', $link->email)->first();

            if ($user === null || ! $user->isActive()) {
                throw new InvalidMagicLink;
            }

            $link->forceFill(['consumed_at' => now()])->save();

            // Receiving mail at the address proves ownership just as well as clicking
            // a verification link does, so don't ask the user to do it twice.
            if (! $user->hasVerifiedEmail()) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            return ['user' => $user, 'redirect_to' => $link->redirect_to];
        });
    }
}
