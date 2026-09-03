<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Seo\Actions\SaveSeoMeta;
use App\Domain\TopRanking\Enums\RankingPlatform;
use App\Domain\TopRanking\Models\TopRankingPage;
use Illuminate\Database\Seeder;

/**
 * The nine ranking pages the site ships with.
 *
 * Reference data, not demo data: these rows are the *configuration* of the feature,
 * and without them the sync command has nothing to sync. Idempotent, and it takes
 * care not to overwrite an admin's edits — `firstOrNew` on the slug, and the
 * presentation fields are only filled on a row that does not exist yet. An admin
 * who rewrites an intro or unpublishes a page keeps that through the next deploy,
 * the same guarantee the tool catalog makes.
 *
 * `source_page` and `source_table` are the exceptions: those are kept in step with
 * this file, because they are the part that has been *verified* to parse and a
 * drifted copy is a page that silently stops updating.
 *
 * Every article below was checked against the live MediaWiki API before being
 * listed: the table index is the one that holds the ranking rather than the
 * "progression of the record" table most of these articles also carry, and the row
 * limit is what the article actually publishes today.
 */
final class TopRankingSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pages() as $order => $definition) {
            $page = TopRankingPage::query()->firstOrNew(['slug' => $definition['slug']]);

            // The source contract, always current — see the class docblock.
            $page->fill([
                'platform' => $definition['platform'],
                'source_page' => $definition['source_page'],
                'source_table' => $definition['source_table'],
                'row_limit' => $definition['row_limit'],
                'metric_label' => $definition['metric_label'],
                'metric_unit' => $definition['metric_unit'],
                'secondary_metric_label' => $definition['secondary_metric_label'] ?? null,
                'secondary_metric_unit' => $definition['secondary_metric_unit'] ?? null,
            ]);

            $isNew = ! $page->exists;

            if ($isNew) {
                $page->fill([
                    'title' => $definition['title'],
                    'intro' => $definition['intro'],
                    'is_published' => true,
                    'sort_order' => ($order + 1) * 10,
                ]);
            }

            $page->save();

            // Seeded once, then left alone — the SEO panel is exactly the surface an
            // admin is expected to hand-tune, and a deploy that reset it every time
            // would make the panel pointless.
            if ($isNew) {
                app(SaveSeoMeta::class)->handle($page, [
                    'title' => $definition['title'].' — updated weekly',
                    'description' => $definition['meta_description'],
                    'og_title' => $definition['title'],
                    'og_description' => $definition['meta_description'],
                    'robots' => 'index,follow',
                    'twitter_card' => 'summary_large_image',
                    'schema_type' => 'ItemList',
                ]);
            }
        }
    }

    /**
     * @return list<array{
     *     slug: string, platform: RankingPlatform, title: string, intro: string,
     *     meta_description: string, source_page: string, source_table: int,
     *     row_limit: int, metric_label: string, metric_unit: string,
     *     secondary_metric_label?: string, secondary_metric_unit?: string
     * }>
     */
    private function pages(): array
    {
        return [
            [
                'slug' => 'most-subscribed-youtube-channels',
                'platform' => RankingPlatform::YouTube,
                'title' => 'Top 100 Most-Subscribed YouTube Channels',
                'intro' => 'The hundred largest channels on YouTube by subscriber count — the entertainers, the music labels and the nursery-rhyme empires that between them hold a measurable share of the internet’s attention.',
                'meta_description' => 'The 100 most-subscribed YouTube channels, with subscriber counts, categories and countries. Sourced from Wikipedia and refreshed every week.',
                'source_page' => 'List of most-subscribed YouTube channels',
                'source_table' => 0,
                'row_limit' => 100,
                'metric_label' => 'Subscribers',
                'metric_unit' => 'millions',
            ],
            [
                'slug' => 'most-viewed-youtube-channels',
                'platform' => RankingPlatform::YouTube,
                'title' => 'Top 50 Most-Viewed YouTube Channels',
                'intro' => 'Subscribers measure who signed up; views measure who actually watched. These fifty channels have the highest lifetime view counts on the platform, and the order is not the one the subscriber chart would suggest.',
                'meta_description' => 'The 50 most-viewed YouTube channels ranked by lifetime views, with networks, categories and countries. Refreshed weekly from Wikipedia.',
                'source_page' => 'List of most-viewed YouTube channels',
                'source_table' => 0,
                'row_limit' => 50,
                'metric_label' => 'Views',
                'metric_unit' => 'billions',
            ],
            [
                'slug' => 'most-followed-instagram-accounts',
                'platform' => RankingPlatform::Instagram,
                'title' => 'Top 50 Most-Followed Instagram Accounts',
                'intro' => 'Footballers, musicians and the platform itself. The fifty largest accounts on Instagram, with the owner behind each handle.',
                'meta_description' => 'The 50 most-followed Instagram accounts with follower counts, owners and countries. Sourced from Wikipedia, refreshed weekly.',
                'source_page' => 'List of most-followed Instagram accounts',
                // Index 1: this article opens with a small "Key" table explaining
                // the brand-account dagger, and that table is a `wikitable` too.
                'source_table' => 1,
                'row_limit' => 50,
                'metric_label' => 'Followers',
                'metric_unit' => 'millions',
            ],
            [
                'slug' => 'most-followed-tiktok-accounts',
                'platform' => RankingPlatform::TikTok,
                'title' => 'Top 50 Most-Followed TikTok Accounts',
                'intro' => 'The fifty biggest accounts on TikTok by followers, with total likes alongside — the second number is where the platform’s economics differ most from everywhere else.',
                'meta_description' => 'The 50 most-followed TikTok accounts with follower and like counts, owners and countries. Refreshed weekly from Wikipedia.',
                'source_page' => 'List of most-followed TikTok accounts',
                'source_table' => 0,
                'row_limit' => 50,
                'metric_label' => 'Followers',
                'metric_unit' => 'millions',
                'secondary_metric_label' => 'Likes',
                'secondary_metric_unit' => 'billions',
            ],
            [
                'slug' => 'most-followed-x-accounts',
                'platform' => RankingPlatform::X,
                'title' => 'Top 50 Most-Followed X Accounts',
                'intro' => 'The fifty largest accounts on X, formerly Twitter — a list where politicians and journalists sit far higher than they do on any other network.',
                'meta_description' => 'The 50 most-followed accounts on X (Twitter), with follower counts and the people and brands behind them. Refreshed weekly from Wikipedia.',
                'source_page' => 'List of most-followed X accounts',
                'source_table' => 0,
                'row_limit' => 50,
                'metric_label' => 'Followers',
                'metric_unit' => 'millions',
            ],
            [
                'slug' => 'most-followed-facebook-pages',
                'platform' => RankingPlatform::Facebook,
                'title' => 'Top 50 Most-Followed Facebook Pages',
                'intro' => 'Facebook’s largest Pages are brands far more often than people — which is itself the finding, and the clearest single illustration of how differently this platform is used.',
                'meta_description' => 'The 50 most-followed Facebook Pages with follower counts, descriptions and countries. Sourced from Wikipedia and refreshed weekly.',
                'source_page' => 'List of most-followed Facebook pages',
                'source_table' => 0,
                'row_limit' => 50,
                'metric_label' => 'Followers',
                'metric_unit' => 'millions',
            ],
            [
                'slug' => 'most-followed-twitch-channels',
                'platform' => RankingPlatform::Twitch,
                'title' => 'Top 50 Most-Followed Twitch Channels',
                'intro' => 'The fifty most-followed channels on Twitch, with the games and categories each is known for. Following on Twitch is free, which is what makes the subscriber list next door a different ranking entirely.',
                'meta_description' => 'The 50 most-followed Twitch channels with follower counts, streamed categories and languages. Refreshed weekly from Wikipedia.',
                'source_page' => 'List of most-followed Twitch channels',
                'source_table' => 0,
                'row_limit' => 50,
                'metric_label' => 'Followers',
                'metric_unit' => 'millions',
            ],
            [
                'slug' => 'most-subscribed-twitch-channels',
                'platform' => RankingPlatform::Twitch,
                'title' => 'Top 50 Most-Subscribed Twitch Channels',
                'intro' => 'Twitch subscriptions are paid, so this is the only ranking here counting people who opened a wallet rather than clicked a button — and the exact peak counts are published rather than rounded.',
                'meta_description' => 'The 50 most-subscribed Twitch channels by peak paid subscriber count, with owners and categories. Refreshed weekly from Wikipedia.',
                'source_page' => 'List of most-subscribed Twitch channels',
                'source_table' => 0,
                'row_limit' => 50,
                'metric_label' => 'Subscribers',
                // The one page here that publishes an exact count ("1,112,947")
                // rather than a magnitude, which is why the unit is a column.
                'metric_unit' => 'exact',
            ],
            [
                'slug' => 'most-followed-bluesky-accounts',
                'platform' => RankingPlatform::Bluesky,
                'title' => 'Top 50 Most-Followed Bluesky Accounts',
                'intro' => 'The largest accounts on the newest network here, at a scale that shows how young it still is: the leader would not make the top four hundred on Instagram.',
                'meta_description' => 'The 50 most-followed Bluesky accounts with follower counts and the people behind them. Refreshed weekly from Wikipedia.',
                'source_page' => 'List of most-followed Bluesky accounts',
                'source_table' => 0,
                'row_limit' => 50,
                'metric_label' => 'Followers',
                'metric_unit' => 'millions',
            ],
        ];
    }
}
