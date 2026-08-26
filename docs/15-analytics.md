# 15 — Analytics & Tracking

Two systems, deliberately separate: **first-party product telemetry** we own and can query, and
**third-party marketing tags** the admin configures.

## First-party telemetry

The `tool_runs` table ([04](04-data-model.md)) is the spine. Writes are queued so measurement never
slows a response, and raw inputs are not retained unless a tool explicitly declares it and the user
consents.

Rolled up nightly into:

| Table | Grain |
| --- | --- |
| `tool_run_daily_stats` | tool × day × tier × access_reason → runs, uniques, success rate, p50/p95 duration, cache hit rate, error breakdown |
| `tool_funnel_daily` | tool × day → views, starts, completions, paywall hits, upgrades attributed |
| `billing_daily_stats` | day → MRR, ARR, active by plan, churn, conversions |
| `content_daily_stats` | post × day → views, read-through, tool clicks, newsletter signups |

Retention: raw runs 90 days, rollups forever. Rollups are what dashboards query — never live tables.

## Admin dashboards

**Overview** — visitors, tool runs, new accounts, MRR, conversion rate, each with a sparkline and a
period-over-period delta.

**Tool analytics** — the screen that drives roadmap decisions:

| Panel | Question it answers |
| --- | --- |
| Runs by tool (sortable, filterable by tier) | What is actually used? |
| Paywall hits by tool | Which premium tools do free users *want*? → pricing and tiering |
| Failure rate + top error codes by tool | What is broken right now? |
| p95 duration trend | What is getting slower? |
| Access reason split | How much premium usage is comped grants? |
| Zero-result / abandoned runs | Where is the UX confusing? |
| Acquisition source per tool | Which tools are earning search traffic? |

**Content analytics** — posts by views, read-through, tool clicks per post, newsletter conversions
per post.

**Funnel** — visitor → run → account → paid, with drop-off at each step and segmentation by entry
tool. This is the number the product is steered by.

## Third-party tags

Admin configures, in `Settings → Tracking`:

| Field | Notes |
| --- | --- |
| GA4 Measurement ID | Loaded via the official gtag snippet |
| Google Tag Manager ID | If set, GTM is used and GA4 is expected to be configured inside it |
| Meta Pixel ID | |
| TikTok Pixel ID | |
| Custom `<head>` start / end | Raw HTML |
| Custom `<body>` start / end | Raw HTML |

Rules that make this safe:

- Only users with `settings.scripts.update` may edit these — a separate permission from general
  settings, because it is effectively arbitrary code execution on the site.
- Every save is diffed into `activity_log` with the actor.
- Scripts are injected via Next's `<Script>` with `strategy="afterInteractive"` so they cannot block
  first paint, and they are never injected into `/admin` or `/dashboard` routes.
- A CSP nonce is applied; inline snippets must be nonce-compatible, and the settings UI warns when a
  pasted snippet will be blocked ([21](21-security.md)).
- Tags load **only after consent** in regions requiring it; the consent state is a first-class
  signal available to GTM as `consent_mode` defaults.

## Event taxonomy (client)

Consistent `snake_case` names shared by the internal collector and GA4:

```
page_view, tool_view, tool_run_started, tool_run_completed, tool_run_failed,
tool_paywall_shown, tool_example_used, signup_started, signup_completed,
checkout_started, checkout_completed, newsletter_signup, post_view,
post_read_complete, tool_card_clicked, support_ticket_created
```

Every event carries `tool_slug`, `tier`, `access_reason`, `plan` and `source` where applicable, so
the same question can be answered in either system and the answers can be reconciled.

## Privacy

No third-party analytics before consent. First-party telemetry stores a rotating daily HMAC of
IP+UA rather than the IP itself, is used only in aggregate, and is documented in the privacy policy.
IPs are never written to `tool_runs`.
