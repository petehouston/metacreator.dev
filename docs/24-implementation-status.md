# 24 — Implementation Status

What is **built and tested** versus what is **specified but not yet written**. Every document in
this handbook describes the intended design; this one is the only place that claims something
exists. Keep it honest — a specification that reads as a status report is how a team ends up
surprised.

Last updated: 2026-09-03.

## Legend

| Mark | Meaning |
| --- | --- |
| ✅ | Built, covered by tests, working end to end |
| 🟡 | Partially built — details in the notes |
| ⬜ | Specified in `docs/`, schema and models may exist, no application code yet |

## Platform

| Area | Status | Notes |
| --- | --- | --- |
| Monorepo, Docker stack, Makefile | ✅ | `make setup` from a clean clone |
| Migrations & schema (all domains) | ✅ | Tables exist for every documented feature, including ones with no code yet |
| RBAC permission catalog & seeded roles | ✅ | [06](06-auth-rbac.md); asserted by architecture tests |
| API envelope, error catalog, exception renderer | ✅ | Domain exceptions self-render by capability |
| Design tokens & core UI kit | ✅ | [17](17-design-system.md) — "Signal" palette: cobalt / emerald / coral |
| Dashboard app shell | ✅ | `app/(app)` route group: fixed collapsible rail, topbar, ⌘K palette, mobile drawer |
| Admin app shell | ✅ | `app/(admin)` route group: permission-filtered nav, ⌘K with user lookup, queue badges |
| Queue topology (Horizon supervisors) | ✅ | `config/horizon.php` now matches [18](18-queues-workers.md). Before this, only `default` was consumed — `analytics`, `tools` and `mail` jobs were dispatched and never run |
| CI: Pest, PHPStan, Pint, ESLint, tsc | ✅ | All green |

## Tools

| Area | Status | Notes |
| --- | --- | --- |
| Tool registry, runner contract, access & quota services | ✅ | [08](08-tool-engine.md) |
| Public catalog, search, filters, tool pages | ✅ | Sort now sits above the grid, on the count's row and right-aligned, rather than inside the filter panel: a filter changes *which* tools you see, a sort only their order, and the two do not belong in one box |
| Per-tool SEO defaults | ✅ | `ToolSeoDefaults` generates a complete, tier-honest payload — title, description, og title/description, focus keyword, card type — for any tool nobody has tuned. Stored overrides win field by field, and a cleared field falls back rather than publishing an empty string ([16](16-seo.md)) |
| Generated `ToolForm` and result renderers | ✅ | |
| Tool runners | 🟡 | **94 registered**, each with a catalog row (`ToolServiceProvider::RUNNERS`; the drift test asserts both directions) — every free tool, plus one account tool. **92 are published and visible**; two are seeded as drafts because their upstream stopped serving what they need to a datacentre IP, and the reasoning is in [07](07-tool-catalog.md#built-not-published). What remains of [07](07-tool-catalog.md) is the account and premium tiers, which need providers rather than runners |
| Custom tool UIs | ✅ | `apps/web/src/tools/custom/`, dispatched on the tool's registry key. Six tools: the fake YouTube comment generator, plus the Facebook, Instagram, X reply, Pinterest and TikTok card generators, which share one schema-driven component (`social-card-generator.tsx`) painting through `social-card.ts`. All draw on a canvas in the browser, so an avatar someone drops in is never uploaded, and all export PNG, JPG, WebP and AVIF at 1×, 2× or 3× |
| Run recording & telemetry | ✅ | |
| Run history (`/dashboard/runs`) | ✅ | Windowed by `history_days`; status filter, and a page per run at `/dashboard/runs/{id}` showing the **input it was given and the result it produced**. Both are stored for signed-in members only (`tool_runs.input_payload` / `result_payload`, capped at 64 KB, never for results carrying expiring artifact URLs) — an anonymous run still keeps nothing but its hash |
| Per-tier run limits, admin-configurable | ✅ | `tools.limits.{free,account,premium}.{daily,weekly,monthly}` — 5 / 20 / unlimited a day by default, week and month off, edited under Settings → Tools (one panel per window). Anonymous is counted per IP via `visitor_hash`; `-1` leaves a window uncounted, `0` closes a tier. The quota wall names the next tier, its allowance and which window ran out, so it can offer the right button and the right wait ([08](08-tool-engine.md)) |
| Per-tool run limits | ✅ | `config.limits.{daily,weekly,monthly}`, edited on the tool's own page under Run limits. A tool cap only narrows — it applies to subscribers too but can never raise a tier's allowance. Blank defers to the tier ([08](08-tool-engine.md)) |
| Favourites | ✅ | `tool_favorites`; heart on every catalog card and the tool page, `Favourites first` sort in the catalog. Members only — an anonymous list keyed on a hash that rotates nightly would empty itself |
| Trending | ✅ | `TrendingTools`, ranked from `tool_runs` over an admin-set window (`tools.trending_days`, default 3) with a floor (`tools.trending_min_runs`). Served at `GET /catalog/tools/trending` rather than baked into the cached catalog page |
| In-app catalog (`/dashboard/tools`) | ✅ | **Removed** — `/dashboard/tools` now 308s to `/tools`. The public catalog already shows per-user access on each card, so a second browser was two surfaces to keep in step for no gain |

## Accounts

| Area | Status | Notes |
| --- | --- | --- |
| Registration (with anonymous-run claiming) | ✅ | |
| Email + password sign-in | ✅ | Argon2id, throttled by email *and* IP |
| Magic-link sign-in | ✅ | Single-use, 15-minute expiry, hashed at rest, no account enumeration |
| Password reset | ✅ | Invalidates other sessions |
| Email verification | ✅ | Signed URL; consuming a magic link verifies implicitly |
| Re-authentication for sensitive actions | ✅ | `password.confirm`, 15-minute window |
| Google OAuth | ⬜ | **Blocked**: `laravel/socialite` pins Guzzle ^7, the framework pulls Guzzle 8. See [03](03-tech-stack.md) |
| Profile, avatar, timezone | ✅ | Email is immutable, enforced in the model and the request |
| Device/session list with revocation | ✅ | |
| Settings split (profile / security / notifications) | ✅ | Tabbed, each tab its own route |
| Plan & billing screen (`/dashboard/billing`) | 🟡 | Plan, limits and usage are live from `EntitlementsService`. No invoice history or card management — there is no Stripe integration to read them from |
| Help & support screen (`/dashboard/support`) | 🟡 | FAQ plus email/contact routes. Not ticketing — see below |
| Account deletion flow | ⬜ | Columns exist (`deletion_requested_at`) |

## Notifications

| Area | Status | Notes |
| --- | --- | --- |
| Event catalog | ✅ | All 30 events from [13](13-notifications-email.md), asserted by tests |
| Delivery (in-app + email) with preference resolution | ✅ | Preferences subtract channels; they can never add one |
| Suppression list honoured before every send | ✅ | |
| Responsive email templates + plain-text alternative | ✅ | Dark-mode aware, table-based |
| Bell menu, batched reads, history page | ✅ | |
| Preferences screen | ✅ | Grouped for humans; non-optional events are absent, not disabled |
| Mailgun webhooks → `email_events` | ⬜ | Table exists; no ingest endpoint |
| Broadcast channel | ⬜ | Reserved for live run updates |

## Blog

| Area | Status | Notes |
| --- | --- | --- |
| Post/category/tag models, status lifecycle | ✅ | [09](09-blog-cms.md); `deleted` is `deleted_at`, not a status |
| Block sanitiser & text extractor | ✅ | Two HTMLPurifier profiles; XSS vectors asserted by test |
| Public API: listing, article, categories, tags | ✅ | 410 for withdrawn posts, 404 for drafts |
| Public blog: grid, paging, filters, article page | ✅ | Lead post on page one only |
| Block renderer | 🟡 | 14 of the 19 types in [09](09-blog-cms.md). Missing: `gallery`, `video`, `audio`, `gif`, `newsletter` |
| Related posts | ✅ | Shared tags → same category → recency, ranked in one query |
| SEO: metadata, OG/Twitter, JSON-LD, sitemap | ✅ | `BlogPosting` + `BreadcrumbList`, `FAQPage` only when an FAQ block is present |
| Scheduled publishing | ✅ | `blog:publish-scheduled`, every minute |
| `features.blog_enabled` kill switch | ✅ | Middleware 404s the route group; sitemap drops the URLs |
| Admin editor, list screens, bulk actions, revisions | ✅ | [25](25-admin.md). Block editor for all 14 implemented types, status tabs with counts, per-row-authorized bulk actions, optimistic concurrency, revisions on every content save |
| Editor renders as the published article | ✅ | Public header and public block renderer inside the editing column; configuration blocks show an options strip only while selected |
| Primary + secondary categories, inline tag search/create | ✅ | `posts.category_id` stays primary; `post_post_category` holds the rest |
| Featured image, media-library modal, full permalink | ✅ | The modal is reachable from the image block and the featured image |
| Autosave (7s) and preview-without-publishing | ✅ | Autosave flags `is_autosave`; preview renders the *unsaved* draft at `/c0ns0le/posts/preview` |


## Top rankings

Wikipedia-sourced leaderboards at `/top-ranking/{slug}`, refreshed weekly by a scheduled job.

| Area | Status | Notes |
| --- | --- | --- |
| Ranking pages & entries | ✅ | `top_ranking_pages` / `top_ranking_entries`. A page is a row, not a constant, because its *source* has to be editable too — a renamed Wikipedia article is a Tuesday, not a deploy |
| Nine seeded rankings | ✅ | YouTube most-subscribed (100) and most-viewed (50); Instagram, TikTok, X, Facebook Pages, Twitch most-followed and most-subscribed, Bluesky (50 each). Every source article verified to parse before it was listed |
| Wikipedia import | ✅ | `action=parse&prop=text`, then a **header-driven** table parser: nine differently-shaped articles share one implementation because it maps column *labels* to fields rather than indexing by position. No API key, no quota |
| Sync reconciliation | ✅ | Matches on a normalised key and updates in place. A row added by hand is never removed; a pinned row is never moved. That is what makes an unattended weekly job safe on a curated page |
| Avatars | 🟡 | Resolved from each platform's own public `og:image`, plus Bluesky's keyless XRPC API and TikTok's page state. **~93% of rows resolve** (464/500 at last run). The gap is structural, not a bug: 34 Facebook Pages publish no handle at all to build a profile URL from. Unresolved rows render a monogram in the platform's colour |
| Expiring avatar links | ✅ | Meta and TikTok sign their CDN URLs. The expiry is read out of the URL, stored, and the API withholds a link past its date — so a reader sees a monogram, never a torn image |
| Public pages | ✅ | Index at `/top-ranking`, one page per ranking: podium for the top three, adaptive table below (columns appear only where a page has data for them), `ItemList` JSON-LD, CC BY-SA attribution. Pre-rendered via `generateStaticParams` |
| Header menu | ✅ | Hover *and* click/keyboard dropdown, built from the API so an admin adding a page adds a menu item. A two-column **grid**, not a scrolling list — nine items fit above the fold, and a reader cannot compare what they cannot see at once. Listed flat in the mobile menu |
| SEO | ✅ | Full per-page control through the shared `seo_meta` row — meta title and description, focus keyword, canonical, robots, Open Graph title/description/image, card type — edited in a **SEO & sharing** tab that is literally the tool editor's panel (`components/admin/seo-panel.tsx`, extracted when the second caller appeared). Live search and social previews; a no-index page drops out of the sitemap |
| Admin | ✅ | List with freshness and missing-picture counts. The editor is **tabbed** like the tool editor — Rows, Presentation, Source & sync, Metrics, SEO — because a sidebar is width a fifty-row table does not have to spare. One Save for the whole form; row actions (reorder, pin, remove, resolve a picture) write immediately, since a fifty-row table behind a Save button means one mistake discards forty-nine good edits. `top_rankings.sync` is its own permission — making the server crawl the web is not the same authority as fixing a name |
| Commands | ✅ | `rankings:sync` and `rankings:avatars`, both `--all`-capable and incremental |
| Weekly job | ✅ | `RefreshTopRankingPage` on the `maintenance` queue, one job per page, staggered. Scheduled Sundays 03:20 |
| Front-end cache invalidation | ✅ | Observers on *both* the page and its entries — a sync rewrites hundreds of rows without touching the page row, so observing the page alone would leave a refreshed ranking behind its cache |

## Global search

One search box over everything the site publishes — tools, blog posts, ranking pages and the
hand-written pages. **Off by default** (`features.search_enabled`, Settings → Features): the API
404s `/api/v1/search`, the header field and the `/search` page disappear with it.

| Area | Status | Notes |
| --- | --- | --- |
| Search endpoint | ✅ | `GET /api/v1/search?q=&page=&per_page=&filter[type]=`. One endpoint for both surfaces — the dropdown asks for five and reads `meta.page.total`, the results page asks for ten. A second "suggest" route would be the same query with a different cap and a second place for the ranking to drift |
| Sources | ✅ | `App\Domain\Search`. Tools and posts through their fulltext indexes, ranking pages by scan (seven rows), and the static pages from `SitePageCatalog` — a code catalog, because those pages are React components with nothing to query. Each entry carries `keywords`, so "refund" finds the terms page |
| Ranking | ✅ | Scored in PHP, not in the `ORDER BY`. MySQL fulltext scores by term *rarity*, which cannot express "prioritise exact matches" — a rare word buried in a long article outranks a page whose title **is** the query. Wide, well-separated bands: exact title (1000), prefix (800), whole word (700), substring (600), all words present (500), summary (400), body (300). A shorter title breaks a tie, so "Hashtag Generator" beats "The Complete Hashtag Generator Playbook" |
| Candidate retrieval | ✅ | Three passes, because none is sufficient alone: `MATCH … AGAINST` for reach into body text, the whole phrase as `LIKE` (fulltext ignores stopwords and short tokens, and InnoDB does not index a row until its transaction commits), and each word against the title so "calculator youtube" reaches "YouTube Money Calculator". Deliberately over-fetches; the scorer drops anything that scores zero |
| Caching | ✅ | The ranked answer per (term, type) for five minutes, so a debounced type-ahead costs one Redis read. Cached as **plain rows**, not objects: `cache.serializable_classes` is `false` here, so nothing may be unserialized from cache as a PHP object. A test asserts the payload survives `allowed_classes: false` — the array cache store the suite runs on never serializes, so nothing else could catch it |
| Feature switch | ✅ | `features.search_enabled`, default **off**. `EnsureSearchEnabled` 404s the route; `siteFeatures()` carries it to the frontend, where the default is also off — the one flag whose safe fallback is absence, because its failure mode is offering a box that 404s |
| Header dropdown | ✅ | Top five, icon on a tinted disc at the left and a wrapping title at the right, then a button to the full results. Debounced 200ms, previous request aborted, answers cached per mount. `/` focuses it; arrows move the highlight, Enter opens the highlighted result or runs the full search. Below `md` it renders as a full-width field **inside the mobile menu** — the phone header has no room for another 36px control, and a menu is where a phone user already goes to navigate |
| Results page | ✅ | `/search`, a list at ten a page: type badge, image (Open Graph or featured, with the type's own icon as the placeholder), title, summary. Type filter chips, windowed pagination (`lib/pagination.ts` — a search can fill sixty pages, and sixty chips is a wall, not navigation), back to top. **`noindex`**, always: a page generated from arbitrary query text is an unbounded set of thin URLs |
| Throttle | ✅ | `throttle:search`, 300/minute per actor. A type-ahead is a request per keystroke by design, so the ordinary 120/minute API ceiling would wall a real person inside half a minute |

## Admin

Full detail in [25](25-admin.md).

| Area | Status | Notes |
| --- | --- | --- |
| Admin API, permission-gated per route | ✅ | 61 routes; a test asserts every one declares a permission *and* that a customer is refused by all of them |
| Overview dashboard | ✅ | Eight headline metrics with period-over-period deltas and sparklines, run volume, funnel, access-reason split, top tools, top errors |
| Tool analytics | ✅ | Every panel in [15](15-analytics.md): runs, paywall hits, failure rate, p95, cache hit rate, access-reason split, comped runs — plus **runs by actor**: total runs across everyone, then per account and per visitor fingerprint, so healthy breadth can be told from one busy script |
| Funnel & content analytics | ✅ | |
| Nightly rollups (`analytics:rollup`) | ✅ | Recompute, not append. Every 15 min for today, 00:10 for yesterday |
| `tool_funnel_daily` + `FunnelRecorder` | ✅ | The table [15](15-analytics.md) specified and nothing created. Paywall hits had **no source at all** before this |
| Users: search, detail, suspend, delete | ✅ | Email immutable here too; last super admin cannot be demoted |
| Roles & granular permission editor | ✅ | Composes any permission set from the catalog without a deploy |
| Tools: tier, status, visibility, featuring | ✅ | `key`, `slug`, `version` and `input_schema` deliberately not editable |
| Tool grants | ✅ | Attributed, expirable, audited, and the user is notified |
| Media library | ✅ | Grid, upload, alt text with a "no alt text" warning, soft delete |
| Billing: plans, subscriptions, invoices | 🟡 | Four sidebar destinations rather than one tabbed screen, so every one is addressable. Plans are full CRUD on their own pages — create, edit, enable/disable, delete — with price locked once a plan has live subscribers. Subscriptions stay read-only and empty until a gateway integration exists; invoices have demo rows and a detail page carrying lines, plan, card, gateway transaction and refund |
| Billing report | ✅ | `/c0ns0le/billing/report`: net revenue, MRR/ARR, churn, ARPU, revenue by plan and gateway, top customers and the refunds behind the refund total. Gated on `invoices.view_any` — this is money, not product analytics |
| Payment provider configuration | 🟡 | Settings → Payments picks Stripe / PayPal / Braintree and stores each one's credentials; plans carry a per-gateway price id. **No gateway is called yet** — this is the configuration surface, not the integration |
| Support queue | ✅ | Overdue-first ordering, reply/internal-note toggle, triage, SLA timeline |
| Contact inbox | ✅ | Triage for the public form |
| Newsletter subscribers | ✅ | List, sync-failure banner, streamed CSV export |
| Settings | ✅ | A section rail (general, blog, accounts, payments, SEO, tracking, newsletter) over one table, each section a stack of titled cards the way a merchant configuration screen reads — Payments is *General*, then Stripe, then PayPal, then Braintree, never one flat column. No Features tab: every flag sits in the section it governs. Three permissions checked per key; secrets never sent to the browser |
| Blog presentation settings | ✅ | Author, date, reading time, featured image, categories, tags, related posts and page size, read by the frontend through the public `GET /api/v1/settings` and defaulting to everything-on if that request fails |
| Demo invoices, tickets and contact messages | ✅ | `CommerceDemoSeeder` and `SupportDemoSeeder`, non-production only. The billing and support screens were correct and untestable while their tables were empty |
| Audit log | ✅ | Actor, subject, diff. No endpoint edits or deletes an entry |

## Not yet started

Each has a full specification and its schema, models and permissions already in place.

| Area | Spec |
| --- | --- |
| Stripe integration: checkout, portal, webhooks | [11](11-billing.md) — the settings and per-plan price ids it will read now exist |
| Customer-facing ticket creation | [12](12-support-tickets.md) |
| Newsletter provider adapters (MailChimp, Sendy, …) | [14](14-newsletter-marketing.md) |
| Google OAuth | [03](03-tech-stack.md) — blocked on a Guzzle major-version conflict |

The admin side of billing, support and the newsletter **is** built — plans, subscriptions, invoices,
the ticket queue and the subscriber list all have working screens ([25](25-admin.md)). What is
missing is the machinery that fills them: nothing writes a Stripe subscription yet, customers cannot
open a ticket from the dashboard, and no provider adapter syncs the list outward.

Public newsletter capture *is* wired: `POST /newsletter/subscribe` and `/newsletter/confirm` write
the local list and run double opt-in. Unsubscribe is still handled only by an admin editing the
row — the footer's "unsubscribe in one click" has no endpoint behind it yet.

## Known gaps worth knowing about

- **Synchronous tool runs record telemetry, not results.** `RecordToolRun` persists the run row;
  only `RunToolJob` (asynchronous runs) writes a `result_ref` through `ArtifactStore`. So history
  lists every run, and the run page can only re-render the output of the ones that ran in the
  background. If "unlimited history" is meant to include the *results* on paid plans, the
  synchronous path needs to store them too — that is a storage and privacy decision, not an
  oversight in the UI.
- **The blog kill switch stops at the API.** `features.blog_enabled` is now its own section in
  Settings, and `EnsureBlogEnabled` 404s the public blog endpoints and drops the sitemap URLs when it
  is off. The Next.js header still links to `/blog` regardless, because the web app does not read
  public settings at all yet — the link goes to a 404 rather than disappearing. Wiring it needs a
  public settings endpoint the frontend layout can read.

- **`POST /api/v1/contact` does not exist.** The public contact form posts to it and reports a
  failure — so the admin's Contact inbox reads a table nothing writes to yet. The dashboard's Help
  screen deliberately links to email and to that form rather than claiming a ticket will be opened.

- **Two silent bugs were found by driving the admin in a browser**, both now covered by tests. Worth
  knowing because the shapes recur:

  - `SavePostRequest` validated `blocks.blocks.*.type` and nothing else, and Laravel's `validated()`
    returns only the keys a rule names — so every block reached the action as `{type: "paragraph"}`
    and the writing was deleted on save, with a 200 and nothing in the logs. Partial validation of a
    nested structure plus `validated()` is a content-loss bug waiting to happen.
  - The admin taxonomy endpoints reused the public resources, which omit numeric primary keys by
    design ([05](05-api.md)). But `posts.category_id` and the tag pivot are numeric, so every option
    in the category picker saved as null. `AdminTaxonomyResource` carries the id.

- **`ui/field.tsx`'s `Select` forwarded children through `{...props}`.** That loses React's
  static-children optimisation, so every multi-option select in the app — customer dashboard
  included — logged a key warning from a wrapper the call site could not see. It now passes children
  through `Children.toArray`.

- **The overview's "run → account" rate can exceed 100%.** It is accounts created per 100 unique
  visitors *who ran a tool*, and someone can register without ever running one. On a seeded
  development database it reads absurdly high. The formula is right; the denominator is narrow on
  purpose, because the product's first meaningful action is a run.

## Known environment notes

- **Use `localhost`, not `127.0.0.1`.** `SESSION_DOMAIN=localhost`, so a cookie set on one is not
  sent to the other and Sanctum's session will not cross. This bites during manual API testing.
- **`MAIL_HOST=mailpit`** resolves inside Docker only. Running `php artisan serve` on the host
  needs `MAIL_MAILER=log`. A failed send no longer breaks the request that triggered it — the
  notifier logs and continues — but nothing is delivered either.
- **The test environment is set in `tests/bootstrap.php`, not `phpunit.xml`.** The containers
  export `APP_ENV`, `DB_DATABASE` and friends as real environment variables, which Laravel reads
  from `$_SERVER` — where neither `.env.testing` nor PHPUnit's `<env force="true">` can displace
  them. Adding a test-only variable to `phpunit.xml` will appear to work and then silently not.
- **A streamed 404 returns HTTP 200.** Next.js cannot change the status once the response has
  begun, and the root `loading.tsx` starts it. It injects `<meta name="robots" content="noindex">`
  instead, so the page is not indexed; this is documented Next.js behaviour, not a defect.
- **Argon2id is deliberately slow.** The test suite overrides `HASH_DRIVER=bcrypt` with 4 rounds;
  do not copy that into any other environment.
- **Prettier has no config** and its 80-column default disagrees with all 61 existing source files.
  `make lint` runs ESLint and `tsc`, not Prettier, so CI is unaffected — but running
  `npm run format` would reformat the entire frontend. Add a `.prettierrc` with `printWidth: 100`
  before anyone does.
