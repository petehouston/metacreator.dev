<?php

declare(strict_types=1);

namespace App\Domain\Users\Models;

use App\Domain\Billing\Models\AccessPass;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Notifications\EventCatalog;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Notifications\Notifications\CatalogNotification;
use App\Domain\Support\Models\Ticket;
use App\Domain\Tools\Models\ToolGrant;
use App\Domain\Tools\Models\ToolRun;
use App\Support\Concerns\HasUlidKey;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property-read string $public_id
 * @property int $id
 * @property string $email
 * @property string|null $display_name
 * @property string|null $name
 * @property string $status
 * @property string $locale
 * @property string $timezone
 * @property string|null $avatar_path
 * @property string|null $password
 * @property bool $marketing_opt_in
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $deletion_requested_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUlidKey, Notifiable, SoftDeletes;

    protected function ulidPrefix(): string
    {
        return 'usr';
    }

    protected $fillable = [
        'name', 'display_name', 'email', 'password', 'avatar_path',
        'locale', 'timezone', 'marketing_opt_in', 'google_id',
    ];

    protected $hidden = ['password', 'remember_token', 'google_id'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'password' => 'hashed',
            'marketing_opt_in' => 'boolean',
        ];
    }

    /**
     * Email is the account's identity and is immutable.
     *
     * Enforced here rather than only in a form request, because "change the email"
     * is exactly the kind of thing a console command or a future admin screen would
     * otherwise do by accident. Staff perform an audited transfer instead.
     */
    public function setEmailAttribute(string $value): void
    {
        if ($this->exists && $this->getOriginal('email') !== null && $this->getOriginal('email') !== $value) {
            throw new \LogicException(
                'User email is immutable. Use the audited TransferAccountEmailAction instead.'
            );
        }

        $this->attributes['email'] = strtolower(trim($value));
    }

    /**
     * Laravel guesses `Database\Factories\Domain\Users\Models\UserFactory` from the
     * namespace. Our models live under `App\Domain`, so the mapping is declared.
     */
    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    // ── Relations ────────────────────────────────────────────────────────────

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** @return HasMany<AccessPass, $this> */
    public function accessPasses(): HasMany
    {
        return $this->hasMany(AccessPass::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasMany<ToolRun, $this> */
    public function toolRuns(): HasMany
    {
        return $this->hasMany(ToolRun::class);
    }

    /** @return HasMany<ToolGrant, $this> */
    public function toolGrants(): HasMany
    {
        return $this->hasMany(ToolGrant::class);
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /** @return HasMany<UserDevice, $this> */
    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    /** @return HasMany<NotificationPreference, $this> */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    // ── Notification routing ─────────────────────────────────────────────────

    /**
     * Password resets and email verification go through the same catalog as every
     * other message, so they inherit the shared template, the suppression check and
     * the queue settings instead of being two bespoke emails nobody remembers exist.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new CatalogNotification(
            EventCatalog::get('user.password_reset'),
            ['minutes' => (int) config('auth.passwords.users.expire', 60)],
            ['mail'],
            sprintf(
                '%s/reset-password?token=%s&email=%s',
                config('app.frontend_url'),
                $token,
                urlencode($this->email),
            ),
        ));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new CatalogNotification(
            EventCatalog::get('user.email_verify'),
            ['email' => $this->email],
            ['mail'],
            $this->emailVerificationUrl(),
        ));
    }

    /**
     * A signed API URL wrapped in a frontend page.
     *
     * The frontend cannot sign anything, and the API cannot render a confirmation
     * screen — so the link lands on the SPA, which calls the signed URL for it.
     */
    public function emailVerificationUrl(): string
    {
        $signed = URL::temporarySignedRoute(
            'auth.email.verify',
            now()->addMinutes(60),
            ['ulid' => $this->ulid, 'hash' => sha1($this->email)],
        );

        return config('app.frontend_url').'/verify-email?link='.urlencode($signed);
    }

    // ── Behaviour ────────────────────────────────────────────────────────────

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function displayName(): string
    {
        return $this->display_name ?: $this->name ?: str($this->email)->before('@')->toString();
    }

    /** True for anyone with a staff role; drives admin routing and audit expectations. */
    public function isStaff(): bool
    {
        return $this->roles()->exists();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function initials(): string
    {
        return collect(explode(' ', $this->displayName()))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
