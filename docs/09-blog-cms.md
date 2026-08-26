# 09 — Blog & CMS

The bar: **WordPress-class capability with a modern editor**, where what the editor shows is exactly
what the published page shows.

## Content format

Posts are stored as **portable block JSON** — an ordered array of typed blocks — with rendered HTML
kept alongside as a cache ([ADR 0003](adr/0003-portable-block-json-for-posts.md)).

```json
{
  "version": 1,
  "blocks": [
    { "id": "b_01H…", "type": "paragraph", "data": { "html": "<p>Text with <strong>marks</strong>.</p>" } },
    { "id": "b_02H…", "type": "heading",   "data": { "level": 2, "text": "Why this matters" } },
    { "id": "b_03H…", "type": "quote",     "data": { "text": "…", "cite": "Jane Doe" } },
    { "id": "b_04H…", "type": "image",     "data": { "mediaId": "med_…", "alt": "…", "caption": "…", "size": "wide" } },
    { "id": "b_05H…", "type": "embed",     "data": { "provider": "youtube", "url": "…", "aspect": "16:9" } },
    { "id": "b_06H…", "type": "code",      "data": { "language": "php", "code": "…", "filename": "…" } },
    { "id": "b_07H…", "type": "html",      "data": { "html": "…", "sanitized": true } },
    { "id": "b_08H…", "type": "toolCard",  "data": { "toolSlug": "youtube-tag-extractor" } }
  ]
}
```

Why JSON and not HTML-in-a-column:

- Blocks can be re-rendered when the design system changes; stored HTML cannot.
- Embeds stay as data, so we can swap the embed strategy (privacy-friendly facade → real iframe on
  click) globally.
- Content is portable to a future mobile app or newsletter renderer.
- Block-level diffing makes revisions genuinely useful.

`content_html` is regenerated on save by the *same* renderer the frontend uses (a shared TS package
executed server-side via a small render endpoint, so there is exactly one implementation).

## Block types at launch

| Block | Notes |
| --- | --- |
| `paragraph` | Rich text: bold, italic, code, link, strikethrough, highlight |
| `heading` | H2–H4; H1 is reserved for the post title. Auto-generates the ToC |
| `list` | Ordered / unordered / checklist |
| `quote` | With optional citation and a "pull quote" variant |
| `image` | From the media library or drag-drop. Sizes: inline, wide, full-bleed |
| `gallery` | Grid or carousel |
| `video` | Direct upload or file URL, poster frame |
| `audio` | Player with waveform |
| `gif` | Treated separately from image: autoplay/pause control, reduced-motion aware |
| `embed` | YouTube, Vimeo, X/Twitter, Instagram, TikTok, CodePen, Spotify, generic oEmbed |
| `code` | Highlighted, language selector, filename, copy button |
| `html` | Raw HTML, **sanitised on save and again on render** ([21](21-security.md)) |
| `callout` | info / tip / warning / danger |
| `divider` | Plain, dots, or asterism |
| `table` | Simple grid with an optional header row |
| `button` | CTA with variant + tracked click |
| `toolCard` | Embeds a live tool card — the internal-linking engine of the blog |
| `newsletter` | Inline capture form |
| `faq` | Q&A pairs; emits `FAQPage` JSON-LD automatically |

### Extensibility

Adding a block requires three files and no core changes:

```
packages/blocks/src/blocks/<type>/
├── schema.ts      zod schema + defaults + migration function
├── editor.tsx     TipTap node view (the editing experience)
└── render.tsx     the published rendering — imported by BOTH editor and site
```

Registration is a single entry in `packages/blocks/src/registry.ts`. Because `render.tsx` is shared,
WYSIWYG fidelity is structural rather than a promise someone has to keep: the editor literally
renders the published component and overlays selection chrome on it.

Unknown block types (from a newer deploy, or a removed plugin) render as a labelled placeholder and
are preserved on save — content is never silently destroyed.

## Editor experience

The requirement is a Medium/Notion feel where the *content* is the whole screen and every other
option lives elsewhere.

```
┌──────────────────────────────────────────────────────────────────┐
│  ← Posts    Draft · saved 12s ago      [Preview] [⚙] [Publish ▾] │  ← slim top bar
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│              ┌────────────────────────────────────┐              │
│              │  Post title, big and editable      │              │
│              │                                    │              │
│              │  Content at the exact published    │              │
│              │  width, typography and spacing.    │              │
│              │                                    │              │
│              │  ⊕ ⠿  block handles on hover       │              │
│              └────────────────────────────────────┘              │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
                                    ⚙ opens a right-side slide-over:
                                      [ Post | SEO | Social | Advanced ]
```

- **`/` slash menu** to insert any block; **`⌘K`** for links; **`⌘/`** for the command palette.
- **Drag handle + ⊕** on hover, keyboard-reorderable (`⌘⇧↑/↓`) for accessibility.
- **Paste intelligence**: a YouTube URL on its own line becomes an embed; a code block from an IDE
  keeps its language; pasted Word/Docs HTML is normalised into blocks, not dumped as markup.
- **Autosave** every 3 s of idle to a revision, with an explicit "restore" browser.
- **Focus mode** hides even the top bar.
- **Live word count + reading time** in the status bar.
- **Real published preview**: the editor canvas already *is* the published rendering, so "Preview"
  only adds the site chrome.

### The settings slide-over

Four tabs so the writing surface stays clean:

| Tab | Contents |
| --- | --- |
| **Post** | Status, publish date/schedule, author, category, tags, featured image, excerpt, featured flag, comments toggle |
| **SEO** | Meta title/description with live SERP preview and pixel-width warnings, focus keyword with an on-page checklist, canonical, robots, schema type |
| **Social** | OG and X card overrides with a live preview at real dimensions |
| **Advanced** | Slug, custom CSS class, redirect-from paths, revision history, block JSON inspector |

## Statuses and lifecycle

```
draft ──▶ scheduled ──(cron)──▶ published ──▶ unpublished ──▶ archived
   └──────────────────────────────▲   │
                                  └───┴──▶ deleted (soft, 30-day recovery) ──▶ purged
```

| Status | Publicly visible | In sitemap | Notes |
| --- | --- | --- | --- |
| `draft` | no | no | Shareable via a signed preview link |
| `scheduled` | no | no | `scheduled_for` set; `PublishScheduledPosts` runs every minute |
| `published` | yes | yes | |
| `unpublished` | no | no | Was public; URL returns 410 unless a redirect is set |
| `archived` | no | no | Kept for reference, excluded from all lists |
| `deleted` | no | no | Soft-deleted, restorable for 30 days |

Transitions are guarded by a state machine, and each one fires an event that drives notifications,
ISR revalidation and search reindexing.

## Management screens

- **Grid + table toggle** on the admin list; filters for status, category, tag, author, date;
  saved views.
- **Bulk actions**: publish, unpublish, archive, delete, restore, change category, add/remove tags,
  change author, regenerate SEO. Every bulk action is queued, batched, and reports per-row results.
- **Inline quick edit** for title, slug, status, category, tags without leaving the list.
- **Duplicate** a post as a new draft.

## Public blog

Grid layout, 12 posts per page, responsive 1/2/3 columns. Card shows featured image (AVIF/WebP with
blur placeholder), category chip, title, excerpt, author, date and reading time. Category and tag
archives are paginated and independently SEO-configurable. Related posts are chosen by shared tags →
same category → recency. Every post ends with relevant tool cards; the newsletter block appears
mid-article and in the footer.

**Admin can disable the blog entirely** via a setting: routes 404, nav links disappear, sitemap
entries are dropped.

---

## What is built, and where it differs from the above

The public blog is complete; the admin editor is not. See
[24 — Implementation Status](24-implementation-status.md) for the current line.

Three deliberate departures from the design above:

**`content_html` is not generated.** The spec called for a shared TypeScript renderer executed
server-side so that stored HTML and the frontend could never disagree. In practice the frontend
renders blocks directly in a React Server Component, which gets the same guarantee — one
implementation — without a render endpoint, a second runtime in the API container, or a cache
column to invalidate. `content_text` *is* generated, because fulltext search needs a column to
index. If a non-React consumer ever needs HTML (a newsletter renderer, a mobile app), that is the
point to build the render endpoint, not before.

**14 of the 19 block types exist.** `paragraph`, `heading`, `list`, `quote`, `image`, `embed`,
`code`, `html`, `callout`, `divider`, `table`, `button`, `toolCard` and `faq`. The five missing
ones — `gallery`, `video`, `audio`, `gif`, `newsletter` — all depend on the media library, which
is not built yet.

**`toolCard` is resolved by the page, not the block.** The renderer takes a `tools` lookup keyed
by slug and stays free of server-only imports. This matters beyond tidiness: the editor has to
mount these same components, and a component that imports the server API client cannot run in the
editor at all. `toolSlugsIn()` collects the slugs so a page can resolve them in one pass.

### Where the pieces live

| Concern | Location |
| --- | --- |
| Status lifecycle and allowed transitions | `app/Domain/Blog/Enums/PostStatus.php` |
| Sanitising on save | `app/Domain/Blog/Blocks/BlockSanitizer.php` |
| Word count, reading time, search text | `app/Domain/Blog/Blocks/BlockTextExtractor.php` |
| HTMLPurifier profiles | `config/purifier.php` |
| Public endpoints | `app/Http/Controllers/Api/V1/Blog/BlogController.php` |
| Kill switch | `app/Http/Middleware/EnsureBlogEnabled.php` |
| Scheduled publishing | `app/Console/Commands/PublishScheduledPosts.php` |
| Rendering | `apps/web/src/components/blocks/block-renderer.tsx` |

### Adding a block type

Three edits, no core changes:

1. A case in `BlockType`, and its field handling in `BlockSanitizer::sanitizeData()`.
2. An entry in the `RENDERERS` map in `block-renderer.tsx`.
3. If it carries prose, add it to `BlockType::isProse()` so it counts toward reading time and is
   searchable.

Unknown types render as a labelled placeholder and are preserved verbatim on save, so content
written by a newer deploy survives a rollback. There is a test for exactly that.

### Two sanitising notes worth keeping

`mark` is an HTML5 element and the profiles declare an HTML 4.01 doctype, so it is registered as a
custom element in `config/purifier.php` — as are `figure` and `figcaption`. Without that
registration HTMLPurifier raises `Element 'mark' is not supported` and the highlight mark
disappears from every post. Custom elements also require `HTML.DefinitionID` on each profile.

`code` blocks are deliberately **not** purified. They are rendered as a text node, so escaping is
the renderer's job; running code through an HTML sanitiser silently corrupts it.
