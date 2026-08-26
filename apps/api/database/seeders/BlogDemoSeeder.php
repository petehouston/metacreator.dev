<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Blog\Enums\PostStatus;
use App\Domain\Blog\Models\Post;
use App\Domain\Blog\Models\PostCategory;
use App\Domain\Blog\Models\Tag;
use App\Domain\Blog\Services\PostContentService;
use App\Domain\Media\Models\Media;
use App\Domain\Users\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo articles that exercise every block type, plus one post per non-published
 * status so the visibility rules can be checked by eye as well as by test.
 *
 * Local only — see {@see DatabaseSeeder}.
 */
final class BlogDemoSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(PostContentService::class);

        $author = User::query()->where('email', 'editor@metacreator.dev')->first()
            ?? User::query()->first();

        if ($author === null) {
            $this->command->warn('No users to attribute posts to — skipping blog demo data.');

            return;
        }

        $categories = $this->categories();
        $tags = $this->tags();

        foreach ($this->posts() as $definition) {
            $this->makePost($definition, $categories, $tags, $author, $service);
        }

        $this->command->info('Seeded '.count($this->posts()).' demo posts.');
    }

    /** @return array<string, PostCategory> */
    private function categories(): array
    {
        $defined = [
            ['slug' => 'growth', 'name' => 'Growth', 'accent_color' => '#6366f1',
                'description' => 'Getting found, getting followed, and keeping the people who show up.'],
            ['slug' => 'analytics', 'name' => 'Analytics', 'accent_color' => '#10b981',
                'description' => 'Reading your numbers without fooling yourself.'],
            ['slug' => 'content-craft', 'name' => 'Content Craft', 'accent_color' => '#f59e0b',
                'description' => 'Writing, filming and editing things people actually finish.'],
            ['slug' => 'platform-news', 'name' => 'Platform News', 'accent_color' => '#ef4444',
                'description' => 'Algorithm and product changes that affect how you post.'],
        ];

        $out = [];

        foreach ($defined as $i => $row) {
            $out[$row['slug']] = PostCategory::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [...$row, 'sort_order' => $i],
            );
        }

        return $out;
    }

    /** @return array<string, Tag> */
    private function tags(): array
    {
        $names = [
            'youtube' => 'YouTube', 'instagram' => 'Instagram', 'tiktok' => 'TikTok',
            'x' => 'X', 'seo' => 'SEO', 'thumbnails' => 'Thumbnails',
            'hashtags' => 'Hashtags', 'engagement' => 'Engagement',
            'shorts' => 'Shorts', 'scheduling' => 'Scheduling',
            'analytics' => 'Analytics',
        ];

        $out = [];

        foreach ($names as $slug => $name) {
            $out[$slug] = Tag::query()->updateOrCreate(['slug' => $slug], ['name' => $name]);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, PostCategory>  $categories
     * @param  array<string, Tag>  $tags
     */
    private function makePost(
        array $definition,
        array $categories,
        array $tags,
        User $author,
        PostContentService $service,
    ): void {
        $derived = $service->derive(['blocks' => $definition['blocks']]);

        $post = Post::query()->updateOrCreate(
            ['slug' => $definition['slug']],
            [
                ...$derived,
                'title' => $definition['title'],
                'excerpt' => $definition['excerpt'],
                'category_id' => $categories[$definition['category']]->id,
                'author_id' => $author->id,
                'status' => $definition['status'],
                'published_at' => $definition['published_at'] ?? null,
                'scheduled_for' => $definition['scheduled_for'] ?? null,
                'is_featured' => $definition['featured'] ?? false,
                'featured_media_id' => $this->image($definition['slug'], $definition['image'])->id,
            ],
        );

        $post->tags()->sync(array_map(
            fn (string $slug): int => $tags[$slug]->id,
            $definition['tags'],
        ));
    }

    /**
     * Featured images point at a remote URL rather than a stored file: the media
     * library's upload pipeline does not exist yet, and {@see Media::url()} passes
     * absolute URLs straight through.
     */
    private function image(string $slug, string $url): Media
    {
        return Media::query()->updateOrCreate(
            ['path' => $url],
            [
                'disk' => 'remote',
                'filename' => $slug.'.jpg',
                'mime_type' => 'image/jpeg',
                'size' => 0,
                'width' => 1200,
                'height' => 630,
                'checksum' => hash('sha256', $url),
                'alt_text' => 'Illustration for '.Str::headline($slug),
                'variant_status' => 'skipped',
            ],
        );
    }

    /** @return list<array<string, mixed>> */
    private function posts(): array
    {
        return [
            [
                'slug' => 'youtube-thumbnails-that-earn-the-click',
                'title' => 'YouTube thumbnails that earn the click',
                'excerpt' => 'Click-through rate is the one number that gates everything else on YouTube. Here is what actually moves it, and what only looks like it does.',
                'category' => 'growth',
                'tags' => ['youtube', 'thumbnails', 'engagement'],
                'status' => PostStatus::Published,
                'published_at' => now()->subDays(2),
                'featured' => true,
                'image' => 'https://images.unsplash.com/photo-1611162617213-7d7a39e9b1d7?w=1200&h=630&fit=crop',
                'blocks' => [
                    ['type' => 'paragraph', 'data' => ['html' => 'Your thumbnail is the only part of your video that competes directly with every other video on the platform. It is also the cheapest thing to change — which makes it the highest-leverage hour in your week.']],
                    ['type' => 'heading', 'data' => ['level' => 2, 'text' => 'Three faces of a good thumbnail']],
                    ['type' => 'list', 'data' => ['style' => 'ordered', 'items' => [
                        ['html' => '<strong>Legible at 168px.</strong> That is the width of a mobile search result. If the text is unreadable there, it does not exist.'],
                        ['html' => '<strong>One idea.</strong> Two competing focal points read as none.'],
                        ['html' => '<strong>Contrast with the row.</strong> You are not designing against a white page, you are designing against nine other thumbnails.'],
                    ]]],
                    ['type' => 'callout', 'data' => ['tone' => 'tip', 'title' => 'Test it properly', 'html' => 'Shrink your thumbnail to 168px wide and look at it on your phone in daylight. Most thumbnails fail this and nothing else matters until they pass.']],
                    ['type' => 'quote', 'data' => ['text' => 'A thumbnail is a promise. The title tells them what the promise is; the thumbnail tells them whether to believe it.', 'cite' => 'Every channel that grew past 100k', 'variant' => 'pull']],
                    ['type' => 'heading', 'data' => ['level' => 2, 'text' => 'Text length is the whole game']],
                    ['type' => 'paragraph', 'data' => ['html' => 'Three to four words is the ceiling. Beyond that you are asking someone to read while scrolling, which nobody does. Our character counter will tell you when you have gone over.']],
                    ['type' => 'toolCard', 'data' => ['toolSlug' => 'youtube-thumbnail-downloader']],
                    ['type' => 'heading', 'data' => ['level' => 2, 'text' => 'What does not work']],
                    ['type' => 'table', 'data' => ['has_header' => true, 'rows' => [
                        ['Tactic', 'Why it fails'],
                        ['Red arrows on everything', 'Signals low quality once the novelty passes'],
                        ['Shocked face, every video', 'Your audience stops seeing it after a week'],
                        ['Full sentences', 'Nobody reads a thumbnail, they glance at it'],
                    ]]],
                    ['type' => 'divider', 'data' => ['style' => 'dots']],
                    ['type' => 'paragraph', 'data' => ['html' => 'Change one variable at a time and give each version a full week. Anything faster and you are reading noise.']],
                    ['type' => 'faq', 'data' => ['items' => [
                        ['question' => 'How often should I change a thumbnail?', 'answer' => 'Once a video has settled — usually after 48 hours. Changing sooner destroys the data you would have learned from.'],
                        ['question' => 'Does the thumbnail affect suggested traffic?', 'answer' => 'Yes. Suggested placement is driven by click-through rate and watch time together, and the thumbnail drives the first of those.'],
                    ]]],
                ],
            ],
            [
                'slug' => 'reading-your-analytics-without-fooling-yourself',
                'title' => 'Reading your analytics without fooling yourself',
                'excerpt' => 'Most creator dashboards are optimised to make you feel good. Here is how to pull the signal out of them.',
                'category' => 'analytics',
                'tags' => ['analytics', 'engagement', 'youtube'],
                'status' => PostStatus::Published,
                'published_at' => now()->subDays(5),
                'image' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=1200&h=630&fit=crop',
                'blocks' => [
                    ['type' => 'paragraph', 'data' => ['html' => 'Every platform shows you the numbers that make the platform look good. Impressions are up! Watch time is up! Neither tells you whether you are building anything.']],
                    ['type' => 'heading', 'data' => ['level' => 2, 'text' => 'Ratios beat totals']],
                    ['type' => 'paragraph', 'data' => ['html' => 'A total always grows if you keep posting. A ratio only grows if you are getting better. Track engagement rate, not likes; retention percentage, not average view duration.']],
                    ['type' => 'code', 'data' => ['language' => 'text', 'filename' => 'engagement-rate.txt', 'code' => "engagement_rate = (likes + comments + saves + shares) / reach × 100\n\n# Under 1%   — the post did not land\n# 1% to 3%   — normal\n# Over 6%    — study this one, then do it again"]],
                    ['type' => 'callout', 'data' => ['tone' => 'warning', 'title' => 'Beware the 28-day window', 'html' => 'Most dashboards default to a rolling 28 days, which quietly hides anything seasonal. Compare like periods against like periods.']],
                    ['type' => 'toolCard', 'data' => ['toolSlug' => 'engagement-rate-calculator']],
                    ['type' => 'heading', 'data' => ['level' => 2, 'text' => 'The only cohort that matters']],
                    ['type' => 'paragraph', 'data' => ['html' => 'Returning viewers. If that number is flat while impressions climb, you are renting attention rather than building an audience — and rented attention leaves the moment you stop paying rent.']],
                ],
            ],
            [
                'slug' => 'writing-hooks-people-actually-finish',
                'title' => 'Writing hooks people actually finish',
                'excerpt' => 'The first three seconds decide the next three minutes. A practical framework, with examples you can steal.',
                'category' => 'content-craft',
                'tags' => ['tiktok', 'shorts', 'engagement'],
                'status' => PostStatus::Published,
                'published_at' => now()->subDays(9),
                'featured' => true,
                'image' => 'https://images.unsplash.com/photo-1516251193007-45ef944ab0c6?w=1200&h=630&fit=crop',
                'blocks' => [
                    ['type' => 'paragraph', 'data' => ['html' => 'A hook is not a gimmick. It is a contract: here is what you are about to get, and here is why it is worth your next three minutes.']],
                    ['type' => 'heading', 'data' => ['level' => 2, 'text' => 'Four hooks that keep working']],
                    ['type' => 'list', 'data' => ['style' => 'unordered', 'items' => [
                        ['html' => '<strong>The correction.</strong> "You have been doing X wrong" — works because it promises a specific fix.'],
                        ['html' => '<strong>The receipt.</strong> "I spent 90 days doing X. Here is the data."'],
                        ['html' => '<strong>The shortcut.</strong> "This takes four hours. Here is the ten-minute version."'],
                        ['html' => '<strong>The stakes.</strong> "This mistake cost me 40,000 subscribers."'],
                    ]]],
                    ['type' => 'embed', 'data' => ['provider' => 'youtube', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'aspect' => '16:9', 'caption' => 'A worked example of the correction hook.']],
                    ['type' => 'callout', 'data' => ['tone' => 'info', 'title' => 'Say the thing', 'html' => 'The most common failure is burying the promise behind an introduction. Nobody needs to know your name in second one.']],
                    ['type' => 'toolCard', 'data' => ['toolSlug' => 'headline-analyzer']],
                    ['type' => 'button', 'data' => ['label' => 'Analyze your headline', 'href' => '/tools/headline-analyzer', 'variant' => 'primary']],
                ],
            ],
            [
                'slug' => 'hashtags-are-not-a-strategy',
                'title' => 'Hashtags are not a strategy',
                'excerpt' => 'They still help — just far less than the advice industry claims, and only in a specific way.',
                'category' => 'growth',
                'tags' => ['instagram', 'hashtags', 'seo'],
                'status' => PostStatus::Published,
                'published_at' => now()->subDays(14),
                'image' => 'https://images.unsplash.com/photo-1611262588024-d12430b98920?w=1200&h=630&fit=crop',
                'blocks' => [
                    ['type' => 'paragraph', 'data' => ['html' => 'Hashtags are classification, not distribution. They tell the platform what your post is about; they do not make anyone see it.']],
                    ['type' => 'quote', 'data' => ['text' => 'Thirty hashtags on a bad post is thirty hashtags on a bad post.', 'cite' => '']],
                    ['type' => 'paragraph', 'data' => ['html' => 'Use five to eight that genuinely describe the content. Mix one broad, several mid-tail and one you could plausibly rank in. Then stop thinking about it.']],
                    ['type' => 'toolCard', 'data' => ['toolSlug' => 'hashtag-generator']],
                ],
            ],
            [
                'slug' => 'what-changed-on-the-platforms-this-month',
                'title' => 'What changed on the platforms this month',
                'excerpt' => 'Product and algorithm changes across YouTube, Instagram, TikTok and X — and which ones you need to act on.',
                'category' => 'platform-news',
                'tags' => ['youtube', 'instagram', 'tiktok', 'x'],
                'status' => PostStatus::Published,
                'published_at' => now()->subDays(21),
                'image' => 'https://images.unsplash.com/photo-1432888622747-4eb9a8efeb07?w=1200&h=630&fit=crop',
                'blocks' => [
                    ['type' => 'paragraph', 'data' => ['html' => 'A short round-up of what actually shipped, with the ones worth changing your workflow over marked clearly.']],
                    ['type' => 'heading', 'data' => ['level' => 2, 'text' => 'Worth acting on']],
                    ['type' => 'list', 'data' => ['style' => 'checklist', 'items' => [
                        ['html' => 'Longer Shorts are now eligible for the same suggested surface as regular uploads.', 'checked' => true],
                        ['html' => 'Link stickers now count toward reach in the ranking signal, not against it.', 'checked' => true],
                        ['html' => 'Scheduled posts no longer take a distribution penalty.', 'checked' => false],
                    ]]],
                    ['type' => 'callout', 'data' => ['tone' => 'danger', 'title' => 'Deprecation notice', 'html' => 'The legacy insights export is being retired. Pull anything you still need from it before the end of the quarter.']],
                    ['type' => 'divider', 'data' => ['style' => 'asterism']],
                    ['type' => 'paragraph', 'data' => ['html' => 'We publish this round-up monthly. Subscribe below and it lands in your inbox the morning it goes out.']],
                ],
            ],
            [
                'slug' => 'the-posting-schedule-that-survives-a-bad-week',
                'title' => 'The posting schedule that survives a bad week',
                'excerpt' => 'Consistency beats volume, but only if the schedule is one you can keep when everything goes wrong.',
                'category' => 'content-craft',
                'tags' => ['scheduling', 'engagement'],
                'status' => PostStatus::Published,
                'published_at' => now()->subDays(28),
                'image' => 'https://images.unsplash.com/photo-1506784365847-bbad939e9335?w=1200&h=630&fit=crop',
                'blocks' => [
                    ['type' => 'paragraph', 'data' => ['html' => 'Every creator eventually discovers that the schedule they designed on a good week is unsurvivable on a bad one. Design for the bad week.']],
                    ['type' => 'heading', 'data' => ['level' => 2, 'text' => 'Pick the floor, not the ceiling']],
                    ['type' => 'paragraph', 'data' => ['html' => 'Your cadence should be the amount you could still ship during a house move. Anything above that is a bonus, not a commitment.']],
                    ['type' => 'html', 'data' => ['html' => '<p>A simple rule: <strong>one anchor post per week</strong>, everything else optional.</p>']],
                ],
            ],
            [
                'slug' => 'a-scheduled-look-ahead',
                'title' => 'A scheduled look ahead',
                'excerpt' => 'This one is scheduled — it appears automatically once its publish time passes.',
                'category' => 'platform-news',
                'tags' => ['scheduling'],
                'status' => PostStatus::Scheduled,
                'scheduled_for' => now()->addDays(3),
                'image' => 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=1200&h=630&fit=crop',
                'blocks' => [
                    ['type' => 'paragraph', 'data' => ['html' => 'Scheduled posts are invisible to the public until the scheduler promotes them.']],
                ],
            ],
            [
                'slug' => 'a-draft-in-progress',
                'title' => 'A draft in progress',
                'excerpt' => 'Drafts never appear on the public blog.',
                'category' => 'growth',
                'tags' => ['seo'],
                'status' => PostStatus::Draft,
                'image' => 'https://images.unsplash.com/photo-1499750310107-5fef28a66643?w=1200&h=630&fit=crop',
                'blocks' => [
                    ['type' => 'paragraph', 'data' => ['html' => 'Still being written.']],
                ],
            ],
        ];
    }
}
