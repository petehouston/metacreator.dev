# ADR 0003 — Portable block JSON as the canonical post format

- **Status:** Accepted
- **Date:** 2026-08-24

## Context

The editor must be a Medium/Notion-style block experience, must be WYSIWYG in the strict sense (the
editing surface looks identical to the published page), must support many content types including
embeds and raw HTML, and must be extensible with new block types over time.

## Decision

Store content as an ordered array of typed blocks in a JSON column. Each block type is defined once
in `packages/blocks` as a zod schema, an editor node view, and a **render component shared by both
the editor and the site**. `content_html` is a regenerable cache, never the source of truth.

## Rationale

- WYSIWYG fidelity becomes structural: the editor renders the published component and overlays
  selection chrome, so the two cannot diverge.
- A design-system change re-renders all historical content correctly; stored HTML could not.
- Embeds stay as data, so we can change embed strategy globally (privacy facade → real iframe on
  click) without touching content.
- Block-level diffing makes revision history genuinely useful.
- Content is portable to a newsletter renderer, a mobile app, or an export.

## Consequences

- Rendering requires the renderer — content is not readable as HTML in the database. Mitigated by
  keeping `content_html` and `content_text` alongside for search, feeds and emergencies.
- Block schemas need versioning and migration functions; unknown types must render as a preserved
  placeholder so a rollback can never destroy content.
- Full-text search runs against `content_text`, not the JSON.

## Alternatives rejected

- **HTML in a column (WordPress classic)** — simplest, but locks content to today's markup and makes
  true WYSIWYG a maintenance promise rather than a guarantee.
- **Markdown** — clean and portable, but cannot express the embed, carousel and tool-card blocks the
  product needs, and its editing experience is not what was asked for.
