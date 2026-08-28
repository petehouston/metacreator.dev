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

Free and account tools together are the **common** tier: a run is pure computation over the user's
own input, cheap enough that we would not mind it running on the client. Premium tools are the ones
that spend money — third-party API quota, sustained compute, or storage.

Legend: 🟢 free · 🔵 account · 🟣 premium · **P1/P2/P3** launch priority

## Two axes, not one

A tool is filed on two independent axes, and they are not interchangeable:

- **Platform** (`tools.platforms[]`) — which networks the tool serves. A tool can serve several.
- **Category** (`tool_categories`) — what kind of job the tool does. A tool has exactly one.

Categories are therefore **never named after a platform**. When they were, every platform appeared
twice in the catalog UI under two different names — once as a platform filter and once as a
category — and half the Instagram tools sat in "Content" while the other half sat in "Instagram".
The categories that exist are: `previews`, `content`, `media`, `analytics`, `utility`.

The sections below are grouped by platform because that is how the tools were brainstormed, and
because it is how people search. The heading is a **platform**, not a category.

---

## 1. YouTube

| Tool | Tier | P | What it does |
| --- | --- | --- | --- |
| Thumbnail Downloader | 🟢 | P1 | Pull every resolution of any video's thumbnail |
| Tag Extractor | 🟢 | P1 | Extract the tags on a public video |
| Title Character Counter | 🟢 | P1 | Live counter with truncation preview across search/suggested/mobile |
| Description Generator | 🔵 | P1 | Structured description with timestamps, links, CTA blocks |
| Channel Description Generator | 🟢 | P1 | About section in three lengths, with the 150-character fold shown |
| Hashtag Generator | 🟢 | P1 | Relevance-ranked hashtags from a topic |
| Channel ID Finder | 🟢 | P2 | Resolve handle/URL → channel ID |
| Video Timestamp Link Builder | 🟢 | P2 | Build `?t=` deep links from a transcript |
| Money / RPM Calculator | 🟢 | P1 | Earnings estimate by niche, geo and RPM band |
| Partner Program Checker | 🟢 | P1 | Distance from both YPP thresholds, plus the account rules |
| Channel Monetization Checker | 🟢 | P1 | Whether another channel is monetized, from its public page |
| Shadowban Detector | 🟢 | P1 | The five public settings that suppress a video, checked one by one |
| Metadata Viewer | 🟢 | P1 | Every field a video declares about itself, in one table |
| Image Downloader | 🟢 | P2 | Channel avatar and banner, and a video's auto-generated frames |
| Subscribe Link Generator | 🟢 | P2 | `?sub_confirmation=1` links, with HTML and Markdown snippets |
| Content Calendar | 🟢 | P2 | Dated upload schedule, slots spaced and pillars rotated |
| Comment Finder | 🟢 | P2 | Search a video's comments via the official Data API (needs a key) |
| Search Suggestions | 🟢 | P1 | Real searches from YouTube's own autocomplete, no volumes invented |
| Embed Code Generator | 🟢 | P2 | Responsive, lazy and privacy-enhanced embeds with working parameters |
| RSS Feed Generator | 🟢 | P2 | Channel and playlist feed URLs, verified against the live feed |
| Handle Availability Checker | 🟢 | P2 | A definite yes or no on an @handle, plus variants |
| Citation Generator | 🟢 | P3 | APA, MLA, Chicago, Harvard and BibTeX from the video's metadata |
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

## 2. Instagram

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

## 3. TikTok

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

## 4. X / Twitter

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

## 5. Facebook

| Tool | Tier | P | What it does |
| --- | --- | --- | --- |
| Ad Text Character Counter | 🟢 | P1 | Primary text / headline / description truncation preview |
| Post Preview | 🟢 | P2 | Feed and mobile rendering preview |
| Engagement Rate Calculator | 🟢 | P1 | |
| Audience Size Estimator | 🟣 | P3 | Reach estimate from targeting inputs |
| Page Audit | 🟣 | P2 | Completeness, cadence, response rate, CTA presence |

## 6. LinkedIn

| Tool | Tier | P | What it does |
| --- | --- | --- | --- |
| Post Character Counter & "See more" preview | 🟢 | P1 | Where the fold lands |
| Headline Generator | 🔵 | P2 | Keyword-aware headline options |
| Carousel (PDF) Builder | 🟣 | P2 | Slides → correctly sized PDF document post |
| Engagement Rate Calculator | 🟢 | P2 | |
| Profile SEO Audit | 🟣 | P3 | |

## 7. Pinterest

Pinterest is a search engine that happens to look like a feed, so its tools are search tools.

| Tool | Tier | P | What it does |
| --- | --- | --- | --- |
| Pin Preview | 🟢 | P1 | The Pin as the feed tile and the closeup render it — they show different things |
| Pin SEO Checker | 🟢 | P1 | Scores keyword placement across title, description, board and destination |
| Pin Image Sizer | 🟢 | P2 | One image → 2:3, 1:1 and 9:16 exports at Pinterest's own dimensions |
| Board Planner | 🔵 | P2 | Board and section structure from a topic, with naming that indexes |
| Keyword Research (Pinterest) | 🟣 | P2 | Guided-search expansion and seasonality for a seed keyword |
| Rich Pin Validator | 🟢 | P3 | Checks the product/article markup a Rich Pin needs |

## 8. Threads

| Tool | Tier | P | What it does |
| --- | --- | --- | --- |
| Post Preview | 🟢 | P1 | Feed rendering, the 500-character limit, and the chain split when it overflows |
| Bio Preview | 🟢 | P2 | Profile header as someone sees it after a single reply |
| Crosspost Checker | 🔵 | P2 | One draft checked against Threads, X and Bluesky limits at once |
| Reply Prompt Generator | 🔵 | P3 | Openers that earn replies, which is the signal Threads ranks on |

## 9. Content (`content`)

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

## 10. Media & assets (`media`)

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

## 11. Analytics & growth (`analytics`)

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

## 12. Utilities (`utility`)

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

---

## What is built

Fifty-nine tools ship in `ToolCatalogSeeder`. **Every 🟢 tool in the tables above is implemented.**
The category column is the functional category the tool is filed under; the platforms it serves are
listed separately on the tool itself.

🔑 marks the one tool that needs an operator-supplied API key; everything else runs on public
page metadata or pure computation.

| Category | Slug | Runner |
| --- | --- | --- |
| Previews | `link-preview-debugger` | `MetadataPreviewRunner` |
| Previews | `facebook-post-preview` | `FacebookPostPreviewRunner` |
| Previews | `linkedin-post-preview` | `LinkedInPostPreviewRunner` |
| Previews | `instagram-bio-preview` | `InstagramBioPreviewRunner` |
| Previews | `threads-post-preview` | `ThreadsPostPreviewRunner` |
| Previews | `threads-bio-preview` | `ThreadsBioPreviewRunner` |
| Previews | `pinterest-pin-preview` | `PinterestPinPreviewRunner` |
| Previews | `pinterest-pin-seo-checker` | `PinterestPinSeoCheckerRunner` |
| Previews | `safe-zone-guide` | `SafeZoneGuideRunner` |
| Previews | `rich-pin-validator` | `RichPinValidatorRunner` |
| Content | `social-media-character-counter` | `CharacterCounterRunner` |
| Content | `word-counter` | `WordCounterRunner` |
| Content | `headline-analyzer` | `HeadlineAnalyzerRunner` |
| Content | `readability-checker` | `ReadabilityCheckerRunner` |
| Content | `text-case-converter` | `TextCaseConverterRunner` |
| Content | `fancy-text-generator` | `FancyTextGeneratorRunner` |
| Content | `emoji-picker` | `EmojiPickerRunner` |
| Content | `cta-generator` | `CtaGeneratorRunner` |
| Content | `script-timer` | `ScriptTimerRunner` |
| Content | `x-thread-splitter` | `ThreadSplitterRunner` |
| Content | `facebook-ad-text-counter` | `FacebookAdTextCounterRunner` |
| Content | `hashtag-generator` 🔵 | `HashtagGeneratorRunner` |
| Content | `youtube-channel-description-generator` | `YouTubeChannelDescriptionGeneratorRunner` |
| Content | `youtube-content-calendar` | `YouTubeContentCalendarRunner` |
| Content | `youtube-search-suggestions` | `YouTubeSearchSuggestRunner` |
| Media | `youtube-thumbnail-downloader` | `YouTubeThumbnailDownloaderRunner` |
| Media | `youtube-image-downloader` | `YouTubeImageDownloaderRunner` |
| Media | `social-image-resizer` | `SocialImageResizerRunner` |
| Media | `image-compressor` | `ImageCompressorRunner` |
| Media | `image-format-converter` | `ImageFormatConverterRunner` |
| Media | `color-palette-extractor` | `ColorPaletteExtractorRunner` |
| Media | `carousel-splitter` | `CarouselSplitterRunner` |
| Media | `reels-cover-cropper` | `ReelsCoverCropperRunner` |
| Media | `story-templates-sizer` | `StoryTemplateSizerRunner` |
| Media | `pin-image-sizer` | `PinImageSizerRunner` |
| Media | `tweet-screenshot-generator` | `TweetScreenshotRunner` |
| Media | `qr-code-generator` | `QrCodeGeneratorRunner` |
| Analytics | `engagement-rate-calculator` | `EngagementRateCalculatorRunner` |
| Analytics | `youtube-money-calculator` | `YouTubeMoneyCalculatorRunner` |
| Analytics | `youtube-partner-program-checker` | `YouTubePartnerProgramCheckerRunner` |
| Analytics | `youtube-channel-monetization-checker` | `YouTubeChannelMonetizationCheckerRunner` |
| Analytics | `youtube-shadowban-detector` | `YouTubeShadowbanDetectorRunner` |
| Analytics | `tiktok-money-calculator` | `TikTokMoneyCalculatorRunner` |
| Utility | `utm-link-builder` | `UtmBuilderRunner` |
| Utility | `aspect-ratio-calculator` | `AspectRatioCalculatorRunner` |
| Utility | `posting-timezone-converter` | `PostingTimezoneConverterRunner` |
| Utility | `handle-strength-checker` | `HandleStrengthRunner` |
| Utility | `follower-milestone-countdown` | `FollowerMilestoneCountdownRunner` |
| Utility | `giveaway-winner-picker` | `GiveawayWinnerPickerRunner` |
| Utility | `youtube-tag-extractor` | `YouTubeTagExtractorRunner` |
| Utility | `youtube-channel-id-finder` | `YouTubeChannelIdFinderRunner` |
| Utility | `youtube-timestamp-link-builder` | `YouTubeTimestampLinkBuilderRunner` |
| Utility | `youtube-metadata-viewer` | `YouTubeMetadataViewerRunner` |
| Utility | `youtube-subscribe-link-generator` | `YouTubeSubscribeLinkGeneratorRunner` |
| Utility | `youtube-comment-finder` 🔑 | `YouTubeCommentFinderRunner` |
| Utility | `youtube-embed-code-generator` | `YouTubeEmbedCodeGeneratorRunner` |
| Utility | `youtube-rss-feed-generator` | `YouTubeRssFeedGeneratorRunner` |
| Utility | `youtube-handle-availability-checker` | `YouTubeHandleAvailabilityRunner` |
| Utility | `youtube-citation-generator` | `YouTubeCitationGeneratorRunner` |
| Utility | `username-availability-checker` | `UsernameAvailabilityRunner` |

### Previews are drawn, not described

Every tool in the previews category returns the `preview.social` view: a mock-up of the post,
profile, link card, Pin or video frame as the platform itself draws it, with the hidden half of a
truncated post greyed out in place rather than reported as a number. A fold position is a fact; your
own call to action sitting under "See more" is a decision. See [08](08-tool-engine.md) for the frame
contract.

### Consolidated

Several rows above are the same tool wearing a platform's name. They ship once, covering every
platform, rather than as near-duplicate catalog entries:

- Every platform's **engagement rate calculator** → `engagement-rate-calculator`.
- Every platform's **character counter**, including the YouTube title counter →
  `social-media-character-counter`.
- **X card debugger** and **social profile metadata preview** → `link-preview-debugger`, which now
  draws the X, Facebook, LinkedIn and chat-app cards side by side from one fetch.
- Instagram's **font / aesthetic text generator** → `fancy-text-generator`.
- Threads' and Pinterest's **character counters** → `social-media-character-counter`, which counts
  the Threads post limit and both Pinterest fields alongside every other surface.

### Image tools work from a URL, not an upload

The ten image tools were the group blocked on the `AcceptsFiles` / `ProducesArtifacts` path — upload
→ Spaces → signed URL — which still does not exist. They ship anyway, by taking a **public image
URL** instead of a file. The source is fetched once through `SafeHttpClient`, so the same SSRF guard
that protects every other URL tool applies; everything after that is GD, in
`App\Support\Media\ImageCanvas`.

Three consequences worth knowing before the upload path lands:

1. **Output is inline.** Results come back as `data:` URIs on the artifact rather than signed links.
   That keeps the whole path synchronous and storage-free, and it caps how much a run may return —
   the resizer at "all platforms" is about 2 MB, which is the practical ceiling. Runs carrying
   artifacts are not cached, so none of these runners implement `Cacheable`.
2. **A run with no URL still works.** Every image tool draws a deterministic placeholder — a
   gradient with a grid and an off-centre ring — so a visitor sees what the tool does before they
   have an asset to give it. The result says so in a warning rather than pretending otherwise.
3. **GD, not Imagick.** JPEG, PNG, WebP and GIF; no AVIF, and no fonts in the image, which is why
   the post-screenshot tool draws SVG rather than rasterising text.

When the upload path is wired, these runners gain an `AcceptsFiles` branch and keep the URL input.
Nothing else about them changes.

### Two tools that answer honestly rather than confidently

**Username availability** was listed as blocked because "the platforms actively block probing". They
do — so the tool separates the two questions it can be asked. The handle is checked against each
network's own length and character rules, which is pure computation and always right; then the
public profile is requested where that returns a usable status. Instagram, TikTok, X and Threads
answer automated requests with a login wall, so their rows say **check manually** and link straight
to the profile. Guessing there would eventually report a taken handle as free, which is worse than
naming the limit.

**The post screenshot generator** draws whatever text it is given, so a card it produces proves
nothing about who posted what. It deliberately draws no verification badge, and every run carries a
warning saying it is a mock-up rather than a screenshot.

## Launch scope

| Phase | Tools | Rationale |
| --- | --- | --- |
| **P1** | 37 tools across all categories | Enough breadth for search coverage and a credible paid tier |
| **P2** | +37 | Depth in the categories that convert best |
| **P3** | +14 | Long tail; built only if telemetry justifies it |

## Adding a tool

1. Add the row here, under the platform it serves, and pick one of the five functional categories
   for the catalog entry — never a platform name.
2. Create `app/Domain/Tools/Runners/<Name>Runner.php` implementing `ToolRunner`.
3. Register it in `ToolRegistry` and add a catalog entry (slug, schema, instructions, example).
4. Write the runner test with at least one golden-file case.
5. Add the frontend renderer only if the generated form + generic result view is insufficient.

Steps 1–4 are backend-only; a tool with a plain input schema needs **zero frontend code**. That is
the point of the engine — see [08](08-tool-engine.md).
