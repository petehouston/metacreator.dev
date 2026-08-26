<?php

declare(strict_types=1);

namespace App\Domain\Media\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A derived rendition of a {@see Media} file — one label/format pair, e.g. the
 * 1200px AVIF. Generated asynchronously; see docs/10-media-library.md.
 */
final class MediaVariant extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['width' => 'integer', 'height' => 'integer', 'size' => 'integer'];
    }

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
