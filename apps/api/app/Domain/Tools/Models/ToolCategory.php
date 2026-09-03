<?php

declare(strict_types=1);

namespace App\Domain\Tools\Models;

use App\Domain\Seo\Models\SeoMeta;
use App\Support\Concerns\PreservesAdminEdits;
use Database\Factories\ToolCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * @property string $slug
 * @property string $name
 * @property list<string>|null $locked_fields
 */
final class ToolCategory extends Model
{
    /** @use HasFactory<ToolCategoryFactory> */
    use HasFactory;

    use PreservesAdminEdits;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
            'locked_fields' => 'array',
        ];
    }

    /** Declared because our models live under App\Domain, not App\Models. */
    protected static function newFactory(): ToolCategoryFactory
    {
        return ToolCategoryFactory::new();
    }

    /** @return HasMany<Tool, $this> */
    public function tools(): HasMany
    {
        return $this->hasMany(Tool::class, 'category_id');
    }

    /** @return MorphOne<SeoMeta, $this> */
    public function seo(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true)->orderBy('sort_order');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
