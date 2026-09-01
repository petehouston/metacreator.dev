<?php

declare(strict_types=1);

namespace App\Domain\Tools\Services;

use App\Domain\Tools\Models\Tool;
use App\Domain\Tools\Models\ToolFavorite;
use App\Domain\Users\Models\User;
use Illuminate\Database\QueryException;

/**
 * A member's saved tools.
 *
 * Small enough to be a service rather than an action because every operation is one
 * statement — the value is that "which slugs has this person saved?" is answered in
 * one place, so the catalog listing, the tool page and the favourites screen cannot
 * disagree about it.
 */
final readonly class FavoriteTools
{
    /**
     * The slugs this user has saved, newest first.
     *
     * @return list<string>
     */
    public function slugsFor(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        /** @var list<string> $slugs */
        $slugs = ToolFavorite::query()
            ->join('tools', 'tools.id', '=', 'tool_favorites.tool_id')
            ->where('tool_favorites.user_id', $user->id)
            ->whereNull('tools.deleted_at')
            ->orderByDesc('tool_favorites.created_at')
            ->pluck('tools.slug')
            ->all();

        return $slugs;
    }

    public function add(User $user, Tool $tool): void
    {
        try {
            ToolFavorite::query()->create(['user_id' => $user->id, 'tool_id' => $tool->id]);
        } catch (QueryException $e) {
            // Saving something already saved is the same outcome the caller asked
            // for, so the unique constraint is a success, not an error. Anything
            // else is a real failure and still surfaces.
            if (! $this->isDuplicate($e)) {
                throw $e;
            }
        }
    }

    public function remove(User $user, Tool $tool): void
    {
        ToolFavorite::query()
            ->where('user_id', $user->id)
            ->where('tool_id', $tool->id)
            ->delete();
    }

    public function has(User $user, Tool $tool): bool
    {
        return ToolFavorite::query()
            ->where('user_id', $user->id)
            ->where('tool_id', $tool->id)
            ->exists();
    }

    private function isDuplicate(QueryException $e): bool
    {
        // 23000 is the SQLSTATE integrity-constraint class, which is what both
        // MySQL and SQLite report a unique-key collision as.
        return $e->getCode() === '23000';
    }
}
