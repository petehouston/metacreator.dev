<?php

declare(strict_types=1);

namespace App\Domain\Blog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 */
final class Tag extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['post_count' => 'integer'];
    }

    /** @return BelongsToMany<Post, $this> */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_tag');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
