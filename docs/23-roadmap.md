# 23 — Roadmap

Phases are sequenced so that something valuable and *shippable* exists at the end of each one.

> Per-area detail lives in [24 — Implementation status](24-implementation-status.md), which is the
> only document that claims something exists. The phases below are the plan.

## Phase 0 — Foundations (weeks 1–2) 🟡 *complete except Google OAuth*

Monorepo, Docker stack, CI, Laravel and Next skeletons, design tokens and the core UI kit, the
`docs/` handbook, auth (three of the four methods — Google OAuth is blocked on a Guzzle conflict,
see [24](24-implementation-status.md)), RBAC with the permission catalog, the settings store, and
health checks.

**Done when:** a developer clones the repo, runs `make setup`, logs in as each seeded role, and sees
a correctly permission-filtered admin shell.

## Phase 1 — The tool platform (weeks 3–6) 🟡 *engine complete, catalog in progress*

Tool registry, runner contract, access + quota services, run recording, the generated `ToolForm`,
the result renderers, the public catalog with search and filters, tool pages with instructions and
examples, and **the P1 tool set (34 tools)** from [07](07-tool-catalog.md).

**Done when:** an anonymous visitor can run a free tool from search, a free account raises their
limits, and a premium tool correctly refuses with an upgrade prompt.

## Phase 2 — Accounts & money (weeks 5–8, overlapping) 🟡 *accounts and notifications complete; billing not started*

User dashboard, run history, entitlements endpoint, Stripe Checkout and Billing Portal, the three
plans, invoices, webhooks and reconciliation, admin billing screens, and the notification system.

**Done when:** a real card can buy each plan, entitlements flip within seconds, invoices are
retrievable, and cancellation is self-serve.

## Phase 3 — Content & growth (weeks 7–11)

The block editor and renderer, all launch block types, post lifecycle and revisions, bulk actions,
media library with variants, the SEO metadata system, sitemaps and JSON-LD, the newsletter provider
abstraction and capture placements, and the marketing pages including the landing page.

**Done when:** an editor publishes an illustrated post with embeds and a tool card, and the page
scores green on Lighthouse and validates in the Rich Results test.

## Phase 4 — Support & operations (weeks 10–12)

Ticketing end to end, admin analytics dashboards and rollups, activity log surfacing, Ansible
production deployment, backups with restore verification, monitoring and alerting.

**Done when:** a production deploy runs from a tag with zero downtime and a rollback is verified.

## Phase 5 — Launch (weeks 12–14)

The P2 tools, 20 seed blog posts covering the top keyword clusters, pricing page polish, legal pages
reviewed, load testing, a security review pass, and beta feedback incorporated.

**Done when:** the funnel metrics in [01](01-product-overview.md) are being measured on real
traffic.

## Post-launch

| Quarter | Theme |
| --- | --- |
| Q+1 | P3 tools, saved projects, tool run history with re-run, API access for Pro |
| Q+2 | Team seats and shared workspaces, brand kits, white-labelled exports |
| Q+3 | Scheduling integrations (read-only analytics connections to platform APIs), Chrome extension |
| Q+4 | Public API and partner program, affiliate system, localisation |

## Explicitly deferred

Posting on a user's behalf, a native mobile app, an in-house payment processor, real-time
collaborative editing, and a plugin marketplace. Each is a large surface with its own compliance and
support burden; none of them is what a creator is missing today.
