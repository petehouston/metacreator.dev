<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Domain\Billing\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Plan */
final class PlanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'billing_mode' => $this->billing_mode,
            'interval' => $this->interval,
            'interval_count' => (int) $this->interval_count,
            'amount' => (int) $this->amount,
            'currency' => $this->currency,
            'duration_days' => $this->duration_days === null ? null : (int) $this->duration_days,
            'features' => $this->features ?? [],
            'limits' => $this->limits ?? [],
            'is_active' => (bool) $this->is_active,
            'is_highlighted' => (bool) $this->is_highlighted,
            'sort_order' => (int) $this->sort_order,
            'stripe_price_id' => $this->stripe_price_id,
            'gateway_ids' => $this->gateway_ids ?? [],
            'total_subscriptions' => (int) $this->subscriptions()->count(),
            'active_subscriptions' => $this->whenNotNull($this->subscriptions_count ?? null),
        ];
    }
}
