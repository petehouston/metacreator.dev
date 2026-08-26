# 02 — Architecture

## Topology

```
┌──────────────────────────────────────────────────────────────────────────────┐
│ DigitalOcean Droplet (Ubuntu 24.04)                                          │
│                                                                              │
│   Caddy (TLS, HTTP/3, compression, security headers)                         │
│        │                                                                     │
│        ├── metacreator.dev            ──▶ Next.js (node, pm2/systemd) :3000  │
│        └── api.metacreator.dev        ──▶ PHP-FPM 8.4 + nginx-unit  :9000    │
│                                                                              │
│   MySQL 8.4      Redis 7.4      Horizon workers (systemd)      Go compute    │
│                                  ├─ default   ├─ tools                       │
│                                  ├─ media     └─ mail                        │
└──────────────────────────────────────────────────────────────────────────────┘
        Object storage: DigitalOcean Spaces (S3 API) + CDN for media
```

Single-droplet by design at launch: it is cheap, it is one thing to reason about, and every
component is already positioned to be pulled onto its own host without code changes (all state is in
MySQL/Redis/Spaces, all config is env-driven).

## Why a decoupled frontend

The frontend is Next.js talking to a JSON API rather than Blade or Inertia. Reasons, in order of
weight:

1. **SEO is the acquisition channel.** Tool and blog pages need ISR, streaming, precise metadata and
   near-instant navigation. Next's App Router gives that with far less effort than a Laravel-side
   equivalent.
2. **Tool UIs are genuinely interactive** — file drops, live previews, incremental results. React is
   the right tool; embedding React islands into Blade is a worse version of the same thing.
3. **The API is needed anyway** for a future mobile client and for partner integrations.

Cost: two runtimes and a network hop. Mitigated by keeping both on the same host and using
cookie-based Sanctum auth so there is no token dance. See
[ADR 0001](adr/0001-decoupled-nextjs-frontend.md).

## Backend module boundaries

`apps/api` is a modular monolith. Domain code lives under `app/Domain/<Module>` and never reaches
across module boundaries except through a module's public service classes.

```
app/
├── Domain/
│   ├── Access/        Roles, permissions, grants, entitlement resolution
│   ├── Billing/       Plans, subscriptions, invoices, Stripe webhooks
│   ├── Blog/          Posts, categories, tags, revisions, blocks
│   ├── Media/         Media items, variants, storage adapters
│   ├── Notifications/ Notification catalog, channels, preferences
│   ├── Newsletter/    Provider adapters, subscriber sync
│   ├── Seo/           Meta model, sitemap generation, JSON-LD builders
│   ├── Settings/      Typed key-value site settings + script injection
│   ├── Support/       Tickets, messages, assignment, SLA
│   ├── Tools/         Registry, runners, access, quota, runs, analytics
│   └── Users/         Accounts, profiles, auth flows, sessions
├── Http/              Controllers (thin), Requests, Resources, Middleware
├── Jobs/              Queueable work
└── Support/           Cross-cutting primitives (Result, Money, Clock, …)
```

### Layering rules

| Layer | May depend on | Contains |
| --- | --- | --- |
| `Http` | `Domain`, `Support` | Routing, validation, serialisation. **No business logic.** |
| `Domain\*\Actions` | own domain, `Support` | One public `handle()` per use case. The only place writes happen. |
| `Domain\*\Models` | `Support` | Eloquent models, relations, scopes, casts. No business rules. |
| `Domain\*\Services` | own domain, other domains' *services* | Stateless queries and policy decisions |
| `Support` | nothing | Value objects and helpers |

An Action is the unit of testability: `CreatePostAction`, `RunToolAction`, `GrantToolAccessAction`.
Controllers construct a DTO from a validated request and hand it to an action.

## Request lifecycles

### A public tool run (free tier)

```
Browser
  └─▶ POST /api/v1/tools/{slug}/run                (Next.js route handler proxies, adds CSRF)
        └─▶ ThrottleToolRuns middleware            (Redis, per IP + per tool)
              └─▶ RunToolController
                    └─▶ ToolAccessService::authorize(tool, actor)
                          └─▶ RunToolAction
                                ├─ cache lookup (Redis, keyed by tool+version+input hash)
                                ├─ ToolRegistry::runner(tool)->run(Input)
                                ├─ ToolRun record written (async, queued)
                                └─ ToolResult returned
```

Fast tools (< 500 ms, no external API) run inline. Anything slower is dispatched to the `tools`
queue and the client polls / subscribes — see [08 — Tool engine](08-tool-engine.md).

### A blog page render

```
Next.js request → generateMetadata() → GET /api/v1/content/posts/{slug} (ISR, tag-revalidated)
                                     → renders blocks with the SAME renderer the editor uses
Publishing a post → Laravel event → POST /api/revalidate (signed) → Next revalidateTag('post:slug')
```

This tag-based revalidation is what makes ISR safe: content is static until the CMS says otherwise.

## Data flow rules

- **MySQL is the system of record** for everything except billing entitlements, where Stripe is
  authoritative and MySQL is a projection kept current by webhooks ([ADR 0004](adr/0004-stripe-as-billing-source-of-truth.md)).
- **Redis holds only regenerable state**: cache, sessions, queues, rate-limit counters, locks.
  Losing Redis must never lose user data.
- **Spaces holds all user-uploaded bytes.** The database stores keys and metadata, never blobs.

## The Go compute service

`apps/compute` handles work that is wasteful in PHP: image/video probing and thumbnailing, bulk
hashing, high-concurrency HTTP fan-out (e.g. checking 200 links), and text-heavy analysis. It is a
stateless HTTP service on the private interface, called from queue workers with a short timeout and
a circuit breaker. If it is down, affected tools degrade to a PHP fallback or report unavailable —
they never hang. See [ADR 0005](adr/0005-go-service-for-cpu-bound-work.md).

## Failure and degradation

| Failure | Behaviour |
| --- | --- |
| Redis down | Sessions/queues unavailable → API returns 503 for writes; cached ISR pages keep serving |
| MySQL down | Full outage; Caddy serves a static status page |
| Go compute down | Affected tools return `tool_unavailable`; catalog marks them degraded |
| Stripe down | Existing entitlements unaffected (projected locally); new checkouts show a retry banner |
| A social platform API down | Per-tool circuit breaker opens; tool shows a precise, honest error |

Every external call goes through a wrapper with timeout, retry-with-jitter, and a circuit breaker
keyed by provider. No exceptions.
