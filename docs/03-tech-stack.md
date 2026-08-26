# 03 — Tech Stack

Every dependency here earns its place; anything that could be replaced by 50 lines of our own code
was.

## Backend — `apps/api`

| Package | Version | Why |
| --- | --- | --- |
| `laravel/framework` | ^13 | Latest LTS-track release. Queues, events, policies, scheduler out of the box |
| `laravel/sanctum` | ^4 | Cookie (SPA) auth for the first-party frontend; tokens for future clients |
| `laravel/horizon` | ^6 | Redis queue supervision, metrics, retries, failure inspection |
| `laravel/scout` + MySQL driver | ^11 | Tool/post search; swappable to Meilisearch without call-site changes |
| `laravel/socialite` | ^5 | Google OAuth only, no other providers at launch. **Not yet installed** — the current release constrains Guzzle to `^7`, and the framework pulls in Guzzle 8. Tracked in the roadmap; email+password and magic-link cover launch without it |
| `laravel/cashier` (Stripe) | ^16 | Subscriptions, invoices, webhook plumbing that is tedious to get right |
| `spatie/laravel-permission` | ^7 | Roles/permissions storage. Our RBAC layer wraps it — see [06](06-auth-rbac.md) |
| `spatie/laravel-medialibrary` | ^12 | Media conversions and storage abstraction |
| `spatie/laravel-activitylog` | ^4 | Audit trail on admin actions (required for grants, billing, RBAC) |
| `spatie/laravel-sitemap` | ^7 | Sitemap generation feeding the Next.js routes |
| `mews/purifier` (HTMLPurifier) | ^3 | Hard sanitisation boundary for custom-HTML blocks |
| `league/flysystem-aws-s3-v3` | ^3 | DigitalOcean Spaces |
| `pestphp/pest` | ^4 | Test runner |
| `larastan/larastan` | ^4 | Static analysis at level 8 |
| `laravel/pint` | ^1 | Formatting, zero config debate |

**Deliberately not used:** Filament/Nova for admin (the admin is part of the Next.js app so the
design system is shared and the API stays the only backend contract); Inertia (see
[ADR 0001](adr/0001-decoupled-nextjs-frontend.md)).

## Frontend — `apps/web`

| Package | Version | Why |
| --- | --- | --- |
| `next` | 16.x | App Router, RSC, ISR with tag revalidation, first-class metadata API |
| `react` | 19.x | Actions, `useOptimistic`, `use()` — real ergonomic wins for tool forms |
| `tailwindcss` | 4.x | CSS-first config, design tokens as CSS variables |
| `shadcn/ui` | latest | Copy-in Radix components we own and restyle; no runtime dependency to fight |
| `@radix-ui/*` | latest | Accessible primitives underneath shadcn |
| `lucide-react` | latest | Icon set, tree-shakeable, consistent stroke weight |
| `@tanstack/react-query` | ^5 | Client cache for dashboard/admin; RSC covers public pages |
| `react-hook-form` + `zod` | ^7 / ^4 | Tool forms are generated from schemas; zod is the shared shape |
| `@tiptap/*` | ^3 | Editor core for the block editor ([09](09-blog-cms.md)) |
| `next-themes` | ^0 | Dark mode without a flash |
| `recharts` | ^3 | Admin analytics charts |
| `vitest` + `@testing-library/react` | latest | Unit/component tests |
| `@playwright/test` | latest | E2E on the critical funnels |

## Compute — `apps/compute`

Go 1.24, standard library plus:

| Package | Why |
| --- | --- |
| `go-chi/chi` | Minimal router |
| `disintegration/imaging` | Image resizing/thumbnailing |
| `golang.org/x/sync/errgroup` | Bounded concurrency for fan-out tools |

## Infrastructure

| Component | Choice | Notes |
| --- | --- | --- |
| Database | MySQL 8.4 | `utf8mb4_0900_ai_ci`, strict mode, JSON columns used deliberately (see [04](04-data-model.md)) |
| Cache/queue | Redis 7.4 | Separate logical DBs: `0` cache, `1` sessions, `2` queues, `3` locks |
| Object storage | DigitalOcean Spaces | S3 API + built-in CDN |
| Web server | Caddy 2 | Automatic TLS, HTTP/3, simple config |
| Email (transactional) | Mailgun | EU/US region configurable; Mailpit locally |
| Payments | Stripe | Checkout + Billing Portal, minimal bespoke UI |
| Error tracking | Sentry | Both apps, release-tagged |
| Local dev | Docker Compose | [19](19-local-development.md) |
| Deployment | Ansible | [20](20-deployment.md) |
| CI | GitHub Actions | Lint → static analysis → tests → build → deploy on tag |

## Version policy

- Pin exact versions in lockfiles; allow caret ranges in manifests.
- Dependabot/Renovate opens weekly grouped PRs; patch updates auto-merge on green CI, minors are
  reviewed, majors get an issue and a dedicated branch.
- Any dependency that has not been released in 18 months is a candidate for removal at review time.
