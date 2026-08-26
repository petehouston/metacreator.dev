<?php

declare(strict_types=1);

namespace App\Domain\Users\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A single-use, short-lived sign-in link.
 *
 * Only the SHA-256 of the token is stored. A leaked database therefore yields no
 * usable links, which is the same reasoning behind hashing password-reset tokens —
 * a magic link *is* a bearer credential.
 *
 * @property string $email
 * @property string $token_hash
 * @property string $intent
 * @property string|null $redirect_to
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 */
final class MagicLink extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['email', 'token_hash', 'intent', 'redirect_to', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'consumed_at' => 'datetime'];
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** @param Builder<self> $query */
    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('consumed_at')->where('expires_at', '>', now());
    }

    public function isUsable(): bool
    {
        return $this->consumed_at === null && $this->expires_at->isFuture();
    }
}
