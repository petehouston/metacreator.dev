<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Addresses that must never be mailed again — bounces and spam complaints synced
 * back from the provider's webhooks.
 *
 * Checked before every send. Sending to a known-bad address is the fastest way to
 * lose the sending reputation that password resets depend on.
 *
 * @property string $email
 * @property string $reason
 */
final class EmailSuppression extends Model
{
    protected $fillable = ['email', 'reason', 'suppressed_at'];

    protected function casts(): array
    {
        return ['suppressed_at' => 'datetime'];
    }

    public static function suppresses(string $email): bool
    {
        return self::query()->where('email', mb_strtolower(trim($email)))->exists();
    }
}
