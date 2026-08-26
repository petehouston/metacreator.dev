<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Seo\Models\SeoMeta;
use App\Domain\Tools\Enums\ToolStatus;
use App\Domain\Tools\Enums\ToolTier;
use App\Domain\Tools\Models\Tool;
use App\Domain\Tools\Models\ToolCategory;
use App\Domain\Tools\ToolRegistry;
use Database\Seeders\Support\Blocks;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Reference data generated from docs/07-tool-catalog.md.
 *
 * Runs in production. The input schema is pulled from the runner itself rather than
 * duplicated here, so the catalog row and the code cannot describe different forms.
 */
final class ToolCatalogSeeder extends Seeder
{
    public function run(ToolRegistry $registry): void
    {
        $categories = ToolCategory::query()->pluck('id', 'slug');

        foreach ($this->definitions() as $index => $definition) {
            $tool = Tool::query()->updateOrCreate(
                ['key' => $definition['key']],
                [
                    'slug' => $definition['slug'],
                    'category_id' => $categories[$definition['category']],
                    'name' => $definition['name'],
                    'tagline' => $definition['tagline'],
                    'description' => $definition['description'],
                    'tier' => $definition['tier'],
                    'status' => ToolStatus::Published,
                    'is_visible' => true,
                    'platforms' => $definition['platforms'],
                    // Pulled from the runner: one definition, no drift.
                    'input_schema' => $registry->resolve($definition['key'])->inputSchema(),
                    'config' => $definition['config'] ?? [],
                    'instructions' => $definition['instructions'],
                    'example' => $definition['example'],
                    'faq' => $definition['faq'] ?? [],
                    'sort_order' => $index,
                    'featured_at' => ($definition['featured'] ?? false) ? now() : null,
                    'published_at' => now(),
                ],
            );

            $this->syncPlatforms($tool, $definition['platforms']);
            $this->syncSeo($tool, $definition);
        }

        $this->command->info('Seeded '.count($this->definitions()).' tools.');
    }

    /** @param  list<string>  $platforms */
    private function syncPlatforms(Tool $tool, array $platforms): void
    {
        DB::table('tool_platform')->where('tool_id', $tool->id)->delete();

        if ($platforms === []) {
            return;
        }

        DB::table('tool_platform')->insert(array_map(
            fn (string $platform) => ['tool_id' => $tool->id, 'platform' => $platform],
            $platforms,
        ));
    }

    /** @param  array<string, mixed>  $definition */
    private function syncSeo(Tool $tool, array $definition): void
    {
        SeoMeta::query()->updateOrCreate(
            ['seoable_type' => Tool::class, 'seoable_id' => $tool->id],
            [
                'title' => $definition['seo_title'] ?? "{$definition['name']} — Free Online Tool",
                'description' => $definition['seo_description'] ?? $definition['tagline'],
                'robots' => 'index,follow',
                'schema_type' => 'SoftwareApplication',
                'focus_keyword' => $definition['focus_keyword'] ?? strtolower($definition['name']),
            ],
        );
    }

    /** @return list<array<string, mixed>> */
    private function definitions(): array
    {
        return [
            [
                'key' => 'youtube.thumbnail-downloader',
                'slug' => 'youtube-thumbnail-downloader',
                'category' => 'youtube',
                'name' => 'YouTube Thumbnail Downloader',
                'tagline' => 'Grab every thumbnail size from any YouTube video in one click.',
                'description' => 'Paste any YouTube link and get all five published thumbnail resolutions, '
                    .'from the 1280×720 maxres image down to the 120×90 grid tile — in both JPG and WebP.',
                'tier' => ToolTier::Free,
                'platforms' => ['youtube'],
                'featured' => true,
                'focus_keyword' => 'youtube thumbnail downloader',
                'seo_title' => 'YouTube Thumbnail Downloader — Get Any Video Thumbnail in HD (Free)',
                'seo_description' => 'Download any YouTube thumbnail in maxres, HQ, MQ and SD. '
                    .'Works with watch links, Shorts, embeds and share URLs. Free, no account needed.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Copy the URL of any public YouTube video and paste it into the field above. '
                        .'The tool works with every link shape YouTube produces — watch pages, share links, embeds, '
                        .'Shorts and mobile URLs — as well as a bare 11-character video ID.'),
                    Blocks::heading('What you get', 2),
                    Blocks::list([
                        '<strong>Max resolution (1280×720)</strong> — only exists if the creator uploaded an HD thumbnail.',
                        '<strong>Standard definition (640×480)</strong> — a 4:3 crop, not always generated.',
                        '<strong>High quality (480×360)</strong> — always available, the safest fallback.',
                        '<strong>Medium (320×180)</strong> and <strong>default (120×90)</strong> — grid and sidebar sizes.',
                    ]),
                    Blocks::callout('info', 'Thumbnails belong to the video owner. Use them for research, '
                        .'competitive analysis or commentary — not to re-upload as your own.'),
                ]),
                'example' => [
                    'input' => ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
                    'note' => 'Try it with a real video — the result appears instantly.',
                ],
                'faq' => [
                    ['q' => 'Why is the maxres thumbnail missing?',
                        'a' => 'YouTube only generates the 1280×720 version when the uploader provided an HD custom thumbnail. '
                            .'For older or auto-generated thumbnails, the 480×360 version is the largest available.'],
                    ['q' => 'Does this work for private or unlisted videos?',
                        'a' => 'Unlisted videos work if you have the link. Private videos do not — their thumbnails are not publicly served.'],
                    ['q' => 'Can I use these thumbnails in my own videos?',
                        'a' => 'Only with permission, or where fair use/fair dealing applies in your jurisdiction. '
                            .'Thumbnails are the copyright of the video owner.'],
                ],
            ],

            [
                'key' => 'analytics.engagement-rate-calculator',
                'slug' => 'engagement-rate-calculator',
                'category' => 'analytics',
                'name' => 'Engagement Rate Calculator',
                'tagline' => 'Work out your engagement rate — and whether it is actually any good.',
                'description' => 'Calculates engagement by followers and by reach across six platforms, '
                    .'then benchmarks the result against the median for accounts of your size. '
                    .'A 2% rate means very different things at 5,000 followers and 500,000.',
                'tier' => ToolTier::Free,
                'platforms' => ['instagram', 'tiktok', 'youtube', 'x', 'facebook', 'linkedin'],
                'featured' => true,
                'focus_keyword' => 'engagement rate calculator',
                'seo_title' => 'Engagement Rate Calculator for Instagram, TikTok & YouTube (Free)',
                'seo_description' => 'Calculate engagement rate by followers or reach, and compare it to the '
                    .'median for your platform and follower band. Free, instant, no signup.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Pick your platform, enter your follower count and the interactions on a '
                        .'single post. If you know the post’s reach, add it — engagement by reach is the figure '
                        .'brands negotiate on, and it is almost always the more flattering one.'),
                    Blocks::heading('Which number should you quote?', 2),
                    Blocks::paragraph('Quote <strong>engagement by reach</strong> in a media kit: it reflects how '
                        .'compelling your content was to the people who actually saw it. Quote '
                        .'<strong>engagement by followers</strong> when comparing yourself to public accounts, '
                        .'since reach is rarely published.'),
                    Blocks::callout('tip', 'A sudden drop in engagement at a stable follower count usually means '
                        .'audience quality, not content quality. Check for a spike in follower growth a few weeks earlier.'),
                ]),
                'example' => [
                    'input' => ['platform' => 'instagram', 'followers' => 12500, 'likes' => 640,
                        'comments' => 48, 'shares' => 22, 'saves' => 95, 'reach' => 18400],
                    'note' => 'A healthy mid-size Instagram account.',
                ],
                'faq' => [
                    ['q' => 'Should saves and shares count as engagement?',
                        'a' => 'Yes. On Instagram and TikTok, saves and shares are weighted more heavily by the '
                            .'ranking systems than likes, so leaving them out understates your performance.'],
                    ['q' => 'Where do the benchmarks come from?',
                        'a' => 'Published industry studies of engagement by platform and follower band. They are '
                            .'medians, not targets — niche matters enormously.'],
                ],
            ],

            [
                'key' => 'content.character-counter',
                'slug' => 'social-media-character-counter',
                'category' => 'content',
                'name' => 'Social Media Character Counter',
                'tagline' => 'One box, every platform’s limit, and exactly where each one cuts you off.',
                'description' => 'Counts your text the way each platform really counts it — emoji as one '
                    .'character, CJK as two on X, and every link as a flat 23 — then shows the limit and '
                    .'truncation point for ten surfaces at once.',
                'tier' => ToolTier::Free,
                'platforms' => ['x', 'instagram', 'tiktok', 'youtube', 'linkedin', 'facebook'],
                'featured' => true,
                'focus_keyword' => 'social media character counter',
                'seo_title' => 'Social Media Character Counter — Limits for X, Instagram, TikTok & More',
                'seo_description' => 'Check your text against the character limits for X, Instagram, TikTok, '
                    .'YouTube, LinkedIn and Facebook at once — including where each platform truncates.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Type or paste your text. Every platform’s count updates as you go, along '
                        .'with the point where that platform hides the rest behind a “see more”.'),
                    Blocks::heading('Why counts differ between tools', 2),
                    Blocks::paragraph('Most character counters use a naïve string length, which is wrong in three '
                        .'common cases: an emoji like 👨‍👩‍👧‍👦 is one character to a reader but seven codepoints; '
                        .'X counts Japanese and Chinese characters as two; and X counts every link as 23 characters '
                        .'no matter how long it is. This tool applies each platform’s real rule.'),
                    Blocks::callout('tip', 'The “before see more” column matters more than the limit. On TikTok you '
                        .'have about 90 characters before your caption is cut — put the hook first.'),
                ]),
                'example' => [
                    'input' => ['text' => "I spent 90 days posting daily on every platform.\n\n"
                        ."Here's what actually moved the needle 👇 https://example.com/results"],
                    'note' => 'Note how the link counts as 23 characters on X.',
                ],
            ],

            [
                'key' => 'x.thread-splitter',
                'slug' => 'x-thread-splitter',
                'category' => 'x',
                'name' => 'X Thread Splitter',
                'tagline' => 'Turn long writing into a thread that breaks in the right places.',
                'description' => 'Splits long-form text into numbered posts at paragraph and sentence '
                    .'boundaries — never mid-sentence — and accounts for the characters that numbering itself '
                    .'consumes so the last post does not overflow.',
                'tier' => ToolTier::Free,
                'platforms' => ['x'],
                'focus_keyword' => 'twitter thread splitter',
                'seo_title' => 'X (Twitter) Thread Splitter — Split Long Text into a Numbered Thread',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste your text and choose a character limit — 280 for a standard account, '
                        .'up to 25,000 for Premium. Blank lines are treated as deliberate breaks, so structure your '
                        .'draft in paragraphs and the split will follow your intent.'),
                    Blocks::heading('Why this splits better', 2),
                    Blocks::paragraph('Naïve splitters cut at exactly 280 characters, which lands mid-sentence and '
                        .'reads badly. This one prefers paragraph breaks, then sentence endings, and only ever cuts '
                        .'inside a sentence when a single sentence genuinely exceeds the limit.'),
                    Blocks::callout('tip', 'Keep the first post as a standalone hook. A thread lives or dies on '
                        .'whether post 1 earns the tap on “Show this thread”.'),
                ]),
                'example' => [
                    'input' => [
                        'text' => "Most creators quit at month four.\n\n"
                            ."It isn't because the work is hard. It's because the feedback loop is broken: you "
                            .'publish, nothing happens, and you have no way to tell whether the idea was wrong or '
                            ."the timing was.\n\n"
                            ."Here's the system I used to fix that, and the three metrics I actually watch.",
                        'limit' => 280,
                        'numbering' => 'slash',
                        'reserve_hook' => true,
                    ],
                ],
            ],

            [
                'key' => 'content.headline-analyzer',
                'slug' => 'headline-analyzer',
                'category' => 'content',
                'name' => 'Headline & Title Analyzer',
                'tagline' => 'Score a title, and get the specific edits that would improve it.',
                'description' => 'Scores length, structure, language and trust signals for the surface you are '
                    .'publishing on, then lists prioritised, concrete fixes. The score exists only to rank which '
                    .'fix to make first.',
                'tier' => ToolTier::Free,
                'platforms' => ['youtube', 'instagram', 'tiktok', 'linkedin'],
                'featured' => true,
                'focus_keyword' => 'headline analyzer',
                'seo_title' => 'Headline Analyzer — Score Your Title and Get Specific Fixes (Free)',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Enter your headline and choose where it will be published. Ideal length '
                        .'differs sharply by surface — YouTube truncates around 60 characters on mobile, while '
                        .'LinkedIn gives you far more room.'),
                    Blocks::heading('How the score is built', 2),
                    Blocks::list([
                        '<strong>Length (25%)</strong> — against the truncation point for your chosen surface.',
                        '<strong>Structure (25%)</strong> — numbers, bracketed qualifiers and word count.',
                        '<strong>Language (25%)</strong> — power and emotional words, penalised for stacking.',
                        '<strong>Clarity &amp; trust (25%)</strong> — clickbait patterns, ALL CAPS, punctuation spam.',
                    ]),
                    Blocks::callout('warning', 'A high score is not a guarantee. Test two titles against each other '
                        .'and let your own audience decide — this tool tells you which two are worth testing.'),
                ]),
                'example' => [
                    'input' => ['headline' => 'How I Grew to 100k Subscribers in 9 Months (Without Going Viral)',
                        'context' => 'youtube'],
                ],
            ],

            [
                'key' => 'content.hashtag-generator',
                'slug' => 'hashtag-generator',
                'category' => 'content',
                'name' => 'Hashtag Generator',
                'tagline' => 'Balanced hashtag sets — mostly niche, where a small account can actually rank.',
                'description' => 'Builds niche, broad and platform-staple hashtag groups from your topic, plus a '
                    .'recommended set in roughly a 70/20/10 ratio and the right total for each platform.',
                'tier' => ToolTier::Account,
                'platforms' => ['instagram', 'tiktok', 'youtube', 'x', 'linkedin'],
                'focus_keyword' => 'hashtag generator',
                'seo_title' => 'Hashtag Generator for Instagram, TikTok & YouTube — Balanced Sets',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Enter your topic and pick a platform. Adding a few related keywords produces '
                        .'noticeably better niche tags, because there is more material to build community tags from.'),
                    Blocks::heading('Why the mix matters more than the tags', 2),
                    Blocks::paragraph('Stacking only huge hashtags is the most common mistake. On a tag with ten '
                        .'million posts, a small account is buried within seconds. Niche tags have smaller audiences '
                        .'but keep a post visible for hours or days — which is where the reach actually comes from.'),
                    Blocks::callout('tip', 'Recommended totals differ a lot: around 12 on Instagram, 5 on TikTok, '
                        .'and 2 on X, where hashtags do very little.'),
                ]),
                'example' => [
                    'input' => ['topic' => 'sourdough baking', 'platform' => 'instagram',
                        'extra_keywords' => 'bread, starter, home baking'],
                ],
            ],

            [
                'key' => 'utility.utm-builder',
                'slug' => 'utm-link-builder',
                'category' => 'utility',
                'name' => 'UTM Link Builder',
                'tagline' => 'Consistent campaign links, so your analytics stay readable.',
                'description' => 'Builds tagged URLs and normalises every parameter to lowercase-hyphenated, '
                    .'preserving any query string the destination already has.',
                'tier' => ToolTier::Free,
                'platforms' => [],
                'focus_keyword' => 'utm builder',
                'seo_title' => 'UTM Link Builder — Build Consistent Campaign URLs (Free)',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Enter the destination URL and describe where the link will live. Source is '
                        .'the platform, medium is the kind of link, and campaign groups everything together.'),
                    Blocks::heading('The mistake this prevents', 2),
                    Blocks::paragraph('Analytics reports turn to mush because of casing. <code>Instagram</code>, '
                        .'<code>instagram</code> and <code>IG</code> become three separate sources in every report, '
                        .'and nobody notices until the quarter is over. Every parameter here is normalised, and you '
                        .'are told when it changed.'),
                    Blocks::callout('tip', 'Use <code>utm_content</code> to separate two links in the same campaign '
                        .'— “bio-link” versus “story-swipe” — so you learn which placement actually works.'),
                ]),
                'example' => [
                    'input' => ['url' => 'https://example.com/pricing', 'source' => 'Instagram',
                        'medium' => 'bio', 'campaign' => 'Spring Launch', 'content' => 'bio-link'],
                    'note' => 'Watch how "Instagram" and "Spring Launch" are normalised.',
                ],
            ],

            [
                'key' => 'utility.giveaway-winner-picker',
                'slug' => 'giveaway-winner-picker',
                'category' => 'utility',
                'name' => 'Giveaway Winner Picker',
                'tagline' => 'Draw winners your entrants can verify for themselves.',
                'description' => 'Picks winners and runners-up from a list of entries. Publish a seed before you '
                    .'draw and anyone can re-run the same draw and confirm the result — which is what makes a '
                    .'giveaway defensible.',
                'tier' => ToolTier::Free,
                'platforms' => ['instagram', 'tiktok', 'x', 'youtube'],
                'focus_keyword' => 'giveaway winner picker',
                'seo_title' => 'Giveaway Winner Picker — Verifiable Random Draw for Comments (Free)',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste your entries, one per line, and choose how many winners and runners-up '
                        .'to draw. Runners-up matter more than people expect — unclaimed prizes are common.'),
                    Blocks::heading('Make the draw verifiable', 2),
                    Blocks::paragraph('Before drawing, post a seed publicly — tomorrow’s date, a lottery number, '
                        .'anything you could not have known in advance. Enter that seed here. The draw is then '
                        .'derived from the seed and the entry list, so anyone who has both can reproduce your exact '
                        .'result. Without a seed the draw is genuinely random but impossible to prove.'),
                    Blocks::callout('warning', 'Check the giveaway rules for your platform and jurisdiction. '
                        .'Requiring a purchase, or running a prize draw without published terms, is restricted in '
                        .'many countries.'),
                ]),
                'example' => [
                    'input' => ['entries' => "@creator_one\n@creator_two\n@creator_three\n@creator_four\n@creator_five",
                        'winners' => 1, 'runners_up' => 2, 'deduplicate' => true, 'seed' => '2026-09-01-draw'],
                    'note' => 'Run this twice with the same seed — the winner does not change.',
                ],
            ],
        ];
    }
}
