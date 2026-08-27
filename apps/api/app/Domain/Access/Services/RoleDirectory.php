<?php

declare(strict_types=1);

namespace App\Domain\Access\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Reads roles together with how many people hold each one.
 *
 * `withCount('users')` cannot be used: Spatie resolves the related user model from
 * the role's own `guard_name` attribute, and the blank instance Eloquent builds to
 * plan a count subquery has no attributes yet — so the relation resolves to null
 * and the query dies. The count is done as its own grouped read instead, which is
 * one extra query and no magic.
 */
final class RoleDirectory
{
    /** @return Collection<int, Role> */
    public function all(): Collection
    {
        /** @var Collection<int, Role> $roles */
        $roles = Role::query()
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get();

        $counts = DB::table(config('permission.table_names.model_has_roles', 'model_has_roles'))
            ->groupBy('role_id')
            ->selectRaw('role_id, COUNT(*) as total')
            ->pluck('total', 'role_id');

        return $roles->each(static function (Role $role) use ($counts): void {
            // Set as an attribute rather than a relation count so the resource can
            // read `$role->users_count` exactly as it would after `withCount`.
            $role->setAttribute('users_count', (int) ($counts[$role->getKey()] ?? 0));
        });
    }
}
