<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Domain\Users\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Activitylog\Models\Activity;

/**
 * One audited action.
 *
 * The subject is described by type and label rather than embedded: the row it
 * points at may have been deleted since, and an audit trail that 500s on deleted
 * subjects is an audit trail nobody can read after an incident.
 *
 * @mixin Activity
 */
final class ActivityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'log_name' => $this->log_name,
            'event' => $this->event,
            'description' => $this->description,
            // The causer is a morph target typed as a bare Model. It is always a
            // User in practice, but an audit trail must not 500 because something
            // unexpected once caused an entry.
            'causer' => $this->causer instanceof User ? [
                'display_name' => $this->causer->displayName(),
                'initials' => $this->causer->initials(),
                'email' => $this->causer->email,
            ] : null,
            'subject' => $this->subject_type === null ? null : [
                'type' => class_basename((string) $this->subject_type),
                'id' => $this->subject_id,
            ],
            'changes' => $this->properties['changes'] ?? null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
