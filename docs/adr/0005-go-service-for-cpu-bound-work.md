# ADR 0005 — A Go sidecar for CPU- and IO-bound tool runners

- **Status:** Accepted
- **Date:** 2026-08-24

## Context

Several catalog tools are a poor fit for PHP workers: image and video processing, batch hashing,
and high-concurrency fan-out such as checking a few hundred links. In PHP these either block a worker
for a long time or require one process per unit of concurrency.

## Decision

A small stateless Go service (`apps/compute`) on the private interface handles this class of work.
Laravel queue workers call it over HTTP with a short timeout, a circuit breaker, and a documented
fallback per tool.

## Rationale

- Goroutines make bounded fan-out trivial: 200 concurrent HTTP checks is one `errgroup` with a
  semaphore, versus 200 PHP processes.
- Image and video work in Go (and shelling to ffmpeg from it) frees PHP workers for what they are
  good at.
- Keeping it stateless and behind a narrow HTTP contract means it can be scaled, replaced, or removed
  without touching domain logic.

## Consequences

- A second language and build in the deploy pipeline, plus its own health check and monitoring.
- A hard rule: **no tool may hang on it.** Every call has a timeout and a circuit breaker; when it is
  open, affected tools report `tool.unavailable` and the catalog marks them degraded.
- The contract is deliberately tiny (a handful of endpoints, JSON in, JSON or an object-storage key
  out) so the coupling stays shallow.

## Alternatives rejected

- **Do it all in PHP** — workable for images with ext-vips, but the fan-out tools would need a
  process-per-request model that is wasteful and hard to bound.
- **Serverless functions** — good fit technically, but adds a cloud dependency and cold-start
  latency to interactive tools, and complicates the single-droplet deployment story.
- **Python instead of Go** — better ML ecosystem, but this workload is concurrency and image
  processing, where Go's deployment story (a single static binary) is materially simpler.
