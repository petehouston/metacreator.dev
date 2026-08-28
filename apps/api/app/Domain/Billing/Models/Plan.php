<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $key
 * @property string $billing_mode
 * @property string|null $interval
 * @property int $amount
 * @property string $key
 * @property string $billing_mode
 * @property string|null $interval
 * @property int $amount
 */
final class Plan extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'gateway_ids' => 'array',
            'limits' => 'array',
            'is_active' => 'boolean',
            'is_highlighted' => 'boolean',
            'amount' => 'integer',
        ];
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function isOneTime(): bool
    {
        return $this->billing_mode === 'one_time';
    }

    /** Display price in major units, e.g. 1900 → "19.00". */
    public function formattedAmount(): string
    {
        return number_format($this->amount / 100, 2);
    }

    /**
     * Effective monthly cost, so the pricing page can show yearly savings honestly.
     */
    public function monthlyEquivalent(): ?int
    {
        return match ($this->interval) {
            'month' => $this->amount,
            'year' => (int) round($this->amount / 12),
            default => null,
        };
    }
}
