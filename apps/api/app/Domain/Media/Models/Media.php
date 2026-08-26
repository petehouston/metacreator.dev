<?php

declare(strict_types=1);

namespace App\Domain\Media\Models;

use App\Support\Concerns\HasUlidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * A file in the media library.
 *
 * Only the read side is implemented so far — enough for posts to carry a featured
 * image. Uploading, variant generation and the admin browser are specified in
 * docs/10-media-library.md and not yet built.
 *
 * @property-read string $public_id
 * @property string $disk
 * @property string $path
 * @property string $mime_type
 * @property string|null $alt_text
 * @property int|null $width
 * @property int|null $height
 */
final class Media extends Model
{
    use HasUlidKey, SoftDeletes;

    protected $table = 'media';

    protected function ulidPrefix(): string
    {
        return 'med';
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration_ms' => 'integer',
            'usage_count' => 'integer',
            'is_decorative' => 'boolean',
        ];
    }

    /** @return HasMany<MediaVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(MediaVariant::class);
    }

    public function url(): string
    {
        // The seeded demo images are absolute URLs; anything uploaded is a disk path.
        if (str_starts_with($this->path, 'http://') || str_starts_with($this->path, 'https://')) {
            return $this->path;
        }

        return Storage::disk($this->resolvedDisk())->url($this->path);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * `spaces` is the production default and does not exist locally, so fall back
     * to whatever the environment actually has configured.
     */
    private function resolvedDisk(): string
    {
        return config("filesystems.disks.{$this->disk}") === null
            ? (string) config('filesystems.default')
            : $this->disk;
    }
}
