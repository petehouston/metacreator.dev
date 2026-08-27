<?php

declare(strict_types=1);

namespace App\Domain\Blog\Actions;

use App\Domain\Blog\Enums\PostStatus;
use App\Domain\Blog\Models\Post;
use App\Domain\Blog\Models\PostRevision;
use App\Domain\Blog\Services\PostContentService;
use App\Domain\Seo\Models\SeoMeta;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The one write path for a post.
 *
 * Every save — create, edit, autosave, status change — goes through here, so the
 * derived columns, the tag pivot, the SEO record, the revision and the version
 * counter can never be updated by one caller and forgotten by another.
 */
final class SavePostAction
{
    public function __construct(private readonly PostContentService $content) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Post $post, array $attributes, User $actor): Post
    {
        return DB::transaction(function () use ($post, $attributes, $actor): Post {
            $exists = $post->exists;

            // Snapshot before the write, not after: a revision is what the post
            // looked like *before* this edit, which is the thing you want to restore.
            if ($exists && array_key_exists('blocks', $attributes)) {
                $this->snapshot($post, $actor, (bool) ($attributes['is_autosave'] ?? false));
            }

            $post->fill(array_intersect_key($attributes, array_flip([
                'title', 'excerpt', 'category_id', 'featured_media_id', 'is_featured', 'allow_comments',
            ])));

            if (! $exists) {
                $post->author_id = $attributes['author_id'] ?? $actor->id;
            }

            $post->slug = $this->resolveSlug($post, $attributes);

            if (array_key_exists('blocks', $attributes)) {
                $this->content->apply($post, (array) $attributes['blocks']);
            }

            if (array_key_exists('status', $attributes)) {
                $this->applyStatus($post, (string) $attributes['status'], $attributes);
            } elseif (! $exists) {
                $post->status = PostStatus::Draft;
            }

            if (blank($post->excerpt)) {
                $post->excerpt = $this->content->autoExcerpt($post);
            }

            // Optimistic concurrency (docs/05): the counter moves on every persisted
            // edit, and a PATCH carrying a stale one is rejected by the controller
            // before it ever reaches this action.
            $post->version = (int) $post->version + 1;

            $post->save();

            if (array_key_exists('tags', $attributes)) {
                $post->tags()->sync((array) $attributes['tags']);
            }

            if (array_key_exists('seo', $attributes) && is_array($attributes['seo'])) {
                $this->saveSeo($post, $attributes['seo']);
            }

            return $post;
        });
    }

    /**
     * Status transitions are validated against the lifecycle, not assigned freely —
     * "archived straight back to published" is a state the rest of the system does
     * not expect, and the enum is where that rule already lives.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function applyStatus(Post $post, string $status, array $attributes): void
    {
        $next = PostStatus::from($status);
        $current = $post->exists ? $post->status : PostStatus::Draft;

        abort_unless(
            $current->canTransitionTo($next),
            422,
            "A {$current->label()} post cannot move directly to {$next->label()}.",
        );

        $post->status = $next;

        if ($next === PostStatus::Scheduled) {
            $scheduledFor = $attributes['scheduled_for'] ?? null;

            abort_if($scheduledFor === null, 422, 'Scheduling a post needs a date to publish it on.');

            $post->scheduled_for = $scheduledFor;
            $post->published_at = null;

            return;
        }

        $post->scheduled_for = null;

        if ($next === PostStatus::Published) {
            // An explicit date wins so a post can be back-dated; otherwise the first
            // publish stamps now and a re-publish keeps its original date.
            $post->published_at = $attributes['published_at'] ?? $post->published_at ?? now();
        }
    }

    /** @param array<string, mixed> $attributes */
    private function resolveSlug(Post $post, array $attributes): string
    {
        $requested = $attributes['slug'] ?? null;

        if (is_string($requested) && $requested !== '') {
            return $this->uniqueSlug(Str::slug($requested), $post);
        }

        // A published post's slug is its URL and its search ranking. Only generate
        // one while it has never been live.
        if ($post->exists && $post->published_at !== null) {
            return $post->slug;
        }

        return $this->uniqueSlug(Str::slug((string) $post->title) ?: 'post', $post);
    }

    private function uniqueSlug(string $base, Post $post): string
    {
        $slug = $base;
        $suffix = 1;

        while (Post::query()
            ->withTrashed()
            ->where('slug', $slug)
            ->when($post->exists, fn ($q) => $q->whereKeyNot($post->getKey()))
            ->exists()
        ) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    private function snapshot(Post $post, User $actor, bool $isAutosave): void
    {
        PostRevision::query()->create([
            'post_id' => $post->id,
            'author_id' => $actor->id,
            'title' => $post->title,
            'blocks' => $post->blocks,
            'is_autosave' => $isAutosave,
            'created_at' => now(),
        ]);

        // Keep the last thirty. Revisions exist to undo a mistake from this week,
        // not to be an append-only history of every keystroke since launch.
        $keep = PostRevision::query()
            ->where('post_id', $post->id)
            ->latest('id')
            ->limit(30)
            ->pluck('id');

        PostRevision::query()
            ->where('post_id', $post->id)
            ->whereNotIn('id', $keep)
            ->delete();
    }

    /** @param array<string, mixed> $seo */
    private function saveSeo(Post $post, array $seo): void
    {
        SeoMeta::query()->updateOrCreate(
            ['seoable_type' => $post->getMorphClass(), 'seoable_id' => $post->id],
            array_intersect_key($seo, array_flip([
                'title', 'description', 'canonical_url', 'robots', 'focus_keyword',
                'og_title', 'og_description', 'og_media_id', 'twitter_card', 'schema_type',
            ])),
        );
    }
}
