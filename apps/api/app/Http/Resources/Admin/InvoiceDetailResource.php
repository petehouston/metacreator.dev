<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\InvoiceLine;
use Illuminate\Http\Request;

/**
 * One invoice, complete enough to settle a dispute without opening the gateway.
 *
 * The extra fields over the list row are all answers to questions somebody asks
 * while looking at a single charge: what exactly was billed (`lines`), for which
 * period, against which subscription and plan, on what card, under which
 * transaction at the provider, and — if money went back — how much, when and why.
 *
 * `transaction_id`, `transaction_url` and the refund reference are gated on
 * `invoices.view` for the same reason `hosted_url` is: they identify a payment at
 * the provider, and anyone holding one can look up more than this screen shows.
 *
 * @mixin Invoice
 */
final class InvoiceDetailResource extends InvoiceResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $mayViewReferences = $request->user()?->can('invoices.view') === true;

        return [
            ...parent::toArray($request),

            'billing_name' => $this->billing_name ?? $this->user?->displayName(),
            'billing_email' => $this->billing_email ?? $this->user?->email,

            'period_start' => $this->period_start?->toIso8601String(),
            'period_end' => $this->period_end?->toIso8601String(),

            'lines' => $this->whenLoaded('lines', fn () => $this->lines
                ->map(fn (InvoiceLine $line): array => [
                    'id' => $line->id,
                    'description' => $line->description,
                    'quantity' => (int) $line->quantity,
                    'unit_amount' => (int) $line->unit_amount,
                    'amount' => (int) $line->amount,
                ])
                ->values()
                ->all(), []),

            'subscription' => $this->whenLoaded('subscription', fn (): ?array => $this->subscription === null ? null : [
                'id' => $this->subscription->id,
                'status' => $this->subscription->stripe_status,
                'current_period_end' => $this->subscription->current_period_end?->toIso8601String(),
                'cancellation_reason' => $this->subscription->cancellation_reason,
            ]),

            'plan' => $this->whenLoaded('plan', fn (): ?array => $this->plan === null ? null : [
                'id' => $this->plan->id,
                'key' => $this->plan->key,
                'name' => $this->plan->name,
                'amount' => (int) $this->plan->amount,
                'currency' => $this->plan->currency,
                'interval' => $this->plan->interval,
                'billing_mode' => $this->plan->billing_mode,
            ]),

            'refund' => $this->amount_refunded > 0 ? [
                'amount' => (int) $this->amount_refunded,
                'is_partial' => (int) $this->amount_refunded < (int) $this->total,
                'refunded_at' => $this->refunded_at?->toIso8601String(),
                'reason' => $this->refund_reason,
                'reference' => $this->when($mayViewReferences, $this->refund_reference),
            ] : null,

            'transaction_id' => $this->when($mayViewReferences, $this->transaction_id),
            'transaction_url' => $this->when($mayViewReferences, $this->transaction_url),
        ];
    }
}
