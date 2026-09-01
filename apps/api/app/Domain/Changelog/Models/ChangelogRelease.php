<?php

declare(strict_types=1);

namespace App\Domain\Changelog\Models;

use App\Domain\Changelog\Enums\ReleaseStatus;
use App\Domain\Users\Models\User;
use App\Support\Concerns\HasUlidKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One dated batch of changes.
 *
 * @property-read string $public_id
 * @property int $id
 * @property string $slug
 * @property string|null $version
 * @property string $title
 * @property string|null $summary
 * @property ReleaseStatus $status
 * @property CarbonImmutable|null $released_at
 * @property bool $is_major
 * @property int|null $author_id
 * @property-read Collection<int, ChangelogItem> $items
 */
final class ChangelogRelease extends Model
{
    use HasUlidKey;

    protected function ulidPrefix(): string
    {
        return 'rel';
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => ReleaseStatus::class,
            'released_at' => 'datetime',
            'is_major' => 'boolean',
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────────

    /**
     * The changes in this release, in the order they should be read.
     *
     * Ordered here rather than at each call site: a release rendered in a different
     * order on the public page than in the editor is a bug an editor cannot fix,
     * because what they arranged is not what shipped.
     *
     * @return HasMany<ChangelogItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ChangelogItem::class, 'release_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Everything a visitor may see.
     *
     * A `scheduled` release whose date has arrived is public without anything having
     * run — the status names the editor's intent, the date decides visibility. That
     * is why this feature needs no cron.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [ReleaseStatus::Published->value, ReleaseStatus::Scheduled->value])
            ->whereNotNull('released_at')
            ->where('released_at', '<=', now());
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $q) use ($like): void {
            $q->where('title', 'like', $like)
                ->orWhere('version', 'like', $like)
                ->orWhere('summary', 'like', $like)
                // A reader searching "csv export" is looking for the entry that
                // mentions it, which is almost never the release title.
                ->orWhereHas('items', fn (Builder $items) => $items
                    ->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like));
        });
    }

    // ── Behaviour ────────────────────────────────────────────────────────────

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isPublic(): bool
    {
        return $this->status->isPublishable()
            && $this->released_at !== null
            && $this->released_at->isPast();
    }
}
