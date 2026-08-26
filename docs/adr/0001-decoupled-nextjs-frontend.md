# ADR 0001 — Decoupled Next.js frontend instead of Blade/Inertia

- **Status:** Accepted
- **Date:** 2026-08-24

## Context

The whole product depends on organic search. Tool pages and blog posts must render fast, carry
precise metadata, and be cacheable at the edge, while the tools themselves are genuinely interactive
(file drops, live previews, incremental results). We had three options: Blade + Alpine, Laravel +
Inertia + React, or a fully decoupled Next.js app against a JSON API.

## Decision

A decoupled Next.js 16 App Router frontend consuming a versioned Laravel JSON API, with
cookie-based Sanctum authentication on a shared apex domain.

## Rationale

- ISR with tag-based revalidation gives near-static delivery of content that an editor can change at
  any moment. Reproducing that on the Laravel side is possible but is a bespoke cache-invalidation
  system we would own forever.
- `generateMetadata`, streaming, and `next/og` cover the SEO surface directly.
- The tool UIs are React-shaped. Inertia would have given us React too, but it couples page rendering
  to the backend and gives up ISR and RSC.
- The API is needed regardless — for a future mobile client, partner integrations, and the Pro API
  tier already on the roadmap.

## Consequences

**Accepted costs:** two runtimes to build, deploy and monitor; a network hop on every request; a
typed client that must stay in sync with the API.

**Mitigations:** both apps run on the same host, so the hop is sub-millisecond; the frontend client
is generated from the OpenAPI spec in CI, so a breaking API change fails the frontend build; cookie
auth avoids a token-refresh dance entirely.

## Alternatives rejected

- **Blade + Alpine** — cheapest to run, but the tool UIs and the block editor would have been a
  fight, and the editor's WYSIWYG guarantee would have been impossible without duplicating the
  renderer.
- **Inertia** — a good middle ground that loses ISR, RSC and the standalone API we need anyway.
