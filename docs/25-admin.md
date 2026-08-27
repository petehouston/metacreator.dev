# 25 — Admin Dashboard

The staff surface: `/admin` in the frontend, `/api/v1/admin/*` in the API.

Two rules run through everything here, and most of the design follows from them.

**Every endpoint declares the permission it needs, and a test fails the build if one does not.**
That is what makes [the permission catalog](06-auth-rbac.md) a control rather than documentation —
a new admin route cannot ship open by accident.

**The UI hiding something is never what makes it safe.** Navigation, buttons and fields are filtered
by the actor's permissions because a screen full of controls that 403 is a bad screen, not because
that filtering protects anything. Every route re-checks server-side.

## Screens

| Screen | Route | Permission | What it is for |
| --- | --- | --- | --- |
| Overview | `/admin` | `analytics.view` | The health of the product in one request |
| Analytics | `/admin/analytics` | `analytics.view` or `tool_analytics.view` | Tools, funnel, content — the panels in [15](15-analytics.md) |
| Posts | `/admin/posts` | `posts.view_any` | Status tabs with counts, search, bulk actions |
| Post editor | `/admin/posts/{id}` | `posts.view` (row-checked) | The block editor plus a settings/SEO/history panel |
| Categories & tags | `/admin/taxonomy` | `post_categories.view_any`, `tags.view_any` | Both taxonomies on one screen |
| Media | `/admin/media` | `media.view_any` | Grid, upload, alt text |
| Tools | `/admin/tools` | `tools.view_any` | Tier, visibility, catalog copy |
| Tool grants | `/admin/grants` | `tool_grants.view_any` | Comped access, attributed and expirable |
| Users | `/admin/users` | `users.view_any` | Find anyone; detail screen per person |
| Roles & permissions | `/admin/roles` | `roles.view_any` | Compose access from the catalog |
| Billing | `/admin/billing` | `plans.*`, `subscriptions.*`, `invoices.*` | Plans (writable), Stripe records (read-only) |
| Tickets | `/admin/tickets` | `tickets.view_any` | The queue, worst-first |
| Contact inbox | `/admin/messages` | `tickets.view_any` | The public form's inbox |
| Newsletter | `/admin/newsletter` | `newsletter.view` | The list and its provider sync |
| Settings | `/admin/settings` | `settings.view` | Branding, flags, SEO, tracking, providers |
| Audit log | `/admin/activity` | `activity_log.view` | Who changed what |

A staff member who lands on a screen their role does not cover is redirected to the first screen it
*does* cover, rather than shown an empty page.

## Decisions worth knowing

### Dashboards read rollups, never live tables

`analytics:rollup` folds `tool_runs` into `tool_run_daily_stats`, `billing_daily_stats` and
`content_daily_stats`. It is scheduled every fifteen minutes for the current day and again after
midnight for the day that closed, and the screen shows when it last ran.

The rollup is a **recompute, not an append** — running it twice for one day produces the same rows,
which is what makes a backfill (`analytics:rollup --days=90`) safe.

Two reasons for the indirection, and the second is the one that bites: raw runs are pruned at 90
days, so a year-long chart read from them would silently flatten to zero for its first nine months.

Point-in-time figures — MRR, open tickets — are read live, because a snapshot of "right now" that is
fifteen minutes old is not a snapshot of right now.

### MRR normalises, it does not sum

A yearly plan contributes `amount / 12`. Booking a year's cash as one month of recurring revenue is
the classic way a dashboard flatters itself. One-time passes are excluded entirely: they do not
recur.

### The funnel counters had to be built

`tool_funnel_daily` was specified in [15](15-analytics.md) and had no table. More importantly,
**paywall hits had no source at all** — an access denial threw before anything was recorded, so
"which premium tools do free users actually want?" was unanswerable, which is the whole reason the
tiering exists.

`FunnelRecorder` now counts views, starts, completions and walls. The three walls are three columns,
not one `blocked` counter, because they are three different product problems:

| Column | What it means | What it suggests |
| --- | --- | --- |
| `paywall_hits` | Hit `tool.subscription_required` | Pricing or tiering |
| `account_walls` | Hit `tool.account_required` | The signup gate is in the wrong place |
| `quota_walls` | Hit `tool.quota_exceeded` | The limit is too tight |

Counters, not raw events: a view is high-volume and worthless individually, so it is folded into a
per-tool-per-day row at write time by a queued, atomic upsert.

### Field-level authorization is real

A support agent sees that someone is on a paid plan without seeing the invoice that proves it.
`AdminUserResource` and `InvoiceResource` check the *actor* before emitting hosted Stripe URLs,
which are effectively bearer links to a financial document.

### Three permissions guard one settings table

`settings.update` for ordinary values. `settings.scripts.update` separately, because raw HTML in
`<head>` is arbitrary code execution on every public page. `settings.secrets.update` separately
again, because provider API keys are credentials. An `admin` holds the first two and not the third.

Checked **per key, not per request**: one payload may legitimately span several groups.

Secrets are never sent to the browser — only whether one is set. A blank secret means "leave it
alone", so saving the newsletter form does not wipe the API key it never showed you.

### The audit log cannot be edited

There is no endpoint that changes or deletes an entry, because a log an administrator can rewrite
answers no question worth asking. `AuditLogger` records only the keys that moved, old and new, and
masks anything whose name looks like a credential — a rotated key must never appear in plaintext in
an audit trail.

### What the admin deliberately cannot change

| Field | Why |
| --- | --- |
| A user's email | The account's identity. Staff perform an audited transfer instead ([06](06-auth-rbac.md)) |
| A tool's `key` | Binds the catalog row to its runner; drift is a 500 on the next run |
| A tool's `slug` | Breaks every link and search result pointing at it |
| A tool's `input_schema`, `version` | Owned by the runner; they move with a deploy |
| A plan's `key`, `interval`, `stripe_price_id` | The contract with Stripe and with every existing subscriber |
| A role's name | Code and audit history reference it by name |
| Anything about a Stripe subscription or invoice | Writes go to Stripe and come back through the webhook |

### Guardrails that are enforced, not implied

- The last `super-admin` cannot be demoted, including by themselves.
- A seeded role cannot be deleted; one still assigned to someone cannot be deleted either.
- `super-admin`'s permission set is not editable — it bypasses checks entirely via `Gate::before`.
- An `admin` cannot assign roles, delete users, read provider secrets, or impersonate.
- Deleting a category or tag never deletes the writing; only the label is removed.
- Posts and users soft-delete, so invoices keep a payer and tool runs keep an owner.

## The post editor

The brief was that the writing experience must be "in full", with everything else arranged somewhere
with better UX than a stack of fields under the article. So: a centred writing column at the
article's own measure, and a collapsible side panel holding status, taxonomy, SEO and history.

WYSIWYG is structural rather than promised. The editing chrome — move, duplicate, delete — lives in
a gutter *outside* the content column, and each block is rendered with the typography the published
article uses. The column you type in is the column that ships.

**Extensibility**: adding a block type is one entry in `block-kinds.ts`, one `case` in
`block-fields.tsx`, one enum case in `BlockType`, one branch in `BlockSanitizer`, and one renderer in
`block-renderer.tsx`. An unknown type is preserved verbatim and shown as a labelled placeholder —
an older deploy must never destroy content written by a newer one.

**Concurrency**: every save carries the post's `version`; a stale one gets a 409 and an explanation.
Two editors in one post is the normal case, not the exotic one.

**Formatting** uses `document.execCommand`. It is deprecated and it is also the only API that
applies inline formatting to a selection with the browser's own undo stack intact. Output is
sanitised server-side on save regardless, which is what makes relying on it safe.

## Two bugs this work surfaced

Both were silent, and both are now covered by tests.

**Block content was deleted on every save.** `SavePostRequest` validated `blocks.blocks.*.type` and
nothing else, and Laravel's `validated()` returns only the keys a rule names — so every block
reached the action as `{type: "paragraph"}`, with a 200 and nothing in the logs. `payload()` now
takes the block document from the raw input; `BlockSanitizer` is the real gatekeeper for its
contents, and always was.

**Category and tag pickers could not save.** The admin listings reused the public resources, which
omit numeric ids by design — so every option in the category dropdown saved as null.
`AdminTaxonomyResource` carries the id, matching every other admin endpoint.

## Frontend conventions

- `useAdminResource` keeps the previous rows on screen while the next request runs, and discards a
  superseded response rather than letting it overwrite a newer one.
- `usePagedFilters` holds filters and the page number in one state object, so changing a filter
  cannot render once with the new filter and the old page.
- Charts are inline SVG. Everything needed is a line, a stacked bar and a proportion strip; a
  charting library would cost more than every chart on the screen combined. Each is `aria-hidden`
  and paired with a real number in text.
- Ticket bodies and contact messages render as plain text. They are untrusted input, and rendering
  them as HTML would make the support queue the softest XSS target in the product.
