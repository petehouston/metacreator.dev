<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Typed key-value site configuration.
 *
 * @property array{v?: mixed}|null $value
 * @property string $key
 * @property string $type
 * @property string $group
 * @property bool $is_public
 * @property bool $is_encrypted
 *
 * Secrets (provider API keys) are encrypted at rest and are never returned by the
 * public settings endpoint, regardless of their `is_public` flag.
 */
final class Setting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_public' => 'boolean',
            'is_encrypted' => 'boolean',
        ];
    }

    /**
     * {@see Settings} caches the whole table as one map, so any write has to drop
     * it. Doing that here rather than in the admin controller means an import, a
     * seeder or a tinker session cannot leave the cache serving stale values.
     */
    protected static function booted(): void
    {
        self::saved(static fn () => app(Settings::class)->flush());
        self::deleted(static fn () => app(Settings::class)->flush());
    }

    public function typedValue(): mixed
    {
        $raw = $this->value['v'] ?? null;

        if ($this->is_encrypted && is_string($raw) && $raw !== '') {
            $raw = Crypt::decryptString($raw);
        }

        return match ($this->type) {
            'bool' => filter_var($raw, FILTER_VALIDATE_BOOL),
            'int' => (int) $raw,
            'json' => is_array($raw) ? $raw : json_decode((string) $raw, true),
            default => $raw,
        };
    }

    public function setTypedValue(mixed $value): void
    {
        if ($this->is_encrypted && is_string($value) && $value !== '') {
            $value = Crypt::encryptString($value);
        }

        $this->value = ['v' => $value];
    }
}
