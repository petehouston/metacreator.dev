<?php

declare(strict_types=1);

namespace App\Domain\Blog\Models;

use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A point-in-time copy of a post's title and blocks. Written by autosave and by
 * every explicit save, so an editor can always get back to yesterday's draft.
 *
 * @property array{version: int, blocks: list<array<string, mixed>>} $blocks
 */
final class PostRevision extends Model
{
    /** Rows are immutable; only `created_at` is meaningful. */
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'is_autosave' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
