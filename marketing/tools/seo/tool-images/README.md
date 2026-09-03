# Tool page social cards (open-graph images)

Design catalogue for the 1200 × 630 image every tool page ships as its
`og:image` / `twitter:image`. Open [`index.html`](index.html) in a browser —
no build step, no server, just double-click it.

**112 cards, 21 families.** Families 01–20 draw a single tool (YouTube
Thumbnail Downloader) twenty different ways in five colourways each, so a
layout can be judged against the palettes it will actually ship in. Family 21
is the house style, drawn once per registered platform.

Every card is real HTML at its true 1200 × 630 and scaled down with a
transform, so the gallery is not a mockup — it is the artwork.

## Viewing modes

| Mode | What it does |
| --- | --- |
| **Gallery** | Two up. For comparing families. |
| **Large** | One up. For judging type sizes at feed scale. |
| **Export 1:1** | Every card at its true 1200 × 630 — screenshot a card and you have the PNG. |

The search box filters by family, colourway and platform (`twitch`,
`terminal`, `ink`, `centred`…).

## Family 21 — Centred browser (the house style)

The card *is* the browser: chrome bar edge to edge across the top with the live
tool URL in it, and the page below it on the site's own dark ground
(`ink-1000 → ink-900`). The real brand mark sits above the tool name, everything
centred, and the stickers are pasted over the bottom of the page — the same
treatment as the *Stickers* family (one filled label, the rest outlined, 12 px
corners, each tilted a few degrees). The filled
"free" sticker carries the network's own primary colour — the only place a
foreign brand colour is allowed on our cards, and what makes a card
recognisable as belonging to that platform's set before a word is read.

One card exists for each of the twelve platforms registered in
`apps/web/src/config/site.ts`:

| # | Platform | Sticker colour | Sample tool |
| --- | --- | --- | --- |
| 101 | YouTube | `#ff0033` | YouTube Thumbnail Downloader |
| 102 | Instagram | `#e1306c` | Instagram Bio Preview |
| 103 | TikTok | `#fe2c55` | TikTok Money Calculator |
| 104 | X | `#e7e9ea` | X Thread Splitter |
| 105 | Facebook | `#1877f2` | Facebook Post Preview |
| 106 | LinkedIn | `#0a66c2` | LinkedIn Post Preview |
| 107 | Threads | `#f5f5f5` | Threads Post Preview |
| 108 | Pinterest | `#e60023` | Pinterest Pin Preview |
| 109 | Bluesky | `#0085ff` | Bluesky Image Downloader |
| 110 | Twitch | `#9146ff` | Twitch Image Downloader |
| 111 | Spotify | `#1db954` | Spotify Cover Art Downloader |
| 112 | Apple Podcasts | `#9933cc` | Apple Podcasts Artwork Downloader |

X and Threads are black-on-white brands, so on our dark ground their sticker
takes the brand's off-white with dark text — the same recognition, legible on
the ground we actually ship.

Each card's mock result takes the shape that tool's answer really has
(thumbnail set, avatar + bio lines, two money figures, pin tiles, square
artwork), tinted with the platform colour. That is deliberate: showing the
answer outperforms describing it on utility pages.

## The rules these cards follow

- **1200 × 630, 1.91:1.** Every network crops to it. Keep 60 px clear on all
  edges — X trims the sides, Slack trims the top.
- **Headline ≥ 56 px.** A card is read at ~360 px wide in a feed; 70 px becomes
  21 px. Nine words maximum.
- **Mark and domain in the same place on every card**, so a hundred shares read
  as one publisher.
- **Say the free part.** "Free · no sign-up" answers the only objection a
  searcher arriving at a utility page has.
- **Text lives outside the pixels.** Google reads `og:image:alt` and the
  surrounding markup, not the image. Always send the tool's real description
  there.

## Shipping it — this is built

Family 21 is implemented and every tool in the catalog already carries its card.
The generator is an artisan command that draws with GD (no headless browser, no
Node in the image):

```bash
php artisan tools:social-cards youtube-thumbnail-downloader   # one tool
php artisan tools:social-cards --all                          # the whole catalog
php artisan tools:social-cards --all --force                  # redraw after a design change
php artisan tools:social-cards --all --dry-run                # sizes only, writes nothing
php artisan tools:social-cards --all --site-url=https://metacreator.dev
```

`--site-url` matters when generating from a laptop: the address is drawn into the
card as artwork, and without it the URL bar reads `localhost:3000`.

| File | What it does |
| --- | --- |
| `app/Console/Commands/GenerateToolSocialCards.php` | The CLI, the reporting, the exit code |
| `app/Domain/Seo/Actions/AttachToolSocialCard.php` | Stores the file, files the media row, points `seo_meta.og_media_id` at it |
| `app/Domain/Seo/Services/ToolSocialCard.php` | The design: layout, platform palette, mock shapes |
| `app/Support/Media/SocialCardCanvas.php` | The drawing toolkit — supersampled GD, gradients, stickers, the brand mark |
| `resources/fonts/` | DM Sans and JetBrains Mono, vendored (SIL OFL) |

Once a card exists it shows in the admin at **Tools → *(tool)* → SEO & sharing →
Share image**, and is served from the media disk like any other upload.

Behaviour worth knowing:

- **Idempotent.** A tool that already has a generated card is skipped, so the
  command is safe on every deploy and only new tools cost anything.
- **An admin's choice wins.** An image picked by hand in *SEO & Sharing* is never
  overwritten without `--force`, and the run reports it rather than skipping
  silently.
- **One file per tool.** The path is `media/og/tools/<slug>.png`, so redrawing
  replaces rather than piling up a file and a media row per run.
- **Alt text is written too** — `"<name> on metacreator.dev — <tagline>"` — because
  crawlers read `og:image:alt`, never the pixels.
- **Size.** PNG at maximum compression, and JPEG instead if a card would exceed
  300 KB. Across the 94 live tools: 75 KB average, 88 KB largest.

## Exporting a PNG by hand

For a one-off that is not a tool page — a deck, an ad, a newsletter header —
switch the gallery to **Export 1:1**, screenshot the card region and save it as
PNG. For anything tied to a tool, run the command instead: a hand-exported image
goes stale the moment a tagline changes, and nothing tells you it has.
