<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Domain\Access\PermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

/**
 * A role and the permissions it grants.
 *
 * `is_system` marks the seeded roles the platform depends on. They are editable —
 * the seeded sets are a starting point, not a hierarchy (docs/06) — but they cannot
 * be deleted, because something is checking `super-admin` in code.
 *
 * @mixin Role
 */
final class RoleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $isSystem = array_key_exists($this->name, PermissionCatalog::ROLES);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => PermissionCatalog::ROLES[$this->name]['description'] ?? null,
            'is_system' => $isSystem,
            'is_super_admin' => $this->name === 'super-admin',
            'users_count' => $this->whenNotNull($this->users_count ?? null),
            'permissions' => $this->name === 'super-admin'
                // Super admin holds no explicit rows — it bypasses via Gate::before.
                // Returning an empty list would read in the UI as "no access".
                ? PermissionCatalog::all()
                : $this->permissions->pluck('name')->values()->all(),
        ];
    }
}
