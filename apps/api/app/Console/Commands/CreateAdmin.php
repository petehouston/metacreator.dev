<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Access\PermissionCatalog;
use App\Domain\Access\Services\AuditLogger;
use App\Domain\Users\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Creates (or promotes) a staff account from the console.
 *
 * This is the bootstrap path: the first `super-admin` on a fresh deploy, and the
 * escape hatch when every remaining admin has locked themselves out. It defaults to
 * `super-admin` because that is the role `Gate::before` short-circuits — the only
 * one that is genuinely unrestricted. `--role=admin` deliberately gets less (see
 * {@see PermissionCatalog::ADMIN_EXCLUSIONS}).
 *
 * Existing accounts are promoted rather than rejected, so running this twice is
 * safe, and a customer who joins the team keeps their history.
 */
final class CreateAdmin extends Command
{
    protected $signature = 'admin:create
                            {email? : Email address of the account}
                            {--name= : Display name (defaults to the local part of the email)}
                            {--role=super-admin : Role to grant}
                            {--password= : Set a password instead of generating one}
                            {--no-password : Leave the account password-less — sign in with admin:login-link}
                            {--replace-roles : Replace existing roles instead of adding to them}';

    protected $description = 'Create a staff account with full admin permissions';

    public function handle(AuditLogger $audit): int
    {
        $email = mb_strtolower(trim(
            is_string($this->argument('email')) && $this->argument('email') !== ''
                ? $this->argument('email')
                : (string) $this->ask('Email address')
        ));

        $role = (string) $this->option('role');

        try {
            $this->validate($email, $role);
        } catch (ValidationException $e) {
            foreach ($e->validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->withTrashed()->first();
        $existing = $user !== null;

        // A soft-deleted account still owns its email — restoring is the only way to
        // reuse the address, since `email` is immutable and uniquely indexed.
        if ($existing && $user->trashed()) {
            $user->restore();
            $this->components->warn('Restored a previously deleted account.');
        }

        [$password, $generated] = $this->resolvePassword();

        $user ??= new User(['email' => $email]);

        $user->fill([
            'name' => $this->resolveName($email, $user->name),
            'display_name' => $this->resolveName($email, $user->display_name),
        ]);

        if ($password !== null) {
            $user->password = $password;
        }

        // Staff created here are usable immediately: a console operator already
        // proved control of the machine, and a verification email nobody can click
        // would only lock them out again.
        $user->forceFill([
            'status' => 'active',
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $before = $user->roles->pluck('name')->all();

        $this->option('replace-roles') || ! $existing
            ? $user->syncRoles([$role])
            : $user->assignRole($role);

        $after = $user->fresh('roles')?->roles->pluck('name')->all() ?? [$role];

        $audit->record(
            event: $existing ? 'promoted' : 'created',
            subject: $user,
            before: ['roles' => $before],
            after: ['roles' => $after],
            description: sprintf('%s granted %s via admin:create (console)', $email, $role),
        );

        $this->components->info(sprintf(
            '%s %s with role "%s".',
            $email,
            $existing ? 'promoted' : 'created',
            $role,
        ));

        $this->components->twoColumnDetail('Roles', implode(', ', $after));

        if ($generated) {
            $this->components->twoColumnDetail('Password', "<comment>{$password}</comment>");
            $this->components->warn('This password is shown once. Store it now, or change it after signing in.');
        }

        if ($password === null && ! $existing) {
            $this->components->warn("No password set — issue a sign-in link with: php artisan admin:login-link {$email}");
        }

        return self::SUCCESS;
    }

    /** @throws ValidationException */
    private function validate(string $email, string $role): void
    {
        validator(
            ['email' => $email, 'role' => $role],
            [
                // `rfc` without `dns`: the bootstrap admin is often created on a
                // machine that cannot resolve anything yet, and a failed lookup here
                // would block the one command that unblocks the deploy.
                'email' => ['required', 'email:rfc', 'max:255'],
                'role' => ['required', 'string', Rule::in(Role::query()->pluck('name'))],
            ],
            [
                'role.in' => "Role \"{$role}\" does not exist. Run `php artisan db:seed --class=RolePermissionSeeder` first, or pick one of: "
                    .implode(', ', array_keys(PermissionCatalog::ROLES)).'.',
            ],
        )->validate();
    }

    /**
     * @return array{0: string|null, 1: bool} The password (null to leave untouched)
     *                                        and whether it needs printing.
     */
    private function resolvePassword(): array
    {
        if ($this->option('no-password')) {
            return [null, false];
        }

        if (is_string($given = $this->option('password')) && $given !== '') {
            return [$given, false];
        }

        if ($this->input->isInteractive() && ! $this->option('no-interaction')) {
            $entered = (string) $this->secret('Password (leave blank to generate one)');

            if ($entered !== '') {
                return [$entered, false];
            }
        }

        // 32 chars from Str::password — plenty of entropy for an account that can do
        // anything, and the operator never has to invent one under pressure.
        return [Str::password(32), true];
    }

    private function resolveName(string $email, ?string $current): string
    {
        if (is_string($name = $this->option('name')) && $name !== '') {
            return $name;
        }

        return $current ?: Str::of($email)->before('@')->headline()->toString();
    }
}
