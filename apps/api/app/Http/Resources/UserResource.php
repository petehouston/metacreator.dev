<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Access\PermissionCatalog;
use App\Domain\Users\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * The signed-in user, as the frontend sees themselves.
 *
 * Roles and permissions are flattened into a list the UI can check directly, so the
 * admin shell never has to guess what to render — and never has to be the thing
 * enforcing it either, since every route re-checks server-side.
 *
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'email' => $this->email,
            'display_name' => $this->displayName(),
            'initials' => $this->initials(),
            'avatar_url' => $this->avatarUrl(),
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'status' => $this->status,
            'marketing_opt_in' => (bool) $this->marketing_opt_in,
            'email_verified' => $this->hasVerifiedEmail(),
            'has_password' => $this->password !== null,
            'is_staff' => $this->isStaff(),
            'roles' => $this->getRoleNames()->values()->all(),
            'permissions' => $this->permissionNames(),
            'deletion_requested_at' => $this->deletion_requested_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function avatarUrl(): ?string
    {
        if ($this->avatar_path === null) {
            return null;
        }

        return Storage::disk(config('filesystems.default'))->url($this->avatar_path);
    }

    /**
     * A super admin's effective permission set is "everything", and they hold no
     * explicit rows — so expand the catalog rather than returning an empty list the
     * UI would read as "no access".
     *
     * @return list<string>
     */
    private function permissionNames(): array
    {
        if ($this->hasRole('super-admin')) {
            return PermissionCatalog::all();
        }

        return array_values(array_map(
            static fn (mixed $name): string => (string) $name,
            $this->getAllPermissions()->pluck('name')->all(),
        ));
    }
}
