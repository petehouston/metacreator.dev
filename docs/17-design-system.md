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

MetaCreator's visual idea is **"Signal"**: the confidence of a social network's own
product surface, without the noise. It reads as an instrument you work in, not as a feed.

Three things carry it, and they are the whole signature:

1. **The two grounds.** The marketing surface sits on a *canvas* — one fixed layer of four soft
   colour washes, a fine engineering grid and a whisper of grain, painted once in
   `body::before`/`::after` so no page has to remember to be interesting. The signed-in app
   opts out of all of it: `data-surface="app"` on `<html>` swaps in a flat, quiet ground. A page
   that has to sell needs weather; a workspace someone sits inside for an hour does not.
2. **The bracket rule.** Every section label is mono, uppercase, wide-tracked, and preceded by a
   short brand-to-accent tick (`.eyebrow`). It appears on the landing page, the catalog, the tool
   pages, the dashboard and the footer, and it is the fastest way the product says "same place"
   across surfaces.
3. **The spine.** A 3px hairline on the left edge of the thing that is current or hovered — the
   active sidebar row, a tool card under the cursor. One shape, one gesture, reused everywhere.

Type does the rest of the work: **DM Sans** for anything read, **JetBrains Mono** for anything
*measured* — counts, durations, prices, breadcrumbs, category labels. That split is what makes the
product read as an instrument rather than as a landing page.

### Colour

Tokens are CSS variables defined in `apps/web/src/app/globals.css`, consumed through Tailwind 4's
`@theme`. Light and dark are both first-class.

```css
/* Brand — cobalt. The blue every network trained people to trust, pushed a few
   degrees toward violet so it is ours rather than anyone else's. */
--color-brand-500: oklch(0.62 0.192 261);
--color-brand-600: oklch(0.55 0.205 262);   /* Primary, light mode */
--color-brand-400: oklch(0.70 0.165 260);   /* Primary, dark mode */

/* Signal — emerald. "This is live / this is the value", never decorative.
   The 600–800 end exists so it can also be *text* on paper. */
--color-signal-400: oklch(0.82 0.150 163);
--color-signal-700: oklch(0.56 0.122 166);

/* Ember — coral. The third voice: author bylines, "most popular", reading times. */
--color-ember-300 … --color-ember-600

/* Grounds — two ramps. Paper is a cool, screen-native white; ink is a deep
   slate-navy tinted toward the brand hue so dark mode is not a hole. */
--color-paper-0 … --color-paper-300
--color-ink-400 … --color-ink-1000

/* Semantic */
--color-background, --color-surface, --color-surface-solid,
--color-surface-raised, --color-surface-sunken,
--color-border, --color-border-strong, --color-border-subtle,
--color-foreground, --color-foreground-muted, --color-foreground-subtle,
--color-primary, --color-primary-foreground, --color-primary-subtle,
--color-accent, --color-accent-surface, --color-ember,
--color-success, --color-warning, --color-danger, --color-info

/* Canvas — the fixed marketing background, per theme */
--canvas-base, --canvas-wash-1 … --canvas-wash-4, --canvas-line, --canvas-grain

/* App — the flat signed-in ground, per theme */
--app-ground, --app-rail, --app-surface
```

Marketing surfaces are deliberately **translucent** (`--color-surface` carries an alpha, and
`.panel` adds a backdrop blur). The canvas behind them tints each card slightly differently
depending on where it sits on the page, which is what stops a long grid from looking like a
spreadsheet. The app uses `.app-card` instead: opaque, no blur — dense tabular data needs to be
read, not admired, and a blur per card in a long list is a composite layer per card.

Rules: never use a raw ramp value in a component — always a semantic token. Every text/background
pair must clear 4.5:1 (3:1 for large text), asserted by a contrast test over the token set.

### Typography

**DM Sans** for everything, **JetBrains Mono** for code and numeric output. Both are OFL-licensed,
self-hosted as variable WOFF2 subsets (latin + latin-ext), preloaded, `font-display: swap`.

| Token | Size / line-height | Use |
| --- | --- | --- |
| `display-xl` | 4.5rem / 0.98, −0.035em | Landing hero |
| `display-lg` | 3.5rem / 1.02, −0.03em | Section heroes |
| `heading-1` | 2.5rem / 1.12, −0.025em | Page titles |
| `heading-2` | 1.875rem / 1.22, −0.02em | Section titles |
| `heading-3` | 1.375rem / 1.3 | Card titles |
| `body-lg` | 1.125rem / 1.7 | Article body |
| `body` | 1rem / 1.65 | Default |
| `body-sm` | 0.875rem / 1.5 | Secondary |
| `caption` | 0.75rem / 1.4, 0.02em | Labels, meta |
| `mono` | 0.875rem / 1.6 | Code, IDs, results |

Article measure is capped at 68ch. Numbers in results use tabular figures so columns align.

Display sizes step down on small screens (`text-heading-1 sm:text-display-lg`) — 4.5rem is a poster
size, not a phone size.

### Space, radius, elevation

4px base scale (`0.5 1 2 3 4 6 8 12 16 24`). Radii: `sm 4px`, `md 8px`, `lg 12px`, `xl 18px`,
`full` — squarer than the usual default, so the interface rhymes with the two-rounded-squares mark
instead of melting into pills. Elevation is expressed as layered, low-opacity shadows plus a 1px border — in dark mode,
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

### Dashboard (the app shell)

The signed-in product is an **app shell**, not the marketing layout with a sidebar bolted on.
`app/(site)` and `app/(app)` are sibling route groups: the first brings the site header, footer and
painted canvas; the second brings the shell and nothing else.

```
┌──────────────┬──────────────────────────────────────────────┐
│  rail 17rem  │  topbar 4rem: title · ⌘K · bell · theme · me │
│  logo        ├──────────────────────────────────────────────┤
│  WORKSPACE   │                                              │
│   Overview   │   page content, max 80rem                    │
│   Tools      │                                              │
│   Run history│                                              │
│  ACCOUNT     │                                              │
│   Notificat. │                                              │
│   Plan…      │                                              │
│   Settings   │                                              │
│  HELP        │                                              │
│   Support    │                                              │
│  ┌────────┐  │                                              │
│  │ plan   │  │                                              │
│  │ meter  │  │                                              │
│  └────────┘  │                                              │
└──────────────┴──────────────────────────────────────────────┘
```

Decisions worth keeping:

- **The rail is fixed and collapsible** to a 4.5rem icon strip, remembered in `localStorage` and
  read through `useSyncExternalStore` so there is no width flash on load. Below `lg` it becomes an
  off-canvas drawer whose open state is stored as *the path it was opened on*, so navigating closes
  it by derivation rather than by an effect firing after the new page has already painted.
- **One nav registry.** `components/app/nav-items.ts` feeds the sidebar, the drawer and the ⌘K
  palette; a screen cannot exist in two of the three and be missing from the third.
- **⌘K is real.** Navigation and account actions are matched locally; tools are searched against the
  live catalog, debounced, with every superseded request aborted.
- **The plan meter is the only permanent upsell,** and it earns the spot by answering "how many runs
  do I have left today" first. It turns amber at 75% and red at 100% — a warning that arrives when
  the wall is hit arrives too late to act on.
- **Entitlements are fetched once** for the whole shell (`EntitlementsProvider`) rather than by each
  screen that needs the plan.
- **Loading is derived, never a flag.** List screens compare "which request is on screen" against
  "which request is current", which keeps the previous page of results visible while the next one
  loads and avoids setting state inside an effect body.

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
