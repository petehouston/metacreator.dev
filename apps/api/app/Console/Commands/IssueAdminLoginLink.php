<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Access\Services\AuditLogger;
use App\Domain\Users\Actions\IssueMagicLinkAction;
use App\Domain\Users\Models\MagicLink;
use App\Domain\Users\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Prints a single-use sign-in URL for a staff account (docs/06).
 *
 * The password-reset path needs working mail; this one does not, which is the whole
 * point — it is what an operator with shell access reaches for when an admin is
 * locked out and SMTP is the thing that broke. The URL is printed to the terminal
 * rather than emailed, so it is only as secure as the channel used to relay it:
 * short TTL, single use, and every issue is written to the audit log.
 *
 * Unlike {@see IssueMagicLinkAction}, this command is loud about a missing or
 * inactive account — console callers are already trusted, and the anti-enumeration
 * silence that protects the public endpoint would only waste an operator's time.
 */
final class IssueAdminLoginLink extends Command
{
    protected $signature = 'admin:login-link
                            {email : Email address of the staff account}
                            {--ttl=15 : Minutes the link stays valid}
                            {--redirect=/c0ns0le : Same-site path to land on after sign-in}
                            {--any-user : Allow issuing for a non-staff account}';

    protected $description = 'Print a one-time sign-in URL for an admin who cannot use their password';

    public function handle(AuditLogger $audit): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $ttl = max(1, (int) $this->option('ttl'));

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->components->error("No account exists for {$email}.");

            return self::FAILURE;
        }

        if (! $user->isActive()) {
            $this->components->error("{$email} is {$user->status}. Reinstate the account before issuing a link.");

            return self::FAILURE;
        }

        if (! $user->isStaff() && ! $this->option('any-user')) {
            $this->components->error("{$email} holds no staff role. Pass --any-user if that is intended.");

            return self::FAILURE;
        }

        $redirect = $this->safeRedirect((string) $this->option('redirect'));

        // Same rule as the emailed link: issuing one invalidates the rest, so a link
        // relayed over a channel that later turns out to be compromised can be
        // revoked simply by issuing another.
        $superseded = MagicLink::query()
            ->where('email', $email)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $token = Str::random(64);

        MagicLink::query()->create([
            'email' => $email,
            'token_hash' => MagicLink::hash($token),
            'intent' => 'login',
            'redirect_to' => $redirect,
            'expires_at' => now()->addMinutes($ttl),
        ]);

        $audit->record(
            event: 'login_link_issued',
            subject: $user,
            after: ['ttl_minutes' => $ttl, 'redirect_to' => $redirect],
            logName: 'security',
            description: "One-time sign-in link issued for {$email} via console",
        );

        $this->newLine();
        $this->components->info('One-time sign-in link (valid once, expires in '.$ttl.' minute(s)):');
        $this->line('  <options=bold>'.config('app.frontend_url').'/auth/magic?token='.$token.'</>');
        $this->newLine();
        $this->components->twoColumnDetail('Account', $email.' ('.implode(', ', $user->getRoleNames()->all() ?: ['no roles']).')');
        $this->components->twoColumnDetail('Expires', now()->addMinutes($ttl)->toDateTimeString());
        $this->components->twoColumnDetail('Lands on', $redirect ?? 'default post-login screen');

        if ($superseded > 0) {
            $this->components->warn("Invalidated {$superseded} outstanding link(s) for this address.");
        }

        $this->components->warn('Treat this URL as a password: anyone holding it becomes this user.');

        return self::SUCCESS;
    }

    /**
     * Only same-site paths survive — an open redirect on the one URL that ends in an
     * authenticated session is exactly the phishing primitive an attacker wants.
     */
    private function safeRedirect(string $redirect): ?string
    {
        if (! str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
            $this->components->warn("Ignoring redirect \"{$redirect}\": only same-site paths are allowed.");

            return null;
        }

        return Str::limit($redirect, 250, '');
    }
}
