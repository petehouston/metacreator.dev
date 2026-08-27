<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Domain\Users\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A user as staff see them.
 *
 * Field-level authorization is real here (docs/06): a support agent may see that
 * someone is on a paid plan without seeing the invoice that proves it. The check is
 * on the actor making the request, never on a flag passed in by the client.
 *
 * @mixin User
 */
final class AdminUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $actor = $request->user();

        return [
            'id' => $this->public_id,
            'email' => $this->email,
            'display_name' => $this->displayName(),
            'initials' => $this->initials(),
            'status' => $this->status,
            'is_staff' => $this->isStaff(),
            'roles' => $this->getRoleNames()->values()->all(),
            'email_verified' => $this->hasVerifiedEmail(),
            'has_password' => $this->password !== null,
            'marketing_opt_in' => (bool) $this->marketing_opt_in,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'deletion_requested_at' => $this->deletion_requested_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            'plan' => $this->whenNotNull($this->plan_key ?? null),
            'runs_count' => $this->whenNotNull($this->tool_runs_count ?? null),
            'tickets_count' => $this->whenNotNull($this->tickets_count ?? null),

            'subscription' => $this->whenLoaded('subscriptions', function (): ?array {
                $subscription = $this->subscriptions
                    ->first(fn ($s) => in_array($s->stripe_status, ['active', 'trialing', 'past_due'], true));

                return $subscription === null ? null : [
                    'plan' => $subscription->plan?->name,
                    'status' => $subscription->stripe_status,
                    'renews_at' => $subscription->current_period_end?->toIso8601String(),
                    'cancels_at' => $subscription->cancel_at?->toIso8601String(),
                ];
            }),

            'grants' => $this->whenLoaded('toolGrants', fn () => $this->toolGrants->map(fn ($grant): array => [
                'id' => $grant->id,
                'tool' => ['slug' => $grant->tool?->slug, 'name' => $grant->tool?->name],
                'reason' => $grant->reason,
                'expires_at' => $grant->expires_at?->toIso8601String(),
                'is_active' => $grant->isActive(),
            ])->all()),

            // Invoices carry hosted Stripe URLs, which are effectively bearer links
            // to a financial document. Only an actor who may read invoices gets them.
            'invoices' => $this->when(
                $actor?->can('invoices.view') === true && $this->relationLoaded('invoices'),
                fn () => $this->invoices->map(fn ($invoice): array => [
                    'number' => $invoice->number,
                    'status' => $invoice->status,
                    'total' => $invoice->total,
                    'currency' => $invoice->currency,
                    'issued_at' => $invoice->issued_at?->toIso8601String(),
                    'hosted_url' => $invoice->hosted_url,
                ])->all(),
            ),
        ];
    }
}
