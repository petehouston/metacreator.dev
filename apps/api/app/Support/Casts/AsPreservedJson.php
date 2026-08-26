<?php

declare(strict_types=1);

namespace App\Support\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * JSON stored in a text column so key order survives the round trip.
 *
 * MySQL's native JSON type re-sorts object keys. That is invisible for most data,
 * but a tool's input schema is different: the order of its `properties` is the order
 * the generated form renders its fields, and a form whose fields shuffle themselves
 * is a bug the user sees immediately.
 *
 * @implements CastsAttributes<array<string, mixed>, array<string, mixed>>
 */
final class AsPreservedJson implements CastsAttributes
{
    /** @return array<string, mixed>|null */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
