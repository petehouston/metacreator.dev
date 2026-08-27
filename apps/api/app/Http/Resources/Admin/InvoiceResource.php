<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Domain\Billing\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invoice */
final class InvoiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'subtotal' => (int) $this->subtotal,
            'tax' => (int) $this->tax,
            'total' => (int) $this->total,
            'amount_refunded' => (int) $this->amount_refunded,
            'currency' => $this->currency,
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user === null ? null : [
                'id' => $this->user->public_id,
                'display_name' => $this->user->displayName(),
                'email' => $this->user->email,
            ]),
            'issued_at' => $this->issued_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            // Bearer links to a financial document: only for actors who may read them.
            'hosted_url' => $this->when($request->user()?->can('invoices.view') === true, $this->hosted_url),
            'pdf_url' => $this->when($request->user()?->can('invoices.view') === true, $this->pdf_url),
        ];
    }
}
