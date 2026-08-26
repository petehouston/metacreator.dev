<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @mixin DatabaseNotification
 */
final class NotificationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->data;

        return [
            'id' => $this->id,
            'event' => $data['event'] ?? null,
            'group' => $data['group'] ?? null,
            'icon' => $data['icon'] ?? 'bell',
            'title' => $data['title'] ?? '',
            'body' => $data['body'] ?? '',
            'action' => isset($data['action_url'])
                ? ['label' => $data['action_label'] ?? 'Open', 'url' => $data['action_url']]
                : null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
