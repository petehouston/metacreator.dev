<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Domain\Billing\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Subscription */
final class SubscriptionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->stripe_status,
            'is_active' => $this->isActive(),
            'is_cancelling' => $this->isCancelling(),
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user === null ? null : [
                'id' => $this->user->public_id,
                'display_name' => $this->user->displayName(),
                'email' => $this->user->email,
            ]),
            'plan' => $this->whenLoaded('plan', fn (): ?array => $this->plan === null ? null : [
                'key' => $this->plan->key,
                'name' => $this->plan->name,
                'amount' => (int) $this->plan->amount,
                'currency' => $this->plan->currency,
                'interval' => $this->plan->interval,
            ]),
            'current_period_end' => $this->current_period_end?->toIso8601String(),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'cancel_at' => $this->cancel_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
