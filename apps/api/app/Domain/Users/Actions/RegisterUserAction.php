<?php

declare(strict_types=1);

namespace App\Domain\Users\Actions;

use App\Domain\Notifications\Notifier;
use App\Domain\Tools\Models\ToolRun;
use App\Domain\Users\Models\User;
use App\Http\Middleware\IdentifyVisitor;
use Illuminate\Support\Facades\DB;

/**
 * Creates an account and everything that must be true the moment it exists.
 *
 * Claiming the visitor's anonymous runs is part of the transaction on purpose: a
 * visitor who tried three tools and then signed up should find those three runs in
 * their history, and a partial failure that loses them is worse than a failed signup.
 */
final readonly class RegisterUserAction
{
    public function __construct(private Notifier $notifier) {}

    /**
     * @param  array{email: string, password?: string|null, display_name?: string|null, marketing_opt_in?: bool, locale?: string, timezone?: string}  $attributes
     * @param  string|null  $visitorHash  From {@see IdentifyVisitor}, when the signup came from a browsing session.
     */
    public function execute(array $attributes, ?string $visitorHash = null): User
    {
        $user = DB::transaction(function () use ($attributes, $visitorHash): User {
            $user = new User;

            $user->email = $attributes['email'];
            $user->fill([
                'display_name' => $attributes['display_name'] ?? null,
                'password' => $attributes['password'] ?? null,
                'marketing_opt_in' => $attributes['marketing_opt_in'] ?? false,
                'locale' => $attributes['locale'] ?? 'en',
                'timezone' => $attributes['timezone'] ?? 'UTC',
            ]);

            // Set explicitly rather than leaning on the column default: a default is
            // only applied by the database, so the in-memory model would carry a null
            // status for the rest of the request — and `isActive()` would be false for
            // the user we just created.
            $user->status = 'active';

            $user->save();

            if ($visitorHash !== null) {
                $this->claimAnonymousRuns($user, $visitorHash);
            }

            return $user;
        });

        $this->notifier->send($user, 'user.welcome', ['name' => $user->displayName()]);

        // Magic-link and Google signups arrive with a proven address; only
        // password signups need to demonstrate they own it.
        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return $user;
    }

    /**
     * The visitor salt rotates daily, so this only ever claims today's runs. That is
     * the intended limit rather than a bug: a hash that could reach further back
     * would also be a hash that correlates a person across days.
     */
    private function claimAnonymousRuns(User $user, string $visitorHash): void
    {
        ToolRun::query()
            ->whereNull('user_id')
            ->where('visitor_hash', $visitorHash)
            ->update(['user_id' => $user->id]);
    }
}
