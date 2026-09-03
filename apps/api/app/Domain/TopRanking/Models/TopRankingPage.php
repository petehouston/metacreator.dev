<?php

declare(strict_types=1);

namespace App\Domain\TopRanking\Models;

use App\Domain\Seo\Models\SeoMeta;
use App\Domain\TopRanking\Enums\RankingPlatform;
use App\Domain\TopRanking\Enums\SyncStatus;
use App\Support\Concerns\HasUlidKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * One ranking: "the top N accounts on this network, by this metric".
 *
 * @property-read string $public_id
 * @property int $id
 * @property string $slug
 * @property RankingPlatform $platform
 * @property string $title
 * @property string $metric_label
 * @property string $metric_unit
 * @property string|null $secondary_metric_label
 * @property string|null $secondary_metric_unit
 * @property string|null $intro
 * @property string $source_page
 * @property int $source_table
 * @property int $row_limit
 * @property bool $is_published
 * @property int $sort_order
 * @property CarbonImmutable|null $synced_at
 * @property SyncStatus $sync_status
 * @property string|null $sync_message
 * @property CarbonImmutable|null $avatars_synced_at
 * @property-read Collection<int, TopRankingEntry> $entries
 */
final class TopRankingPage extends Model
{
    use HasUlidKey;

    protected function ulidPrefix(): string
    {
        return 'rank';
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'platform' => RankingPlatform::class,
            'sync_status' => SyncStatus::class,
            'source_table' => 'integer',
            'row_limit' => 'integer',
            'sort_order' => 'integer',
            'is_published' => 'boolean',
            'synced_at' => 'datetime',
            'avatars_synced_at' => 'datetime',
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────────

    /**
     * The rows, in the order the page renders them.
     *
     * Ordered here rather than at each call site, for the same reason the changelog
     * orders its items: a table that renders in a different order than the editor
     * arranged is a bug the editor has no way to fix.
     *
     * @return HasMany<TopRankingEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(TopRankingEntry::class, 'page_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * The page's SEO overrides.
     *
     * The same polymorphic row every other entity on the site uses, rather than a
     * pair of columns of its own — so a ranking page gets the canonical URL, the
     * robots directive, the share image and the card type for free, and the admin
     * panel that edits them is the one that already exists.
     *
     * @return MorphOne<SeoMeta, $this>
     */
    public function seo(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * The order the nav and the index page show, which is the admin's, not the
     * database's.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeInMenuOrder(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }

    // ── Behaviour ────────────────────────────────────────────────────────────

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** The article this page is built from. */
    public function sourceUrl(): string
    {
        return 'https://en.wikipedia.org/wiki/'.rawurlencode(str_replace(' ', '_', $this->source_page));
    }

    /**
     * How stale the data is, in whole days, or null if it has never synced.
     *
     * Exposed as a number rather than a boolean "is stale": the weekly job sets the
     * cadence, and what counts as too old is a judgement the reader of the page
     * should be allowed to make for themselves from a printed date.
     */
    public function daysSinceSync(): ?int
    {
        // `diffInDays` returns a float in Carbon 3 — 0.4 for a sync four hours ago —
        // so this floors rather than letting a fraction hit an `?int` return type,
        // which is a 500 on a screen whose whole job is reporting health.
        return $this->synced_at === null
            ? null
            : (int) floor($this->synced_at->diffInDays(CarbonImmutable::now()));
    }
}
