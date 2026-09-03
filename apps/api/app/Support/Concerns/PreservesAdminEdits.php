<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use Database\Seeders\ProductionSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Reference data that a deploy re-seeds *and* an admin can edit.
 *
 * ── THE PROBLEM ──────────────────────────────────────────────────────────────
 * {@see ProductionSeeder} runs on every deploy so a new tool,
 * category or plan appears without anybody touching the database by hand. Written
 * as a plain `updateOrCreate`, that also reassigns every column on every existing
 * row — so a tagline rewritten in the admin at 10am is silently replaced by the
 * one in the seeder file at the next deploy. From the outside it looks like the
 * deploy wiped production and reset it to the code's idea of the world.
 *
 * ── THE RULE ─────────────────────────────────────────────────────────────────
 * The first person to touch a field owns it. A field an admin has saved is
 * *locked*: the seeder will never write it again. Every other field still tracks
 * the file, so copy fixed in a commit still ships and a brand new row is seeded
 * whole.
 *
 * Locking is automatic — any save that does not come from `seedRow()` records the
 * columns it changed — so there is no way to add an admin screen and forget to
 * mark what it edits. Counters the application itself maintains (run totals,
 * timestamps) are excluded by {@see unlockableAttributes()}: those are not
 * decisions anybody made, and locking them would freeze the seeder out of a
 * column no human ever edits.
 *
 * Using models must cast `locked_fields` to `array` — spelled out in each model's
 * own `casts()` rather than injected here, because trait initialisers run in an
 * order Eloquent does not promise relative to the one that builds the cast list.
 *
 * @phpstan-require-extends Model
 */
trait PreservesAdminEdits
{
    /**
     * True only while `seedRow()` is writing.
     *
     * Static because the guard is about *which code path* is saving, not about
     * which row: a seeder write must not lock the fields it just wrote, or the
     * first deploy would freeze the entire table.
     */
    private static bool $seeding = false;

    public static function bootPreservesAdminEdits(): void
    {
        static::updating(function (Model $model): void {
            if (self::$seeding) {
                return;
            }

            /** @var static $model */
            $changed = array_diff(
                array_keys($model->getDirty()),
                $model->unlockableAttributes(),
            );

            if ($changed === []) {
                return;
            }

            $model->locked_fields = array_values(array_unique([
                ...$model->lockedFields(),
                ...$changed,
            ]));
        });
    }

    /**
     * Seed one row: create it whole, or refresh only the fields nobody has claimed.
     *
     * @param  array<string, mixed>  $keys  what identifies the row (`['key' => …]`)
     * @param  array<string, mixed>  $attributes  what the seeder would like it to say
     * @param  array<string, mixed>  $onCreate  written only when the row is new — for
     *                                          facts about the row's own birth, like
     *                                          the date it was first published
     */
    public static function seedRow(array $keys, array $attributes, array $onCreate = []): static
    {
        /** @var static $model */
        $model = static::query()->firstOrNew($keys);

        $model->fill($model->exists
            ? Arr::except($attributes, $model->lockedFields())
            : [...$keys, ...$attributes, ...$onCreate]);

        if ($model->isDirty()) {
            self::$seeding = true;

            try {
                $model->save();
            } finally {
                self::$seeding = false;
            }
        }

        return $model;
    }

    /** @return list<string> */
    public function lockedFields(): array
    {
        return array_values(array_filter(
            $this->locked_fields ?? [],
            is_string(...),
        ));
    }

    /** Has an admin taken ownership of this field? */
    public function isFieldLocked(string $attribute): bool
    {
        return in_array($attribute, $this->lockedFields(), true);
    }

    /**
     * Columns an edit never locks.
     *
     * Two kinds: bookkeeping nobody decides (timestamps, the lock list itself) and
     * anything owned by code rather than by a person — a tool's input schema is
     * generated from its runner, so an admin saving the row beside it must not stop
     * the next deploy from updating it. Models name the second kind in
     * {@see codeOwnedAttributes()}.
     *
     * @return list<string>
     */
    final public function unlockableAttributes(): array
    {
        return ['locked_fields', 'created_at', 'updated_at', ...$this->codeOwnedAttributes()];
    }

    /**
     * Columns this model keeps under the code's ownership, whatever an admin saves.
     *
     * @return list<string>
     */
    protected function codeOwnedAttributes(): array
    {
        return [];
    }
}
