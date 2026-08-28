<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $status
 * @property CarbonImmutable|null $issued_at
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $refunded_at
 * @property CarbonImmutable|null $period_start
 * @property CarbonImmutable|null $period_end
 */
final class Invoice extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The subscription this invoice renewed, where there was one.
     *
     * Nullable: a one-off pass is a purchase with no agreement behind it, and an
     * invoice whose subscription was later removed is still a financial record.
     *
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return HasMany<InvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isRefunded(): bool
    {
        return (int) $this->amount_refunded > 0;
    }

    /** Paid minus refunded: what the business actually kept. */
    public function netAmount(): int
    {
        return max(0, (int) $this->total - (int) $this->amount_refunded);
    }
}
