<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Domain\Support\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Ticket */
final class AdminTicketResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'reference' => $this->reference,
            'subject' => $this->subject,
            'category' => $this->category,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'priority' => $this->priority->value,
            'is_overdue' => $this->isOverdue(),
            'requester' => $this->whenLoaded('user', fn (): ?array => $this->user === null ? null : [
                'id' => $this->user->public_id,
                'display_name' => $this->user->displayName(),
                'email' => $this->user->email,
                'initials' => $this->user->initials(),
            ]),
            'assignee' => $this->whenLoaded('assignee', fn (): ?array => $this->assignee === null ? null : [
                'id' => $this->assignee->public_id,
                'display_name' => $this->assignee->displayName(),
                'initials' => $this->assignee->initials(),
            ]),
            'messages_count' => $this->whenNotNull($this->messages_count ?? null),
            'messages' => $this->whenLoaded('messages', fn () => $this->messages->map(fn ($message): array => [
                'id' => $message->id,
                'body' => $message->body,
                'author_type' => $message->author_type,
                'is_internal_note' => (bool) $message->is_internal_note,
                'author' => $message->author === null ? null : [
                    'display_name' => $message->author->displayName(),
                    'initials' => $message->author->initials(),
                ],
                'created_at' => $message->created_at?->toIso8601String(),
            ])->all()),
            'first_response_at' => $this->first_response_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'due_at' => $this->due_at?->toIso8601String(),
            'last_activity_at' => $this->last_activity_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
