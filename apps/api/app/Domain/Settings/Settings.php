<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Read access to site settings.
 *
 * Settings are read on nearly every request and written from one admin screen, so
 * the whole table is cached as a single map and the cache is dropped on write.
 * That keeps a page render at zero queries instead of one per key.
 */
final class Settings
{
    private const CACHE_KEY = 'settings.map';

    private const TTL = 3600;

    public function __construct(private readonly Cache $cache) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->map()[$key] ?? $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key);

        return is_scalar($value) ? (string) $value : $default;
    }

    /** Public settings only — safe to hand to the frontend. */
    /** @return array<string, mixed> */
    public function publicMap(): array
    {
        return $this->map(publicOnly: true);
    }

    public function flush(): void
    {
        $this->cache->forget(self::CACHE_KEY);
        $this->cache->forget(self::CACHE_KEY.'.public');
    }

    /** @return array<string, mixed> */
    private function map(bool $publicOnly = false): array
    {
        $key = $publicOnly ? self::CACHE_KEY.'.public' : self::CACHE_KEY;

        return $this->cache->remember($key, self::TTL, function () use ($publicOnly): array {
            $query = Setting::query();

            if ($publicOnly) {
                // Encrypted values are secrets regardless of how `is_public` is set.
                $query->where('is_public', true)->where('is_encrypted', false);
            }

            return $query->get()
                ->mapWithKeys(fn (Setting $s): array => [$s->key => $s->typedValue()])
                ->all();
        });
    }
}
