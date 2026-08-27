<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Domain\Support\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContactMessage */
final class ContactMessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'subject' => $this->subject,
            'message' => $this->message,
            'topic' => $this->topic,
            'handled_at' => $this->handled_at?->toIso8601String(),
            'handled_by' => $this->whenLoaded('handler', fn () => $this->handler?->displayName()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
