<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Time-boxed paid access from a one-off purchase (the 7-day pass) or an admin comp.
 *
 * Expiry is enforced by the query, not only by a scheduled job — a missed job must
 * never silently extend someone's access.
 *
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $expires_at
 * @property int $user_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $expires_at
 * @property int $user_id
 */
final class AccessPass extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('starts_at', '<=', now())->where('expires_at', '>', now());
    }

    public function isActive(): bool
    {
        return $this->starts_at->isPast() && $this->expires_at->isFuture();
    }
}
