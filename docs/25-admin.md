# 25 — Admin Dashboard

The staff surface: `/c0ns0le` in the frontend, `/api/v1/admin/*` in the API.

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
| Overview | `/c0ns0le` | `analytics.view` | The health of the product in one request |
| Analytics | `/c0ns0le/analytics` | `analytics.view` or `tool_analytics.view` | Tools, funnel, content — the panels in [15](15-analytics.md) |
| Posts | `/c0ns0le/posts` | `posts.view_any` | Status tabs with counts, search, bulk actions |
| Post editor | `/c0ns0le/posts/{id}` | `posts.view` (row-checked) | The article itself, editable, plus a settings/SEO/history panel |
| Post preview | `/c0ns0le/posts/preview` | `posts.view` | The unsaved draft, rendered as the public article |
| Categories & tags | `/c0ns0le/taxonomy` | `post_categories.view_any`, `tags.view_any` | Both taxonomies on one screen |
| Media | `/c0ns0le/media` | `media.view_any` | Grid, upload, alt text |
| Tools | `/c0ns0le/tools` | `tools.view_any` | Tier, visibility, catalog copy |
| Tool grants | `/c0ns0le/grants` | `tool_grants.view_any` | Comped access, attributed and expirable |
| Users | `/c0ns0le/users` | `users.view_any` | Find anyone; detail screen per person |
| Roles & permissions | `/c0ns0le/roles` | `roles.view_any` | Compose access from the catalog |
| Plans | `/c0ns0le/billing/plans` | `plans.view_any` | The catalogue: what is for sale, at what price |
| Plan editor | `/c0ns0le/billing/plans/{id}`, `/new` | `plans.view_any`, `plans.create` | One plan on its own page — pricing, features, gateway identifiers |
| Subscriptions | `/c0ns0le/billing/subscriptions` | `subscriptions.view_any` | Who is paying and what renews when (read-only) |
| Invoices | `/c0ns0le/billing/invoices` | `invoices.view_any` | Every charge, refund and outstanding balance |
| Invoice | `/c0ns0le/billing/invoices/{id}` | `invoices.view` | One invoice: lines, plan, card, transaction, refund |
| Billing report | `/c0ns0le/billing/report` | `invoices.view_any` | Revenue, churn and the breakdowns behind them |
| Tickets | `/c0ns0le/tickets` | `tickets.view_any` | The queue, worst-first |
| Contact inbox | `/c0ns0le/messages` | `tickets.view_any` | The public form's inbox |
| Newsletter | `/c0ns0le/newsletter` | `newsletter.view` | The list and its provider sync |
| Settings | `/c0ns0le/settings` | `settings.view` | A section rail: general, blog, accounts, payments, SEO, tracking, email, newsletter |
| Audit log | `/c0ns0le/activity` | `activity_log.view` | Who changed what |

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

### Settings has no "features" tab

Every `features.*` flag lives in the section it governs: the blog switch at the top of Blog, the
checkout switch at the top of Payments, the sign-up switch under Accounts. A tab of unrelated
toggles is a tab you visit to turn something on and then leave to configure it.

The `group` column is unchanged, because `group` and *section* answer different questions. `group`
is a permission and storage concern — everything under `scripts` needs `settings.scripts.update`.
A section is where a human would look for something.

Inside a section, keys are dealt into titled cards rather than one flat column: Payments is
*General*, then Stripe, then PayPal, then Braintree, each with its own explanation. Three providers'
credentials in one column is a screen where a Stripe key gets pasted into a PayPal field. A key no
card claims falls through to the section's catch-all, so a setting added server-side appears in the
UI without a matching frontend change.

### Mail settings carry a delivery check

Every other section on the settings screen changes something an admin can immediately see — a flag
flips, a template renders. Email does not: a wrong SMTP password looks exactly like a right one
until a customer cannot reset their password, and the failure surfaces days later as a support
ticket.

So the Email section opens with a card that does two things a save cannot. It reports what the
*saved* settings add up to, naming the keys still empty rather than a bare "not configured", and it
sends a real message through the configured provider and reports the provider's own error verbatim.
It reads saved state, never the draft, which is why an unsaved change is called out rather than
quietly tested around — a test that silently used the pending values would be a green tick for a
configuration nobody is running.

Klaviyo is flagged separately wherever it appears, because it is not a sending provider: it receives
an event and a flow the operator builds does the sending. A green "ready" there means the API key
works, not that mail arrives. See [13](13-notifications-email.md).

### Blog presentation is configuration

`blog.show_author`, `blog.show_published_date`, `blog.show_reading_time`, `blog.show_featured_image`,
`blog.show_categories`, `blog.show_tags`, `blog.show_related_posts` and `blog.posts_per_page` are
public settings read by the frontend through `GET /api/v1/settings` — the only endpoint that serves
the settings table without a session, and it serves `is_public` and non-encrypted rows only.

The frontend defaults to *everything on* when that request fails. The direction matters: a settings
endpoint that is briefly unavailable must not blank the bylines off every article on the site.

### An invoice answers its own questions

The detail page exists because somebody opens one charge with a customer waiting. So it carries what
that conversation needs in one load: the lines, the period, the plan and subscription behind it, the
card it was taken from, the transaction at the gateway, and the refund with its reason.

The columns are gateway-neutral — `transaction_id` is a Stripe payment intent, a PayPal capture or a
Braintree transaction depending on `gateway`. A per-provider column set would make adding PayPal a
migration.

Nothing on the page is editable. An invoice is corrected by issuing another document, never by
rewriting the first one. `transaction_id`, `transaction_url` and the refund reference are gated on
`invoices.view` for the same reason `hosted_url` is: they identify a payment at the provider.

### The billing report is net, and normalised

Revenue is always **net of refunds** — a charge that came back was never revenue, and reporting gross
alongside refunds as two positives makes a bad month read as a good one. Recurring revenue is
normalised to a month, so a yearly plan contributes a twelfth.

Churn divides by what was live when the window *opened*, not by what is live now: dividing by
today's count means a month of growth quietly deflates churn.

It is gated on `invoices.view_any` rather than `analytics.view`. This is money — the marketing
analyst who reads the funnel has no business reading customer revenue.

### Three permissions guard one settings table

`settings.update` for ordinary values. `settings.scripts.update` separately, because raw HTML in
`<head>` is arbitrary code execution on every public page. `settings.secrets.update` separately
again, because provider API keys are credentials. An `admin` holds the first two and not the third.

Checked **per key, not per request**: one payload may legitimately span several groups.

Secrets are never sent to the browser — only whether one is set. A blank secret means "leave it
alone", so saving the newsletter form does not wipe the API key it never showed you.

### A plan with subscribers cannot be re-priced

Plans are ours: create, edit, enable, disable, delete. But `amount`, `interval` and `billing_mode`
lock the moment a plan has a live subscription, and the API refuses their presence rather than
ignoring them. Re-pricing somebody's active subscription from an admin form is a chargeback, not a
feature. The way to sell at a new price is a new plan with the old one turned off — nobody new can
buy it, and nobody already on it is moved behind their back. Deleting is offered only for a plan
nobody has *ever* bought; anything with history is deactivated, because every invoice referencing it
would otherwise lose the record of what was sold.

### Payment providers are configuration, not a rewrite

Which gateway takes the money is `payments.provider` in Settings, and each plan carries the
identifier every gateway knows it by in one `gateway_ids` column. Adding PayPal is a settings change
and a column value, not a migration and not a second plan table. Credentials for the providers that
are not live are kept rather than cleared, so switching back is a dropdown.

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

### Console access, for when the UI cannot help

Two commands cover the cases the admin UI cannot: there is no first admin yet, or every admin is
locked out. Both are deliberately console-only — shell access on the server is the authorisation.

```
php artisan admin:create [email] [--role=super-admin] [--name=] [--password=] [--no-password]
php artisan admin:login-link <email> [--ttl=15] [--redirect=/c0ns0le]
```

`admin:create` is idempotent: an existing account is promoted rather than rejected, keeping its
history, and `--replace-roles` is the opt-in for swapping its roles instead of adding one. It
defaults to `super-admin` because that is the only genuinely unrestricted role; `--role=admin` gets
the reduced set above. The account is created active and email-verified, since a verification link
nobody can click would just re-create the lockout.

`admin:login-link` prints a single-use magic link ([06](06-auth-rbac.md)) instead of emailing it,
which is what makes it useful when mail itself is what broke. It carries the same guarantees as the
emailed link — one use, short TTL, same-site redirects only — plus two of its own: issuing one
invalidates every outstanding link for that address, and each issue is written to the `security`
audit log. Unlike the public endpoint it reports a missing, suspended or non-staff account out
loud; the anti-enumeration silence protects strangers, not operators.

## The post editor

The brief was that the writing experience must be "in full", with everything else arranged somewhere
with better UX than a stack of fields under the article. So: a centred writing column at the
article's own measure, and a collapsible side panel holding status, taxonomy, SEO and history.

WYSIWYG is structural rather than promised. The editing column *is* the article: the same header the
public page renders — category badge, reading time, title, standfirst, byline, featured image — at
the same measure, with the block canvas beneath it. The editing chrome — move, duplicate, delete —
lives in a gutter *outside* the content column. The column you type in is the column that ships.

**The difference between the editor and the article is the tools, and only the tools.** A block that
is text is typed directly. A block that is a configuration — an image, an embed, a button, a tool
card, a divider — renders through the *public* renderer and reveals an options strip beneath it when
the caret is in it. Nothing is drawn twice, so the two cannot drift.

**The permalink is spelled out in full**, `https://…/blog/slug`, with edit and copy: what an editor
needs to judge is the address readers will see, and a bare slug does not tell you where it lands.

**Categories work the way WordPress's do**, and for the same reason. A post sits on several shelves
but has one home: `posts.category_id` is the *primary* category — it owns the URL, the breadcrumb and
the archive — and `post_post_category` holds the rest. Ticking a box adds a shelf; the star moves the
home. New categories and tags are created inline, because the moment someone has to open the taxonomy
screen mid-sentence, the post gets filed under whatever already exists.

**The media library is a modal, not a screen you have to leave for.** Browse, search, upload and fix
alt text without losing the post — reachable from the image block's own link and from the featured
image. An image picker that makes people find a URL by hand produces hotlinks and an empty library.

**Autosave** runs seven seconds after the last keystroke, flagged `is_autosave` so the revision it
snapshots is distinguishable from a deliberate save. It never runs on a post that has not been
created yet — otherwise every abandoned "New post" leaves a husk behind — and it never changes
status.

**Preview without publishing** hands the *current, unsaved* draft to a second tab through session
storage, where it is rendered by the public article layout and the public block renderer. Previewing
what is already on the server would answer a question nobody asked; a preview drawn from a second set
of components would be a preview of nothing in particular.

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
