# 16 — SEO

Organic search is the primary acquisition channel, so SEO is a system requirement, not a checklist
someone runs before launch.

## The metadata model

Every indexable entity — post, tool, category, tag, static page — has a `seo_meta` row
([04](04-data-model.md)). Resolution is layered, most specific wins:

```
explicit seo_meta value  →  entity-derived default  →  site-wide template  →  hard-coded fallback
```

Site-wide templates are editable in settings, e.g.
`{{title}} | MetaCreator.Dev` and, for tools, `{{name}} — Free {{platform}} Tool | MetaCreator.Dev`.

The admin UI shows a **live SERP preview** with pixel-width measurement (not character counts —
Google truncates by pixels), warning at 580 px for titles and 960 px for descriptions.

### Tool defaults are generated, not blank

The "entity-derived default" step above is a real generator for tools, not a shrug:
`App\Domain\Seo\Services\ToolSeoDefaults` produces a complete payload for any tool nobody has
tuned by hand — title, meta description, og title, og description, focus keyword, card type,
schema type. Tool pages carry the organic traffic and convert the paywall, so a blank meta
description (a snippet Google writes for us) and a bare og title (a grey box in a timeline) are
not acceptable failure modes for the long tail of the catalog.

The rules the copy follows:

| Field | Rule |
| --- | --- |
| Title | Leads with the tool's own name, because that is the phrase people search for. Ends with the qualifier that earns the click — "Free" **only** when the tool genuinely is; a premium tool never claims it. Names the platform only when the tool serves exactly one, and only when it still fits inside 60 characters |
| Description | The tagline (the promise) plus one clause on what it costs to try, built to fit ~155 characters rather than truncated mid-word |
| og title | A timeline gives a link one line, and the name alone is a label rather than a reason to tap — so the hook is the tagline's first clause, in sentence case, inside 70 characters |
| og description | The promise plus where it runs. Large card, always: a tool page's share is a screenshot-shaped promise |
| Focus keyword | The tool's name, lowercased. These are named after the query on purpose ("engagement rate calculator"), so the name *is* the keyword |

Defaults are merged **field by field** under whatever an admin stored, and a blank string counts as
a gap rather than a choice — a cleared input means "use the default", and storing the empty string
would publish it. The merge happens in `ToolDetailResource`, not in `SeoResource`: that resource
also feeds the admin editor, which has to show what was actually typed, or the first Save turns a
fallback into a hard-coded override.

`ToolCatalogSeeder` writes the same generator's output, so a tool with hand-written copy in the
seeder and one left alone are described the same way.

## Per-page implementation

Next's `generateMetadata` is used on every route; nothing is set client-side.

| Element | Rule |
| --- | --- |
| `<title>` | Unique on every URL. Verified by a crawl test in CI |
| Meta description | Unique, written for clicks, 140–160 chars |
| Canonical | Absolute, self-referencing by default; paginated pages self-canonicalise (never to page 1) |
| Robots | `index,follow` default; `noindex` on search results, dashboards, admin, thin tag archives |
| OG / Twitter | Full set with a 1200×630 image; dynamic OG images per tool and post via `next/og` |
| `hreflang` | Not needed at launch (English only), but the metadata builder already supports it |
| Pagination | `rel=prev/next` in the payload plus crawlable `?page=` links |

## Structured data

Emitted as JSON-LD from typed builders in `apps/api` so the same graph serves the site and any
future surface:

| Page | Schema |
| --- | --- |
| Site-wide | `Organization`, `WebSite` with `SearchAction` |
| Tool page | `SoftwareApplication` (`applicationCategory: UtilitiesApplication`) + `Offer` reflecting the real tier + `HowTo` from the instructions blocks |
| Blog post | `BlogPosting` with author, dates, image, `wordCount`; `FAQPage` if a FAQ block exists |
| Category / tag | `CollectionPage` + `BreadcrumbList` |
| Pricing | `Product` with `AggregateOffer` |
| Contact / About | `ContactPage` / `AboutPage` |
| All pages | `BreadcrumbList` |

`Offer` honesty matters: a `premium` tool must not claim `price: 0`. Mis-marked pricing is a
manual-action risk.

## URL design

```
/                                   landing
/tools                              catalog (filterable, crawlable facets only)
/tools/{category}                   category archive
/tools/{slug}                       tool page          ← the money pages
/blog                               grid, paginated
/blog/{slug}                        post
/blog/category/{slug}               category archive
/blog/tag/{slug}                    tag archive
/pricing /about /contact /terms /privacy /cookies
/dashboard/* /c0ns0le/*             noindex (the staff console is unlisted, see below)
```

Rules: lowercase, hyphenated, no dates in post URLs (so posts can be refreshed without changing the
URL), no trailing slashes, no more than one level of nesting on tools. **Slugs never change after
publication**; a slug edit creates a 301 in the `redirects` table automatically.

## Sitemaps

`/sitemap.xml` is an index referencing `/sitemap-tools.xml`, `/sitemap-posts.xml`,
`/sitemap-categories.xml`, `/sitemap-pages.xml`. Generated from the API, cached, and revalidated on
publish events. Only `published`, visible, indexable entities appear. `lastmod` is real. Nothing is
in a sitemap that returns anything other than 200.

`/robots.txt` disallows `/dashboard`, `/api`, and `?*` search parameters, and points to the
sitemap index. The staff console at `/c0ns0le` is deliberately **not** listed there, nor in any
sitemap or feed: a `Disallow` line is a public directory of the paths worth attacking. It stays out
of the index through `robots: { index: false }` on its own layout, and behind the staff check
regardless.

## Performance as SEO

Core Web Vitals targets, enforced by a Lighthouse CI budget that fails the build:

| Metric | Budget |
| --- | --- |
| LCP | < 2.0 s (mobile, p75) |
| INP | < 150 ms |
| CLS | < 0.05 |
| TTFB | < 400 ms |
| JS shipped to a tool page | < 120 KB gzipped |

Enablers: RSC by default with client components only where interaction demands it, ISR for all
public content, AVIF/WebP with explicit dimensions and blur placeholders, self-hosted fonts with
`font-display: swap` and preloaded subsets, and route-level code splitting for custom tool UIs.

## Internal linking

The highest-leverage, least-glamorous part of the strategy:

- Every tool page lists 4–6 related tools (computed + editorially pinned — see [08](08-tool-engine.md)).
- Every tool page lists relevant blog posts.
- Every post ends with tool cards and can embed `toolCard` blocks inline.
- Category pages link to every tool and post in the category.
- The footer carries the top tools by category — sitewide equity to the money pages.

## Content programme

Per tool: a "how to" post, a "best practices" post, and a comparison/alternatives post. Per
platform: a pillar guide linking to every tool for that platform. A quarterly refresh cycle updates
the highest-traffic posts, which is consistently cheaper than writing new ones.

## Monitoring

Search Console and Bing Webmaster verified via settings-configurable meta tags. A weekly job checks
for: pages that lost more than 20% of impressions, `noindex` on pages that should be indexed, 404s
with inbound links, duplicate titles or descriptions, and posts with no internal links pointing at
them. Results land in the admin dashboard as an **SEO health** panel.
