<?php

declare(strict_types=1);

namespace App\Domain\Changelog\Models;

use App\Domain\Changelog\Enums\ChangeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single line in a release: "we added X", "we fixed Y".
 *
 * No ULID and no public id — an item is never addressed on its own. It is reached
 * through its release, which is what a permalink points at.
 *
 * @property int $id
 * @property int $release_id
 * @property ChangeType $type
 * @property string $title
 * @property string|null $description
 * @property int $sort_order
 */
final class ChangelogItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => ChangeType::class,
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<ChangelogRelease, $this> */
    public function release(): BelongsTo
    {
        return $this->belongsTo(ChangelogRelease::class, 'release_id');
    }
}
