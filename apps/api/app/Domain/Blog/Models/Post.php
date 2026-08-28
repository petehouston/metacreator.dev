<?php

declare(strict_types=1);

namespace App\Domain\Blog\Models;

use App\Domain\Blog\Enums\PostStatus;
use App\Domain\Media\Models\Media;
use App\Domain\Seo\Models\SeoMeta;
use App\Domain\Users\Models\User;
use App\Support\Concerns\HasUlidKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A blog post. `blocks` is the canonical content (ADR 0003); `content_html` and
 * `content_text` are regenerable caches for rendering and search respectively.
 *
 * @property-read string $public_id
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string|null $excerpt
 * @property array{version: int, blocks: list<array<string, mixed>>} $blocks
 * @property string|null $content_html
 * @property PostStatus $status
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $scheduled_for
 * @property int $reading_minutes
 * @property int $word_count
 * @property bool $is_featured
 * @property int $version
 */
final class Post extends Model
{
    use HasUlidKey, SoftDeletes;

    protected function ulidPrefix(): string
    {
        return 'post';
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'blocks' => 'array',
            'published_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'is_featured' => 'boolean',
            'allow_comments' => 'boolean',
            'reading_minutes' => 'integer',
            'word_count' => 'integer',
            'view_count' => 'integer',
            'version' => 'integer',
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────────

    /** @return BelongsTo<PostCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class, 'category_id');
    }

    /**
     * Secondary categories.
     *
     * The primary one stays on `category_id` — it owns the URL, the breadcrumb and
     * the archive a post belongs to. These are the extra shelves it also sits on,
     * which is exactly how WordPress behaves once you stop pretending "one of these
     * many is special" can be derived rather than declared.
     *
     * @return BelongsToMany<PostCategory, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(PostCategory::class, 'post_post_category');
    }

    /** @return BelongsTo<Media, $this> */
    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_media_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tag');
    }

    /** @return HasMany<PostRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(PostRevision::class)->latest('created_at');
    }

    /** @return MorphOne<SeoMeta, $this> */
    public function seo(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Everything a visitor may see. A published post with a future `published_at`
     * stays hidden, so back-dating and embargoes both behave as an editor expects.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query
            ->where('status', PostStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
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

        // Fulltext for relevance, with a prefix LIKE so short words and partial
        // titles — which fulltext ignores — still find the obvious post.
        return $query->where(function (Builder $q) use ($term): void {
            $q->whereFullText(['title', 'excerpt', 'content_text'], $term)
                ->orWhere('title', 'like', $term.'%');
        });
    }

    // ── Behaviour ────────────────────────────────────────────────────────────

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isPublic(): bool
    {
        return $this->status->isPublic()
            && $this->published_at !== null
            && $this->published_at->isPast();
    }

    /** @return list<array<string, mixed>> */
    public function blockList(): array
    {
        return $this->blocks['blocks'] ?? [];
    }
}
