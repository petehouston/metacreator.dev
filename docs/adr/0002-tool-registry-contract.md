# ADR 0002 — Tools as declarative registry entries, not bespoke controllers

- **Status:** Accepted
- **Date:** 2026-08-24

## Context

The catalog will reach 60+ tools in year one. If each tool is a controller, a route, a request
class, a React page and its own access check, the marginal cost of a tool stays high forever — and
the access/quota/telemetry logic gets copy-pasted, which means it will eventually be wrong somewhere.

## Decision

A tool is a **database row** (slug, tier, JSON Schema, instructions, example, config) plus **one
runner class** implementing `ToolRunner`. One controller, one action and one frontend route serve
every tool. Capability interfaces (`Cacheable`, `Queueable`, `AcceptsFiles`, …) opt a runner into
extra behaviour.

## Rationale

- Access control, quotas, validation, caching and telemetry are implemented once, in the action, and
  are impossible for a runner to skip — a runner has no other invocation path.
- The input JSON Schema drives both server validation and the generated form, so the two cannot
  drift.
- Results declare a *view type*; the frontend has one renderer per view type, so most tools need
  zero frontend code.
- Admin controls (tier, visibility, kill switch, featuring) become data edits rather than deploys.

## Consequences

- Adding a typical tool is one class, one catalog entry and one test.
- Genuinely bespoke UIs are still possible — a runner's key can map to a custom component — but that
  is the exception, and it is visible as such.
- The schema-to-form generator is now a critical shared component and needs strong tests; a bug in it
  breaks every tool at once.

## Alternatives rejected

- **A controller per tool** — familiar, but the duplication of access and telemetry logic is exactly
  the failure mode this product cannot afford.
- **Tools as plugins/packages** — over-engineered for a single-team codebase, and it complicates
  deployment for no current benefit.
