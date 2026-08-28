<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Domain\Billing\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Row shape for the invoice list.
 *
 * {@see InvoiceDetailResource} extends this to add the lines, the subscription and
 * the gateway references, so it is not final — the columns a list and a detail page
 * share are deliberately declared once.
 *
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'gateway' => $this->gateway,
            'subtotal' => (int) $this->subtotal,
            'tax' => (int) $this->tax,
            'total' => (int) $this->total,
            'amount_refunded' => (int) $this->amount_refunded,
            'net_total' => $this->netAmount(),
            'currency' => $this->currency,
            'payment_method' => $this->paymentMethod(),
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user === null ? null : [
                'id' => $this->user->public_id,
                'display_name' => $this->user->displayName(),
                'email' => $this->user->email,
            ]),
            'issued_at' => $this->issued_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            // Bearer links to a financial document: only for actors who may read them.
            'hosted_url' => $this->when($request->user()?->can('invoices.view') === true, $this->hosted_url),
            'pdf_url' => $this->when($request->user()?->can('invoices.view') === true, $this->pdf_url),
        ];
    }

    /**
     * The card, wallet or bank the money came from — never the full number, which
     * we neither store nor ever want to (docs/15, PCI scope).
     *
     * @return array<string, mixed>|null
     */
    protected function paymentMethod(): ?array
    {
        if ($this->payment_method_type === null && $this->payment_method_brand === null) {
            return null;
        }

        return [
            'type' => $this->payment_method_type,
            'brand' => $this->payment_method_brand,
            'last4' => $this->payment_method_last4,
        ];
    }
}
