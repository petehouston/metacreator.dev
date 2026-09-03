<?php

declare(strict_types=1);

namespace App\Domain\Seo\Models;

use App\Domain\Media\Models\Media;
use App\Support\Concerns\PreservesAdminEdits;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Per-entity SEO overrides. Absent or null fields fall back to entity-derived
 * defaults and then to the site-wide template (see docs/16).
 *
 * @property string|null $robots
 * @property list<string>|null $locked_fields
 */
final class SeoMeta extends Model
{
    use PreservesAdminEdits;

    protected $table = 'seo_meta';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['schema_overrides' => 'array', 'locked_fields' => 'array'];
    }

    /** @return MorphTo<Model, $this> */
    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }

    /** The social-card image override, when one is set. */
    /** @return BelongsTo<Media, $this> */
    public function ogMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'og_media_id');
    }

    public function isIndexable(): bool
    {
        return ! str_contains((string) $this->robots, 'noindex');
    }
}
