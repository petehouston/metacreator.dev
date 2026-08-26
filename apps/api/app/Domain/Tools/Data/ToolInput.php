<?php

declare(strict_types=1);

namespace App\Domain\Tools\Data;

use Illuminate\Support\Arr;

/**
 * Validated, normalised tool input.
 *
 * Instances only ever exist after the payload has been checked against the runner's
 * JSON Schema, so runners can read values without defensive checks.
 */
final readonly class ToolInput
{
    /**
     * @param  array<string, mixed>  $values
     * @param  list<UploadedAsset>  $files
     */
    public function __construct(
        public array $values,
        public array $files = [],
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->values, $key, $default);
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function float(string $key, float $default = 0.0): float
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (float) $value : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        return filter_var($this->get($key, $default), FILTER_VALIDATE_BOOL);
    }

    /** @return list<mixed> */
    public function list(string $key): array
    {
        $value = $this->get($key, []);

        return is_array($value) ? array_values($value) : [];
    }

    public function has(string $key): bool
    {
        return Arr::has($this->values, $key);
    }

    public function file(int $index = 0): ?UploadedAsset
    {
        return $this->files[$index] ?? null;
    }

    /**
     * Canonical hash of the input, used as the cache key and for de-duplication.
     *
     * Keys are sorted recursively so that `{a:1,b:2}` and `{b:2,a:1}` hash the same;
     * uploaded files contribute their content checksum, not their filename.
     */
    public function hash(): string
    {
        $canonical = self::canonicalise($this->values);
        $canonical['__files'] = array_map(fn (UploadedAsset $f) => $f->checksum, $this->files);

        return hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private static function canonicalise(array $value): array
    {
        ksort($value);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::canonicalise($item);
            }
        }

        return $value;
    }
}
