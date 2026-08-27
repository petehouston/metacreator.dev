<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Domain\Newsletter\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin NewsletterSubscriber */
final class NewsletterSubscriberResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'status' => $this->status,
            'source' => $this->source,
            'tags' => $this->tags ?? [],
            'provider' => $this->provider,
            'sync_status' => $this->sync_status,
            'sync_error' => $this->sync_error,
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'unsubscribed_at' => $this->unsubscribed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
