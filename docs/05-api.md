# 05 — API Design

Base URL: `https://api.metacreator.dev/api/v1`. Local: `http://localhost:8080/api/v1`.

## Principles

1. **Resource-oriented REST** with a small number of deliberate RPC-style actions
   (`/tools/{slug}/run`, `/posts/{id}/publish`) where a verb is genuinely clearer than a resource.
2. **One envelope, always.** Clients never branch on response shape.
3. **Errors are machine-readable first**, human-readable second.
4. **The API never leaks Eloquent.** Everything passes through an API Resource with an explicit
   field list, so adding a DB column can never accidentally expose data.

## Envelopes

Single resource:

```json
{
  "data": { "id": "tl_01HZ...", "type": "tool", "attributes": { "...": "..." } },
  "meta": { "request_id": "req_01HZ..." }
}
```

Collection — produced by `App\Http\Resources\ApiCollection`, which every list
endpoint returns so Laravel's default (flat, Blade-oriented) paginator meta never
reaches a client:

```json
{
  "data": [ { "...": "..." } ],
  "meta": {
    "request_id": "req_01HZ...",
    "page": { "current": 2, "per_page": 24, "total": 137, "last_page": 6 }
  },
  "links": { "first": "...", "prev": "...", "next": "...", "last": "..." }
}
```

Error:

```json
{
  "error": {
    "code": "tool.subscription_required",
    "message": "This tool requires an active Pro subscription.",
    "status": 402,
    "details": { "tool": "youtube-thumbnail-ab-tester", "required_tier": "premium" },
    "request_id": "req_01HZ..."
  }
}
```

### Error code catalog (excerpt)

| Code | HTTP | Meaning |
| --- | --- | --- |
| `validation.failed` | 422 | `details` contains a field→messages map |
| `auth.unauthenticated` | 401 | No/expired session |
| `auth.forbidden` | 403 | Authenticated but lacks the permission |
| `tool.account_required` | 401 | Free visitor hit an `account` tool |
| `tool.subscription_required` | 402 | Account hit a `premium` tool |
| `tool.quota_exceeded` | 429 | Daily/period run quota spent; `details.resets_at` |
| `tool.rate_limited` | 429 | Short-window throttle; `Retry-After` header set |
| `tool.unavailable` | 503 | Runner or upstream provider is down |
| `resource.not_found` | 404 | Also used for objects the actor may not see |
| `resource.conflict` | 409 | Optimistic-concurrency failure (stale `version`) |

## Conventions

| Concern | Convention |
| --- | --- |
| IDs | Public IDs are prefixed ULIDs (`usr_`, `tl_`, `pst_`, `sub_`). Numeric PKs never leave the API |
| Timestamps | ISO-8601 UTC with `Z`, field names end in `_at` |
| Money | Integer minor units + currency: `{"amount": 1900, "currency": "USD"}` |
| Pagination | `?page=2&per_page=24`, `per_page` capped at 100 |
| Sorting | `?sort=-published_at,title` (leading `-` = desc), allow-listed per endpoint |
| Filtering | `?filter[category]=youtube&filter[tier]=free` |
| Sparse fields | `?fields[post]=title,excerpt` |
| Includes | `?include=category,tags` — allow-listed, depth 1 |
| Idempotency | `Idempotency-Key` header honoured on all POSTs that cost money or send email |
| Concurrency | Mutable resources return `version`; `PATCH` requires it or gets a 409 |
| Rate limits | `X-RateLimit-Limit`, `-Remaining`, `-Reset` on every response |

## Route map

### Public (no auth)

```
GET    /catalog/tools                      list + search + filter
GET    /catalog/tools/{slug}               detail incl. instructions, examples, related
GET    /catalog/categories
POST   /tools/{slug}/run                   free tools only; throttled by IP
GET    /tools/runs/{id}                    poll an async run (signed for guests)
GET    /content/posts                      paginated grid feed
GET    /content/posts/{slug}
GET    /content/categories | /content/tags
GET    /content/sitemap                    URL set for Next's sitemap route
GET    /site/settings                      public settings: scripts, feature flags, nav
POST   /newsletter/subscribe
POST   /contact
```

### Authenticated (Sanctum cookie)

```
POST   /auth/register | /auth/login | /auth/logout
POST   /auth/magic-link | /auth/magic-link/consume
GET    /auth/google/redirect | /auth/google/callback
POST   /auth/password/forgot | /auth/password/reset
PATCH  /account/profile | /account/password | /account/avatar
GET    /account/entitlements               the single source of UI gating
GET    /account/tool-runs                  run history
GET    /billing/plans
POST   /billing/checkout                   → Stripe Checkout URL
GET    /billing/portal                     → Stripe Billing Portal URL
GET    /billing/subscription | /billing/invoices
GET/POST /support/tickets | /support/tickets/{id}/messages
GET    /notifications | POST /notifications/{id}/read | POST /notifications/read-all
```

### Admin (`/admin/*`, permission-gated per route)

```
tools, tool-grants, tool-analytics
posts, post-revisions, categories, tags, media
users, roles, permissions
subscriptions, invoices, refunds
tickets
settings (branding, scripts, newsletter, feature flags)
analytics (dashboards)
```

Every admin route declares its required permission in the route definition, and a test asserts that
**no admin route lacks one**.

## Versioning

The URL carries the major version. Within `v1`, changes must be additive: new optional fields, new
endpoints, new enum members that clients are told to treat as unknown. Anything else ships as `v2`
with a 6-month overlap and a `Deprecation`/`Sunset` header on `v1`.

## OpenAPI

`apps/api/openapi/v1.yaml` is generated from route attributes and resource definitions
(`php artisan api:spec`) and validated in CI. The frontend's typed client is generated from it, so a
breaking backend change fails the frontend build.
