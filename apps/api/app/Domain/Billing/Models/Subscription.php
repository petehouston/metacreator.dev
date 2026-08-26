<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A projection of a Stripe subscription (ADR 0004).
 *
 * Never write to this outside a webhook handler — the architecture test enforces it.
 *
 * @property string $stripe_status
 * @property CarbonImmutable|null $current_period_start
 * @property CarbonImmutable|null $current_period_end
 * @property CarbonImmutable|null $trial_ends_at
 * @property CarbonImmutable|null $cancel_at
 * @property CarbonImmutable|null $ends_at
 * @property-read Plan|null $plan
 * @property string $stripe_status
 * @property CarbonImmutable|null $current_period_start
 * @property CarbonImmutable|null $current_period_end
 * @property CarbonImmutable|null $trial_ends_at
 * @property CarbonImmutable|null $cancel_at
 * @property CarbonImmutable|null $ends_at
 * @property-read Plan|null $plan
 */
final class Subscription extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'trial_ends_at' => 'datetime',
            'cancel_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
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

    public function isActive(): bool
    {
        return in_array($this->stripe_status, ['active', 'trialing'], true);
    }

    public function isCancelling(): bool
    {
        return $this->cancel_at !== null && $this->cancel_at->isFuture();
    }
}
