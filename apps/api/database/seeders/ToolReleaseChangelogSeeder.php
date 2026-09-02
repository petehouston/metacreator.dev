<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Changelog\Enums\ChangeType;
use App\Domain\Changelog\Enums\ReleaseStatus;
use App\Domain\Changelog\Models\ChangelogRelease;
use App\Domain\Tools\Enums\ToolStatus;
use App\Domain\Tools\Models\Tool;
use App\Domain\Users\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * The public record of which tools shipped on which day.
 *
 * The catalog grows most days, and until now the only place that fact was written
 * down was the git log — which visitors cannot read. This seeder turns each day's
 * batch into one dated release on /changelog, with one `Added` entry per tool.
 *
 * Runs in production from {@see ProductionSeeder}, so adding a day here is the
 * whole workflow: append an entry to {@see self::DAYS} in the same commit that
 * adds the tools, and the next deploy publishes it.
 *
 * ── WHY THE NAMES ARE NOT IN THIS FILE ───────────────────────────────────────
 * A day lists tool *slugs*, never display names. The name, the tagline and the
 * URL are read back from the `tools` table at seed time, so a tool renamed in the
 * admin is renamed on the changelog too. Writing the names here would create a
 * second copy that silently goes stale — the same drift ToolCatalogSeeder avoids
 * by pulling each input schema from its runner.
 *
 * A slug that is missing or unpublished is skipped rather than fatal: an entry is
 * a description of the catalog, and it should not be able to break a deploy.
 * ─────────────────────────────────────────────────────────────────────────────
 */
final class ToolReleaseChangelogSeeder extends Seeder
{
    /**
     * One entry per day the catalog grew, oldest first.
     *
     * `slug` is the release's URL and its idempotency key: re-running this seeder
     * updates the release of that name in place rather than adding a second copy,
     * which is what makes it safe on every deploy.
     *
     * @var list<array<string, mixed>>
     */
    private const DAYS = [
        [
            'slug' => 'launch-62-tools',
            'version' => '1.0',
            'date' => '2026-08-30',
            'title' => 'MetaCreator.Dev launches with 62 tools',
            'is_major' => true,
            'summary' => 'The first public catalog: 62 free creator tools for previews, '
                .'downloads, calculators and text work — no account, no install, no upload '
                .'of anything we did not need.',
            // The launch is summarised rather than itemised. Sixty-two entries would
            // bury every release after it, and "what launched" is one event to a
            // reader even though it was sixty-two to us. Days below list every tool.
            'items' => [
                [ChangeType::Added, 'The tool catalog', '62 tools across previews, images and video, analytics and growth, content and utilities.'],
                [ChangeType::Added, 'Accounts and saved tools', 'Optional sign-in that remembers your favourites. Every free tool works without one.'],
            ],
            'tools' => [],
        ],
        [
            'slug' => 'tools-2026-09-02',
            'date' => '2026-09-02',
            'title' => '30 new tools',
            'summary' => 'The largest single day so far: link handling and hashtag work, '
                .'per-platform image downloaders for six networks, five mock-up generators, '
                .'podcast and Twitch artwork, and four new earnings calculators. '
                .'The catalog goes from 62 tools to 92.',
            'tools' => [
                // Links, hashtags and embeds.
                'youtube-link-shortener',
                'social-media-link-shortener',
                'link-expander',
                'social-media-url-cleaner',
                'hashtag-extractor',
                'social-media-embed-code-generator',
                'app-deep-link-builder',

                // Downloaders — the generic one, then per platform.
                'social-media-image-downloader',
                'x-image-downloader',
                'instagram-image-downloader',
                'facebook-image-downloader',
                'threads-image-downloader',
                'bluesky-image-downloader',
                'pinterest-image-downloader',
                'apple-podcasts-artwork-downloader',
                'spotify-cover-art-downloader',
                'twitch-image-downloader',

                // Mock-ups.
                'fake-facebook-post-generator',
                'fake-instagram-post-generator',
                'fake-x-reply-generator',
                'fake-pinterest-pin-generator',
                'fake-tiktok-comment-generator',

                // Previews.
                'google-serp-preview',
                'email-subject-line-preview',
                'youtube-banner-safe-area',

                // Money and planning.
                'instagram-money-calculator',
                'twitch-money-calculator',
                'cpm-to-rpm-calculator',
                'youtube-ad-break-planner',
                'youtube-advertiser-friendly-checker',
            ],
        ],
    ];

    public function run(): void
    {
        $author = User::query()->orderBy('id')->first();

        foreach (self::DAYS as $day) {
            $release = ChangelogRelease::query()->firstOrNew(['slug' => $day['slug']]);

            $release->fill([
                'version' => $day['version'] ?? null,
                'title' => $day['title'],
                'summary' => $day['summary'],
                'status' => ReleaseStatus::Published,
                // Noon rather than midnight: the timeline sorts on this column, and a
                // date-only value would order the day's releases by whatever the
                // database does with equal timestamps.
                'released_at' => Carbon::parse($day['date'].' 12:00:00'),
                'is_major' => $day['is_major'] ?? false,
            ]);

            // Only on create. Re-attributing an existing release on every deploy
            // would overwrite whoever edited it in the admin since.
            if (! $release->exists && $author !== null) {
                $release->author_id = $author->id;
            }

            $release->save();

            // Rebuilt rather than merged: `sort_order` is positional, so appending a
            // tool to a day that already seeded would otherwise leave the old rows
            // interleaved with the new ones in an order nobody chose.
            $release->items()->delete();

            $sort = 0;

            foreach ($day['items'] ?? [] as [$type, $title, $description]) {
                $release->items()->create([
                    'type' => $type,
                    'title' => $title,
                    'description' => $description,
                    'sort_order' => $sort++,
                ]);
            }

            foreach ($this->tools($day['tools']) as $tool) {
                $release->items()->create([
                    'type' => ChangeType::Added,
                    'title' => $tool->name,
                    // The tagline is the one sentence the catalog already uses to say
                    // what a tool is for, so the changelog and the tool page cannot
                    // end up describing it differently. Items render as plain text
                    // (components/changelog/release-entry.tsx), so there is no link
                    // to add here — the name in the title is what a reader searches.
                    'description' => $tool->tagline,
                    'sort_order' => $sort++,
                ]);
            }
        }

        $this->command?->info('Seeded '.count(self::DAYS).' tool-release entries.');
    }

    /**
     * The published tools for a day, in the order the day lists them.
     *
     * One query per day rather than one per tool, then re-ordered in PHP: the list
     * is an editorial grouping (links, then downloaders, then mock-ups) and the
     * database has no column that would reproduce it.
     *
     * @param  list<string>  $slugs
     * @return list<Tool>
     */
    private function tools(array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }

        $tools = Tool::query()
            ->whereIn('slug', $slugs)
            ->where('status', ToolStatus::Published)
            ->get()
            ->keyBy('slug');

        $found = [];

        foreach ($slugs as $slug) {
            if ($tools->has($slug)) {
                $found[] = $tools->get($slug);

                continue;
            }

            // Named but not in the catalog: a typo here, or a tool pulled after the
            // fact. Say so on the console and carry on — see the class docblock.
            $this->command?->warn("Changelog: no published tool for slug '{$slug}' — skipped.");
        }

        return $found;
    }
}
