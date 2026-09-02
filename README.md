# MetaCreator.Dev

> A professional toolkit for creators and influencers — analyze, optimize and grow accounts across
> YouTube, Instagram, TikTok, X, Facebook and LinkedIn from one clean, fast web app.

MetaCreator.Dev is a multi-tenant SaaS built around a **catalog of creator tools**. Each tool is a
self-contained, versioned unit with its own input schema, execution runner, instructions, examples
and access tier (`free` / `account` / `premium`). Around that catalog sits everything a real product
needs: a marketing site, an SEO-first blog CMS, a media library, subscriptions and invoicing,
support tickets, granular RBAC, notifications, transactional email and deep usage analytics.

---

## Table of contents

- [Highlights](#highlights)
- [Architecture at a glance](#architecture-at-a-glance)
- [Repository layout](#repository-layout)
- [Quick start](#quick-start)
- [Common commands](#common-commands)
- [Documentation](#documentation)
- [Deployment](#deployment)
- [License](#license)

---

## Highlights

This table is the **product scope**, not a claim about what is finished.
[`docs/24-implementation-status.md`](docs/24-implementation-status.md) is the only place that says
what actually exists today — read it before planning work.

| Area | What you get |
| --- | --- |
| **Tool platform** | 90+ creator tools, a declarative tool registry, per-tool access tiers, rate limits, quotas, per-user grants, usage analytics, async execution via queues |
| **Content** | WordPress-class blog: block editor (WYSIWYG that matches the front end 1:1), categories, tags, 6 statuses, scheduling, revisions, bulk edit, per-post SEO |
| **Media** | Central media library with variants, EXIF stripping, alt/caption/SEO metadata, S3-compatible storage (DO Spaces) |
| **Accounts** | Email+password, magic-link email login, Google OAuth, password reset, profile management |
| **Billing** | Stripe-backed 7-day / monthly / yearly plans, invoices, dunning, self-serve billing portal |
| **Support** | Ticketing with threads, attachments, statuses and SLA timers |
| **RBAC** | Roles (Admin, Editor, Support, Accountant) composed from ~120 granular permissions |
| **Admin** | A permission-gated staff dashboard: product analytics from nightly rollups, funnel and paywall reporting, users, roles, tools, grants, the blog editor, media, billing, the support queue, settings and an audit log |
| **Growth** | GA4 / GTM / Meta Pixel + custom head/body scripts, newsletter provider adapters, sitemaps, JSON-LD everywhere |

## Architecture at a glance

```
                      ┌──────────────────────────────┐
  Browser ──HTTPS──▶  │  Next.js 16 (App Router)     │  SSR/ISR, SEO, RSC
                      │  apps/web                    │
                      └──────────────┬───────────────┘
                                     │ REST/JSON (+ Sanctum cookie auth)
                      ┌──────────────▼───────────────┐
                      │  Laravel 13 API              │  Domain modules, Actions,
                      │  apps/api                    │  Policies, Form Requests
                      └───┬─────────┬─────────┬──────┘
                          │         │         │
                   ┌──────▼──┐ ┌────▼────┐ ┌──▼────────────┐
                   │  MySQL  │ │  Redis  │ │ Queue workers │  Horizon
                   │   8.4   │ │   7.4   │ │ (Laravel)     │
                   └─────────┘ └─────────┘ └──┬────────────┘
                                              │ gRPC/HTTP for CPU-bound jobs
                                       ┌──────▼───────────┐
                                       │ Go compute svc   │  media/thumbnails,
                                       │ apps/compute     │  scraping, hashing
                                       └──────────────────┘
```

Full reasoning behind these choices: [`docs/02-architecture.md`](docs/02-architecture.md).

## Repository layout

```
metacreator.dev/
├── apps/
│   ├── api/            Laravel 12 — API, admin domain logic, queues, billing
│   ├── web/            Next.js 15 — public site, dashboards, tool UIs
│   └── compute/        Go service — CPU/IO-bound tool runners
├── deploy/
│   ├── scripts/        Provision, deploy, rollback and remote ops (live)
│   ├── templates/      nginx, php-fpm and systemd config, rendered per host
│   └── ansible/        Superseded — see deploy/README.md before running it
├── docker/             Local dev images and service configs
├── docs/               The project handbook (start at docs/README.md)
├── docker-compose.yml  Local development stack
└── Makefile            Task shortcuts for everyday work
```

## Quick start

Requirements: Docker Desktop (or Docker Engine + Compose v2), `make`, and ~4 GB free RAM.

```bash
git clone git@github.com:metacreator/metacreator.dev.git && cd metacreator.dev
make setup
```

`make setup` copies env files, builds images, starts the stack, installs dependencies, runs
migrations and seeds a demo dataset (admin user, tool catalog, sample posts).

| Service | URL |
| --- | --- |
| Web (Next.js) | http://localhost:3000 |
| API (Laravel) | http://localhost:8080 |
| Horizon (queues) | http://localhost:8080/horizon |
| Mailpit (email inbox) | http://localhost:8025 |
| MySQL | `localhost:3307` (`metacreator` / `secret`) |
| Redis | `localhost:6380` |

Seeded admin: `admin@metacreator.dev` / `password` — sign in and open
[localhost:3000/c0ns0le](http://localhost:3000/c0ns0le).

Details and troubleshooting: [`docs/19-local-development.md`](docs/19-local-development.md).

## Common commands

```bash
make up          # start the stack
make down        # stop it
make logs        # tail all services
make sh-api      # shell into the PHP container
make migrate     # run migrations
make fresh       # wipe + migrate + seed
make test        # run the full test suite (Pest + Vitest + Playwright)
make lint        # Pint, PHPStan, ESLint, tsc, Prettier
```

## Documentation

Everything about this project — product decisions, schema, API contracts, design system, runbooks —
lives in [`docs/`](docs/README.md). That file is the master index; each topic is its own markdown
file so pages stay short and reviewable.

The handbook describes the *intended* design throughout. For what is actually built right now, read
[`docs/24-implementation-status.md`](docs/24-implementation-status.md) — it is the only document
that claims something exists, and it lists the known environment gotchas.

## Deployment

Production is a **shared** DigitalOcean droplet that also serves ten unrelated
websites, so deploys are driven by audited shell scripts that only ever touch
this app's own resources — never Ansible against shared config.

```bash
make preflight   # read-only check that the host still matches deploy/config.sh
make deploy      # upload, build, migrate, switch — with automatic rollback
make status      # health of the app, and of the rest of the droplet
make rollback    # back one release
```

Run commands against production from your machine:

```bash
./deploy/scripts/artisan.sh migrate:status
./deploy/scripts/remote.sh                  # the full operations menu
```

Everything — first-time setup, the safety model that keeps eleven sites on one
box, and troubleshooting — is in [`deploy/README.md`](deploy/README.md).
Rationale is in [`docs/20-deployment.md`](docs/20-deployment.md).

## License

Proprietary. © MetaCreator.Dev. All rights reserved.
