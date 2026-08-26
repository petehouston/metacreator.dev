# 07 — Tool Catalog

The catalog is the product. This document is the brainstormed universe of tools, the tier assigned
to each, and the reasoning behind the tiering. The seeder in
`apps/api/database/seeders/ToolCatalogSeeder.php` is generated from this list, so **this file is the
source of truth for what exists**.

## Tiering rules

A tool is `free` when it costs us almost nothing per run and is likely to rank in search.
It is `account` when it benefits from persistence (history, saved projects) or when the free version
is being abused. It is `premium` when a run consumes metered third-party API quota, meaningful
compute, or storage — or when the output is worth real money to a professional.

Legend: 🟢 free · 🔵 account · 🟣 premium · **P1/P2/P3** launch priority

---

## 1. YouTube (`youtube`)

| Tool | Tier | P | What it does |
| --- | --- | --- | --- |
| Thumbnail Downloader | 🟢 | P1 | Pull every resolution of any video's thumbnail |
| Tag Extractor | 🟢 | P1 | Extract the tags on a public video |
| Title Character Counter | 🟢 | P1 | Live counter with truncation preview across search/suggested/mobile |
| Description Generator | 🔵 | P1 | Structured description with timestamps, links, CTA blocks |
| Hashtag Generator | 🟢 | P1 | Relevance-ranked hashtags from a topic |
| Channel ID Finder | 🟢 | P2 | Resolve handle/URL → channel ID |
| Video Timestamp Link Builder | 🟢 | P2 | Build `?t=` deep links from a transcript |
| Money / RPM Calculator | 🟢 | P1 | Earnings estimate by niche, geo and RPM band |
| Subscriber Milestone Predictor | 🔵 | P2 | Projects milestone dates from growth history |
| Video SEO Score | 🟣 | P1 | Audits title, description, tags, thumbnail, chapters against ranked competitors |
| Thumbnail A/B Tester | 🟣 | P1 | Side-by-side CTR simulation at real feed sizes, on multiple devices |
| Competitor Channel Analyzer | 🟣 | P1 | Upload cadence, best-performing formats, tag overlap, gap analysis |
| Keyword Research (YT) | 🟣 | P1 | Search volume, competition, suggest-tree expansion |
| Transcript / Subtitle Extractor | 🔵 | P2 | Clean transcript export (txt/srt/vtt) |
| Chapter Generator | 🟣 | P2 | Chapters from a transcript with timestamps |
| Shorts Idea Generator | 🔵 | P2 | Short-form angles derived from a long-form video |
| Comment Sentiment Analyzer | 🟣 | P2 | Sentiment + theme clustering over a video's comments |
| End Screen / Playlist Planner | 🔵 | P3 | Suggests next-video links to maximise session time |
| Channel Audit Report | 🟣 | P2 | Full PDF audit: branding, SEO, cadence, retention signals |

## 2. Instagram (`instagram`)

| Tool | Tier | P | What it does |
| --- | --- | --- | --- |
| Engagement Rate Calculator | 🟢 | P1 | ER by reach/followers with benchmark bands per follower tier |
| Hashtag Generator | 🟢 | P1 | Mixed-difficulty hashtag sets (broad/mid/niche) |
| Hashtag Difficulty Checker | 🟣 | P2 | Post volume, competition and recency for a hashtag set |
| Bio Link Preview | 🟢 | P2 | Renders how a bio + link appears across app versions |
| Caption Generator | 🔵 | P1 | On-brand captions with hook/body/CTA structure |
| Font / Aesthetic Text Generator | 🟢 | P1 | Unicode styled text for bios and captions |
| Grid Planner & Preview | 🔵 | P1 | Drag-and-drop 3×N grid preview before posting |
| Reels Cover Cropper | 🟢 | P2 | Crops one image correctly for both the Reel cover and the grid tile |
| Carousel Splitter | 🟢 | P1 | Slices a wide image into seamless carousel panels |
| Best Time to Post | 🟣 | P2 | Posting windows from audience timezone distribution |
| Influencer Rate Calculator | 🔵 | P1 | Suggested sponsorship pricing from ER, niche and format |
| Fake Follower Checker | 🟣 | P1 | Audience-quality score from engagement distribution |
| Story Templates Sizer | 🟢 | P3 | Safe-zone overlay export for stories |
| Competitor Content Analyzer | 🟣 | P2 | Format mix, cadence, top posts, hook patterns |

## 3. TikTok (`tiktok`)

| Tool | Tier | P | What it does |
| --- | --- | --- | --- |
| Video Downloader (no watermark, own content) | 🔵 | P1 | Own-content download for repurposing |
| Engagement Rate Calculator | 🟢 | P1 | ER benchmarked against the niche |
| Hashtag Generator | 🟢 | P1 | Trend-weighted hashtag sets |
| Money Calculator | 🟢 | P1 | Creator-fund and brand-deal estimates |
| Hook Analyzer | 🟣 | P1 | Scores the first three seconds against retention patterns |
| Trending Sounds Finder | 🟣 | P1 | Rising sounds by niche and region |
| Caption & Hook Generator | 🔵 | P1 | Multiple hook variants per idea |
| Best Time to Post | 🟣 | P2 | From audience activity |
| Video Idea Generator | 🔵 | P2 | Format-aware ideas from a niche and a goal |
| Watermark Remover (own uploads) | 🟣 | P3 | Compute-heavy; own content only |

## 4. X / Twitter (`x`)

| Tool | Tier | P | What it does |
| --- | --- | --- | --- |
| Character Counter | 🟢 | P1 | Weighted count with URL/CJK handling |
| Thread Splitter | 🟢 | P1 | Splits long text into numbered posts at sentence boundaries |
| Card / Preview Debugger | 🟢 | P2 | Renders the link card as X will |
| Thread Composer & Scheduler Helper | 🔵 | P2 | Composes and exports a thread as a schedulable file |
| Engagement Rate Calculator | 🟢 | P1 | |
| Best Time to Post | 🟣 | P2 | |
| Hashtag / Topic Analyzer | 🟣 | P3 | |
| Tweet Screenshot Generator | 🟢 | P1 | Clean, theme-aware image of a post for reuse |

## 5. Facebook (`facebook`)

| Tool | Tier | P | What it does |
| --- | --- | --- | --- |
| Ad Text Character Counter | 🟢 | P1 | Primary text / headline / description truncation preview |
| Post Preview | 🟢 | P2 | Feed and mobile rendering preview |
| Engagement Rate Calculator | 🟢 | P1 | |
| Audience Size Estimator | 🟣 | P3 | Reach estimate from targeting inputs |
| Page Audit | 🟣 | P2 | Completeness, cadence, response rate, CTA presence |

## 6. LinkedIn (`linkedin`)

| Tool | Tier | P | What it does |
| --- | --- | --- | --- |
| Post Character Counter & "See more" preview | 🟢 | P1 | Where the fold lands |
| Headline Generator | 🔵 | P2 | Keyword-aware headline options |
| Carousel (PDF) Builder | 🟣 | P2 | Slides → correctly sized PDF document post |
| Engagement Rate Calculator | 🟢 | P2 | |
| Profile SEO Audit | 🟣 | P3 | |

## 7. Cross-platform content (`content`)

| Tool | Tier | P | What it does |
| --- | --- | --- | --- |
| Content Calendar Generator | 🔵 | P1 | 30-day plan from niche, cadence and pillars — CSV/ICS export |
| Content Repurposing Planner | 🟣 | P1 | One long-form asset → a mapped set of short-form derivatives |
| Hook Library & Generator | 🔵 | P1 | Proven hook patterns filled with the user's topic |
| CTA Generator | 🟢 | P2 | |
| Headline Analyzer | 🟢 | P1 | Scores clarity, emotion, power words, length |
| Readability Checker | 🟢 | P2 | Flesch–Kincaid and friends with rewrite hints |
| Emoji Picker & Keyword Search | 🟢 | P2 | |
| Unicode / Fancy Text Generator | 🟢 | P1 | |
| Text Case Converter | 🟢 | P2 | |
| Word & Character Counter | 🟢 | P1 | |
| Script Timer (words → runtime) | 🟢 | P2 | Reading-speed aware duration estimate |
| Brand Voice Sheet Builder | 🔵 | P3 | Codifies tone rules to reuse across tools |

## 8. Media & assets (`media`)

| Tool | Tier | P | What it does |
| --- | --- | --- | --- |
| Social Image Resizer | 🟢 | P1 | One upload → every platform's required sizes, zipped |
| Image Compressor | 🟢 | P1 | Lossy/lossless with a live quality comparison |
| Image Format Converter | 🟢 | P1 | png/jpg/webp/avif |
| Video Trimmer / Cropper | 🟣 | P2 | Aspect conversion for vertical repurposing |
| Video Compressor | 🟣 | P2 | |
| Audio Extractor | 🔵 | P2 | Video → mp3/wav |
| Subtitle / Caption Burner | 🟣 | P2 | SRT burn-in with styling |
| GIF Maker | 🔵 | P2 | Clip → optimised GIF |
| Safe-Zone Overlay Preview | 🟢 | P1 | Where UI chrome will cover your frame, per platform |
| Color Palette Extractor | 🟢 | P2 | Palette + hex from an image, for brand consistency |
| Thumbnail Text Legibility Check | 🟣 | P2 | Contrast and size check at real feed dimensions |
| Watermark Applier | 🔵 | P3 | Batch watermarking |
| QR Code Generator | 🟢 | P2 | Branded, error-corrected QR to any URL |

## 9. Analytics & growth (`analytics`)

| Tool | Tier | P | What it does |
| --- | --- | --- | --- |
| Cross-Platform Engagement Benchmark | 🔵 | P1 | Your ER vs. niche medians across networks |
| Follower Growth Tracker | 🟣 | P1 | Tracked over time with anomaly flags |
| Audience Overlap Estimator | 🟣 | P3 | How much two audiences share |
| Post Performance Predictor | 🟣 | P2 | Predicts relative performance before publishing |
| Competitor Tracking Dashboard | 🟣 | P1 | Watchlist of competitor accounts with weekly digests |
| UTM Builder & Link Manager | 🟢 | P1 | Consistent campaign tagging with a saved scheme |
| Link-in-Bio Click Analyzer | 🔵 | P2 | |
| Sponsorship Rate Card Builder | 🟣 | P2 | Generates a branded, exportable rate card |
| Media Kit Generator | 🟣 | P1 | One-page PDF media kit from profile stats |

## 10. Utilities (`utility`)

| Tool | Tier | P | What it does |
| --- | --- | --- | --- |
| Username Availability Checker | 🟢 | P1 | Across all supported networks at once |
| Link Checker (bulk) | 🔵 | P2 | Finds dead links across a list — uses Go fan-out |
| Timezone Converter for Posting | 🟢 | P2 | |
| Aspect Ratio Calculator | 🟢 | P2 | |
| Social Profile Metadata Preview | 🟢 | P2 | OG/Twitter card debugger for any URL |
| Password / Handle Strength | 🟢 | P3 | |
| Giveaway Winner Picker | 🟢 | P1 | Auditable random draw with a verifiable seed |
| Follower Milestone Countdown | 🟢 | P3 | |

---

## Launch scope

| Phase | Tools | Rationale |
| --- | --- | --- |
| **P1** | 34 tools across all categories | Enough breadth for search coverage and a credible paid tier |
| **P2** | +32 | Depth in the categories that convert best |
| **P3** | +12 | Long tail; built only if telemetry justifies it |

## Adding a tool

1. Add the row here.
2. Create `app/Domain/Tools/Runners/<Name>Runner.php` implementing `ToolRunner`.
3. Register it in `ToolRegistry` and add a catalog entry (slug, schema, instructions, example).
4. Write the runner test with at least one golden-file case.
5. Add the frontend renderer only if the generated form + generic result view is insufficient.

Steps 1–4 are backend-only; a tool with a plain input schema needs **zero frontend code**. That is
the point of the engine — see [08](08-tool-engine.md).
