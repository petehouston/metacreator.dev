<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Models;

use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's per-event channel toggles.
 *
 * Rows only exist for events a user has actually changed — absence means "use the
 * catalog default", which keeps the table small and makes adding a new event a
 * no-op for existing users.
 *
 * @property int $user_id
 * @property string $event_key
 * @property bool $email
 * @property bool $in_app
 */
final class NotificationPreference extends Model
{
    protected $fillable = ['user_id', 'event_key', 'email', 'in_app'];

    protected function casts(): array
    {
        return ['email' => 'boolean', 'in_app' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
