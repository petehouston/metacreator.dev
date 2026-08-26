# 17 — Design System

## Design principles

1. **The tool is the interface.** Chrome recedes; the input, the button and the result get the
   space. No hero image on a tool page above the tool itself.
2. **Show the value before asking for anything.** Free tools run with zero friction; upgrade prompts
   appear next to a real result, never as an interstitial.
3. **Confident, not loud.** One accent colour used sparingly earns more attention than a gradient on
   every surface.
4. **Fast is a feature people can feel.** Skeletons that match final layout, optimistic UI,
   never a layout shift.
5. **Accessible by construction.** WCAG 2.2 AA is the floor, verified in CI, not retrofitted.

## Brand

MetaCreator's visual idea is **"the creator's workshop"**: a calm, near-black canvas with precise,
instrument-like surfaces and one electric accent that marks where the work happens. It should feel
closer to a pro audio/video tool than to a marketing site — because the audience already lives in
those tools.

### Colour

Tokens are CSS variables defined in `apps/web/src/app/globals.css`, consumed through Tailwind 4's
`@theme`. Light and dark are both first-class; dark is the default on the marketing surface.

```css
/* Brand */
--brand-50:  oklch(0.97 0.02 265);
--brand-500: oklch(0.62 0.21 264);   /* Electric indigo — primary accent */
--brand-600: oklch(0.55 0.22 264);
--brand-950: oklch(0.24 0.10 264);

/* Signal (used for "this is live/valuable", never decoratively) */
--signal-400: oklch(0.82 0.19 152);  /* Spring green */

/* Neutrals — a warm-cool balanced grey ramp */
--ink-0 … --ink-1000

/* Semantic */
--color-background, --color-surface, --color-surface-raised,
--color-border, --color-border-strong,
--color-foreground, --color-muted-foreground,
--color-primary, --color-primary-foreground,
--color-success, --color-warning, --color-danger, --color-info
```

Rules: never use a raw ramp value in a component — always a semantic token. Every text/background
pair must clear 4.5:1 (3:1 for large text), asserted by a contrast test over the token set.

### Typography

**DM Sans** for everything, **JetBrains Mono** for code and numeric output. Both are OFL-licensed,
self-hosted as variable WOFF2 subsets (latin + latin-ext), preloaded, `font-display: swap`.

| Token | Size / line-height | Use |
| --- | --- | --- |
| `display-xl` | 4.5rem / 1.02, −0.03em | Landing hero |
| `display-lg` | 3.5rem / 1.05, −0.025em | Section heroes |
| `heading-1` | 2.5rem / 1.15, −0.02em | Page titles |
| `heading-2` | 1.875rem / 1.25 | Section titles |
| `heading-3` | 1.375rem / 1.3 | Card titles |
| `body-lg` | 1.125rem / 1.7 | Article body |
| `body` | 1rem / 1.65 | Default |
| `body-sm` | 0.875rem / 1.5 | Secondary |
| `caption` | 0.75rem / 1.4, 0.02em | Labels, meta |
| `mono` | 0.875rem / 1.6 | Code, IDs, results |

Article measure is capped at 68ch. Numbers in results use tabular figures so columns align.

### Space, radius, elevation

4px base scale (`0.5 1 2 3 4 6 8 12 16 24`). Radii: `sm 6px`, `md 10px`, `lg 14px`, `xl 20px`,
`full`. Elevation is expressed as layered, low-opacity shadows plus a 1px border — in dark mode,
depth comes from *surface lightness*, not shadow, because shadows disappear on dark backgrounds.

### Motion

| Token | Duration / easing | Use |
| --- | --- | --- |
| `fast` | 120 ms · ease-out | Hover, focus |
| `base` | 200 ms · cubic-bezier(0.2, 0, 0, 1) | Dropdowns, tabs |
| `slow` | 320 ms | Slide-overs, modals |

Everything respects `prefers-reduced-motion` — animations become instant state changes, never merely
faster.

## Component inventory

Built on shadcn/ui (Radix) primitives, restyled to the tokens above. Owned in-repo under
`apps/web/src/components/ui`.

| Group | Components |
| --- | --- |
| Foundation | Button (5 variants × 4 sizes), Input, Textarea, Select, Combobox, Checkbox, Radio, Switch, Slider, Label, Field (label+hint+error) |
| Feedback | Toast, Alert, Banner, Skeleton, Spinner, EmptyState, ErrorState, ProgressBar |
| Overlay | Dialog, SlideOver, Popover, Tooltip, DropdownMenu, CommandPalette, ContextMenu |
| Navigation | Header, MegaMenu, Sidebar, Tabs, Breadcrumb, Pagination, Footer |
| Data | Table (sortable, selectable, bulk bar), DataGrid, Card, Stat, Badge, Avatar, Chart wrappers |
| Domain | ToolCard, ToolForm (schema-generated), ResultPanel, CopyButton, PaywallCard, UpgradePrompt, PlanCard, PostCard, BlockRenderer, MediaPicker, TicketThread, NotificationItem |

`ToolForm` and `ResultPanel` are the two that matter: they are what make a new tool cost zero
frontend work ([08](08-tool-engine.md)).

## Key screens

### Landing page

Ordered for conversion, each section earning the scroll:

1. **Hero with a working tool.** Not a screenshot — a real, runnable tool embedded in the hero
   (character counter / engagement calculator). The visitor gets value in under five seconds, before
   any ask. This single decision is the landing page's whole strategy.
2. **Social proof strip** — creator count, runs this month, platforms supported.
3. **Tool grid preview** — 8 tools with tier badges, filterable by platform. Real cards, real links.
4. **"How it works"** — three steps, no signup in any of them.
5. **Outcome sections** — Grow / Analyze / Create, each with a concrete before-and-after.
6. **Pricing** — three cards, yearly pre-selected, "no card needed to start" stated plainly.
7. **FAQ** — the actual objections (is it free, do you post for me, is my data safe), FAQ schema.
8. **Final CTA** — one button, one line of copy.

Sticky header CTA appears after the hero scrolls out. Exactly one primary action per viewport.

### Tool page

```
Breadcrumb · Tier badge · Platform chips
H1: tool name          Short outcome-focused subtitle
┌──────────────────────────────┐ ┌──────────────────────┐
│  INPUT (generated form)      │ │  Related tools       │
│  [Try with sample data]      │ │  Related reading     │
│  [ Run tool ]                │ │  Upgrade card (free) │
├──────────────────────────────┤ └──────────────────────┘
│  RESULT panel                │
│  copy / download / save      │
└──────────────────────────────┘
How to use it   ·   Worked example   ·   FAQ   ·   Related tools
```

The result panel is reserved above the fold on desktop so the page does not jump when a result
arrives — a skeleton of the correct shape occupies it beforehand.

### Admin

Persistent left sidebar grouped by domain, filtered by the actor's permissions (an Accountant never
sees a "Posts" item at all). Dense tables with sticky headers, a bulk-action bar that slides up on
selection, and a global `⌘K` palette for navigation and actions.

## Responsive

| Breakpoint | Width | Layout |
| --- | --- | --- |
| `base` | < 640 | Single column, bottom-sheet overlays, 44px touch targets |
| `sm` | 640 | Two-column cards |
| `md` | 768 | Sidebars appear |
| `lg` | 1024 | Full tool page layout |
| `xl` | 1280 | Max content width 1200px |
| `2xl` | 1536 | Wider gutters, content stays 1200px |

Mobile is designed first for tool pages, because that is where the traffic lands.

## Accessibility

WCAG 2.2 AA. Concretely: visible focus rings on every interactive element (never `outline: none`
without a replacement), full keyboard operation including the editor and drag interactions, correct
landmarks and one H1 per page, live regions announcing tool results, forms with programmatically
associated labels and errors, motion and contrast preferences honoured, and `axe` assertions in
component tests plus a Playwright a11y sweep of key routes in CI.
