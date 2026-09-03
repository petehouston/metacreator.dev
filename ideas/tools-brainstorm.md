# Tools Brainstorm — candidate mini tools

A brainstormed backlog of **new** tools, checked against
[`docs/07-tool-catalog.md`](../docs/07-tool-catalog.md) and the runners in
`apps/api/app/Domain/Tools/Runners` so nothing here duplicates something that already exists.
This file is a *backlog*, not a commitment — the catalog doc stays the source of truth for what
ships.

## How to read it

- **Category** is one of the five that exist: `previews`, `content`, `media`, `analytics`,
  `utility`. Never a platform name.
- **Tier** follows the rules in the catalog doc: 🟢 free (pure computation over the user's own
  input), 🔵 account (benefits from persistence), 🟣 premium (spends metered API quota, sustained
  compute or storage).
- Rows marked **↔** are adjacent to something that already exists and need a scope decision before
  they are built, not a second implementation.
- Rows marked **⚠** depend on an upstream we do not control — the same risk that stranded
  `youtube-subtitle-downloader` and `instagram-profile-picture-downloader`.

**Count: 243 candidates.**

| Category | Candidates |
| --- | --- |
| `previews` | 34 |
| `content` | 50 |
| `media` | 50 |
| `analytics` | 44 |
| `utility` | 65 |

---

## Shortlist — the ten to build first

Ranked on search demand × build cost × durability (no upstream to go dark).

| # | Tool | Category | Tier |
| --- | --- | --- | --- |
| 1 | Caption Line-Break Fixer | `content` | 🟢 |
| 2 | Platform Limits Lookup | `utility` | 🟢 |
| 3 | Zero-Width Character Cleaner | `content` | 🟢 |
| 4 | Subtitle Format Converter | `media` | 🟢 |
| 5 | Fake DM / Chat Screenshot Generator | `media` | 🟢 |
| 6 | Feed Crop Simulator | `previews` | 🟢 |
| 7 | Avatar Circle-Crop Preview | `previews` | 🟢 |
| 8 | Ad Metrics Calculator | `analytics` | 🟢 |
| 9 | YouTube Chapter Validator | `utility` | 🟢 |
| 10 | FTC Disclosure Generator | `content` | 🟢 |

---

## 1. Previews (`previews`) — 34

The answer is a picture, and the fold is a width rather than a character count.

| # | Tool | Tier | What it does | Input | Output |
| --- | --- | --- | --- | --- | --- |
| P1 | Feed Crop Simulator | 🟢 | Draws every network's feed and profile-grid crop over one image so the user sees what gets cut ↔ distinct from `social-image-resizer`, which exports files | Image upload; networks to check | Per-network crop overlays, safe area %, list of crops that lose subject matter |
| P2 | Avatar Circle-Crop Preview | 🟢 | The circle mask at each network's real avatar diameter, down to the 32px comment size | Image upload | Circle renders at 6 diameters, legibility verdict, corrected export |
| P3 | Cross-Platform Caption Fold Tester | 🟢 | One draft, the "see more" fold rendered on all seven networks at once | Caption text | Seven feed cards with the fold line drawn, chars before fold per network |
| P4 | Reddit Post Preview | 🟢 | Card, compact and classic views, with the title truncation each uses | Title, body, subreddit, flair, thumbnail | Three rendered views, title truncation warnings |
| P5 | Discord Embed Preview | 🟢 | How a link unfurls in Discord, from the page's own OG tags ⚠ fetches the URL | URL, or manual OG fields | Rendered Discord embed, missing-tag list |
| P6 | Slack Unfurl Preview | 🟢 | Slack's unfurl, which reads a different tag set from Discord's | URL, or manual OG fields | Rendered unfurl, tag gap list |
| P7 | WhatsApp Link Preview | 🟢 | WhatsApp's small-thumbnail unfurl and its 300KB image ceiling ⚠ | URL | Rendered bubble, image weight verdict |
| P8 | Telegram Link Preview | 🟢 | Telegram's instant-view vs. plain unfurl | URL | Both renders, which one Telegram will pick |
| P9 | iMessage Link Preview | 🟢 | The large-card unfurl Apple shows for a link sent alone | URL | Rendered bubble at phone width |
| P10 | Bluesky Post Preview | 🟢 | 300-grapheme limit, facets, quote-post nesting | Post text, images, quoted post | Rendered card, grapheme count, facet map |
| P11 | Mastodon Post Preview | 🟢 | 500-char default, CW fold, alt-text badges | Post text, CW, media alt | Rendered toot with the CW collapsed and expanded |
| P12 | YouTube Search Result Preview | 🟢 | Title + thumbnail as a search row, where truncation differs from the watch page | Title, channel, thumbnail, duration | Desktop and mobile search rows |
| P13 | YouTube Suggested Sidebar Preview | 🟢 | The narrow sidebar card, the harshest title truncation on the platform | Title, thumbnail, channel | Sidebar card render, chars visible |
| P14 | YouTube Shorts Shelf Preview | 🟢 | The vertical shelf tile and its two-line title | Title, cover frame | Shelf tile render |
| P15 | YouTube End Screen Layout Preview | 🟢 | Element placement against the 20s window and the safe rectangle | Video length, elements chosen | Layout diagram, overlap and timing warnings |
| P16 | Thumbnail Size Ladder Preview | 🟢 | One thumbnail at every size YouTube serves it, TV down to sidebar | Thumbnail upload | Six renders, smallest legible size verdict |
| P17 | TikTok Feed Preview | 🟢 | Caption fold under the real UI chrome, which eats the right third | Caption, cover frame | Full-bleed render with chrome overlay |
| P18 | Instagram Reels Caption Preview | 🟢 | The two-line Reels fold, different from the feed fold | Caption, cover | Reels render with fold |
| P19 | Instagram Story Sticker Safe Preview | 🟢 | Where link, poll and mention stickers may sit without the UI covering them ↔ adjacent to `story-templates-sizer` | Story image, stickers chosen | Overlay with sticker-legal zones |
| P20 | Instagram Grid Tile Preview | 🟢 | A single image as the square grid tile vs. the full post ↔ narrower than the 🔵 grid planner | Image | Tile crop and full render side by side |
| P21 | X Profile Header Preview | 🟢 | Banner with the avatar punch-out and the bio block, at three widths | Banner, avatar, name, bio | Three renders, hidden-area map |
| P22 | LinkedIn Feed Card Preview | 🟢 | Headline + first lines as the feed shows them ↔ complements `linkedin-post-preview` | Name, headline, post text | Feed card render |
| P23 | LinkedIn Newsletter Preview | 🟢 | Title, subtitle and cover in the subscribe card and the email | Title, subtitle, cover | Both renders, truncation points |
| P24 | Facebook Page Cover Preview | 🟢 | The cover at desktop and mobile, where the crops disagree badly | Cover image | Both crops, common safe rectangle |
| P25 | Pinterest Board Cover Preview | 🟢 | Board cover and section tiles at their real ratios | Cover image, board name | Tile renders |
| P26 | Twitch Offline Banner Preview | 🟢 | The offline screen with the chat column and player chrome over it | Banner image | Render with chrome, safe area |
| P27 | Twitch Panel Preview | 🟢 | Panels stacked at 320px, where most panel art is illegible | Panel images, titles | Stacked render at true width |
| P28 | Podcast Episode Card Preview | 🟢 | Apple and Spotify episode rows, whose title truncation differs | Show art, episode title, description | Both rows rendered |
| P29 | Google Discover Card Preview | 🟢 | The Discover card, which needs a 1200px-wide image to appear at all | Title, image, source | Card render, image-width verdict |
| P30 | Push Notification Preview | 🟢 | Title and body truncation in iOS and Android notification rows | Title, body, app name | Both rows rendered |
| P31 | Dark Mode Post Preview | 🟢 | The same post on light and dark UI, catching white-background images that halo | Image, caption, network | Side-by-side renders, contrast verdict |
| P32 | Link-in-Bio Page Preview | 🟢 | A bio-link page at phone width, with tap-target and fold checks | Links, avatar, headline | Phone render, accessibility notes |
| P33 | YouTube Channel Search Preview | 🟢 | The channel row in search: avatar, handle, subscriber count, description fold | Channel fields | Row render |
| P34 | Threads Reply Chain Preview | 🟢 | A chained post as the thread line renders it ↔ complements `threads-post-preview` | Post text, chain parts | Chain render with the 500-char split |

---

## 2. Content (`content`) — 50

Text in, better text out. All deterministic; nothing here needs a model to be useful.

| # | Tool | Tier | What it does | Input | Output |
| --- | --- | --- | --- | --- | --- |
| C1 | Caption Line-Break Fixer | 🟢 | Inserts the invisible character each platform needs so blank lines survive the paste | Caption with line breaks; target network | Fixed caption, copy button, before/after render |
| C2 | Zero-Width Character Cleaner | 🟢 | Names every non-printing character, smart quote and ZWJ in a draft and offers a cleaned copy | Any text | Annotated text, per-character table, cleaned output |
| C3 | FTC / ASA Disclosure Generator | 🟢 | Picks the compliant disclosure wording *and* placement for the network and format | Network, format, deal type, region | Disclosure string, placement rule, a pass/fail on the user's draft |
| C4 | Disclosure Placement Checker | 🟢 | Checks an existing caption: is the disclosure above the fold and outside the hashtag block? | Caption, network | Pass/fail per rule with the offending position marked |
| C5 | Hashtag Set Validator | 🟢 | Count limits, duplicates, character budget consumed, casing consistency | Hashtag list, network | Per-rule verdict, cleaned set |
| C6 | Restricted Hashtag Checker | 🟢 | Looks a set up against known banned and action-limited tags ⚠ needs a maintained list | Hashtag list | Flagged tags with the restriction type |
| C7 | Emoji Render Checker | 🟢 | Finds ZWJ sequences, skin tones and flags that fall back to tofu on specific platforms | Text with emoji | Per-platform render table, safe substitutes |
| C8 | Emoji Density Score | 🟢 | Emoji-to-word ratio against the range each platform's spam heuristics tolerate | Caption | Score, per-platform band, trim suggestion |
| C9 | Crosspost Limit Checker | 🟢 | One draft against every network's limit at once ↔ generalises the Threads-only version scoped in the catalog | Draft text, media count | Per-network pass/fail, split plan where it overflows |
| C10 | Thread Numbering Formatter | 🟢 | Applies `1/`, `1/12` or emoji numbering to an existing split ↔ pairs with `x-thread-splitter` | Split posts, style | Numbered posts, copy-all |
| C11 | Alt-Text Checker | 🟢 | Length ceilings per network plus the accessibility rules people get wrong | Alt text, network | Length verdict, rule checklist, rewrite hints |
| C12 | Community Guidelines Risk Checker | 🟢 | A caption read against IG/TikTok moderation-sensitive vocabulary ↔ the multi-platform sibling of `youtube-advertiser-friendly-checker` | Caption, network | Risk bands with the triggering phrases quoted |
| C13 | Clickbait Score | 🟢 | Scores a title on the patterns that get demoted rather than the ones that get clicks | Title | Score, per-signal breakdown |
| C14 | Power Word Highlighter | 🟢 | Marks emotional, urgency and curiosity words in a draft | Text | Highlighted text, counts by class |
| C15 | Filler Word Trimmer | 🟢 | Finds hedges, filler and passive constructions in a script | Script text | Marked text, tightened version, words saved |
| C16 | Hook Pattern Filler | 🟢 | Fills proven hook templates with the user's topic ↔ free counterpart to the 🔵 hook library | Topic, format | 15 hook variants by pattern class |
| C17 | Video Script Formatter | 🟢 | Turns prose into scene/beat blocks with running timecodes | Script text, words-per-minute | Formatted beats with timecodes, total runtime |
| C18 | Teleprompter Formatter | 🟢 | Reflows a script to a readable line width with breath marks | Script, line width | Prompter-ready text, estimated read time |
| C19 | Shot List Builder | 🟢 | Extracts shots and B-roll cues from a formatted script | Script with beats | Shot table, B-roll checklist, CSV |
| C20 | Keyword Density Checker | 🟢 | Density and placement of a target phrase in a description | Description, target keyword | Density %, first-occurrence position, over-use flag |
| C21 | Multi-Platform Title Truncator | 🟢 | One title, every network's truncation point, in one table | Title | Per-network truncated forms and character budgets |
| C22 | Title Case Converter (style guides) | 🟢 | AP, Chicago, APA and sentence case ↔ narrower than `text-case-converter` | Title, style | Converted title, rules applied |
| C23 | Jargon & Acronym Detector | 🟢 | Flags terms a general audience will not know | Text, audience level | Flagged terms with plain-language swaps |
| C24 | Reading Level per Platform | 🟢 | Grade level against the level each network's median post sits at ↔ extends `readability-checker` | Text, network | Grade level, platform band, rewrite hints |
| C25 | Sentence Rhythm Analyzer | 🟢 | Sentence-length distribution — the thing that makes captions feel flat | Text | Histogram, monotony flag, split suggestions |
| C26 | Crutch Phrase Finder | 🟢 | Repeated words and phrases across a batch of posts | Multiple posts | Frequency table, per-post markers |
| C27 | Translation Length Budget | 🟢 | How far a caption expands in each target language, so layouts survive | Source text, target languages | Expansion %, per-network overflow warnings |
| C28 | Weighted Character Counter (CJK) | 🟢 | Counts by grapheme and by each platform's own weighting ↔ deeper than `social-media-character-counter` | Text, network | Weighted count, grapheme count, breakdown |
| C29 | Caption Variant Builder | 🟢 | Deterministic template variants of one caption for A/B testing | Caption, variant count | Variants with the changed element labelled |
| C30 | Carousel Copy Splitter | 🟢 | A numbered list into one copy block per slide, with per-slide char budgets | List text, slide count | Per-slide copy, overflow flags |
| C31 | Newsletter Section Splitter | 🟢 | A long post into newsletter sections with subheads | Long text | Sectioned draft with headings |
| C32 | Quote Card Text Fitter | 🟢 | How many characters fit per line at a chosen size and card ratio | Quote, card size, font size | Line-broken text, overflow verdict |
| C33 | Chapter Title Formatter | 🟢 | Formats a chapter list to the exact shape a description needs | Rough chapter notes | Formatted list ↔ feeds the chapter validator (U2) |
| C34 | Description Template Builder | 🟢 | A reusable description skeleton with link, CTA and disclosure blocks | Blocks chosen, links | Filled template per network |
| C35 | Pinned Comment Builder | 🟢 | The first-comment block: links, timestamps, disclosure | Links, timestamps | Formatted comment, per-network limits applied |
| C36 | Bio Builder per Platform | 🟢 | A bio built to each network's limit and line rules ↔ pairs with `instagram-bio-preview` | Role, niche, CTA, link | Per-network bios at their exact limits |
| C37 | Link-in-Bio Copy Structure | 🟢 | Orders and labels bio links by intent | Link list, goal | Ordered labels, tap-priority rationale |
| C38 | Content Pillar Planner | 🟢 | Splits a niche into pillars with a rotation ratio ↔ feeds `youtube-content-calendar` | Niche, pillar count, cadence | Pillars with weights and a rotation order |
| C39 | Series Naming Builder | 🟢 | A consistent episode-title convention that stays inside title limits | Series name, numbering style | Convention, 10 sample titles, length check |
| C40 | Hashtag Rotation Builder | 🟢 | Splits a tag pool into non-repeating sets across N posts | Tag pool, posts, set size | Rotation schedule, repeat distance |
| C41 | Keyword Cluster Splitter | 🟢 | Groups a keyword dump into topic clusters for a content plan | Keyword list | Clusters with a suggested pillar per cluster |
| C42 | Poll Question Builder | 🟢 | Poll and option text inside each platform's option limits | Topic, network | Question + options, char verdict |
| C43 | Comment Reply Templates | 🔵 | A saved bank of reply patterns for recurring comment types | Comment categories, tone | Reply bank, saved per account |
| C44 | Brand Pitch DM Builder | 🔵 | An outreach DM built from real stats rather than adjectives | Stats, brand, ask | Pitch text at DM length, follow-up variant |
| C45 | Sponsorship Email Builder | 🔵 | The long-form version of the pitch, with a deliverables table | Stats, deliverables, rate | Email draft, deliverables table |
| C46 | Media Kit Copy Blocks | 🔵 | The prose a media kit needs — bio, audience, case study ↔ text half of the 🟣 media kit generator | Stats, highlights | Copy blocks, three lengths each |
| C47 | Run-of-Show Builder | 🔵 | A livestream rundown with segment timings that add up | Segments, total length | Timed rundown, overrun warnings |
| C48 | Contract Deliverables Checklist | 🔵 | Turns a deal description into a checklist with due dates | Deal terms, dates | Checklist, ICS export |
| C49 | Slug Generator | 🟢 | A clean slug for a blog post and its matching social handles/hashtag | Title | Slug, hashtag form, handle-safe form |
| C50 | HTML → Caption Plain Text | 🟢 | Strips markup to caption-safe text, keeping links as bare URLs | HTML or rich text | Plain caption, link list |

---

## 3. Media & assets (`media`) — 50

| # | Tool | Tier | What it does | Input | Output |
| --- | --- | --- | --- | --- | --- |
| M1 | Subtitle Format Converter | 🟢 | SRT ↔ WebVTT ↔ plain text, no upstream to break — unlike the stranded downloader | Subtitle file or pasted text | Converted file in the chosen formats |
| M2 | Subtitle Timestamp Shifter | 🟢 | Offsets every cue by ±N seconds, or rescales for a frame-rate change | Subtitle file, offset or ratio | Resynced file, first/last cue preview |
| M3 | Subtitle Line-Length Fixer | 🟢 | Enforces the 42-char line and the characters-per-second ceiling | Subtitle file, CPS target | Rewrapped file, list of cues that were too fast |
| M4 | Subtitle Merge / Split | 🟢 | Merges short cues and splits long ones at sentence boundaries | Subtitle file, min/max duration | Rebuilt file, cue count delta |
| M5 | Transcript Speaker Formatter | 🟢 | Applies consistent speaker labels and paragraphing to a raw transcript | Transcript, speaker names | Formatted transcript, txt/docx |
| M6 | EXIF Viewer & Stripper | 🟢 | Shows the GPS coordinates and device data in a photo before it gets posted, then removes them | Image upload | Metadata table, map pin if geotagged, stripped image |
| M7 | IPTC Credit Editor | 🟢 | Writes creator, credit and copyright fields that some platforms preserve | Image, credit fields | Image with IPTC written |
| M8 | Video Bitrate & File Size Calculator | 🟢 | Resolution + length + bitrate against each platform's upload ceiling | Resolution, fps, length, bitrate | Predicted file size, per-platform pass/fail, recommended bitrate |
| M9 | Video Spec Checker | 🟢 | One file's specs read against Reels, Shorts and TikTok at once | Video file or its specs | Per-platform verdict table, what to change |
| M10 | Fake DM / Chat Screenshot Generator | 🟢 | An iMessage, WhatsApp or IG DM thread drawn on canvas ↔ joins the existing `fake-*` family | Messages, names, avatars, timestamps | PNG/JPG/WebP |
| M11 | Fake Reddit Post Generator | 🟢 | A Reddit card with vote column, flair and award row | Subreddit, title, body, score | PNG/JPG/WebP |
| M12 | Fake LinkedIn Post Generator | 🟢 | A LinkedIn feed card with the "see more" fold shown | Name, headline, post, reactions | PNG/JPG/WebP |
| M13 | Fake Threads Post Generator | 🟢 | A Threads card with the chain line | Handle, post, replies | PNG/JPG/WebP |
| M14 | Fake Bluesky Post Generator | 🟢 | A Bluesky card with the domain handle rendered correctly | Handle, post, counts | PNG/JPG/WebP |
| M15 | Fake Discord Message Generator | 🟢 | A Discord message block with role colour and reply reference | Username, role colour, message | PNG/JPG/WebP |
| M16 | Fake Livestream Chat Generator | 🟢 | A Twitch/YouTube chat column with badges and emotes | Messages, badges | PNG, and a transparent overlay variant |
| M17 | Fake Subscribe Notification | 🟢 | The YouTube subscribe/bell toast used in end cards | Channel name, avatar | PNG with alpha |
| M18 | Blur-Edge Vertical Fill | 🟢 | Fits a landscape frame into 9:16 with a blurred pillar fill instead of bars | Image or frame | Filled 9:16 export |
| M19 | Padding / Letterbox Fitter | 🟢 | Fits any image to a target ratio with a solid or gradient pad rather than a crop | Image, ratio, fill | Padded export |
| M20 | Grid Puzzle Splitter | 🟢 | Slices one image across a 3×3 or 3×N profile grid ↔ different job from `carousel-splitter` | Image, grid size | Numbered tiles, upload order, zip |
| M21 | Video Frame Extractor | 🔵 | Pulls candidate thumbnail frames from an uploaded clip | Video upload, frame count | Frames as PNGs, sharpness ranking |
| M22 | Thumbnail Contrast Checker | 🟢 | Text-over-image contrast at real feed size ↔ free counterpart to the 🟣 legibility check | Thumbnail with text, or text + colours | Contrast ratios, pass/fail, minimum size |
| M23 | Brand Palette Exporter | 🟢 | A palette out as CSS variables, ASE, JSON and a contrast matrix ↔ extends `color-palette-extractor` | Colours or an image | CSS/ASE/JSON, pairing matrix |
| M24 | Duotone / Brand Overlay | 🟢 | Applies a two-colour treatment so a feed reads as one brand | Image, two colours | Treated image |
| M25 | Watermark Position Preview | 🟢 | Where a watermark lands under each platform's UI chrome before batch-applying | Watermark, position, networks | Overlay previews, safe positions |
| M26 | Twitch Emote Pack Sizer | 🟢 | One artwork to 112/56/28px with the transparency check Twitch enforces | Artwork | Three PNGs, per-size legibility verdict |
| M27 | Discord Emoji & Sticker Sizer | 🟢 | 128px emoji and 320px APNG stickers inside the file-size ceiling | Artwork | Sized assets, weight verdict |
| M28 | Twitch Panel Image Generator | 🟢 | Panel art at 320px with title text that stays legible | Title, colours, icon | Panel PNGs |
| M29 | Profile Banner Multi-Exporter | 🟢 | One artwork to every network's banner spec at once ↔ the banner sibling of `social-image-resizer` | Artwork | Zip of banners, per-network safe-area overlay |
| M30 | App Icon / Favicon Set | 🟢 | A logo to the full icon set a link-in-bio page or PWA needs | Logo | Icon set zip, manifest snippet |
| M31 | Circle Avatar Exporter | 🟢 | Avatar with an optional ring, exported at every network's size | Image, ring colour | Sized PNGs |
| M32 | Screenshot Mockup Framer | 🟢 | Puts a phone or browser bezel around a screenshot for carousel slides | Screenshot, device | Framed PNG, transparent variant |
| M33 | Animated GIF → MP4 | 🟢 | Converts a GIF to the MP4 every platform actually wants ↔ the inverse of the planned GIF maker | GIF | MP4, size saved |
| M34 | Audio Waveform Image | 🟢 | A waveform strip from an audio file, for quote posts and audiograms | Audio file, colours | PNG/SVG waveform |
| M35 | Audiogram Builder | 🟣 | Waveform + caption + cover rendered to a square or vertical video | Audio, cover, captions | MP4 |
| M36 | Silence Trimmer | 🟣 | Cuts dead air from a recording | Audio/video, threshold | Trimmed file, time saved |
| M37 | Loudness (LUFS) Checker | 🟣 | Measures against each platform's normalisation target | Audio file | LUFS, true peak, per-platform verdict |
| M38 | Subtitle Burn Preview | 🟢 | Where burned-in captions land under the UI chrome, before the burn | Frame, subtitle style | Overlay preview, collision warnings |
| M39 | PDF Carousel Spec Validator | 🟢 | Checks a PDF against LinkedIn's page-size, count and weight rules ↔ free counterpart to the 🟣 builder | PDF | Per-rule verdict, fixes |
| M40 | Slide Deck → Carousel Images | 🔵 | A PDF or deck to per-slide images at the right ratio | PDF/deck | Numbered images, zip |
| M41 | Image Sequence → Carousel Zip | 🟢 | Orders, renames and zips images in upload order | Images | Ordered zip, upload order list |
| M42 | Batch Asset Renamer | 🟢 | Applies a naming convention across a batch | Files, convention | Renamed zip, mapping table |
| M43 | Image Upscaler | 🟣 | Raises a small asset to a usable size | Image, factor | Upscaled image |
| M44 | Background Remover | 🟣 | Cut-out for thumbnails and stickers | Image | PNG with alpha |
| M45 | Sticker / PNG Trimmer | 🟢 | Crops transparent padding and reports the true bounding box | PNG with alpha | Trimmed PNG, bounds |
| M46 | Aspect Padding Calculator | 🟢 | The exact pad pixels to move between two ratios without a crop | Source and target ratio | Pad values, ffmpeg filter string |
| M47 | Contact Sheet Generator | 🟢 | A grid contact sheet of a shoot for client selection | Images | Contact sheet PNG/PDF |
| M48 | Cover Frame Comparator | 🟢 | Several candidate covers side by side at feed size | Images | Comparison sheet at true size |
| M49 | Image Weight Budget Checker | 🟢 | Whether an image survives each platform's re-encode without visible loss | Image | Predicted re-encode weight, quality verdict |
| M50 | Colour Blindness Simulator | 🟢 | A thumbnail or chart through three colour-vision deficiencies | Image | Three simulated renders, problem areas marked |

---

## 4. Analytics & growth (`analytics`) — 44

Arithmetic the user can check, over numbers the user already has. Nothing here needs a platform API.

| # | Tool | Tier | What it does | Input | Output |
| --- | --- | --- | --- | --- | --- |
| A1 | Ad Metrics Calculator | 🟢 | CPM, CPC, CTR, CPA and ROAS solved in any direction | Any three of spend, impressions, clicks, conversions, revenue | The remaining metrics, with the identity shown |
| A2 | Break-Even Sponsorship Calculator | 🟢 | What a brand must earn per view for a deal to make sense | Rate, expected views, product margin, conversion rate | Break-even conversion rate, verdict |
| A3 | Affiliate Earnings Calculator | 🟢 | Clicks → conversions → commission, with the cookie window applied | Views, CTR, conversion rate, commission | Earnings estimate, per-1k-views figure |
| A4 | Creator Payout Fee Calculator | 🟢 | What actually lands after platform and processor fees | Platform (Patreon/Ko-fi/Substack/Super Chat/diamonds), gross | Net, fee breakdown, effective % |
| A5 | Follower Growth Rate Calculator | 🟢 | Monthly growth %, doubling time, projection to a target | Two counts and their dates, target | Growth rate, doubling time, projected date |
| A6 | Watch-Hours Calculator | 🟢 | Views × average view duration against the 4,000-hour threshold ↔ deepens `youtube-partner-program-checker` | Views, AVD, window | Hours accrued, gap, views still needed |
| A7 | A/B Significance Calculator | 🟢 | Two thumbnails, two CTRs, one honest confidence answer ↔ free entry to the 🟣 A/B tester | Impressions and clicks per variant | p-value, confidence, "not yet conclusive" verdict |
| A8 | Sample Size Calculator | 🟢 | How many impressions a test needs before it can say anything | Baseline rate, effect to detect, confidence | Required sample per variant, expected days |
| A9 | Post Percentile Calculator | 🟢 | One post against the user's own median, as a percentile and a z-score | Post metric, historical values | Percentile, z-score, outlier verdict |
| A10 | Outlier Detector | 🟢 | Flags the posts that actually moved a metric series | Metric series | Flagged points, thresholds used |
| A11 | Reach / Impressions / Frequency Calculator | 🟢 | The three-way identity most creators get backwards | Any two of the three | The third, plus overexposure warning |
| A12 | Retention Curve Reader | 🟢 | Paste retention points, get the drop-off moments named | Retention percentages by timestamp | Curve, drop-off list, 30-second verdict |
| A13 | AVD ↔ APV Converter | 🟢 | Average view duration and average percentage viewed, both directions | Video length and either metric | The other metric, benchmark band |
| A14 | Video Length vs Retention Analyzer | 🔵 | Correlates length against retention across the user's own library | CSV export | Scatter, best-performing length band |
| A15 | Posting Cadence vs Growth | 🔵 | Whether posting more actually grew the account | CSV of posts and follower counts | Correlation, honest "no signal" answer when there is none |
| A16 | Best Time from Own Export | 🔵 | Posting windows from the user's own analytics export ↔ free-input counterpart to the 🟣 best-time tools | CSV export | Heatmap by day/hour, top windows |
| A17 | Hashtag Performance Tracker | 🔵 | Which tags actually accompany the user's best posts | CSV of posts, tags and reach | Per-tag median reach, keep/drop list |
| A18 | Content Pillar Performance Split | 🔵 | Performance by pillar, so the plan can be rebalanced | CSV with pillar labels | Per-pillar medians, rebalanced ratio |
| A19 | Multi-Platform Follower Aggregator | 🔵 | One total across networks, weighted by engagement rather than raw count | Counts and ER per network | Weighted audience figure, per-network share |
| A20 | Engagement Benchmark by Tier | 🟢 | The ER band for the user's follower tier and niche ↔ free lookup behind the 🔵 benchmark tool | Followers, niche, network | Median, quartiles, the user's position |
| A21 | Follower/Following Ratio Health | 🟢 | The ratio and what it signals on each network | Followers, following, network | Ratio, band, note |
| A22 | Views Needed for a Revenue Goal | 🟢 | Works backwards from a monthly income target | Target income, RPM, network | Views per month, per day, per video at a cadence |
| A23 | Subscribers Needed for a Goal | 🟢 | The membership version of the same question | Target income, tier price, churn | Subscribers needed, gross vs. net |
| A24 | Shorts / Reels Pool Revenue | 🟢 | Revenue-share pools, which do not work like ad revenue | Views, pool RPM band, share % | Estimate with the range stated |
| A25 | Rate Card Range Calculator | 🟢 | A defensible price range per format from reach and ER ↔ free version of the 🟣 rate card builder | Reach, ER, format, niche | Low/fair/ask per format |
| A26 | Audience-Weighted CPM | 🟢 | Adjusts a rate for where the audience actually is | Country split, base CPM | Weighted CPM, effective rate |
| A27 | Deal Structure Comparator | 🟢 | Flat fee vs. CPM vs. affiliate, on the same expected views | Three offers, expected views | Expected value each, break-even crossover |
| A28 | Usage Rights Uplift Calculator | 🟢 | What whitelisting and paid usage should add to a rate | Base rate, term, channels | Uplifted rate, itemised |
| A29 | Cost Per Video / Production ROI | 🟢 | Production cost against revenue per video | Costs, revenue, hours | Cost per video, ROI, effective hourly |
| A30 | Tax Set-Aside Estimator | 🟢 | A holding percentage from gross — explicitly a rule of thumb, not advice | Gross, region, bracket | Set-aside amount, disclaimer |
| A31 | Conversion Funnel Calculator | 🟢 | Views → clicks → signups → sales, with the leak named | Stage counts | Stage rates, weakest stage |
| A32 | Newsletter Benchmark Calculator | 🟢 | Open, click and unsubscribe rates against list-size bands | List size, opens, clicks, unsubs | Rates, benchmark band |
| A33 | Churn & LTV Calculator | 🟢 | Membership churn into lifetime value | Churn %, ARPU | Average lifetime, LTV, LTV:CAC if CAC given |
| A34 | Compound Growth Planner | 🟢 | The cadence needed to hit a follower goal by a date | Current count, target, date | Required monthly growth, posts/week at observed rate |
| A35 | Milestone Date Predictor | 🟢 | Simple linear and compound projections to the next round number ↔ free version of the 🔵 predictor | Two counts, dates | Both projections, honest range |
| A36 | Seasonality Index | 🔵 | Which months are actually up, from the user's own history | Monthly series | Index per month, planning note |
| A37 | Cross-Post Cannibalisation Estimator | 🔵 | Whether posting the same asset twice split the audience | Two posts' metrics, overlap estimate | Combined vs. expected, verdict |
| A38 | Audience Overlap Calculator | 🟢 | Overlap between two audiences from a sampled follower list | Two follower samples | Overlap %, method note |
| A39 | Confidence Interval for Small Samples | 🟢 | Error bars on a rate computed from few events | Successes, trials | Rate with interval, "too few" warning |
| A40 | Sponsored Post Performance Report | 🔵 | The numbers a brand asks for after a campaign, formatted | Post metrics, deal terms | Report table, CSV/PDF |
| A41 | Media Kit Stats Formatter | 🔵 | Raw numbers into the phrasing a media kit uses ↔ stats half of the 🟣 media kit generator | Metrics per network | Formatted stat blocks |
| A42 | Goal Tracker Dashboard | 🔵 | Manual monthly entries into a trend the user actually owns | Periodic entries | Trend chart, pace vs. goal |
| A43 | Time-to-Publish Tracker | 🔵 | Hours per asset, so the cadence plan is honest | Task times per video | Hours per asset, sustainable cadence |
| A44 | Revenue Diversification Score | 🟢 | Concentration risk across income sources | Revenue by source | Concentration index, risk note |

---

## 5. Utilities (`utility`) — 65

| # | Tool | Tier | What it does | Input | Output |
| --- | --- | --- | --- | --- | --- |
| U1 | Platform Limits Lookup | 🟢 | One filterable reference: characters, video length, file size, aspect, hashtag count, bio length, alt-text length, across every network | Network and/or field filter | Reference table, per-network card, deep links to the matching tool |
| U2 | YouTube Chapter Validator | 🟢 | Checks a description against the chapter rules — 0:00 first, three minimum, ten seconds each ↔ free counterpart to the 🟣 generator | Description text | Per-rule verdict, the offending lines quoted, fixed list |
| U3 | Click-to-Chat Link Builder | 🟢 | WhatsApp, Telegram and Messenger links with a prefilled message ↔ check overlap with `app-deep-link-builder` | Number/handle, message | Link, QR, HTML snippet |
| U4 | Discord Timestamp Generator | 🟢 | The `<t:unix:f>` tokens, all seven formats, live-previewed | Date, time, timezone | Seven tokens with their rendered forms |
| U5 | Unix Timestamp Converter | 🟢 | Epoch ↔ human time in a chosen timezone, for APIs and Discord | Timestamp or date | Both forms, ISO 8601, relative |
| U6 | Timecode ↔ Seconds Converter | 🟢 | HH:MM:SS.mmm and seconds, both directions, with frame rates | Timecode or seconds, fps | The other form, frame number |
| U7 | Add-to-Calendar Link Generator | 🟢 | An ICS file plus Google/Outlook/Yahoo links for a premiere or livestream | Title, start, length, timezone, URL | ICS download, three links, embed snippet |
| U8 | Multi-Timezone Announcement Builder | 🟢 | The "9pm ET / 6pm PT / 2am BST" block, generated correctly ↔ extends `posting-timezone-converter` | Time, timezone, audience regions | Announcement text, per-network length check |
| U9 | Audience Timezone Overlap Finder | 🟢 | The hours when two or more audience regions are both awake | Regions, waking hours | Overlap windows, best single hour |
| U10 | Countdown Snippet Generator | 🟢 | An embeddable countdown to a launch | Target datetime, style | HTML/JS snippet, image fallback |
| U11 | Bluesky Domain Handle Helper | 🟢 | Generates the DNS TXT record and the `.well-known` file for a custom handle, then verifies it | Domain, DID | Record value, file contents, verification result |
| U12 | Fediverse `rel="me"` Generator | 🟢 | The link tag Mastodon needs for verification, plus a live check | Profile URL, site URL | Tag snippet, verification result |
| U13 | Social Meta Tag Generator | 🟢 | OG and Twitter card tags from filled fields ↔ the write side of `link-preview-debugger` | Title, description, image, type | Tag block, per-platform completeness check |
| U14 | Open Graph Image Validator | 🟢 | Whether an OG image survives each platform's crop and weight limits | Image or URL | Per-platform crop preview, weight verdict |
| U15 | Crawler Fetch Tester | 🟢 | What `facebookexternalhit`, `Twitterbot` and friends actually receive ⚠ fetches the URL | URL, crawler | Status, returned tags, blocked-by-robots verdict |
| U16 | Social Crawler robots.txt Checker | 🟢 | Whether a site accidentally blocks the unfurl bots | Domain | Per-crawler allow/deny, the offending rule |
| U17 | Unfurl Cache Refresh Guide | 🟢 | The per-platform debugger URLs and steps to force a re-scrape | URL | Deep links to each platform's debugger, ordered steps |
| U18 | Social Profile JSON-LD Generator | 🟢 | `Person`/`Organization` markup with `sameAs` across every profile | Name, role, profile URLs | JSON-LD block, validation result |
| U19 | VideoObject JSON-LD Generator | 🟢 | Video schema for a page that embeds a video | Video fields | JSON-LD block |
| U20 | Video Sitemap Generator | 🟢 | The video extension entries for a sitemap | Video list | XML block |
| U21 | Share Intent Link Builder | 🟢 | Official share URLs for a dozen platforms, prefilled ↔ complements `social-media-embed-code-generator` | URL, text, hashtags | Share links, HTML button row |
| U22 | Follow Button Embed Generator | 🟢 | The official follow/subscribe widget for each network | Handle, network | Embed snippet, fallback link |
| U23 | Social Icon Row Generator | 🟢 | An accessible icon link row for a site footer | Profile URLs, style | HTML + CSS, SVG icons inlined |
| U24 | Responsive Embed Sizer | 🟢 | The wrapper maths for any embed ratio ↔ generalises the YouTube-only embed tool | Ratio, max width | CSS snippet, live preview |
| U25 | Video URL Parser | 🟢 | Any social URL into its IDs, timestamps and parameters, named | URL | ID table, canonical form, parameter meanings |
| U26 | Username Variant Generator | 🟢 | One name into variants that satisfy each network's charset and length rules ↔ feeds `username-availability-checker` | Desired name | Per-network legal variants, ranked |
| U27 | Unicode Confusable Detector | 🟢 | Finds the lookalike characters used to impersonate a handle | Handle | Confusable set, risk verdict |
| U28 | Impersonation Handle Scanner | 🔵 | Generates and checks the likely impersonation variants of a brand ⚠ per-network checks | Handle | Taken/free variant table, flagged lookalikes |
| U29 | Domain & Handle Match Finder | 🔵 | Which domains match an available handle set ⚠ registrar lookups | Name | Domain + handle availability grid |
| U30 | Vanity URL Builder | 🟢 | The canonical profile URL form for each network, from a handle | Handle | Per-network URLs, the ones that need an ID instead |
| U31 | Bulk UTM Generator | 🔵 | A CSV of destinations into consistently tagged links ↔ batch version of `utm-link-builder` | CSV, tagging scheme | Tagged CSV, naming-convention check |
| U32 | UTM Auditor | 🟢 | Paste a list of live links and find the inconsistent casing and stray parameters | URL list | Inconsistency report, normalised list |
| U33 | Bulk QR Generator | 🔵 | A CSV of links into branded QRs ↔ batch version of `qr-code-generator` | CSV, style | Zip of QRs, index sheet |
| U34 | QR Size & Distance Calculator | 🟢 | The print size a QR needs to scan from a given distance | Distance, error correction, data length | Minimum size in mm/px, quiet zone |
| U35 | Short Link Health Checker | 🔵 | Whether a batch of short links still resolve, and to where ↔ narrower than the planned bulk link checker | Link list | Status, final destination, redirect count |
| U36 | Follower Count Badge | 🔵 | An embeddable badge showing a live count ⚠ needs per-network fetching | Handle, network, style | Badge image/snippet |
| U37 | RSS Feed Builder (multi-platform) | 🟢 | Feed URLs for podcasts, Reddit, Mastodon and YouTube in one place ↔ generalises `youtube-rss-feed-generator` | Profile or show URL | Feed URL, verified live, OPML entry |
| U38 | OPML Builder | 🟢 | A subscription bundle for feed readers | Feed list | OPML file |
| U39 | Podcast RSS Validator | 🟢 | Checks a podcast feed against the Apple and Spotify requirements | Feed URL | Per-rule verdict, blocking issues first |
| U40 | Podcast Chapter JSON Generator | 🟢 | The chapters JSON the podcast namespace defines | Chapter list | JSON file, hosting note |
| U41 | ID3 Tag Editor | 🟢 | Writes episode metadata into an MP3 | MP3, fields | Tagged MP3 |
| U42 | Content Calendar CSV Validator | 🔵 | Checks an import file before it breaks a calendar ↔ guards `youtube-content-calendar` | CSV | Row-level errors, cleaned file |
| U43 | ICS Content Schedule Exporter | 🔵 | A publishing plan out as a calendar subscription | Schedule | ICS file, subscribe URL |
| U44 | Filename Convention Builder | 🟢 | A naming scheme for assets, with a validator | Scheme tokens | Convention string, sample names, regex |
| U45 | Batch Slugifier | 🟢 | A list of titles into clean slugs, collisions flagged | Title list | Slug list, collision report |
| U46 | Emoji Shortcode Converter | 🟢 | `:smile:` ↔ 😄 across Slack, Discord and GitHub dialects ↔ adjacent to `emoji-picker` | Text, source and target dialect | Converted text, unmapped list |
| U47 | Markdown → Platform Text | 🟢 | Markdown into what each network actually renders — most render none of it | Markdown, network | Converted text, list of what was dropped |
| U48 | Moderation Wordlist Builder | 🔵 | A blocked-terms list in the upload format each platform expects | Terms, networks | Per-platform file, count vs. their limit |
| U49 | Comment Filter Regex Builder | 🟢 | Builds and tests the regex some platforms accept for moderation | Patterns, test strings | Regex, match highlighting |
| U50 | Brand Mention Regex Builder | 🟢 | A pattern that catches misspellings of a brand name for monitoring | Brand name | Regex, matched variants |
| U51 | Giveaway Rules Generator | 🟢 | Promotion rules that satisfy each platform's own requirements ↔ pairs with `giveaway-winner-picker` | Prize, region, network, dates | Rules text, per-network required clauses |
| U52 | Giveaway Entry Deduplicator | 🟢 | Strips duplicate and disqualified entries before the draw ↔ feeds the picker | Entry list, rules | Cleaned list, removal reasons |
| U53 | Comment Export Parser | 🟢 | A pasted comment dump into a structured entry list | Pasted comments | Table of handle, text, timestamp; CSV |
| U54 | Winner Announcement Builder | 🟢 | The announcement post plus the verifiable seed | Winners, seed | Announcement text, verification block |
| U55 | Collab Mention List Builder | 🟢 | Tags and mentions for a collab post, inside each network's mention cap | Handles, network | Formatted mention block, cap verdict |
| U56 | Currency Converter for Rate Cards | 🟢 | Converts a rate card at a rate the user supplies, so nothing silently goes stale | Amounts, rate | Converted card, rate and date stamped |
| U57 | Sponsorship Invoice Builder | 🔵 | An invoice from deliverables, with tax lines | Deliverables, rates, tax | Invoice PDF, numbering |
| U58 | Usage Rights Cheat Sheet | 🟢 | What each platform's terms allow a brand to do with a creator's post | Network, usage type | Plain-language answer, clause reference |
| U59 | Music Licence Checker | 🟢 | Whether a track's licence covers monetised social use ⚠ needs a maintained source | Track/licence type | Verdict per platform, caveats |
| U60 | Repost Credit Builder | 🟢 | A correctly attributed repost caption and the permission DM to send first | Original post URL, creator | Credit line, permission message |
| U61 | Handle Squat Watchlist | 🔵 | Watches a set of handles on new networks and reports when one frees up ⚠ | Handle list | Weekly digest, status changes |
| U62 | Two-Account Follower Diff | 🔵 | What changed between two exported follower lists | Two exports | Gained, lost, net |
| U63 | Link Rot Reporter | 🔵 | Finds dead links in a bio, description or pinned comment | Profile or text with links | Per-link status, suggested replacement |
| U64 | Deep Link QR Builder | 🟢 | An app deep link with a web fallback, as a QR ↔ combines `app-deep-link-builder` and the QR generator | Target, fallback URL | QR, link, fallback behaviour note |
| U65 | Frame Rate & Duration Planner | 🟢 | Frames, duration and fps solved in any direction, for edit planning | Any two of frames, duration, fps | The third, plus common-fps table |

---

## Notes on risk and sequencing

**Build the durable ones first.** Everything marked 🟢 with no ⚠ is pure computation over user input:
no upstream can take it away, and it can run on the client. That is the same property that makes the
existing free catalog stable, and the absence of it is what stranded two built tools.

**Two whole surfaces are missing.** Reddit and Discord have zero tools in the catalog and appear
here 8 times between them (P4, P5, M11, M15, U4, U5, U37, U46). Both are deterministic-quirk
platforms — Reddit's crops and markdown, Discord's timestamp tokens — which is exactly the shape
that suits this catalog.

**The `fake-*` family has an obvious grid to fill.** Six exist; M10–M17 complete the set across
Reddit, LinkedIn, Threads, Bluesky, Discord, DMs, livestream chat and notifications, all on the
canvas renderer that already ships.

**Free tools should funnel into premium ones.** U2 → the 🟣 chapter generator, A7 → the 🟣 A/B
tester, A25 → the 🟣 rate card builder, A41 → the 🟣 media kit generator, M22 → the 🟣 legibility
check. Each free tool answers the cheap half of a question the premium tool answers fully.

**Anything marked ⚠ needs a stated fallback** before it is built: what the tool says when the
upstream stops serving. The catalog's honesty rule already covers this — say plainly where a
platform allows nothing to be built.
