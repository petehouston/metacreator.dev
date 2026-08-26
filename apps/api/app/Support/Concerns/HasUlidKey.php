<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use Illuminate\Support\Str;

/**
 * Gives a model an unguessable public identifier while keeping a compact auto-
 * increment primary key for InnoDB.
 *
 * Numeric primary keys never leave the API; everything external sees
 * `prefix_ULID`, which is sortable by creation time and safe to expose.
 */
trait HasUlidKey
{
    public static function bootHasUlidKey(): void
    {
        static::creating(function (self $model): void {
            $model->ulid ??= strtoupper((string) Str::ulid());
        });
    }

    /**
     * The public-id prefix for this model (`usr`, `tl`, `run`, …).
     *
     * Abstract rather than a trait property, because PHP will not let a class
     * override a trait property's default — and a prefix that silently fell back to
     * a generic value would produce ambiguous public ids.
     */
    abstract protected function ulidPrefix(): string;

    public function getPublicIdAttribute(): string
    {
        return $this->ulidPrefix()."_{$this->ulid}";
    }

    /** Resolve from either the bare ULID or the prefixed public id. */
    public static function findByPublicId(string $publicId): ?static
    {
        $ulid = str_contains($publicId, '_')
            ? substr($publicId, strpos($publicId, '_') + 1)
            : $publicId;

        return static::query()->where('ulid', strtoupper($ulid))->first();
    }
}
