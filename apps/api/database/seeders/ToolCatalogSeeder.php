<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Seo\Models\SeoMeta;
use App\Domain\Seo\Services\ToolSeoDefaults;
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
    /**
     * Keys this catalog has moved away from, dropped before the upsert below.
     *
     * Rows are matched on `key`, so a renamed tool would otherwise seed itself a
     * second time and leave the old name, slug and SEO record published alongside
     * it. Only keys listed here are deleted — a tool an admin added by hand is not
     * this seeder's to remove.
     *
     * @var list<string>
     */
    private const RETIRED_KEYS = [
        // Renamed to youtube.partner-program-checker, so that "monetization
        // checker" belongs to the tool that checks somebody else's channel.
        'youtube.monetization-checker',
    ];

    public function run(ToolRegistry $registry, ToolSeoDefaults $seo): void
    {
        Tool::query()->whereIn('key', self::RETIRED_KEYS)->delete();

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
            $this->syncSeo($tool, $definition, $seo);
        }

        $this->pruneEmptyCategories();

        $this->command->info('Seeded '.count($this->definitions()).' tools.');
    }

    /**
     * Drop categories this catalog no longer uses.
     *
     * Runs here rather than in {@see ToolCategorySeeder} because a category can only
     * be deleted once every tool has been moved off it, which is what the loop above
     * just did. Categories that still hold a tool are left alone: an admin may have
     * created one by hand, and silently deleting somebody's data to match a seeder
     * is not a trade worth making.
     */
    private function pruneEmptyCategories(): void
    {
        ToolCategory::query()
            ->whereNotIn('slug', ToolCategorySeeder::slugs())
            ->whereDoesntHave('tools')
            ->delete();
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

    /**
     * Seed the tool's SEO row.
     *
     * Hand-written copy in the definition wins; everything else comes from
     * {@see ToolSeoDefaults}, which is the same generator the API falls back to at
     * read time. One source for both means a tool tuned in this file and a tool
     * left alone are described the same way, and neither ships a blank field.
     *
     * @param  array<string, mixed>  $definition
     */
    private function syncSeo(Tool $tool, array $definition, ToolSeoDefaults $seo): void
    {
        $defaults = $seo->for($tool);

        SeoMeta::query()->updateOrCreate(
            ['seoable_type' => Tool::class, 'seoable_id' => $tool->id],
            [
                'title' => $definition['seo_title'] ?? $defaults['title'],
                'description' => $definition['seo_description'] ?? $defaults['description'],
                'robots' => $defaults['robots'],
                'schema_type' => $defaults['schema_type'],
                'focus_keyword' => $definition['focus_keyword'] ?? $defaults['focus_keyword'],
                // Share copy is generated rather than hand-written per tool: sixty
                // bespoke og titles is sixty chances to leave one empty, and the
                // generated one is shaped for a timeline rather than a SERP.
                'og_title' => $definition['og_title'] ?? $defaults['og_title'],
                'og_description' => $definition['og_description'] ?? $defaults['og_description'],
                'twitter_card' => $defaults['twitter_card'],
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
                'category' => 'media',
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
                'category' => 'content',
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

            [
                'key' => 'content.word-counter',
                'slug' => 'word-counter',
                'category' => 'content',
                'name' => 'Word & Character Counter',
                'tagline' => 'Words, characters, and how long it takes to read or say out loud.',
                'description' => 'Counts words, characters, sentences and paragraphs, then converts the total '
                    .'into a silent reading time and a spoken runtime — the two numbers that decide whether a '
                    .'script fits its slot.',
                'tier' => ToolTier::Free,
                'platforms' => [],
                'focus_keyword' => 'word counter',
                'seo_title' => 'Word & Character Counter — With Reading and Speaking Time (Free)',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste anything — a caption, a script, a whole article. Everything updates '
                        .'in one pass, including the two timings.'),
                    Blocks::paragraph('Reading time assumes 238 words per minute, the measured average for silent '
                        .'reading on screen. Speaking time assumes 140, which is a natural presenting pace rather '
                        .'than the 180 you will hit reading a teleprompter flat out.'),
                ]),
                'example' => [
                    'input' => ['text' => "Most creators quit at month four.\n\nIt isn't because the work is hard — "
                        .'it is because the feedback loop is broken.'],
                ],
            ],

            [
                'key' => 'content.text-case-converter',
                'slug' => 'text-case-converter',
                'category' => 'content',
                'name' => 'Text Case Converter',
                'tagline' => 'Eleven casings at once, each one a click away from your clipboard.',
                'description' => 'Converts text to upper, lower, title, sentence, camel, Pascal, snake, kebab and '
                    .'constant case, plus alternating and reversed — all at the same time, so you can pick by eye.',
                'tier' => ToolTier::Free,
                'platforms' => [],
                'focus_keyword' => 'text case converter',
                'seo_title' => 'Text Case Converter — Title Case, camelCase, snake_case & More (Free)',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste your text and copy whichever casing you need. Title Case follows the '
                        .'standard editorial rule: minor words such as “of”, “the” and “and” stay lowercase unless '
                        .'they open or close the title.'),
                    Blocks::callout('tip', 'Sentence case consistently outperforms Title Case in headlines and '
                        .'subject lines — it reads as a person talking rather than as a press release.'),
                ]),
                'example' => [
                    'input' => ['text' => 'how i grew to 100k subscribers in nine months'],
                ],
            ],

            [
                'key' => 'content.fancy-text-generator',
                'slug' => 'fancy-text-generator',
                'category' => 'content',
                'name' => 'Fancy Text Generator',
                'tagline' => 'Bold, italic and script text that survives being pasted into a bio.',
                'description' => 'Turns plain text into fifteen Unicode styles — bold, italic, script, outline, '
                    .'monospace, small caps, upside down and more — that keep their look anywhere text is accepted, '
                    .'because they are real characters rather than formatting.',
                'tier' => ToolTier::Free,
                'platforms' => ['instagram', 'tiktok', 'x'],
                'featured' => true,
                'focus_keyword' => 'fancy text generator',
                'seo_title' => 'Fancy Text Generator — Bold, Italic & Aesthetic Fonts for Bios (Free)',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Type your text and copy the style you want. It will look the same in an '
                        .'Instagram bio, a TikTok caption or an X post — no app or font install involved.'),
                    Blocks::heading('These are not fonts', 2),
                    Blocks::paragraph('Each “style” is a separate block of Unicode characters that happens to look '
                        .'like a styled alphabet. That is why it survives copy and paste — and also why search '
                        .'cannot match it and screen readers struggle with it.'),
                    Blocks::callout('warning', 'Keep your account name, your key claim and your call to action in '
                        .'plain text. Styled text is decoration, not content.'),
                ]),
                'example' => [
                    'input' => ['text' => 'creator studio'],
                    'note' => 'Copy any style straight into your bio.',
                ],
            ],

            [
                'key' => 'content.readability-checker',
                'slug' => 'readability-checker',
                'category' => 'content',
                'name' => 'Readability Checker',
                'tagline' => 'Flesch scores, plus the exact sentences making your writing hard work.',
                'description' => 'Calculates Flesch reading ease and Flesch–Kincaid grade level for your audience, '
                    .'then names the specific long sentences and heavy words responsible for the score.',
                'tier' => ToolTier::Free,
                'platforms' => [],
                'focus_keyword' => 'readability checker',
                'seo_title' => 'Readability Checker — Flesch Reading Ease & Grade Level (Free)',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste your text and pick who it is for. The target grade moves with the '
                        .'audience: a caption read on a phone should sit around grade 6, documentation can sit at 12.'),
                    Blocks::callout('tip', 'Sentence length moves the score far faster than word choice. Splitting '
                        .'three long sentences usually beats swapping twenty long words.'),
                ]),
                'example' => [
                    'input' => ['text' => 'The implementation of a comprehensive content strategy necessitates the '
                        .'careful consideration of numerous interdependent variables, not least of which is the '
                        .'fundamental question of audience composition and the corresponding editorial register '
                        .'that such composition demands.', 'audience' => 'social'],
                    'note' => 'A deliberately difficult paragraph — watch the grade level.',
                ],
            ],

            [
                'key' => 'content.script-timer',
                'slug' => 'script-timer',
                'category' => 'content',
                'name' => 'Script Timer',
                'tagline' => 'How long your script runs, at every pace you might deliver it.',
                'description' => 'Converts a written script into a spoken runtime across five delivery paces, and '
                    .'tells you how many words to cut to hit a target length.',
                'tier' => ToolTier::Free,
                'platforms' => ['youtube', 'tiktok', 'instagram'],
                'focus_keyword' => 'script timer',
                'seo_title' => 'Script Timer — Words to Video Runtime Calculator (Free)',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste the words you will actually say. Anything in [square brackets] is '
                        .'treated as a stage direction and excluded from the count, so you can leave your notes in.'),
                    Blocks::callout('tip', 'Set a target of 59 seconds for Shorts and Reels. Going one second over '
                        .'changes which surface the video is eligible for.'),
                ]),
                'example' => [
                    'input' => ['script' => '[hook] Most people set up their first video wrong. Here is the fix. '
                        .'Start with the result, not the introduction — nobody stays for a preamble.',
                        'target_seconds' => 30, 'pace' => 'shorts'],
                ],
            ],

            [
                'key' => 'content.cta-generator',
                'slug' => 'cta-generator',
                'category' => 'content',
                'name' => 'CTA Generator',
                'tagline' => 'Calls to action built from patterns that work, filled with your topic.',
                'description' => 'Produces call-to-action lines for the goal you pick — follows, comments, saves, '
                    .'clicks, signups or sales — plus one alternative per other goal, so you always have something '
                    .'to test against.',
                'tier' => ToolTier::Free,
                'platforms' => ['instagram', 'tiktok', 'youtube', 'linkedin'],
                'focus_keyword' => 'cta generator',
                'seo_title' => 'CTA Generator — Call to Action Ideas for Social Posts (Free)',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Describe the post and choose the one thing you want people to do. One CTA '
                        .'per post: two competing asks reliably produce neither.'),
                    Blocks::callout('tip', 'Match the ask to what the post earned. A tip post earns a save, a story '
                        .'post earns a follow, and only a demonstration post earns a sale.'),
                ]),
                'example' => [
                    'input' => ['topic' => 'sourdough baking', 'goal' => 'comment',
                        'audience' => 'home bakers', 'keyword' => 'STARTER'],
                ],
            ],

            [
                'key' => 'content.emoji-picker',
                'slug' => 'emoji-picker',
                'category' => 'content',
                'name' => 'Emoji Picker & Search',
                'tagline' => 'Find the emoji by what it means, not by its official Unicode name.',
                'description' => 'Searches a curated set of creator-relevant emoji by meaning — “growth”, “money”, '
                    .'“comment”, “boring” — which is how people actually look for them, and what the system picker '
                    .'cannot do.',
                'tier' => ToolTier::Free,
                'platforms' => ['instagram', 'tiktok', 'x', 'linkedin'],
                'focus_keyword' => 'emoji search',
                'seo_title' => 'Emoji Picker & Keyword Search for Captions and Bios (Free)',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Search by meaning, or filter by group to browse. Every entry lists the words '
                        .'it also matches, so you can see why it came up.'),
                    Blocks::callout('warning', 'Screen readers announce every emoji by name. Three in a caption is '
                        .'fine; thirty makes the caption unusable for anyone using one.'),
                ]),
                'example' => [
                    'input' => ['query' => 'growth', 'group' => ''],
                ],
            ],

            [
                'key' => 'linkedin.post-preview',
                'slug' => 'linkedin-post-preview',
                'category' => 'previews',
                'name' => 'LinkedIn Post Preview',
                'tagline' => 'See exactly where “…see more” cuts your post.',
                'description' => 'Shows the slice of your post visible before the fold on mobile and desktop, what '
                    .'is hidden behind the tap, and whether you are within the 3,000-character limit.',
                'tier' => ToolTier::Free,
                'platforms' => ['linkedin'],
                'focus_keyword' => 'linkedin character counter',
                'seo_title' => 'LinkedIn Post Preview — Where “See More” Cuts Your Post (Free)',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste the full post. The mobile fold at roughly 140 characters is the one '
                        .'that matters — that is all most people ever see.'),
                    Blocks::callout('tip', 'The tap on “see more” is a positive engagement signal. Writing a first '
                        .'line that demands the tap is the single highest-leverage edit on LinkedIn.'),
                ]),
                'example' => [
                    'input' => ['text' => "I turned down a client last week.\n\nThey had budget, a clear brief and a "
                        ."sensible timeline. I said no anyway, and it was the right call.\n\nHere is the test I now "
                        .'run before saying yes to anything.'],
                ],
            ],

            [
                'key' => 'facebook.post-preview',
                'slug' => 'facebook-post-preview',
                'category' => 'previews',
                'name' => 'Facebook Post Preview',
                'tagline' => 'Where the feed cuts your post — and it is earlier with a photo attached.',
                'description' => 'Shows the visible portion of a Facebook post across the four fold points that '
                    .'matter: text only, with a photo, with a link preview, and in the mobile app.',
                'tier' => ToolTier::Free,
                'platforms' => ['facebook'],
                'focus_keyword' => 'facebook post preview',
                'seo_title' => 'Facebook Post Preview — See Where “See More” Truncates (Free)',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste your post and say what is attached. Attaching a photo or a link moves '
                        .'the fold from 477 characters to around 200 — the most common reason a post reads as '
                        .'truncated nonsense in the feed.'),
                ]),
                'example' => [
                    'input' => ['text' => 'We spent six months rebuilding the way we plan content, and the biggest '
                        .'change had nothing to do with tools. It was deciding, in advance, what we would not post.',
                        'attachment' => 'photo'],
                ],
            ],

            [
                'key' => 'facebook.ad-text-counter',
                'slug' => 'facebook-ad-text-counter',
                'category' => 'content',
                'name' => 'Facebook Ad Text Counter',
                'tagline' => 'Primary text, headline and description — checked against what actually displays.',
                'description' => 'Ads Manager accepts far more text than any placement shows. This checks all three '
                    .'fields against the display limits and previews exactly what will be visible.',
                'tier' => ToolTier::Free,
                'platforms' => ['facebook', 'instagram'],
                'focus_keyword' => 'facebook ad character counter',
                'seo_title' => 'Facebook Ad Text Character Counter — Primary Text, Headline & Description',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste each field. The recommended lengths are display limits, not the limits '
                        .'Ads Manager enforces — it will happily accept 3,000 characters and then cut them at 125.'),
                    Blocks::callout('tip', 'Put the offer in the first 125 characters of the primary text and in the '
                        .'first 27 of the headline. Everything after that is for the people already convinced.'),
                ]),
                'example' => [
                    'input' => ['primary_text' => 'Every tool a creator needs, in one place — and most of them are '
                        .'free with no account at all.',
                        'headline' => 'Free tools for creators', 'description' => 'No signup required'],
                ],
            ],

            [
                'key' => 'instagram.bio-preview',
                'slug' => 'instagram-bio-preview',
                'category' => 'previews',
                'name' => 'Instagram Bio Preview',
                'tagline' => 'What your bio really looks like in the app — including what gets clipped.',
                'description' => 'Previews your display name, bio and link the way the app renders them, counts '
                    .'line breaks against the 150-character limit, and shows the ~80 characters visible before '
                    .'“… more”.',
                'tier' => ToolTier::Free,
                'platforms' => ['instagram'],
                'focus_keyword' => 'instagram bio preview',
                'seo_title' => 'Instagram Bio Preview & Character Counter (Free)',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste your bio exactly as you intend to save it, line breaks included — they '
                        .'each count against the 150-character limit.'),
                    Blocks::heading('The display name is the searchable field', 2),
                    Blocks::paragraph('Instagram search matches your username and your display name, not the body '
                        .'of your bio. If you bake sourdough, the word “sourdough” belongs in the name field.'),
                ]),
                'example' => [
                    'input' => ['name' => 'Sam · Sourdough', 'bio' => "Real bread, no mystique.\n"
                        ."Weekly starter tips 🍞\nFree beginner guide below 👇", 'link' => 'https://example.com/guide'],
                ],
            ],

            [
                'key' => 'youtube.timestamp-link-builder',
                'slug' => 'youtube-timestamp-link-builder',
                'category' => 'utility',
                'name' => 'YouTube Timestamp Link Builder',
                'tagline' => 'Deep links to exact moments, plus a chapter block that actually works.',
                'description' => 'Turns a list of “0:00 Label” lines into shareable deep links for every moment, and '
                    .'validates the same list against YouTube’s three rules for rendering real chapters.',
                'tier' => ToolTier::Free,
                'platforms' => ['youtube'],
                'focus_keyword' => 'youtube timestamp link',
                'seo_title' => 'YouTube Timestamp Link Builder & Chapter Generator (Free)',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste your video URL and one moment per line, starting with the time. Hours '
                        .'are supported — “1:04:20 The twist” works.'),
                    Blocks::heading('Why chapters sometimes do not appear', 2),
                    Blocks::list([
                        'The first timestamp must be <strong>0:00</strong>.',
                        'There must be <strong>at least three</strong> timestamps.',
                        'Each chapter must be <strong>at least 10 seconds</strong> long.',
                    ]),
                    Blocks::paragraph('Break any one of those and YouTube silently renders plain links instead. '
                        .'This tool checks all three before you publish.'),
                ]),
                'example' => [
                    'input' => ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'timestamps' => "0:00 Intro\n1:12 The problem\n4:35 The fix\n8:02 Results"],
                ],
            ],

            [
                'key' => 'youtube.money-calculator',
                'slug' => 'youtube-money-calculator',
                'category' => 'analytics',
                'name' => 'YouTube Money Calculator',
                'tagline' => 'Ad revenue by niche and geography — and what a sponsor would pay for the same views.',
                'description' => 'Estimates monthly and yearly ad revenue from an RPM band for your niche, adjusted '
                    .'for where your audience is, then prices a single sponsored integration against the same '
                    .'audience — usually the larger number.',
                'tier' => ToolTier::Free,
                'platforms' => ['youtube'],
                'featured' => true,
                'focus_keyword' => 'youtube money calculator',
                'seo_title' => 'YouTube Money Calculator — RPM, Ad Revenue & Sponsorship Value (Free)',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Enter your monthly views and pick the niche and audience geography that best '
                        .'describe your channel. Both move the result enormously: the same 100,000 views can be '
                        .'worth $60 or $2,000.'),
                    Blocks::heading('Why the sponsorship number matters more', 2),
                    Blocks::paragraph('For most channels under a million views a month, one sponsored integration '
                        .'is worth more than a month of ad revenue. Channels that plateau financially are usually '
                        .'the ones that never priced that number.'),
                    Blocks::callout('warning', 'Q4 pays roughly double January. An annual figure built from a '
                        .'November month will disappoint you.'),
                ]),
                'example' => [
                    'input' => ['monthly_views' => 250000, 'niche' => 'tech',
                        'audience' => 'us_uk', 'monetised_share' => 60],
                ],
                'faq' => [
                    ['q' => 'Is RPM the same as CPM?',
                        'a' => 'No. CPM is what an advertiser pays per 1,000 impressions; RPM is what lands in your '
                            .'account per 1,000 views after YouTube takes its 45% and after unmonetised views are '
                            .'included. RPM is always the smaller, more honest number.'],
                    ['q' => 'Why is my real RPM lower than this?',
                        'a' => 'Usually audience geography or a low monetised-playback rate. Shorts views in '
                            .'particular drag the blended figure down hard.'],
                ],
            ],

            [
                'key' => 'youtube.tag-extractor',
                'slug' => 'youtube-tag-extractor',
                'category' => 'utility',
                'name' => 'YouTube Tag Extractor',
                'tagline' => 'See the tags on any public video, read from the page itself.',
                'description' => 'Pulls the tag list a creator set on a public video, with the character budget each '
                    .'one consumes — useful for understanding how a competitor describes their own content.',
                'tier' => ToolTier::Free,
                'platforms' => ['youtube'],
                'focus_keyword' => 'youtube tag extractor',
                'seo_title' => 'YouTube Tag Extractor — See Any Video’s Tags (Free)',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste any public video URL. Tags come from the page’s own metadata, so no '
                        .'API key is needed and nothing is scraped that YouTube does not publish.'),
                    Blocks::callout('info', 'Many videos have no tags at all now. Tags carry very little ranking '
                        .'weight compared with the title, description and thumbnail — an empty result is a finding, '
                        .'not a failure.'),
                ]),
                'example' => [
                    'input' => ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
                ],
            ],

            [
                'key' => 'youtube.channel-id-finder',
                'slug' => 'youtube-channel-id-finder',
                'category' => 'utility',
                'name' => 'YouTube Channel ID Finder',
                'tagline' => 'Handle in, UC… channel ID out — plus the RSS feed nobody can find.',
                'description' => 'Resolves a handle, custom URL or legacy /user/ link to the immutable channel ID, '
                    .'and hands you the uploads playlist and RSS feed derived from it.',
                'tier' => ToolTier::Free,
                'platforms' => ['youtube'],
                'focus_keyword' => 'youtube channel id finder',
                'seo_title' => 'YouTube Channel ID Finder — Handle to UC ID (Free)',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Enter a handle like <code>@mkbhd</code>, or paste any channel URL. Old '
                        .'<code>/c/</code> and <code>/user/</code> links work too.'),
                    Blocks::heading('What the ID unlocks', 2),
                    Blocks::paragraph('The RSS feed needs no API key and has no quota — it is the cheapest way to '
                        .'watch a competitor’s uploads. The uploads playlist is the channel ID with its '
                        .'<code>UC</code> prefix swapped for <code>UU</code>, which is the trick most people never '
                        .'learn.'),
                ]),
                'example' => [
                    'input' => ['channel' => '@YouTube'],
                ],
            ],

            [
                'key' => 'tiktok.money-calculator',
                'slug' => 'tiktok-money-calculator',
                'category' => 'analytics',
                'name' => 'TikTok Money Calculator',
                'tagline' => 'What Creator Rewards really pays — and what a brand deal is worth instead.',
                'description' => 'Estimates Creator Rewards earnings on qualified views and prices a sponsored video '
                    .'two ways, on views and on followers, so you can see the gap between the two income sources.',
                'tier' => ToolTier::Free,
                'platforms' => ['tiktok'],
                'focus_keyword' => 'tiktok money calculator',
                'seo_title' => 'TikTok Money Calculator — Creator Rewards & Brand Deal Rates (Free)',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Enter your monthly views, your follower count, and roughly what share of '
                        .'your views come from videos over a minute long — only those qualify for Creator Rewards.'),
                    Blocks::callout('tip', 'If the brand-deal figure dwarfs the rewards figure, that is the correct '
                        .'result, not a bug. Rewards are a bonus; sponsorships are the business.'),
                ]),
                'example' => [
                    'input' => ['monthly_views' => 800000, 'followers' => 45000,
                        'niche' => 'beauty', 'qualified_share' => 40],
                ],
            ],

            [
                'key' => 'utility.metadata-preview',
                'slug' => 'link-preview-debugger',
                'category' => 'previews',
                'name' => 'Link Preview Debugger',
                'tagline' => 'What X, Facebook and LinkedIn will show when you share your link.',
                'description' => 'Reads the Open Graph and Twitter Card tags on any public URL and reports what each '
                    .'platform will do with them, including the fallbacks applied when a tag is missing.',
                'tier' => ToolTier::Free,
                'platforms' => ['x', 'facebook', 'linkedin'],
                'featured' => true,
                'focus_keyword' => 'link preview debugger',
                'seo_title' => 'Link Preview Debugger — OG & Twitter Card Checker (Free, No Login)',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste any public URL. Unlike the platforms’ own debuggers, this needs no '
                        .'login and checks every platform’s tags in one pass.'),
                    Blocks::heading('The one tag worth fixing first', 2),
                    Blocks::paragraph('<code>og:image</code>. A link without one renders as a bare line of text in '
                        .'every feed, and the click-through difference is large enough to see in any analytics '
                        .'tool. Use 1200×630.'),
                    Blocks::callout('info', 'Platforms cache link previews aggressively. After fixing your tags you '
                        .'may still need to clear the cache in Facebook’s or LinkedIn’s own inspector.'),
                ]),
                'example' => [
                    'input' => ['url' => 'https://example.com'],
                ],
            ],

            [
                'key' => 'utility.aspect-ratio-calculator',
                'slug' => 'aspect-ratio-calculator',
                'category' => 'utility',
                'name' => 'Aspect Ratio Calculator',
                'tagline' => 'Resize without squashing, and see which platform slot the result fits.',
                'description' => 'Simplifies any dimensions to a ratio, solves for the missing side when you change '
                    .'one, and names the closest standard social size so you know what the numbers mean.',
                'tier' => ToolTier::Free,
                'platforms' => [],
                'focus_keyword' => 'aspect ratio calculator',
                'seo_title' => 'Aspect Ratio Calculator — Resize Images & Video Without Distortion',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Enter your original dimensions, then fill in either the new width or the new '
                        .'height. The other one is solved for you at the same ratio.'),
                    Blocks::callout('tip', '4:5 is the tallest post Instagram allows in the feed and takes up the '
                        .'most screen. If you export one ratio for the feed, export that one.'),
                ]),
                'example' => [
                    'input' => ['width' => 1920, 'height' => 1080, 'new_width' => 1280, 'new_height' => 0],
                ],
            ],

            [
                'key' => 'utility.timezone-converter',
                'slug' => 'posting-timezone-converter',
                'category' => 'utility',
                'name' => 'Posting Timezone Converter',
                'tagline' => 'One posting time, shown in every market your audience lives in.',
                'description' => 'Converts a scheduled post time into twelve major markets, flags where it lands in '
                    .'the middle of the night, and applies the daylight-saving rules for that exact date.',
                'tier' => ToolTier::Free,
                'platforms' => [],
                'focus_keyword' => 'posting time zone converter',
                'seo_title' => 'Posting Timezone Converter for Social Media Schedules (Free)',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Enter the date and time you plan to post and your own timezone. Offsets are '
                        .'calculated for that specific date, so daylight saving is already accounted for.'),
                    Blocks::callout('tip', 'If most of your audience is in one market, schedule in <em>their</em> '
                        .'evening, not yours. The convenience of posting at your own lunchtime costs reach.'),
                ]),
                'example' => [
                    'input' => ['datetime' => '2026-09-01 18:30', 'timezone' => 'Europe/London'],
                ],
            ],

            [
                'key' => 'utility.handle-strength',
                'slug' => 'handle-strength-checker',
                'category' => 'utility',
                'name' => 'Handle Strength Checker',
                'tagline' => 'Will this username survive being said out loud and typed from memory?',
                'description' => 'Scores a handle on length against every platform’s cap, how cleanly it dictates, '
                    .'and how memorable it is — the three things that decide whether people find the right account.',
                'tier' => ToolTier::Free,
                'platforms' => ['instagram', 'tiktok', 'x', 'youtube', 'linkedin'],
                'focus_keyword' => 'username checker',
                'seo_title' => 'Handle Strength Checker — Score a Username Before You Commit (Free)',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Enter the handle you are considering. X’s 15-character cap is the binding '
                        .'constraint: go past it and you are forced into a different handle there, which makes '
                        .'every piece of cross-promotion harder for the rest of the account’s life.'),
                    Blocks::callout('warning', 'This scores the handle, not its availability. Check each platform '
                        .'before you commit to one.'),
                ]),
                'example' => [
                    'input' => ['handle' => '@the_bread_lab_99'],
                    'note' => 'A handle with every common problem in it.',
                ],
            ],

            [
                'key' => 'utility.milestone-countdown',
                'slug' => 'follower-milestone-countdown',
                'category' => 'utility',
                'name' => 'Follower Milestone Countdown',
                'tagline' => 'When you hit 10k, 100k and the rest — at your current pace.',
                'description' => 'Projects the date you reach each follower milestone from your average weekly '
                    .'growth, including a custom target of your own.',
                'tier' => ToolTier::Free,
                'platforms' => ['instagram', 'tiktok', 'youtube', 'x'],
                'focus_keyword' => 'follower milestone calculator',
                'seo_title' => 'Follower Milestone Countdown — When Will You Hit 10k? (Free)',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Enter your current followers and your average weekly gain over the last '
                        .'month — not your best week, which will flatter you into a date that never arrives.'),
                    Blocks::callout('info', 'This is a straight-line projection on purpose. Fitting a growth curve '
                        .'to two numbers produces confident nonsense.'),
                ]),
                'example' => [
                    'input' => ['current' => 8400, 'weekly_growth' => 180, 'target' => 0],
                ],
            ],

            [
                'key' => 'media.safe-zone-guide',
                'slug' => 'safe-zone-guide',
                'category' => 'previews',
                'name' => 'Safe Zone Guide',
                'tagline' => 'Where each app’s buttons will cover your frame, in pixels.',
                'description' => 'The exact margins to keep clear on TikTok, Reels, Stories, Shorts and YouTube '
                    .'thumbnails, with the usable safe area for each at the canvas size you design on.',
                'tier' => ToolTier::Free,
                'platforms' => ['tiktok', 'instagram', 'youtube', 'facebook'],
                'focus_keyword' => 'safe zone guide',
                'seo_title' => 'Social Video Safe Zones — TikTok, Reels, Shorts & Stories Margins',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Pick the surface you are exporting for, or leave it on “all” to compare. '
                        .'Margins are given in pixels on the standard 1080×1920 canvas.'),
                    Blocks::heading('The right rail is the trap', 2),
                    Blocks::paragraph('TikTok’s avatar, like, comment and sound column claims around 260 pixels on '
                        .'the right. Text centred on the canvas sits underneath it, which is why so much reposted '
                        .'Reels content is unreadable on TikTok.'),
                ]),
                'example' => [
                    'input' => ['surface' => 'tiktok'],
                ],
            ],
            [
                'key' => 'threads.post-preview',
                'slug' => 'threads-post-preview',
                'category' => 'previews',
                'name' => 'Threads Post Preview',
                'tagline' => 'Your Threads post as the feed draws it, collapse and all.',
                'description' => 'Renders a Threads post at feed size, shows what survives before the feed '
                    .'collapses it behind “more”, counts against the 500-character limit and splits an over-long '
                    .'post into a chain on sentence boundaries.',
                'tier' => ToolTier::Free,
                'platforms' => ['threads'],
                'focus_keyword' => 'threads character counter',
                'seo_title' => 'Threads Post Preview & Character Counter — See the Feed View (Free)',
                'seo_description' => 'Preview a Threads post exactly as the feed renders it, check it against the '
                    .'500-character limit and split long posts into a chain. Free, no account needed.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste the post. Threads allows 500 characters, but the feed collapses '
                        .'anything much past four lines behind “more” — so the first four lines are the post as '
                        .'far as most people are concerned.'),
                    Blocks::heading('Over 500 characters', 2),
                    Blocks::paragraph('Threads will not publish it as one post. The preview breaks it into a chain '
                        .'on sentence boundaries so you can see where the seams land before you write them.'),
                    Blocks::callout('tip', 'A post that opens with the conclusion outperforms one that builds to '
                        .'it, because the collapse hides the build-up and shows the opening.'),
                ]),
                'example' => [
                    'input' => ['text' => "Everyone told me to post daily.\n\nI did it for 90 days and the only "
                        ."thing that moved was my sleep schedule.\n\nWhat actually worked was posting three times "
                        .'a week and replying to everyone.', 'handle' => 'yourhandle', 'attachment' => 'none'],
                ],
                'faq' => [
                    ['question' => 'How many characters is a Threads post?',
                        'answer' => 'Five hundred. Line breaks and emoji each count as one character.'],
                    ['question' => 'Why is my post collapsed in the feed?',
                        'answer' => 'Threads collapses tall posts behind “more”. The cut follows height rather '
                            .'than an exact character count, so treat roughly four lines as the visible part.'],
                ],
            ],

            [
                'key' => 'threads.bio-preview',
                'slug' => 'threads-bio-preview',
                'category' => 'previews',
                'name' => 'Threads Bio Preview',
                'tagline' => 'Your Threads profile as someone sees it after one reply.',
                'description' => 'Draws your Threads profile header — name, username, bio and link — counts the '
                    .'bio against the 150-character limit including line breaks, and flags a header tall enough to '
                    .'push your posts off the first screen.',
                'tier' => ToolTier::Free,
                'platforms' => ['threads'],
                'focus_keyword' => 'threads bio ideas',
                'seo_title' => 'Threads Bio Preview & Character Counter (Free)',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste the bio exactly as you plan to save it, line breaks included — each '
                        .'one costs a character against the 150-character limit.'),
                    Blocks::heading('Threads bios are read in a different context', 2),
                    Blocks::paragraph('Almost nobody arrives at a Threads profile from search. They arrive from a '
                        .'single reply in someone else’s conversation, having seen one sentence you wrote. The bio '
                        .'has to work for a reader with no other context.'),
                ]),
                'example' => [
                    'input' => ['name' => 'Sam · Sourdough', 'handle' => 'thebreadlab',
                        'bio' => "Real bread, no mystique.\nWeekly starter tips 🍞",
                        'link' => 'https://example.com/guide', 'followers' => 4200],
                ],
            ],

            [
                'key' => 'pinterest.pin-preview',
                'slug' => 'pinterest-pin-preview',
                'category' => 'previews',
                'name' => 'Pinterest Pin Preview',
                'tagline' => 'Your Pin in the feed tile and the closeup — they show different things.',
                'description' => 'Renders a Pin at both sizes Pinterest uses: the cropped masonry tile, where only '
                    .'the start of the title survives and the description is invisible, and the closeup, where the '
                    .'description finally appears behind “more”.',
                'tier' => ToolTier::Free,
                'platforms' => ['pinterest'],
                'focus_keyword' => 'pinterest pin preview',
                'seo_title' => 'Pinterest Pin Preview — Feed Tile & Closeup Mockup (Free)',
                'seo_description' => 'See a Pin exactly as Pinterest renders it in the home feed and on the '
                    .'closeup, with title and description limits checked. Free, no account needed.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Enter the title, description and image ratio. The two frames are the two '
                        .'places a Pin is seen, and they are not the same: the feed tile is a cropped image with a '
                        .'clipped title, and the description does not appear on it at all.'),
                    Blocks::heading('2:3 is the only ratio that never crops', 2),
                    Blocks::paragraph('A 1000 × 1500 Pin renders in full everywhere. Taller Pins are cut in the '
                        .'feed, and landscape Pins take so little vertical space that they are scrolled past.'),
                    Blocks::callout('tip', 'Write the title for the tile and the description for search. They have '
                        .'different jobs, and using the same sentence twice wastes both.'),
                ]),
                'example' => [
                    'input' => ['title' => 'Sourdough starter schedule for beginners',
                        'description' => 'A seven-day feeding schedule for a new sourdough starter, with what to '
                            .'look for each day and what to do when it stalls.',
                        'aspect' => '2:3', 'link' => 'https://example.com/starter', 'board' => 'Sourdough basics'],
                ],
                'faq' => [
                    ['question' => 'How long can a Pin title be?',
                        'answer' => 'A hundred characters, but the home feed tile shows roughly the first forty. '
                            .'Front-load the words that matter.'],
                    ['question' => 'Does the description show in the feed?',
                        'answer' => 'No. It appears on the Pin closeup, and Pinterest indexes it for search — '
                            .'which is why it is worth writing even though the feed hides it.'],
                ],
            ],

            [
                'key' => 'pinterest.pin-seo-checker',
                'slug' => 'pinterest-pin-seo-checker',
                'category' => 'previews',
                'name' => 'Pinterest Pin SEO Checker',
                'tagline' => 'Scores a Pin the way a search engine reads it, because that is what Pinterest is.',
                'description' => 'Checks your keyword against the four fields Pinterest indexes — title, '
                    .'description, board name and destination link — and returns a weighted score with the fixes '
                    .'that move it most, highest impact first.',
                'tier' => ToolTier::Free,
                'platforms' => ['pinterest'],
                'focus_keyword' => 'pinterest seo',
                'seo_title' => 'Pinterest Pin SEO Checker — Score Your Title, Description & Board (Free)',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Enter the search you want the Pin to rank for, then the Pin as you have '
                        .'written it. The score weights the title most heavily, then the description, the '
                        .'destination link, and the board it is saved to.'),
                    Blocks::heading('Hashtags are not the answer here', 2),
                    Blocks::paragraph('Pinterest reads the description as prose, not as tags. Three or four '
                        .'hashtags are harmless; a wall of them displaces the sentences that actually rank.'),
                ]),
                'example' => [
                    'input' => ['keyword' => 'sourdough starter schedule',
                        'title' => 'Sourdough starter schedule for beginners',
                        'description' => 'A simple seven-day sourdough starter schedule for beginners, including '
                            .'what your starter should look like each day and how to rescue one that has stalled.',
                        'board' => 'Sourdough basics', 'link' => 'https://example.com/starter'],
                ],
            ],
            [
                'key' => 'media.image-resizer',
                'slug' => 'social-image-resizer',
                'category' => 'media',
                'name' => 'Social Image Resizer',
                'tagline' => 'One image, cropped to every size the networks actually use.',
                'description' => 'Paste an image URL and get it back at all thirteen sizes the major networks '
                    .'publish at — feed posts, stories, thumbnails, headers and covers — each a true cover crop '
                    .'around the part of the frame you tell it to keep.',
                'tier' => ToolTier::Free,
                'platforms' => ['instagram', 'facebook', 'x', 'linkedin', 'youtube', 'pinterest', 'tiktok'],
                'featured' => true,
                'focus_keyword' => 'social media image sizes',
                'seo_title' => 'Social Media Image Resizer — Every Platform Size From One Image (Free)',
                'seo_description' => 'Resize one image to Instagram, Facebook, X, LinkedIn, YouTube, Pinterest '
                    .'and TikTok dimensions at once. Exact current sizes, free, no account needed.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste a public link to your image, choose which network you are posting '
                        .'to, and pick the part of the frame that must survive. Every output is a cover crop at '
                        .'the exact pixel size that surface uses — not a scaled-down approximation.'),
                    Blocks::heading('Why the focal point matters', 2),
                    Blocks::paragraph('A square crop of a 16:9 photo throws away two thirds of it. Something is '
                        .'going to be lost; the only question is whether you chose what. Set “keep in frame” to '
                        .'<strong>top</strong> for portraits so faces survive the square and story crops.'),
                    Blocks::callout('tip', 'Start from an image at least 2048&nbsp;px wide. Anything smaller has '
                        .'to be enlarged for the YouTube banner and channel art, and enlarged pixels look soft '
                        .'on exactly the surfaces people judge you on.'),
                ]),
                'example' => [
                    'input' => ['image_url' => '', 'platform' => 'instagram', 'focus' => 'center', 'format' => 'jpeg'],
                    'note' => 'Leave the URL empty and the tool crops a generated sample so you can see the sizes first.',
                ],
                'faq' => [
                    ['question' => 'Can I upload a file instead of a link?',
                        'answer' => 'Not yet — the tool works from a public image URL. Any link that opens the '
                            .'image directly in a browser works, including one from your own site or CDN.'],
                    ['question' => 'Which format should I export?',
                        'answer' => 'JPEG for photographs, PNG when you need hard edges or transparency, WebP '
                            .'when you control the destination and want the smallest file.'],
                ],
            ],

            [
                'key' => 'media.image-compressor',
                'slug' => 'image-compressor',
                'category' => 'media',
                'name' => 'Image Compressor',
                'tagline' => 'See the saving and the damage side by side, then pick.',
                'description' => 'Re-encodes your image at four quality levels in one pass so you can compare '
                    .'the file size against the visible quality, rather than trusting a single number.',
                'tier' => ToolTier::Free,
                'platforms' => [],
                'focus_keyword' => 'compress image online',
                'seo_title' => 'Image Compressor — Compare Quality Levels Before You Download (Free)',
                'seo_description' => 'Compress JPEG and WebP images at four quality levels at once and compare '
                    .'the saving against the visible damage. Free, no sign-up.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste an image URL, choose a format and a maximum width, and compare the '
                        .'four results. Look at the images, not just the kilobytes: the point is to find the '
                        .'setting where the file gets small and the picture still looks like itself.'),
                    Blocks::heading('Resize first, compress second', 2),
                    Blocks::paragraph('Most of the saving is in the resize. A 4000&nbsp;px photograph displayed '
                        .'at 1080&nbsp;px is carrying four times the pixels it needs, and no amount of quality '
                        .'tuning fixes that. Set the maximum width to the size it will actually be displayed at.'),
                    Blocks::callout('warning', 'Always compress from the original export. Re-compressing a JPEG '
                        .'that has already been compressed loses quality without saving much.'),
                ]),
                'example' => [
                    'input' => ['image_url' => '', 'format' => 'webp', 'max_width' => 1600],
                ],
                'faq' => [
                    ['question' => 'Is WebP safe to use?',
                        'answer' => 'Yes. Every current browser reads it, and it is typically 25–35% smaller '
                            .'than JPEG at the same visual quality. Keep a JPEG copy for anything you email.'],
                    ['question' => 'What quality should I pick?',
                        'answer' => 'Eighty is right for almost everything. Go higher only for images with '
                            .'large flat areas or fine text, where compression artefacts show first.'],
                ],
            ],

            [
                'key' => 'media.image-format-converter',
                'slug' => 'image-format-converter',
                'category' => 'media',
                'name' => 'Image Format Converter',
                'tagline' => 'PNG, JPEG, WebP and GIF — with the transparency trap called out.',
                'description' => 'Converts between the four formats the web runs on and tells you when the '
                    .'conversion is about to cost you something — most often the transparent background a logo '
                    .'loses the moment it becomes a JPEG.',
                'tier' => ToolTier::Free,
                'platforms' => [],
                'focus_keyword' => 'image format converter',
                'seo_title' => 'Image Format Converter — PNG, JPEG, WebP & GIF Online (Free)',
                'seo_description' => 'Convert images between PNG, JPEG, WebP and GIF. Warns you before a '
                    .'conversion loses transparency or bands your colours. Free, no account.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste an image URL, choose the format you want, and download the result. '
                        .'Quality applies to JPEG and WebP; PNG and GIF are lossless and ignore it.'),
                    Blocks::heading('Which format when', 2),
                    Blocks::list([
                        '<strong>WebP</strong> — the default for the web. Smallest files, keeps transparency.',
                        '<strong>PNG</strong> — logos, screenshots, anything with hard edges or transparency.',
                        '<strong>JPEG</strong> — photographs, and anywhere that still refuses WebP.',
                        '<strong>GIF</strong> — flat graphics only. 256 colours means photographs band visibly.',
                    ]),
                    Blocks::callout('warning', 'JPEG has no alpha channel. Converting a transparent PNG to JPEG '
                        .'fills the background with white, permanently.'),
                ]),
                'example' => [
                    'input' => ['image_url' => '', 'format' => 'webp', 'quality' => 88],
                ],
                'faq' => [
                    ['question' => 'Why is AVIF not offered?',
                        'answer' => 'The image library on our servers is not built with AVIF support. WebP is '
                            .'close enough in size and works in more places.'],
                    ['question' => 'Does converting a GIF keep the animation?',
                        'answer' => 'No — only the first frame is converted. Animation needs a video tool.'],
                ],
            ],

            [
                'key' => 'media.color-palette-extractor',
                'slug' => 'color-palette-extractor',
                'category' => 'media',
                'name' => 'Color Palette Extractor',
                'tagline' => 'The colours already in your work, as hex you can paste.',
                'description' => 'Pulls the dominant colours out of any image and reports each one with its '
                    .'share of the frame and the contrast ratio of white and black text on top of it.',
                'tier' => ToolTier::Free,
                'platforms' => ['instagram', 'pinterest', 'youtube'],
                'focus_keyword' => 'color palette from image',
                'seo_title' => 'Color Palette Extractor — Get Hex Colours From Any Image (Free)',
                'seo_description' => 'Extract a brand palette from an image with hex codes, RGB values and '
                    .'WCAG contrast ratios for text. Free, no sign-up needed.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste an image URL and choose how many colours you want. The results are '
                        .'ordered by how much of the image each one covers, so the first row is the colour a '
                        .'viewer will actually register.'),
                    Blocks::heading('A palette you cannot put text on is a palette you will abandon', 2),
                    Blocks::paragraph('Every swatch carries the contrast ratio of white and black text against '
                        .'it. A colour marked <strong>background only</strong> falls under 4.5:1 with both, so '
                        .'a thumbnail caption on it will be unreadable at feed size.'),
                    Blocks::callout('tip', 'Extract from your three best-performing posts rather than from a '
                        .'logo. The palette people associate with you is the one they have actually seen.'),
                ]),
                'example' => [
                    'input' => ['image_url' => '', 'colors' => 6],
                ],
                'faq' => [
                    ['question' => 'Why are the colours not exactly the ones in my image?',
                        'answer' => 'Similar shades are grouped and averaged. A photograph holds tens of '
                            .'thousands of distinct values, almost none of which repeat — counting them raw '
                            .'returns noise rather than a palette.'],
                ],
            ],

            [
                'key' => 'instagram.carousel-splitter',
                'slug' => 'carousel-splitter',
                'category' => 'media',
                'name' => 'Carousel Splitter',
                'tagline' => 'One wide image, sliced into panels that line up when you swipe.',
                'description' => 'Cuts a panorama into 2–10 carousel panels at exact integer boundaries, so the '
                    .'seams meet in the app instead of showing a few duplicated or missing pixels at every join.',
                'tier' => ToolTier::Free,
                'platforms' => ['instagram', 'linkedin'],
                'featured' => true,
                'focus_keyword' => 'instagram carousel splitter',
                'seo_title' => 'Instagram Carousel Splitter — Seamless Swipe Panels From One Image (Free)',
                'seo_description' => 'Split a wide image into seamless Instagram carousel slides at 4:5, 1:1 or '
                    .'9:16. Exact cuts, correct order, free and no account needed.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste a link to your wide image, choose how many panels you want, and '
                        .'pick a panel shape. Download the slides and upload them <strong>in order</strong> — '
                        .'Instagram does not reorder a carousel for you, and it cannot be fixed after posting.'),
                    Blocks::heading('Export the source at the right width', 2),
                    Blocks::paragraph('Three 4:5 panels need a 3240 × 1350 source; four need 4320 × 1350. If '
                        .'your image is a different shape the panels get stretched to fit, which is visible on '
                        .'straight lines and faces.'),
                    Blocks::callout('tip', '4:5 is the default because it takes the most vertical space in the '
                        .'feed, and vertical space is attention.'),
                ]),
                'example' => [
                    'input' => ['image_url' => '', 'panels' => 3, 'ratio' => '4:5'],
                ],
                'faq' => [
                    ['question' => 'How many slides can a carousel hold?',
                        'answer' => 'Ten. Beyond that you need a second post — or a video.'],
                    ['question' => 'Will the seams really be invisible?',
                        'answer' => 'The cuts are exact, so yes, provided your source is at the right aspect '
                            .'ratio and you upload the slides in order without editing them in between.'],
                ],
            ],

            [
                'key' => 'instagram.reels-cover-cropper',
                'slug' => 'reels-cover-cropper',
                'category' => 'media',
                'name' => 'Reels Cover Cropper',
                'tagline' => 'One cover that works on the Reels tab and on your grid.',
                'description' => 'Instagram uses a single 9:16 cover in two places and crops the middle out of '
                    .'it for the profile grid. This exports both crops from your image so you can see what the '
                    .'grid tile keeps before the cover is locked in.',
                'tier' => ToolTier::Free,
                'platforms' => ['instagram'],
                'focus_keyword' => 'reels cover size',
                'seo_title' => 'Instagram Reels Cover Cropper — Grid Tile & Full Cover (Free)',
                'seo_description' => 'Crop one Reel cover for both the Reels tab and the profile grid tile. '
                    .'See what the grid crop cuts before you publish. Free, no account.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste a link to your cover frame and choose which band of it the square '
                        .'grid tile should keep. You get the full 9:16 cover, the 4:5 grid tile and a 1:1 crop.'),
                    Blocks::heading('The grid is where people browse', 2),
                    Blocks::paragraph('A cover designed for the full 9:16 frame loses its top and bottom on the '
                        .'profile — which is exactly where most people put the text. Keep every word inside the '
                        .'middle 1080 × 1350 of the frame and both crops survive.'),
                    Blocks::callout('info', 'Instagram also draws its own caption and audio row over the bottom '
                        .'fifth of the Reels view. The safe-zone guide has those margins.'),
                ]),
                'example' => [
                    'input' => ['image_url' => '', 'grid_focus' => 'center'],
                ],
                'faq' => [
                    ['question' => 'Can I change a Reel cover after posting?',
                        'answer' => 'You can edit the cover, but the grid crop is recalculated from it — so '
                            .'the cover still has to be designed for both places.'],
                ],
            ],

            [
                'key' => 'instagram.story-sizer',
                'slug' => 'story-templates-sizer',
                'category' => 'media',
                'name' => 'Story Safe-Zone Sizer',
                'tagline' => 'A 1080 × 1920 export, plus proof of what the app covers.',
                'description' => 'Exports your artwork at story size twice: the clean file you upload, and the '
                    .'same frame with the progress bar, profile row and reply box shaded over it — so the parts '
                    .'you cannot use are visible rather than remembered.',
                'tier' => ToolTier::Free,
                'platforms' => ['instagram', 'facebook', 'tiktok', 'youtube'],
                'focus_keyword' => 'instagram story size',
                'seo_title' => 'Story Safe-Zone Sizer — 1080×1920 Export With Overlay Check (Free)',
                'seo_description' => 'Export a story at 1080 × 1920 and see exactly which margins Instagram, '
                    .'TikTok and Shorts cover with their own UI. Free, no sign-up.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste an image URL, choose the surface you are designing for and which '
                        .'part of a wider image to keep. Upload the clean export; use the overlay to check it.'),
                    Blocks::heading('The bottom intrusion is taller than it feels', 2),
                    Blocks::paragraph('TikTok covers roughly 500&nbsp;px at the bottom of a 1920&nbsp;px frame, '
                        .'Reels around 420, Shorts around 380. Text placed “near the bottom” on a desktop canvas '
                        .'lands underneath a row of buttons on a phone.'),
                    Blocks::callout('tip', 'Design once inside the tightest safe area — TikTok’s — and the same '
                        .'artwork works on every vertical surface without a re-export.'),
                ]),
                'example' => [
                    'input' => ['image_url' => '', 'surface' => 'instagram_story', 'focus' => 'center'],
                ],
                'faq' => [
                    ['question' => 'Do these margins change?',
                        'answer' => 'Yes, whenever a platform redesigns. Leave a little more room than the '
                            .'minimum on anything you cannot re-export later.'],
                ],
            ],

            [
                'key' => 'pinterest.pin-image-sizer',
                'slug' => 'pin-image-sizer',
                'category' => 'media',
                'name' => 'Pin Image Sizer',
                'tagline' => 'One image at the three shapes Pinterest distributes.',
                'description' => 'Exports your artwork as a 2:3 standard Pin, a 1:1 board cover and a 9:16 Idea '
                    .'Pin, cropped around the part of the frame you need to keep — usually the text.',
                'tier' => ToolTier::Free,
                'platforms' => ['pinterest'],
                'focus_keyword' => 'pinterest image size',
                'seo_title' => 'Pinterest Pin Image Sizer — 2:3, 1:1 and 9:16 Exports (Free)',
                'seo_description' => 'Resize one image to Pinterest’s standard Pin, square and Idea Pin '
                    .'dimensions at once. Correct sizes, free, no account needed.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste a link to your Pin artwork and choose which band of it every crop '
                        .'must keep. The 2:3 export is the one to Pin unless you have a specific reason not to.'),
                    Blocks::heading('2:3 is the only ratio that is never cropped', 2),
                    Blocks::paragraph('A 1000 × 1500 Pin renders in full everywhere on Pinterest. Taller Pins '
                        .'get cut in the feed, and landscape Pins take so little vertical space that they are '
                        .'scrolled straight past.'),
                    Blocks::callout('tip', 'Feed tiles are about 236&nbsp;px wide. If your text is not legible '
                        .'at thumbnail size, it is not legible.'),
                ]),
                'example' => [
                    'input' => ['image_url' => '', 'focus' => 'center'],
                ],
                'faq' => [
                    ['question' => 'What resolution should I start from?',
                        'answer' => 'At least 1000 × 1500. Pinterest displays smaller than that, but a larger '
                            .'source survives the 9:16 Idea Pin crop without softening.'],
                ],
            ],

            [
                'key' => 'x.tweet-screenshot',
                'slug' => 'tweet-screenshot-generator',
                'category' => 'media',
                'name' => 'Post Screenshot Generator',
                'tagline' => 'A clean card of a post, drawn instead of screenshotted.',
                'description' => 'Draws an X-style post card as SVG — sharp at any size, no phone chrome to '
                    .'crop — in the light, dim and dark themes, with links, mentions and hashtags coloured the '
                    .'way X colours them.',
                'tier' => ToolTier::Free,
                'platforms' => ['x'],
                'focus_keyword' => 'tweet screenshot generator',
                'seo_title' => 'Tweet Screenshot Generator — Clean Post Cards in Any Theme (Free)',
                'seo_description' => 'Generate a clean, sharp image of an X post for slides, threads and '
                    .'newsletters. Light, dim and dark themes. Free, no account needed.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Type the name, handle and post text, pick a theme, and download the '
                        .'card. It is an SVG, so it stays sharp in a slide deck, a blog post or a print layout.'),
                    Blocks::heading('This is a mock-up, not evidence', 2),
                    Blocks::paragraph('The card draws whatever you type into it, which means a card proves '
                        .'nothing about who said what. It deliberately draws no verification badge. Use it to '
                        .'illustrate your own posts and quotes you have permission to reproduce — never to '
                        .'present invented text as something a real person published.'),
                    Blocks::callout('tip', 'Leave the counts at zero for a card that will still look right in '
                        .'six months. Engagement numbers date a slide faster than anything else on it.'),
                ]),
                'example' => [
                    'input' => ['name' => 'Ada Lovelace', 'handle' => 'ada',
                        'text' => 'The Analytical Engine has no pretensions whatever to originate anything. '
                            ."It can do whatever we know how to order it to perform.\n\nStill true.",
                        'theme' => 'light', 'timestamp' => '2:14 PM · Jul 10, 2025',
                        'replies' => 128, 'reposts' => 940, 'likes' => 12400],
                ],
                'faq' => [
                    ['question' => 'Can I use it for a real post?',
                        'answer' => 'Yes — retype the post exactly and credit the author. That is the normal '
                            .'use. What the tool will not help with is passing off text nobody wrote.'],
                    ['question' => 'Why SVG rather than PNG?',
                        'answer' => 'It scales without blurring and weighs a few kilobytes. Any design tool, '
                            .'browser or slide app will open it, and can export a PNG if you need one.'],
                ],
            ],

            [
                'key' => 'media.qr-code-generator',
                'slug' => 'qr-code-generator',
                'category' => 'media',
                'name' => 'QR Code Generator',
                'tagline' => 'Your colours, and an error-correction level that survives being printed.',
                'description' => 'Generates a scannable SVG QR code with your own foreground and background '
                    .'colours, and checks the two things that actually break codes in the wild: not enough '
                    .'contrast, and an error-correction level too low for the surface it is going on.',
                'tier' => ToolTier::Free,
                'platforms' => [],
                'focus_keyword' => 'qr code generator',
                'seo_title' => 'QR Code Generator — Custom Colours, Print-Safe SVG (Free)',
                'seo_description' => 'Create a branded QR code as a scalable SVG, with a contrast check and '
                    .'the right error-correction level for print. Free, no sign-up, no tracking redirect.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste the link or text the code should carry, set your colours, and '
                        .'download the SVG. The code points straight at your URL — there is no redirect '
                        .'through us, so it cannot stop working if we do.'),
                    Blocks::heading('Error correction is the setting that matters', 2),
                    Blocks::list([
                        '<strong>L</strong> — about 7% damage tolerated. Screens only.',
                        '<strong>M</strong> — about 15%. Fine for a slide or a web page.',
                        '<strong>Q</strong> — about 25%. The default here, and the right choice for print.',
                        '<strong>H</strong> — about 30%. Use it if a logo will sit over the code.',
                    ]),
                    Blocks::callout('warning', 'Dark on light, always. Cameras look for a dark pattern on a '
                        .'light field, and an inverted code fails on a large share of phones.'),
                ]),
                'example' => [
                    'input' => ['content' => 'https://metacreator.dev', 'error_correction' => 'Q',
                        'foreground' => '#101828', 'background' => '#FFFFFF', 'size' => 600],
                ],
                'faq' => [
                    ['question' => 'Does the code expire?',
                        'answer' => 'No. It encodes your URL directly rather than routing through a shortener, '
                            .'so nothing on our side can break it later.'],
                    ['question' => 'How big should I print it?',
                        'answer' => 'A rough rule: the code should be at least a tenth of the distance people '
                            .'scan from. Two metres away means a 20&nbsp;cm code.'],
                ],
            ],

            [
                'key' => 'pinterest.rich-pin-validator',
                'slug' => 'rich-pin-validator',
                'category' => 'previews',
                'name' => 'Rich Pin Validator',
                'tagline' => 'Which tags your page is missing, not just whether it failed.',
                'description' => 'Reads the Open Graph markup on any page the way Pinterest does and reports '
                    .'every required and optional tag for Article and Product Rich Pins — present, empty or '
                    .'missing — so a rejection becomes a list of things to add.',
                'tier' => ToolTier::Free,
                'platforms' => ['pinterest'],
                'focus_keyword' => 'rich pin validator',
                'seo_title' => 'Rich Pin Validator — Check Article & Product Markup Free',
                'seo_description' => 'Validate the Open Graph tags a Pinterest Rich Pin needs before you apply. '
                    .'Shows every missing tag and why it matters. Free, no account.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste the URL a Pin will link to and choose the Pin type. The score '
                        .'weights the required tags at four fifths — they are pass or fail — and the optional '
                        .'ones at one fifth, because they only polish a Pin that already validates.'),
                    Blocks::heading('og:type is the tag that fails most often', 2),
                    Blocks::paragraph('It has to say <code>article</code> or <code>product</code>, not '
                        .'<code>website</code>. Most themes ship <code>website</code> on every page, and '
                        .'Pinterest then treats a fully marked-up post as an ordinary link.'),
                    Blocks::callout('info', 'Rich Pins are enabled per domain, not per page. Validate a page '
                        .'that is typical of your markup — Pinterest applies the result across the whole site.'),
                ]),
                'example' => [
                    'input' => ['url' => 'https://example.com/blog/sourdough-starter', 'type' => 'article'],
                ],
                'faq' => [
                    ['question' => 'What about Recipe Rich Pins?',
                        'answer' => 'Recipes need a full schema.org Recipe graph rather than Open Graph tags, '
                            .'which is a different check. This tool covers Article and Product.'],
                    ['question' => 'I added the tags — how long until Pinterest sees them?',
                        'answer' => 'Apply once through Pinterest’s own validator after the tags are live. '
                            .'Approval is usually same-day, and it applies to the whole domain.'],
                ],
            ],

            [
                'key' => 'utility.username-availability',
                'slug' => 'username-availability-checker',
                'category' => 'utility',
                'name' => 'Username Availability Checker',
                'tagline' => 'One handle, checked against every network’s rules at once.',
                'description' => 'Checks a handle against the length and character rules of eight networks, '
                    .'then requests the public profile page where that is possible — and says plainly which '
                    .'platforms block automated checks rather than guessing on your behalf.',
                'tier' => ToolTier::Free,
                'platforms' => ['instagram', 'tiktok', 'x', 'youtube', 'pinterest', 'threads'],
                'featured' => true,
                'focus_keyword' => 'username availability checker',
                'seo_title' => 'Username Availability Checker — All Networks at Once (Free)',
                'seo_description' => 'Check one handle against Instagram, TikTok, X, YouTube, Pinterest, '
                    .'Threads, GitHub and Twitch rules and profiles. Free, no account needed.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Type the handle without the @. Every network here is case-insensitive, '
                        .'so case is ignored. Each row shows two separate things: whether the handle is even '
                        .'legal on that platform, and whether anybody is currently using it.'),
                    Blocks::heading('Why some rows say “check manually”', 2),
                    Blocks::paragraph('Instagram, TikTok, X and Threads answer automated requests with a login '
                        .'wall, so the honest answer is that we cannot see. Those rows link straight to the '
                        .'profile — one click tells you. A tool that guessed here would eventually tell you a '
                        .'taken handle was free, which is worse than admitting the limit.'),
                    Blocks::callout('warning', 'A handle nobody holds is not automatically yours to keep. '
                        .'Trademarked and impersonating names get reclaimed after the fact, however cleanly '
                        .'they register today.'),
                ]),
                'example' => [
                    'input' => ['handle' => 'metacreator'],
                ],
                'faq' => [
                    ['question' => 'Why is the handle invalid on X but fine on Instagram?',
                        'answer' => 'X requires 4–15 characters and allows only letters, numbers and '
                            .'underscores. Instagram allows periods and up to 30 characters. The rules genuinely '
                            .'differ per network, which is why the same name is not always available as-is.'],
                    ['question' => 'Should I register the same handle everywhere?',
                        'answer' => 'Yes, even on networks you do not use yet. Consistency is worth more than '
                            .'the perfect name, and reclaiming a handle somebody else took is close to '
                            .'impossible.'],
                ],
            ],

            [
                'key' => 'youtube.metadata-viewer',
                'slug' => 'youtube-metadata-viewer',
                'category' => 'utility',
                'name' => 'YouTube Metadata Viewer',
                'tagline' => 'Every field a YouTube video declares about itself, in one table.',
                'description' => 'Reads the public metadata on any YouTube watch page — exact publish '
                    .'timestamp, duration, category, view count, tags, availability and the settings that '
                    .'govern where the video can appear — and lays it out as one readable table.',
                'tier' => ToolTier::Free,
                'platforms' => ['youtube'],
                'featured' => true,
                'focus_keyword' => 'youtube metadata viewer',
                'seo_title' => 'YouTube Metadata Viewer — See Any Video’s Full Metadata (Free)',
                'seo_description' => 'View the title, tags, category, exact upload time, duration, view '
                    .'count and visibility settings of any public YouTube video. Free, no account, no API key.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste any YouTube link. The tool reads the page’s own published '
                        .'metadata — the same tags Google, Facebook and every embed reads — and shows the '
                        .'fields grouped by what they describe.'),
                    Blocks::heading('The fields worth knowing about', 2),
                    Blocks::list([
                        '<strong>Published vs. uploaded</strong> — these differ on scheduled videos, and the '
                            .'gap tells you whether an upload was planned or reactive.',
                        '<strong>Available countries</strong> — anything short of the full list means the '
                            .'video is blocked somewhere, usually for music licensing.',
                        '<strong>Made for kids</strong> — turns off comments, notifications and personalised '
                            .'ads, which is a much larger decision than the checkbox suggests.',
                        '<strong>Tags</strong> — most uploads no longer set any, and that is fine. They carry '
                            .'very little ranking weight now.',
                    ]),
                    Blocks::callout('info', 'Nothing here needs an API key, because none of it is private. '
                        .'It is the metadata YouTube publishes to every crawler that asks.'),
                ]),
                'example' => [
                    'input' => ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
                ],
                'faq' => [
                    ['question' => 'Why can’t I see likes or comment counts?',
                        'answer' => 'YouTube stopped publishing those in page metadata. Reading them needs '
                            .'the Data API and an API key, which this tool deliberately does not require.'],
                    ['question' => 'It says some fields are missing.',
                        'answer' => 'That happens when a video is unlisted, private or region-blocked — '
                            .'YouTube’s public oEmbed endpoint refuses to describe it, and the warning says so.'],
                ],
            ],

            [
                'key' => 'youtube.shadowban-detector',
                'slug' => 'youtube-shadowban-detector',
                'category' => 'analytics',
                'name' => 'YouTube Shadowban Detector',
                'tagline' => 'Find out whether a setting is suppressing your video — or whether it just underperformed.',
                'description' => 'YouTube has no shadowban, but it has five settings that quietly remove a '
                    .'video from search, recommendations, embeds and the subscriber feed. This checks every '
                    .'one of them on a public video and tells you which is costing you reach.',
                'tier' => ToolTier::Free,
                'platforms' => ['youtube'],
                'featured' => true,
                'focus_keyword' => 'youtube shadowban checker',
                'seo_title' => 'YouTube Shadowban Detector — Check If Your Video Is Restricted (Free)',
                'seo_description' => 'Check whether a YouTube video is unlisted, age-restricted, marked for '
                    .'kids, blocked from embedding or missing from your public feed. Free, instant, no login.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste the video that is not getting the reach you expected. The tool '
                        .'checks five public visibility signals and scores them by how much reach each one '
                        .'costs when it is set wrong.'),
                    Blocks::heading('What "shadowban" actually means on YouTube', 2),
                    Blocks::paragraph('It does not mean anything — YouTube has no such feature. What it does '
                        .'have is <em>restriction</em>: an age gate, a made-for-kids flag, an unlisted '
                        .'setting, embedding turned off. Every one of those is publicly visible, which is why '
                        .'a tool can check them and why nobody has to guess.'),
                    Blocks::heading('A clean result is still useful', 2),
                    Blocks::paragraph('If all five come back clear, the video is not being suppressed — which '
                        .'redirects the question to the thumbnail, the title and the first thirty seconds. '
                        .'That is where the answer almost always is, and it is much harder to accept than a '
                        .'penalty, which is exactly why the myth persists.'),
                    Blocks::callout('warning', 'Restrictions applied to a whole channel, and reach lost to a '
                        .'copyright claim, are visible only in YouTube Studio. This checks what a signed-out '
                        .'viewer can see.'),
                ]),
                'example' => [
                    'input' => ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
                ],
                'faq' => [
                    ['question' => 'My video is clean but still got no views. What now?',
                        'answer' => 'Then distribution was offered and refused. Check the click-through rate '
                            .'and the first-30-second retention in Studio: a low CTR is a packaging problem, '
                            .'and a cliff in the first half minute is an opening problem.'],
                    ['question' => 'Does age restriction really matter that much?',
                        'answer' => 'Yes. It removes the video from signed-out viewing, blocks embedding '
                            .'entirely, excludes it from most recommendation surfaces, and limits ads. It is '
                            .'the single most costly setting on this list.'],
                    ['question' => 'Why does the feed check only apply to recent videos?',
                        'answer' => 'A channel’s public RSS feed holds only the 15 most recent uploads, so an '
                            .'older video being absent means nothing. The check is skipped past 30 days rather '
                            .'than reported as a false alarm.'],
                ],
            ],

            [
                'key' => 'youtube.partner-program-checker',
                'slug' => 'youtube-partner-program-checker',
                'category' => 'analytics',
                'name' => 'YouTube Partner Program Checker',
                'tagline' => 'How far your own channel is from the Partner Program, on both thresholds.',
                'description' => 'Checks a channel against every YouTube Partner Program requirement — the '
                    .'fan funding tier at 500 subscribers and the ad revenue tier at 1,000 — and shows exactly '
                    .'what is missing, including the account requirements that disqualify a channel outright.',
                'tier' => ToolTier::Free,
                'platforms' => ['youtube'],
                'featured' => true,
                'focus_keyword' => 'youtube partner program checker',
                'seo_title' => 'YouTube Partner Program Checker — Am I Eligible for YPP? (Free)',
                'seo_description' => 'Check your channel against every YouTube Partner Program requirement: '
                    .'subscribers, watch hours, Shorts views and the account rules. Free, instant.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Enter your subscriber count, then whichever of watch hours or Shorts '
                        .'views you have. Both come from YouTube Studio → Analytics; the watch hours figure '
                        .'needs the date range set to the last 365 days, and Shorts views to the last 90.'),
                    Blocks::heading('The two thresholds people confuse', 2),
                    Blocks::list([
                        '<strong>Fan funding — 500 subscribers</strong>, 3 public uploads in 90 days, and '
                            .'either 3,000 watch hours or 3 million Shorts views. Unlocks memberships, Super '
                            .'Thanks and Shopping.',
                        '<strong>Ad revenue — 1,000 subscribers</strong> and either 4,000 watch hours or '
                            .'10 million Shorts views. Unlocks ads and the Premium revenue share.',
                    ]),
                    Blocks::heading('Watch hours and Shorts views are alternatives, not a sum', 2),
                    Blocks::paragraph('This is the arithmetic almost everybody gets wrong. 2,000 watch hours '
                        .'and 1.5 million Shorts views is not "halfway to both" — it is halfway on each route '
                        .'separately, and neither one qualifies. Pick the route you are closer to and commit '
                        .'to it; the tool says which that is.'),
                    Blocks::callout('info', 'Nothing you type is sent anywhere or stored against your '
                        .'channel. Watch hours are private analytics only you can see, which is why the tool '
                        .'asks instead of pretending to look them up.'),
                ]),
                'example' => [
                    'input' => [
                        'subscribers' => 740,
                        'watch_hours' => 2100,
                        'shorts_views' => 450000,
                        'uploads_90d' => 6,
                    ],
                ],
                'faq' => [
                    ['question' => 'Do Shorts views count towards the 4,000 watch hours?',
                        'answer' => 'No. Shorts are the separate route, with their own threshold. Watch hours '
                            .'come only from public long-form videos, and they exclude live premieres before '
                            .'they end, unlisted videos and anything you later deleted.'],
                    ['question' => 'I hit the numbers. How long does approval take?',
                        'answer' => 'Usually under a month, though it varies. It is a manual review of the '
                            .'whole channel against the monetization policies, not an automatic switch — '
                            .'meeting the thresholds makes you eligible to apply, not monetized.'],
                    ['question' => 'Can I tell whether somebody else’s channel is monetized?',
                        'answer' => 'Only partly, and not with this tool — this one measures your own '
                            .'channel against the rules using figures only you can see. The <a '
                            .'href="/tools/youtube-channel-monetization-checker">YouTube Channel '
                            .'Monetization Checker</a> reads any channel’s public page instead, and '
                            .'reports the monetization features that are visibly switched on.'],
                ],
            ],

            [
                'key' => 'youtube.channel-monetization-checker',
                'slug' => 'youtube-channel-monetization-checker',
                'category' => 'analytics',
                'name' => 'YouTube Channel Monetization Checker',
                'tagline' => 'Is that channel monetized? Read from its own public page, not guessed.',
                'description' => 'Enter any channel — username, ID or URL — and see whether monetization '
                    .'is enabled, with the evidence it was decided on: channel memberships, the Shopping '
                    .'shelf and the 500-subscriber floor, each of which settles the question outright.',
                'tier' => ToolTier::Free,
                'platforms' => ['youtube'],
                'featured' => true,
                'focus_keyword' => 'youtube channel monetization checker',
                'seo_title' => 'YouTube Channel Monetization Checker — Is a Channel Monetized? (Free)',
                'seo_description' => 'Check whether any YouTube channel has monetization enabled. Paste a '
                    .'handle, channel ID or URL and see the public evidence. Free, no account.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste a handle like <code>@mkbhd</code>, a <code>UC…</code> channel '
                        .'ID, or any channel URL — old <code>/c/</code> and <code>/user/</code> links '
                        .'included. The channel card, its counts and the verdict come back together.'),
                    Blocks::heading('What actually proves monetization', 2),
                    Blocks::list([
                        '<strong>Channel memberships</strong> — the Join button. YouTube only offers '
                            .'memberships to a channel already in the Partner Program, so seeing one '
                            .'settles it.',
                        '<strong>The Shopping shelf</strong> — the Store tab. Same reasoning: YouTube '
                            .'Shopping requires monetization to be switched on first.',
                        '<strong>Under 500 subscribers</strong> — conclusive the other way. That is the '
                            .'floor of the lowest tier, so nothing is available below it.',
                    ]),
                    Blocks::heading('Why ads cannot be checked', 2),
                    Blocks::paragraph('YouTube does not serve ad slots to signed-out requests, so a channel '
                        .'earning ad revenue and nothing else leaves no public trace at all. When that is '
                        .'the case this tool says so instead of inventing a verdict — which is the whole '
                        .'difference between it and the checkers that always answer yes or no.'),
                    Blocks::callout('info', 'Checking your own channel against the rules is a different '
                        .'question, and needs figures only you can see. Use the <a '
                        .'href="/tools/youtube-partner-program-checker">YouTube Partner Program '
                        .'Checker</a> for that.'),
                ]),
                'example' => [
                    'input' => ['channel' => '@mkbhd'],
                ],
                'faq' => [
                    ['question' => 'It says “no public monetization features”. Is the channel monetized or not?',
                        'answer' => 'Unknown, honestly. The channel is past the subscriber floor but runs '
                            .'neither memberships nor a shop, which is the normal state for a monetized '
                            .'channel that only takes ad revenue — and identical, from outside, to one '
                            .'that has never applied. Nothing public separates the two.'],
                    ['question' => 'Can I check my own channel this way?',
                        'answer' => 'You can, and you will get the same partial answer everyone else does. '
                            .'Your own status is in YouTube Studio → Earn, stated outright.'],
                    ['question' => 'Do ads showing on a video prove the channel is monetized?',
                        'answer' => 'Not on its own. YouTube runs ads on some non-monetized channels '
                            .'without sharing the revenue, which is exactly why this tool does not treat '
                            .'an ad as evidence even where one could be seen.'],
                    ['question' => 'The subscriber count is missing.',
                        'answer' => 'That channel has hidden it, which is a setting. The subscriber floor '
                            .'check is skipped rather than guessed, and the other two signals still stand.'],
                ],
            ],

            [
                'key' => 'youtube.image-downloader',
                'slug' => 'youtube-image-downloader',
                'category' => 'media',
                'name' => 'YouTube Image Downloader',
                'tagline' => 'Channel avatars, banners and the auto-generated frames — every image YouTube publishes.',
                'description' => 'Paste a channel link for its avatar and banner at every size Google’s CDN '
                    .'will serve, or a video link for its thumbnail plus the three auto-generated frames '
                    .'YouTube offered the creator. None of these have a download button anywhere in YouTube.',
                'tier' => ToolTier::Free,
                'platforms' => ['youtube'],
                'focus_keyword' => 'youtube image downloader',
                'seo_title' => 'YouTube Image Downloader — Avatars, Banners & Video Frames (Free)',
                'seo_description' => 'Download any YouTube channel avatar or banner in full resolution, or a '
                    .'video’s auto-generated thumbnail frames. JPG and WebP. Free, no account needed.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste either a channel link or a video link — the tool works out which '
                        .'you gave it. Channel handles like <code>@mkbhd</code> work, and so do old '
                        .'<code>/c/</code> and <code>/user/</code> URLs.'),
                    Blocks::heading('Video links give you more than the thumbnail', 2),
                    Blocks::paragraph('Alongside the chosen thumbnail, YouTube keeps the three frames it '
                        .'generated automatically from the quarter, half and three-quarter marks of the video. '
                        .'They survive even after a custom thumbnail replaces them, which makes them useful '
                        .'for grabbing a clean still without downloading the video.'),
                    Blocks::heading('Channel links give you the assets nobody exposes', 2),
                    Blocks::paragraph('Avatars are served at any square size on request, and banners at the '
                        .'four widths YouTube crops to for mobile, tablet, desktop and TV. Both come straight '
                        .'from Google’s image CDN at full quality.'),
                    Blocks::callout('warning', 'These images belong to the channel owner. Downloading one is '
                        .'not a licence to republish it — use them for research, mock-ups and reference.'),
                ]),
                'example' => [
                    'input' => ['url' => 'https://www.youtube.com/@mkbhd'],
                ],
                'faq' => [
                    ['question' => 'How is this different from the thumbnail downloader?',
                        'answer' => 'The thumbnail downloader gives you one image — the chosen thumbnail — in '
                            .'five resolutions. This gives you the images around it: the auto-generated frames '
                            .'on a video, and the avatar and banner on a channel.'],
                    ['question' => 'The banner is missing.',
                        'answer' => 'Some channels have not set one. The result says so rather than showing a '
                            .'broken link.'],
                    ['question' => 'What size should my own banner be?',
                        'answer' => 'Upload at 2560 × 1440. YouTube crops it down for every device, and the '
                            .'1546 × 423 centre strip is the only part guaranteed to be visible everywhere.'],
                ],
            ],

            [
                'key' => 'youtube.subscribe-link-generator',
                'slug' => 'youtube-subscribe-link-generator',
                'category' => 'utility',
                'name' => 'YouTube Subscribe Link Generator',
                'tagline' => 'A link that opens the subscribe prompt instead of your channel page.',
                'description' => 'Turns a handle or channel URL into the <code>?sub_confirmation=1</code> '
                    .'link that opens YouTube with the subscribe dialog already showing — plus ready-made '
                    .'HTML, Markdown and description snippets to paste wherever you need them.',
                'tier' => ToolTier::Free,
                'platforms' => ['youtube'],
                'focus_keyword' => 'youtube subscribe link generator',
                'seo_title' => 'YouTube Subscribe Link Generator — One-Click Subscribe URLs (Free)',
                'seo_description' => 'Generate a YouTube subscribe link that opens the confirmation popup. '
                    .'Includes HTML button, Markdown and description snippets. Free, no account.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Enter your handle, channel ID or any channel URL. The tool resolves it '
                        .'to a channel ID first, because the ID form keeps working after a rename and the '
                        .'handle form does not.'),
                    Blocks::heading('Why the popup converts better', 2),
                    Blocks::paragraph('A plain channel link asks the visitor to find a button. The '
                        .'<code>sub_confirmation</code> link puts the dialog on screen the moment the page '
                        .'loads, so the only remaining decision is yes or no. It is the same subscribe '
                        .'action either way — nothing is subscribed without a click.'),
                    Blocks::heading('Where to use it', 2),
                    Blocks::list([
                        'The first line of every video description.',
                        'Your link-in-bio page, as the primary button.',
                        'Email signatures and newsletter footers.',
                        'End screens on your own website, where an embedded subscribe button no longer works.',
                    ]),
                    Blocks::callout('danger', 'Never offer anything in return for subscribing. Sub4sub, '
                        .'gated giveaways and incentivised clicks breach YouTube’s spam policy, and the '
                        .'subscribers get stripped out afterwards regardless.'),
                ]),
                'example' => [
                    'input' => ['channel' => '@mkbhd', 'label' => 'Subscribe on YouTube'],
                ],
                'faq' => [
                    ['question' => 'Is sub_confirmation an official parameter?',
                        'answer' => 'It is undocumented but entirely public, and it has worked for over a '
                            .'decade. It changes nothing about how subscribing works — it only opens the '
                            .'dialog.'],
                    ['question' => 'Should I use the handle link or the ID link?',
                        'answer' => 'The ID link, anywhere you cannot easily edit later — video descriptions, '
                            .'printed material, other people’s sites. The handle link is prettier and fine '
                            .'somewhere you control.'],
                ],
            ],

            [
                'key' => 'youtube.channel-description-generator',
                'slug' => 'youtube-channel-description-generator',
                'category' => 'content',
                'name' => 'YouTube Channel Description Generator',
                'tagline' => 'An About section that is front-loaded, searchable and finished in a minute.',
                'description' => 'Builds a channel description around the structure that works — topic, '
                    .'audience and schedule in the first sentence, search terms in real sentences after it — '
                    .'and shows you exactly what the 150-character preview will cut.',
                'tier' => ToolTier::Free,
                'platforms' => ['youtube'],
                'focus_keyword' => 'youtube channel description generator',
                'seo_title' => 'YouTube Channel Description Generator — Free About Section Writer',
                'seo_description' => 'Generate a YouTube channel description that ranks and reads well. '
                    .'Three lengths, four tones, with the 150-character preview shown. Free, no account.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Fill in what the channel is about, who it is for and how often you '
                        .'publish. Write the topic as the phrase somebody would type into search — that '
                        .'phrase ends up in the first sentence, where it does the most work.'),
                    Blocks::heading('The first 150 characters are the whole game', 2),
                    Blocks::paragraph('YouTube indexes the entire description, but a human only sees the '
                        .'opening line — in the channel sidebar, in search results, and in the preview card. '
                        .'A description that opens with "Hi everyone, welcome to my channel!" has spent that '
                        .'space on nothing. The tool shows you the exact fold so you can see what survives.'),
                    Blocks::heading('Keywords in sentences, not in a list', 2),
                    Blocks::paragraph('A block of comma-separated terms at the bottom of a description looks '
                        .'like keyword stuffing because it is. The generator works your search terms into a '
                        .'sentence instead, which is indexed the same and reads like a person wrote it.'),
                    Blocks::callout('info', 'YouTube allows 1,000 characters. Using all of them is not a '
                        .'goal — the result warns you only when you go over.'),
                ]),
                'example' => [
                    'input' => [
                        'channel_name' => 'The Slow Loaf',
                        'topic' => 'sourdough baking for small kitchens',
                        'audience' => 'home bakers with no proving drawer',
                        'schedule' => 'every Tuesday',
                        'tone' => 'friendly',
                        'keywords' => 'sourdough starter, no-knead bread, bread scoring',
                    ],
                ],
                'faq' => [
                    ['question' => 'Does the channel description affect search rankings?',
                        'answer' => 'It is indexed and it helps YouTube understand what the channel is about, '
                            .'which feeds both channel search and the suggested-channels surface. It is not a '
                            .'ranking lever for individual videos — those are ranked on their own metadata '
                            .'and performance.'],
                    ['question' => 'Should I put my links in the description?',
                        'answer' => 'Add them in the dedicated Links section of your channel settings, which '
                            .'renders them as buttons on the banner. The lines this tool generates are a '
                            .'fallback for platforms that only show the raw description.'],
                    ['question' => 'Is this AI-written?',
                        'answer' => 'No. It fills a proven structure with your specifics, which is why the '
                            .'output is consistent and does not need fact-checking.'],
                ],
            ],

            [
                'key' => 'youtube.content-calendar',
                'slug' => 'youtube-content-calendar',
                'category' => 'content',
                'name' => 'YouTube Content Calendar',
                'tagline' => 'A publishing schedule that spaces uploads instead of clustering them.',
                'description' => 'Turns a cadence — so many long-form videos and Shorts a week — into a dated '
                    .'calendar with slots spread evenly through each week and your content pillars rotated '
                    .'through them, so no theme gets neglected and no week gets front-loaded.',
                'tier' => ToolTier::Free,
                'platforms' => ['youtube'],
                'focus_keyword' => 'youtube content calendar',
                'seo_title' => 'YouTube Content Calendar — Free Upload Schedule Planner',
                'seo_description' => 'Plan a YouTube upload schedule that spaces long-form videos and Shorts '
                    .'evenly and rotates your content pillars. Up to 52 weeks. Free, no account needed.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Set a start date and how much you can realistically publish. The '
                        .'calendar begins on the Monday of that week and runs for as many weeks as you ask '
                        .'for, up to a year.'),
                    Blocks::heading('Spacing beats timing', 2),
                    Blocks::paragraph('The advice to post at 5pm on a Saturday comes from aggregate data that '
                        .'says nothing about your audience — and YouTube surfaces videos over weeks, not '
                        .'hours, so the exact minute matters far less than on a feed-based platform. What '
                        .'does hold is rhythm: a channel that publishes on a predictable spacing gets '
                        .'browsed and suggested more than one that drops three videos on a Sunday and then '
                        .'goes quiet for a fortnight.'),
                    Blocks::heading('Pillars stop the drift', 2),
                    Blocks::paragraph('Listing three to five recurring themes and rotating through them is '
                        .'what keeps a channel from becoming whatever you felt like making that week. Each '
                        .'slot gets the next pillar in turn, so the balance holds across the whole run.'),
                    Blocks::callout('warning', 'Pick the cadence you can hold in month six, not the one you '
                        .'can hold in week one. A schedule you abandon costs more than a slower one you keep.'),
                ]),
                'example' => [
                    'input' => [
                        'start_date' => '2026-09-07',
                        'weeks' => 8,
                        'long_form_per_week' => 1,
                        'shorts_per_week' => 3,
                        'publish_time' => '16:00',
                        'pillars' => "Tutorial\nReview\nBehind the scenes",
                    ],
                ],
                'faq' => [
                    ['question' => 'Why does long-form land on a Thursday?',
                        'answer' => 'A weekly upload placed late in the week has the whole weekend to '
                            .'accumulate its first-48-hours signal, which is the window YouTube leans on '
                            .'hardest when deciding how far to push a video. Move it if your analytics say '
                            .'otherwise — your data beats the default.'],
                    ['question' => 'What time zone are the times in?',
                        'answer' => 'Your channel’s local time — the calendar is a plan for you, not a '
                            .'scheduling API. Enter the times as you would set them in YouTube Studio.'],
                    ['question' => 'Can I export it?',
                        'answer' => 'Copy the table straight into a spreadsheet or your project tool. Every '
                            .'row carries its full date, so it pastes cleanly.'],
                ],
            ],

            [
                'key' => 'youtube.comment-generator',
                'slug' => 'fake-youtube-comment-generator',
                'category' => 'media',
                'name' => 'Fake YouTube Comment Generator',
                'tagline' => 'A YouTube comment card, drawn instead of screenshotted — light or dark.',
                'description' => 'Draws a comment exactly as YouTube draws one — avatar, handle, age, body, '
                    .'like count, creator heart — in the light and dark themes, and downloads it as a PNG, '
                    .'JPG or WebP. In the browser the whole card is rendered locally: an avatar you drop in '
                    .'is never uploaded and never stored.',
                'tier' => ToolTier::Free,
                'platforms' => ['youtube'],
                'focus_keyword' => 'fake youtube comment generator',
                'seo_title' => 'Fake YouTube Comment Generator — Light & Dark, PNG/JPG/WebP (Free)',
                'seo_description' => 'Create a realistic YouTube comment image for thumbnails, slides and '
                    .'videos. Custom avatar, likes, age and creator heart. Nothing is uploaded. Free.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Type the username and the comment, set how long ago it was posted and '
                        .'how many likes it has, then download the card. The preview updates as you type, in '
                        .'whichever theme you pick.'),
                    Blocks::heading('Your images stay on your device', 2),
                    Blocks::paragraph('Drop in an avatar and a creator avatar if you want them. The card is '
                        .'drawn in your own browser onto a canvas, so those files are never uploaded to us '
                        .'and nothing is stored — close the tab and they are gone.'),
                    Blocks::heading('This is a mock-up, not evidence', 2),
                    Blocks::paragraph('The card draws whatever you type into it, which means it proves '
                        .'nothing about who commented what. Use it to illustrate your own comments, to mock '
                        .'up a thumbnail, or to reproduce a comment you have permission to quote — never to '
                        .'present invented text as something a real person posted.'),
                    Blocks::callout('tip', 'Match the theme to wherever the card is going. A light card on a '
                        .'dark thumbnail reads as a sticker; a dark card reads as a screenshot.'),
                ]),
                'example' => [
                    'input' => [
                        'username' => 'John_Smith',
                        'content' => 'This video was very funny, thanks for sharing',
                        'time' => 5,
                        'unit' => 'hours',
                        'likes' => 5000,
                        'reaction' => 'neutral',
                        'creator_liked' => true,
                        'theme' => 'light',
                    ],
                ],
                'faq' => [
                    ['question' => 'Are my images uploaded anywhere?',
                        'answer' => 'No. The web tool renders the card in your browser, so the avatars you '
                            .'drop in never leave your device and nothing is saved on our side.'],
                    ['question' => 'Which format should I download?',
                        'answer' => 'PNG for anything with text you want crisp, and it keeps a transparent '
                            .'background if you turn one on. JPG is smallest for photo-heavy thumbnails. '
                            .'WebP splits the difference and every current browser reads it.'],
                    ['question' => 'Can I use this to fake a real person’s comment?',
                        'answer' => 'Please do not. Passing off an invented comment as a real one is '
                            .'defamatory at worst and dishonest at best. The tool draws no verified badge '
                            .'and no channel link, so a card is never a substitute for the real thing.'],
                ],
            ],

            [
                'key' => 'youtube.comment-finder',
                'slug' => 'youtube-comment-finder',
                'category' => 'utility',
                'name' => 'YouTube Comment Finder',
                'tagline' => 'Search the comments on any video for the one you half-remember.',
                'description' => 'Searches a video’s comments through YouTube’s official Data API and returns '
                    .'the matches with their author, like count, date and a direct link that jumps straight '
                    .'to the comment in the thread.',
                'tier' => ToolTier::Free,
                'platforms' => ['youtube'],
                'focus_keyword' => 'youtube comment finder',
                'seo_title' => 'YouTube Comment Finder — Search Comments on Any Video (Free)',
                'seo_description' => 'Find a specific comment on a YouTube video by searching its text. '
                    .'Shows likes, replies, date and a direct link to each match. Free, no account.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste the video and the words you remember. YouTube’s own comment '
                        .'search does the matching, so a phrase in quotes matches exactly and loose words '
                        .'match anywhere in the comment.'),
                    Blocks::heading('Every card is a link', 2),
                    Blocks::paragraph('Click a result and it opens that comment in the thread rather than '
                        .'the top of the page — which is the difference between finding a comment and '
                        .'scrolling for it. Sorting puts the most-liked matches first, because the comment '
                        .'people half-remember is usually the one everybody upvoted.'),
                    Blocks::callout('info', 'This tool uses the official YouTube Data API rather than '
                        .'scraping the page, which is why it can search comments at all — they are not in '
                        .'the page metadata every other YouTube tool here reads.'),
                ]),
                'example' => [
                    'input' => [
                        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'query' => 'timestamp',
                        'order' => 'relevance',
                        'limit' => 50,
                    ],
                ],
                'faq' => [
                    ['question' => 'It says comment search is unavailable.',
                        'answer' => 'The tool needs a YouTube Data API key, which the site owner configures '
                            .'in settings. Every other YouTube tool on this site works without one.'],
                    ['question' => 'Why did a comment I know exists not show up?',
                        'answer' => 'Replies are not searched — only top-level comments are. A comment held '
                            .'for review, removed, or posted on a video with comments disabled is also '
                            .'invisible to the API.'],
                    ['question' => 'Can I search my own channel’s comments across every video?',
                        'answer' => 'Not here — this searches one video at a time. YouTube Studio’s comments '
                            .'tab searches across a whole channel and can act on what it finds.'],
                ],
            ],

            [
                'key' => 'youtube.search-suggest',
                'slug' => 'youtube-search-suggestions',
                'category' => 'content',
                'name' => 'YouTube Search Suggestions',
                'tagline' => 'Real searches, straight from YouTube’s own search box — no volumes invented.',
                'description' => 'Expands a seed keyword against YouTube’s autocomplete to surface the '
                    .'questions and phrases people are actually typing, ranked longest-first because those '
                    .'are the searches a small channel can still rank for.',
                'tier' => ToolTier::Free,
                'platforms' => ['youtube'],
                'featured' => true,
                'focus_keyword' => 'youtube keyword research',
                'seo_title' => 'YouTube Search Suggestions — Free Keyword Research Tool',
                'seo_description' => 'Expand any keyword into the real searches YouTube suggests. Questions, '
                    .'commercial intent and A–Z sweeps. Free, no account, no API key.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Enter a seed of two or three words — a single word returns searches '
                        .'too broad to act on. The default run queries YouTube’s suggestion endpoint once '
                        .'per modifier and groups what comes back the way the search box behaves: the '
                        .'direct completions for your seed, then the question and intent phrasings, then '
                        .'the A–Z sweep. Inside each group the order is YouTube’s own, not ours.'),
                    Blocks::heading('Why there are no search volumes', 2),
                    Blocks::paragraph('Nobody outside Google has YouTube search volumes. Every tool that '
                        .'shows them is modelling them from Google Ads data for web search, which is a '
                        .'different index with different behaviour. A number is the one thing on a keyword '
                        .'report anybody actually acts on, so inventing one would be the most damaging thing '
                        .'this tool could do. What you get instead is certainty: every row is a search '
                        .'YouTube itself suggests, which means people type it.'),
                    Blocks::heading('YouTube’s order, kept', 2),
                    Blocks::paragraph('The endpoint returns each list ranked, and that ranking is the '
                        .'closest thing in free data to popularity — so the rows appear exactly as the '
                        .'search box would drop them down. “Sourdough” is unwinnable; “how to fix a '
                        .'sourdough starter that won’t rise” is a video you can make this week, and the '
                        .'questions group is where those live.'),
                    Blocks::callout('info', 'Suggestions differ by country. If your audience is not in the '
                        .'US, change the region — the lists are often barely related.'),
                ]),
                'example' => [
                    'input' => ['keyword' => 'sourdough starter', 'expansion' => 'everything',
                        'position' => 'before', 'region' => 'US'],
                ],
                'faq' => [
                    ['question' => 'How is this different from a paid keyword tool?',
                        'answer' => 'Paid tools add estimated volume and competition scores on top of this '
                            .'same suggestion data. If the estimates matter to you they are worth paying '
                            .'for. If what you need is “what do people actually search”, this is the source '
                            .'those tools are built on.'],
                    ['question' => 'Why did the A–Z sweep stop early?',
                        'answer' => 'It makes 26 requests, and the tool holds itself to a 15-second budget '
                            .'so one slow run cannot tie up a worker. It tells you how far it got — run it '
                            .'again and the cached portion returns instantly.'],
                    ['question' => 'Should I put these words in my tags?',
                        'answer' => 'Put them in the title and the first line of the description, where '
                            .'they carry weight. Tags barely count for ranking any more.'],
                ],
            ],

            [
                'key' => 'youtube.embed-code-generator',
                'slug' => 'youtube-embed-code-generator',
                'category' => 'utility',
                'name' => 'YouTube Embed Code Generator',
                'tagline' => 'Embed code with the parameters that still work, and none that quietly do not.',
                'description' => 'Builds fixed, responsive and lazy-loading embed code with start and end '
                    .'times, autoplay, looping and the privacy-enhanced domain — and explains the two '
                    .'parameters every other generator still gets wrong.',
                'tier' => ToolTier::Free,
                'platforms' => ['youtube'],
                'focus_keyword' => 'youtube embed code generator',
                'seo_title' => 'YouTube Embed Code Generator — Responsive, Privacy-Safe (Free)',
                'seo_description' => 'Generate YouTube embed code with start time, autoplay, loop and '
                    .'no-cookie privacy mode. Responsive and lazy-loading options. Free, no account.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste the video, set the options you want, and copy whichever of the '
                        .'four snippets suits where it is going. The responsive one is right for almost every '
                        .'modern site: it fills its container and reserves the space before the player loads, '
                        .'so nothing jumps.'),
                    Blocks::heading('Two parameters that no longer mean what they say', 2),
                    Blocks::list([
                        '<strong><code>rel=0</code> does not hide related videos.</strong> YouTube changed it '
                            .'in 2018 — it now means “suggest videos from this channel only”. Nothing removes '
                            .'the end screen.',
                        '<strong><code>autoplay=1</code> alone does nothing.</strong> Every browser blocks '
                            .'unmuted autoplay, so this tool adds <code>mute=1</code> with it. Without the '
                            .'mute, the embed silently refuses to start.',
                    ]),
                    Blocks::heading('Why no-cookie is the default', 2),
                    Blocks::paragraph('The standard <code>youtube.com</code> embed sets tracking cookies the '
                        .'moment the page renders, which in the EU and UK needs consent <em>before</em> the '
                        .'iframe appears. <code>youtube-nocookie.com</code> defers them until somebody presses '
                        .'play, which is what makes an embed safe to drop into a page without a consent gate.'),
                    Blocks::callout('info', 'Looping needs the video named as a one-item playlist. That is '
                        .'why <code>playlist=</code> appears in the URL — remove it and the loop stops working.'),
                ]),
                'example' => [
                    'input' => ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'start' => '1:23',
                        'width' => 560, 'privacy_mode' => true, 'controls' => true],
                ],
                'faq' => [
                    ['question' => 'How do I hide the YouTube logo?',
                        'answer' => 'You cannot. <code>modestbranding</code> was removed in 2023 and the '
                            .'watermark is now permanent. Any generator still offering it is producing a '
                            .'parameter YouTube ignores.'],
                    ['question' => 'Does lazy loading hurt SEO?',
                        'answer' => 'The opposite. A YouTube iframe costs roughly half a megabyte before '
                            .'anyone presses play, and <code>loading="lazy"</code> is usually the single '
                            .'largest Core Web Vitals win available on a page with a video on it.'],
                ],
            ],

            [
                'key' => 'youtube.rss-feed-generator',
                'slug' => 'youtube-rss-feed-generator',
                'category' => 'utility',
                'name' => 'YouTube RSS Feed Generator',
                'tagline' => 'The feed YouTube publishes for every channel, and links to from nowhere.',
                'description' => 'Turns a handle, channel URL or playlist link into its RSS feed URL, then '
                    .'fetches the feed and shows what came back — so you know the URL works before you wire '
                    .'it into a reader or an automation.',
                'tier' => ToolTier::Free,
                'platforms' => ['youtube'],
                'focus_keyword' => 'youtube rss feed generator',
                'seo_title' => 'YouTube RSS Feed Generator — Channel & Playlist Feed URLs (Free)',
                'seo_description' => 'Get the RSS feed URL for any YouTube channel or playlist, verified '
                    .'against the live feed. No API key, no quota, works with @handles. Free.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste a handle, a channel URL or a playlist link. Channels need their '
                        .'immutable channel ID resolved first, which is the step that makes the feed URL hard '
                        .'to find by hand — the handle on its own will not work.'),
                    Blocks::heading('Why RSS is worth the trouble', 2),
                    Blocks::paragraph('It is the only way to follow a channel that costs no API quota, needs '
                        .'no key, has no rate limit and cannot be reordered by an algorithm. Every upload, in '
                        .'order, as soon as it publishes — which is why nearly every YouTube automation is '
                        .'built on it rather than on the Data API.'),
                    Blocks::heading('What you can wire it into', 2),
                    Blocks::list([
                        'Any RSS reader, to follow channels without a Google account.',
                        'Zapier, Make or n8n, as a trigger that fires on every new upload.',
                        'Discord and Slack, which both accept a raw feed URL.',
                        'Your own site, to list a channel’s latest videos without an API key.',
                    ]),
                    Blocks::callout('warning', 'YouTube caps every feed at the 15 most recent items and '
                        .'there is no pagination. RSS is for watching what is new, not for reading a back '
                        .'catalogue.'),
                ]),
                'example' => [
                    'input' => ['source' => '@mkbhd'],
                ],
                'faq' => [
                    ['question' => 'Why does my handle not work in the feed URL directly?',
                        'answer' => 'The feed only accepts <code>channel_id</code> or <code>playlist_id</code>. '
                            .'Handles are a display layer on top and the feed endpoint has never understood '
                            .'them, which is why this tool resolves the ID first.'],
                    ['question' => 'Do Shorts appear in the feed?',
                        'answer' => 'Usually, but not reliably — YouTube has filtered them in and out of the '
                            .'feed more than once. If Shorts matter to your automation, verify against a '
                            .'channel that posts them before you depend on it.'],
                    ['question' => 'How often should I poll it?',
                        'answer' => 'Every 15 minutes is plenty and stays well clear of anything that looks '
                            .'abusive. The feed is cached upstream, so polling harder does not get you the '
                            .'video sooner.'],
                ],
            ],

            [
                'key' => 'youtube.hashtag-generator',
                'slug' => 'youtube-hashtag-generator',
                'category' => 'content',
                'name' => 'YouTube Hashtag Generator',
                'tagline' => 'Twenty-five tags built from what YouTube’s own search box completes.',
                'description' => 'Expands your topic against YouTube autocomplete and turns the real searches '
                    .'it returns into 25 ranked hashtags for a Short or a long-form upload — with each tag '
                    .'placed where YouTube will actually use it: the first three above your title, fifteen in '
                    .'the description, the rest held back as spares.',
                'tier' => ToolTier::Free,
                'platforms' => ['youtube'],
                'focus_keyword' => 'youtube hashtag generator',
                'seo_title' => 'YouTube Hashtag Generator — 25 Tags From Real Searches (Free)',
                'seo_description' => 'Generate 25 YouTube hashtags for Shorts or long-form video, built from '
                    .'YouTube’s own autocomplete. Ranked, placed by YouTube’s real limits. No invented '
                    .'numbers. Free.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Describe the video, pick whether it is a Short or a long-form upload, '
                        .'and run it. Adding two or three related keywords sharpens the tags noticeably — '
                        .'“catching butterflies” gives more to work with than “butterflies”.'),
                    Blocks::heading('Where the tags come from', 2),
                    Blocks::paragraph('Most of them are built from YouTube’s autocomplete: the phrases the '
                        .'search box completes are searches people demonstrably type, which is the closest '
                        .'thing to real demand that is available without a paid data source. When '
                        .'autocomplete has little to say about a topic, the list is topped up from your own '
                        .'words and those rows say so.'),
                    Blocks::heading('Why there are no video counts', 2),
                    Blocks::paragraph('Other generators print a “used by 1M videos” figure beside each tag. '
                        .'Nobody outside Google has hashtag volumes, and that number is the only one on the '
                        .'page anyone would act on, so we do not print one. The breadth column is a shape '
                        .'heuristic — short tags are crowded, long compounds are quiet — and it is labelled '
                        .'as such.'),
                    Blocks::heading('YouTube’s actual limits', 2),
                    Blocks::paragraph('Fifteen hashtags in the description is the cap; anything after the '
                        .'fifteenth is dropped. The first three appear above your video title, so those three '
                        .'are the ones that matter. Go past sixty hashtags on an upload and YouTube ignores '
                        .'every hashtag on it.'),
                    Blocks::callout('tip', 'Do not paste all 25. Take the top fifteen, drop anything that '
                        .'does not describe the video, and keep the rest as spares for the next upload.'),
                ]),
                'example' => [
                    'input' => [
                        'topic' => 'catching butterflies',
                        'format' => 'shorts',
                        'extra_keywords' => 'nature, kids outdoors',
                        'region' => 'US',
                    ],
                ],
                'faq' => [
                    ['question' => 'How many hashtags should I actually use?',
                        'answer' => 'Between three and five that genuinely describe the video. YouTube keeps '
                            .'fifteen in the description, but a wall of loosely related tags helps nobody '
                            .'find you and past sixty it ignores all of them.'],
                    ['question' => 'Should a long-form video use #shorts?',
                        'answer' => 'No. It pushes the video in front of an audience expecting something '
                            .'under a minute, and they swipe away — which is a retention signal working '
                            .'against you. Pick the long-form format and the tool leaves those tags out.'],
                    ['question' => 'Why do the results change by region?',
                        'answer' => 'Autocomplete is regional, sometimes completely. Set the region to where '
                            .'your audience actually watches, not where you are sitting.'],
                    ['question' => 'Do hashtags help a video rank?',
                        'answer' => 'A little, and mostly for discovery rather than ranking — they group '
                            .'your video onto a hashtag page and give YouTube another signal about the '
                            .'topic. They do not rescue a weak title or thumbnail.'],
                ],
            ],

            [
                'key' => 'youtube.handle-availability',
                'slug' => 'youtube-handle-availability-checker',
                'category' => 'utility',
                'name' => 'YouTube Handle Availability Checker',
                'tagline' => 'A definite yes or no — YouTube is one of the few platforms that will tell you.',
                'description' => 'Checks a handle against YouTube’s own rules and then against YouTube '
                    .'itself, which answers 404 when nobody holds it. When it is taken, a short list of '
                    .'variants is checked too.',
                'tier' => ToolTier::Free,
                'platforms' => ['youtube'],
                'focus_keyword' => 'youtube handle availability',
                'seo_title' => 'YouTube Handle Availability Checker — Is @yourname Free? (Free)',
                'seo_description' => 'Check whether a YouTube @handle is taken, with a definite answer and '
                    .'suggested alternatives if it is. Free, instant, no account needed.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Type the handle with or without the @. The rules are checked first, '
                        .'because a handle YouTube would reject outright is not worth a request — then the '
                        .'handle itself is looked up.'),
                    Blocks::heading('Why this one gives a real answer', 2),
                    Blocks::paragraph('Instagram, TikTok and X answer automated profile requests with a login '
                        .'wall, which is why our general username checker can only link to those. YouTube '
                        .'serves <code>/@handle</code> to anyone: 200 if somebody has it, 404 if nobody does. '
                        .'That makes a definite answer possible here, and this tool gives one — including '
                        .'a third state, “unknown”, when YouTube does not answer at all. A timeout reported '
                        .'as “available” is how people lose a name.'),
                    Blocks::heading('YouTube’s handle rules', 2),
                    Blocks::list([
                        '3 to 30 characters.',
                        'Letters, numbers, underscores, hyphens and periods only.',
                        'Cannot be only digits and periods — anything that reads as a phone number or URL '
                            .'is rejected.',
                        'Case-insensitive, so @TheSlowLoaf and @theslowloaf are the same handle.',
                    ]),
                    Blocks::callout('warning', 'Claiming a handle is not the same as owning the name. '
                        .'Trademarked and impersonating handles get reclaimed after the fact, however '
                        .'cleanly they register today.'),
                ]),
                'example' => [
                    'input' => ['handle' => 'theslowloaf', 'suggest_variants' => true],
                ],
                'faq' => [
                    ['question' => 'Where do I actually claim it?',
                        'answer' => 'YouTube Studio → Customisation → Basic info. You can change a handle '
                            .'twice every 14 days, and your old one is released for anyone to take the '
                            .'moment you do.'],
                    ['question' => 'It says taken, but the channel looks abandoned.',
                        'answer' => 'YouTube does not release handles from inactive channels, and there is '
                            .'no reclaim process. Treat a taken handle as permanently taken.'],
                    ['question' => 'Does the handle have to match my channel name?',
                        'answer' => 'No — they are separate fields and the channel name can be anything. '
                            .'Matching them is still worth doing, because the handle is what people type '
                            .'and what appears in your URL.'],
                ],
            ],

            [
                'key' => 'youtube.citation-generator',
                'slug' => 'youtube-citation-generator',
                'category' => 'utility',
                'name' => 'YouTube Citation Generator',
                'tagline' => 'APA, MLA, Chicago, Harvard and BibTeX, from the video’s own metadata.',
                'description' => 'Reads a video’s title, channel and publication date, then formats them '
                    .'five ways — because every style treats the uploader differently, and that is the part '
                    .'people get wrong.',
                'tier' => ToolTier::Free,
                'platforms' => ['youtube'],
                'focus_keyword' => 'youtube citation generator',
                'seo_title' => 'YouTube Citation Generator — APA, MLA, Chicago, Harvard (Free)',
                'seo_description' => 'Cite any YouTube video in APA 7, MLA 9, Chicago 17, Harvard or '
                    .'BibTeX. Reads the metadata automatically. Free, no account needed.',
                'instructions' => Blocks::make([
                    Blocks::paragraph('Paste the video link. Title, channel and publication date are read '
                        .'from the video itself, so there is nothing to retype and nothing to mistype.'),
                    Blocks::heading('The uploader is the hard part', 2),
                    Blocks::paragraph('APA wants the person with the channel in square brackets — '
                        .'<em>Brownlee, M. [Marques Brownlee]</em>. MLA wants “uploaded by” after the title. '
                        .'Chicago leads with the channel as the author outright. If you know the real name '
                        .'behind the channel, add it in the optional field; if no person is credited, leave '
                        .'it blank and the channel is cited as the author, which is correct.'),
                    Blocks::callout('info', 'Check the output against your institution’s guide before you '
                        .'submit. Departments vary the details, and a generator cannot know which variant '
                        .'your marker uses.'),
                ]),
                'example' => [
                    'input' => ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
                ],
                'faq' => [
                    ['question' => 'How do I cite a specific moment in a video?',
                        'answer' => 'Cite the video as normal and put the timestamp in the in-text '
                            .'citation — APA uses (Channel, 2009, 1:23). Our timestamp link builder will '
                            .'give you a link that jumps straight there.'],
                    ['question' => 'The date says n.d.',
                        'answer' => 'No publication date was published for that video, which happens on some '
                            .'older and some unlisted uploads. “n.d.” is the correct fallback in every style '
                            .'here; add the date by hand if you can find it on the watch page.'],
                ],
            ],
        ];
    }
}
