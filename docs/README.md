# MetaCreator.Dev — Project Handbook

This is the master index. Every document below is self-contained and short enough to read in one
sitting; start here and follow the links.

> **Convention:** decisions live in `adr/`, *how things work* lives in the numbered files, and
> *how to operate them* lives in the 19–22 range. If you change behaviour, change the doc in the
> same pull request.

## 1. Product

| # | Document | What it covers |
| --- | --- | --- |
| 01 | [Product overview](01-product-overview.md) | Audience, positioning, access tiers, monetisation, success metrics |
| 07 | [Tool catalog](07-tool-catalog.md) | The full brainstormed catalog of creator tools, tiering and priority |
| 23 | [Roadmap](23-roadmap.md) | Phased delivery plan and what "done" means per phase |
| 24 | [Implementation status](24-implementation-status.md) | What is actually built versus specified — read this first |

## 2. Engineering

| # | Document | What it covers |
| --- | --- | --- |
| 02 | [Architecture](02-architecture.md) | System topology, request lifecycles, module boundaries |
| 03 | [Tech stack](03-tech-stack.md) | Every dependency and why it was chosen |
| 04 | [Data model](04-data-model.md) | Tables, relationships, indexes, migration conventions |
| 05 | [API design](05-api.md) | REST conventions, envelopes, errors, pagination, versioning |
| 06 | [Auth & RBAC](06-auth-rbac.md) | Sessions, magic links, Google OAuth, roles and the permission matrix |
| 08 | [Tool engine](08-tool-engine.md) | The tool contract, registry, runners, quotas, caching |
| 18 | [Queues & workers](18-queues-workers.md) | Queue topology, priorities, idempotency, Horizon config |

## 3. Content & growth

| # | Document | What it covers |
| --- | --- | --- |
| 09 | [Blog & CMS](09-blog-cms.md) | Block editor, statuses, revisions, bulk actions, extensibility |
| 10 | [Media library](10-media-library.md) | Uploads, variants, storage, metadata and SEO fields |
| 14 | [Newsletter & marketing](14-newsletter-marketing.md) | Provider adapters, capture placements, double opt-in |
| 15 | [Analytics & tracking](15-analytics.md) | Internal tool telemetry, GA4/GTM/Pixel, custom scripts |
| 25 | [Admin dashboard](25-admin.md) | The staff surface: screens, permissions, rollups, guardrails |
| 16 | [SEO](16-seo.md) | Metadata model, structured data, sitemaps, canonical rules |

## 4. Commerce & support

| # | Document | What it covers |
| --- | --- | --- |
| 11 | [Billing & subscriptions](11-billing.md) | Plans, Stripe integration, invoices, dunning, entitlements |
| 12 | [Support tickets](12-support-tickets.md) | Ticket lifecycle, threading, SLA, admin workflows |
| 13 | [Notifications & email](13-notifications-email.md) | Channels, event catalog, admin-configured transactional providers |

## 5. Design

| # | Document | What it covers |
| --- | --- | --- |
| 17 | [Design system](17-design-system.md) | Tokens, typography, colour, components, motion, a11y |

## 6. Operations

| # | Document | What it covers |
| --- | --- | --- |
| 19 | [Local development](19-local-development.md) | Docker stack, seeding, day-to-day workflow, troubleshooting |
| 20 | [Deployment](20-deployment.md) | Ansible roles, droplet layout, releases, rollback, secrets |
| 21 | [Security](21-security.md) | Threat model, hardening, HTML sanitisation, PII, compliance |
| 22 | [Testing](22-testing.md) | Test pyramid, tooling, fixtures, CI gates |

## 7. Decision records

| ADR | Decision |
| --- | --- |
| [0001](adr/0001-decoupled-nextjs-frontend.md) | Decoupled Next.js frontend instead of Blade/Inertia |
| [0002](adr/0002-tool-registry-contract.md) | Tools as declarative registry entries, not bespoke controllers |
| [0003](adr/0003-portable-block-json-for-posts.md) | Portable block JSON as the canonical post format |
| [0004](adr/0004-stripe-as-billing-source-of-truth.md) | Stripe as the source of truth for entitlements |
| [0005](adr/0005-go-service-for-cpu-bound-work.md) | A Go sidecar for CPU/IO-bound tool runners |

## Glossary

| Term | Meaning |
| --- | --- |
| **Tool** | A single creator utility (e.g. "YouTube Tag Extractor") with a declared input schema and runner |
| **Runner** | The class/service that executes a tool and returns a normalised result |
| **Tier** | Access requirement of a tool: `free`, `account`, or `premium` |
| **Entitlement** | The computed set of things a user may do, derived from plan + grants + roles |
| **Grant** | An explicit, admin-issued permission for one user to use one tool regardless of tier |
| **Run** | One recorded execution of a tool by a user or guest |
| **Block** | One unit of post content in the editor (paragraph, quote, embed, code, …) |
