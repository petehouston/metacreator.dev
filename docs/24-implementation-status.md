# 24 — Implementation Status

What is **built and tested** versus what is **specified but not yet written**. Every document in
this handbook describes the intended design; this one is the only place that claims something
exists. Keep it honest — a specification that reads as a status report is how a team ends up
surprised.

Last updated: 2026-08-26.

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
| Design tokens & core UI kit | ✅ | [17](17-design-system.md) |
| CI: Pest, PHPStan, Pint, ESLint, tsc | ✅ | All green |

## Tools

| Area | Status | Notes |
| --- | --- | --- |
| Tool registry, runner contract, access & quota services | ✅ | [08](08-tool-engine.md) |
| Public catalog, search, filters, tool pages | ✅ | |
| Generated `ToolForm` and result renderers | ✅ | |
| Tool runners | 🟡 | **8 of the 78** catalogued in [07](07-tool-catalog.md). The engine is proven end to end; the rest are mechanical |
| Run recording & telemetry | ✅ | |
| Run history (`/dashboard/runs`) | ✅ | Windowed by the plan's `history_days` entitlement |

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
| Admin editor, list screens, bulk actions, revisions | ⬜ | `post_revisions` table exists and is unused |

## Not yet started

Each has a full specification and its schema, models and permissions already in place.

| Area | Spec |
| --- | --- |
| Blog admin: editor, list screens, bulk actions | [09](09-blog-cms.md) |
| Media library | [10](10-media-library.md) |
| Stripe billing, plans, invoices, webhooks | [11](11-billing.md) |
| Support ticketing | [12](12-support-tickets.md) |
| Newsletter provider adapters | [14](14-newsletter-marketing.md) |
| Admin dashboard & analytics rollups | [15](15-analytics.md) |

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
