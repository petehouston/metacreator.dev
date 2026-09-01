<?php

declare(strict_types=1);

namespace App\Domain\Tools\Models;

use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tool a member has saved.
 *
 * There is no ULID and no public id: a favourite is never addressed on its own, it
 * is only ever created, deleted or counted through the pair it names.
 *
 * @property int $user_id
 * @property int $tool_id
 */
final class ToolFavorite extends Model
{
    protected $guarded = ['id'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Tool, $this> */
    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }
}
