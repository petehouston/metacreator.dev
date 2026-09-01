<?php

declare(strict_types=1);

namespace App\Domain\Changelog\Actions;

use App\Domain\Changelog\Enums\ReleaseStatus;
use App\Domain\Changelog\Models\ChangelogRelease;
use App\Domain\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The one write path for a release.
 *
 * Create and edit share it so that slug generation, the items and the date default
 * cannot be handled one way by the create endpoint and another by the update — the
 * usual reason a record created in the admin differs subtly from one that was
 * edited there afterwards.
 */
final class SaveReleaseAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(ChangelogRelease $release, array $attributes, User $actor): ChangelogRelease
    {
        return DB::transaction(function () use ($release, $attributes, $actor): ChangelogRelease {
            $exists = $release->exists;

            $release->fill(array_intersect_key($attributes, array_flip([
                'version', 'title', 'summary', 'is_major', 'released_at',
            ])));

            if (! $exists) {
                $release->author_id = (int) $actor->id;
            }

            if (array_key_exists('status', $attributes)) {
                $release->status = ReleaseStatus::from((string) $attributes['status']);
            } elseif (! $exists) {
                $release->status = ReleaseStatus::Draft;
            }

            // Publishing without naming a date means "today". Leaving it null instead
            // would satisfy the column and then hide the release from the public
            // scope forever — a save that reports success and publishes nothing.
            if ($release->status->isPublishable() && $release->released_at === null) {
                $release->released_at = CarbonImmutable::now();
            }

            $release->slug = $this->resolveSlug($release, $attributes);

            $release->save();

            if (array_key_exists('items', $attributes)) {
                $this->syncItems($release, (array) $attributes['items']);
            }

            return $release->load(['items', 'author:id,name,display_name,avatar_path']);
        });
    }

    /**
     * Replace the release's items with the submitted list.
     *
     * Deleted and re-created rather than diffed by id. An item carries no state of
     * its own — no permalink, no view count, nothing anyone can have linked to — so
     * there is nothing for a diff to preserve, and reordering by hand is the common
     * edit that a diff makes hardest.
     *
     * @param  array<array-key, mixed>  $items  as submitted, so possibly sparse
     */
    private function syncItems(ChangelogRelease $release, array $items): void
    {
        $release->items()->delete();

        $rows = [];
        $now = now();

        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $title = trim((string) ($item['title'] ?? ''));

            // A blank row is what an editor leaves behind after clicking "add" once
            // too often. Dropping it is kinder than a validation error on a field
            // they never meant to fill.
            if ($title === '') {
                continue;
            }

            $description = trim((string) ($item['description'] ?? ''));

            $rows[] = [
                'release_id' => $release->id,
                'type' => (string) $item['type'],
                'title' => $title,
                'description' => $description === '' ? null : $description,
                // The submitted order wins over any `sort_order` the client sent:
                // the list on screen is what the editor arranged.
                'sort_order' => $index,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            $release->items()->insert($rows);
        }

        $release->unsetRelation('items');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveSlug(ChangelogRelease $release, array $attributes): string
    {
        $explicit = trim((string) ($attributes['slug'] ?? ''));

        if ($explicit !== '') {
            return $this->unique(Str::slug($explicit), $release);
        }

        // An existing slug is never regenerated from a retitled release: the URL is
        // a promise, and someone has already linked to it.
        if ($release->exists && $release->slug !== '') {
            return $release->slug;
        }

        // Version first — `/changelog/v2-4-0` is what someone would guess, and it
        // stays stable while the title is still being wordsmithed.
        $base = $this->versionSlug($release->version) ?: Str::slug($release->title);

        return $this->unique($base ?: 'release', $release);
    }

    /**
     * A version label as a URL segment.
     *
     * `Str::slug()` alone is wrong here: it *drops* dots rather than separating on
     * them, so `4.2.0` and `42.0` both become `420` and the second release silently
     * takes a `-2` suffix it should never have needed. Dots become hyphens first.
     *
     * The `v` prefix is for the reader: `/changelog/420` looks like an id, and
     * `/changelog/v4-2-0` obviously does not.
     */
    private function versionSlug(?string $version): string
    {
        $version = trim((string) $version);

        if ($version === '') {
            return '';
        }

        $slug = Str::slug(str_replace(['.', '_'], '-', $version));

        if ($slug === '') {
            return '';
        }

        return ctype_digit($slug[0]) ? "v{$slug}" : $slug;
    }

    private function unique(string $base, ChangelogRelease $release): string
    {
        $slug = $base;
        $suffix = 2;

        while (ChangelogRelease::query()
            ->where('slug', $slug)
            ->when($release->exists, fn ($q) => $q->whereKeyNot($release->getKey()))
            ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
