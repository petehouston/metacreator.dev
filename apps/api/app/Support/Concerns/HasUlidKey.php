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

    /**
     * Resolve a route parameter from a public id, a bare ULID, *or* the model's own
     * route key.
     *
     * Admin screens address rows by the public id they were given
     * (`usr_01J…`), while public pages address the same models by slug. Accepting
     * both here means neither surface has to know how the other refers to a row, and
     * a support agent can paste an id out of an email into a URL and have it work.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        if ($field !== null) {
            return parent::resolveRouteBinding($value, $field);
        }

        $candidate = (string) $value;
        $prefix = $this->ulidPrefix().'_';

        if (str_starts_with($candidate, $prefix)) {
            return $this->newQuery()
                ->where('ulid', strtoupper(substr($candidate, strlen($prefix))))
                ->first();
        }

        // A bare 26-character Crockford ULID. Checked by shape rather than tried
        // speculatively, so a slug that happens to be 26 characters long does not
        // turn into a wasted query on every request.
        if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $candidate) === 1) {
            return $this->newQuery()->where('ulid', strtoupper($candidate))->first();
        }

        return parent::resolveRouteBinding($value, $field);
    }
}
