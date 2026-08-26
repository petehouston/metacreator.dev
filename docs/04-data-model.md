# 04 — Data Model

MySQL 8.4, `utf8mb4_0900_ai_ci`, strict mode, InnoDB. Foreign keys everywhere; the database is the
last line of defence for consistency.

## Conventions

| Rule | Rationale |
| --- | --- |
| `id` = `BIGINT UNSIGNED AUTO_INCREMENT` primary key | Compact, fast, good for InnoDB clustering |
| `ulid` = `CHAR(26)` unique, exposed publicly as `prefix_ULID` | Sortable, unguessable, no PK leakage |
| Timestamps `created_at` / `updated_at`, soft deletes only where recovery matters | Avoids "deleted" rows piling up in hot tables |
| Enums stored as `VARCHAR` + PHP enum cast, never MySQL `ENUM` | Adding a value must not require an ALTER on a big table |
| JSON columns only for **schema-free, non-queried** payloads | Anything filtered or joined gets a real column |
| JSON whose **object key order is meaningful** goes in a `LONGTEXT` column | MySQL's `JSON` type normalises objects by sorting their keys (see below) |
| Money as `INT UNSIGNED` minor units + `CHAR(3)` currency | No floats near money, ever |
| Every FK has an index; every `WHERE`+`ORDER BY` pair used by a list screen has a composite index | Verified by a slow-query test in CI |

## Entity map

```
users ──┬─< user_profiles                        users >──< roles >──< permissions
        ├──< sessions / personal_access_tokens
        ├──< subscriptions >── plans             (Stripe-projected)
        ├──< invoices >──< invoice_lines
        ├──< tool_runs >── tools                 (telemetry)
        ├──< tool_grants >── tools               (explicit access)
        ├──< tickets >──< ticket_messages >──< ticket_attachments
        ├──< notifications
        └──< media                               (uploader)

tools >── tool_categories                        tools >──< tools (related, self-join)
posts  >── post_categories, >──< post_tags, ──< post_revisions
posts / tools / pages ──1:1── seo_meta (polymorphic)
media ──< media_variants
settings (typed KV)      newsletter_subscribers      activity_log
```

## Core tables

### `users`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint PK | |
| `ulid` | char(26) unique | public id `usr_…` |
| `email` | varchar(255) unique | **immutable after creation** — enforced in the model and by a DB trigger |
| `email_verified_at` | timestamp null | |
| `password` | varchar(255) null | null for OAuth/magic-link-only accounts |
| `display_name` | varchar(60) | |
| `avatar_media_id` | bigint FK null | → `media` |
| `locale`, `timezone` | varchar | defaults `en`, `UTC` |
| `status` | varchar(20) | `active` \| `suspended` \| `pending_deletion` |
| `last_seen_at` | timestamp null | |
| `marketing_opt_in` | boolean | drives newsletter sync |
| soft deletes | | 30-day recovery window, then a scheduled hard purge |

Indexes: `email` unique, `ulid` unique, `(status, last_seen_at)`.

### `tools`

| Column | Type | Notes |
| --- | --- | --- |
| `slug` | varchar(120) unique | URL identity; never changes once published |
| `key` | varchar(120) unique | Registry key binding the row to its runner class |
| `name`, `tagline`, `description` | | |
| `category_id` | FK → `tool_categories` | |
| `tier` | varchar(20) | `free` \| `account` \| `premium` |
| `platforms` | json | `["youtube","tiktok"]` — also denormalised into `tool_platform` for filtering |
| `status` | varchar(20) | `draft` \| `published` \| `hidden` \| `deprecated` |
| `is_visible` | boolean | admin show/hide independent of status |
| `version` | unsigned int | bumped when output semantics change; part of the cache key |
| `input_schema` | **longtext** | JSON Schema; drives the generated form and server validation. Text, not JSON — see the warning below |
| `config` | json | runner options (timeouts, provider, limits) |
| `instructions`, `example` | json | block content, rendered by the same renderer as posts |
| `run_count`, `avg_duration_ms` | | denormalised counters updated by the analytics job |
| `sort_order`, `featured_at` | | catalog merchandising |

Indexes: `slug` unique, `key` unique, `(status, is_visible, category_id)`, `(tier, status)`,
fulltext on `(name, tagline, description)`.

> **`input_schema` is `LONGTEXT`, not `JSON`, and that is deliberate.**
>
> MySQL's native `JSON` type stores a normalised representation and **re-sorts object
> keys** (shortest first, then lexicographically). That is harmless for most data, but
> a tool's `properties` order *is* the order its generated form renders fields in — so
> storing the schema as `JSON` silently shuffles every form. We hit exactly this: a
> calculator that should ask for platform and followers first instead opened with
> "Saves / bookmarks".
>
> The fix is `LONGTEXT` plus the `AsPreservedJson` cast, which round-trips the document
> byte-for-byte. **Arrays are unaffected** — JSON array order is preserved — so the
> block documents in `instructions`, `example` and `faq` remain `JSON` columns.

### `tool_runs` — the telemetry spine

| Column | Type | Notes |
| --- | --- | --- |
| `tool_id` | FK | |
| `tool_version` | unsigned int | which behaviour produced this |
| `user_id` | FK null | null = anonymous |
| `visitor_hash` | char(64) null | HMAC(ip+ua+daily salt) — attribution without storing PII |
| `status` | varchar(20) | `queued` \| `running` \| `succeeded` \| `failed` \| `cancelled` |
| `access_reason` | varchar(30) | `free` \| `account` \| `subscription` \| `grant` \| `admin` — *why* it was allowed |
| `duration_ms` | unsigned int null | |
| `input_hash` | char(64) | for cache hits and dedupe; raw input is **not** stored by default |
| `input_preview` | json null | only when the tool declares `retain_input: true` and the user consented |
| `result_ref` | varchar(255) null | Spaces key for large results |
| `error_code` | varchar(60) null | |
| `cache_hit` | boolean | |
| `referrer_source` | varchar(60) null | organic/blog/related-tool/etc. |
| `created_at` | | |

Indexes: `(tool_id, created_at)`, `(user_id, created_at)`, `(status, created_at)`,
`(tool_id, status, created_at)`. Partitioned by month once past ~50M rows; a nightly job rolls
data older than 90 days into `tool_run_daily_stats` and prunes.

### `tool_grants`

`user_id`, `tool_id`, `granted_by`, `reason`, `expires_at` (null = forever), unique
`(user_id, tool_id)`. Every write is mirrored into `activity_log`.

### `plans` / `subscriptions` / `invoices`

`plans`: `key` (`pass_7d`, `pro_monthly`, `pro_yearly`), `stripe_price_id`, `interval`,
`amount`, `currency`, `features` json, `is_active`, `sort_order`.

`subscriptions`: `user_id`, `plan_id`, `stripe_id`, `stripe_status`, `current_period_start/end`,
`trial_ends_at`, `cancel_at`, `ends_at`. Index `(user_id, stripe_status)`.

`invoices`: `user_id`, `stripe_invoice_id`, `number`, `status`, `subtotal`, `tax`, `total`,
`currency`, `hosted_url`, `pdf_url`, `paid_at`. Lines in `invoice_lines`.

Stripe is authoritative; these tables are a projection rebuilt by webhooks and a nightly
reconciliation command ([11](11-billing.md)).

### `posts`

`ulid`, `slug` unique, `title`, `excerpt`, `blocks` json (canonical content), `content_html`
(rendered cache), `content_text` (search/reading time), `featured_media_id`, `category_id`,
`author_id`, `status` (`draft` \| `unpublished` \| `published` \| `scheduled` \| `archived` \| `deleted`),
`published_at`, `scheduled_for`, `reading_minutes`, `view_count`, `is_featured`, `allow_comments`,
`version`, soft deletes.

Indexes: `slug` unique, `(status, published_at)`, `(category_id, status, published_at)`,
`(status, scheduled_for)` for the publisher job, fulltext `(title, excerpt, content_text)`.

`post_revisions`: `post_id`, `author_id`, `blocks` json, `title`, `note`, `created_at` — one row per
save, pruned to the last 50 per post.

### `media` / `media_variants`

`media`: `ulid`, `disk`, `path`, `filename`, `mime`, `size`, `width`, `height`, `duration_ms`,
`checksum` (sha256, dedupes re-uploads), `alt_text`, `caption`, `title`, `credit`, `folder_id`,
`uploaded_by`, `usage_count`.
`media_variants`: `media_id`, `label` (`thumb`/`card`/`hero`/`og`), `format` (`webp`/`avif`/`jpg`),
`width`, `height`, `path`, `size`.

### `seo_meta` (polymorphic)

`seoable_type`, `seoable_id`, `title`, `description`, `canonical_url`, `robots` (`index,follow`…),
`og_title`, `og_description`, `og_media_id`, `twitter_card`, `schema_type`, `schema_overrides` json,
`focus_keyword`. Unique `(seoable_type, seoable_id)`.

### `settings`

Typed key-value: `key` unique, `value` json, `type` (`string|bool|int|json|secret`), `group`
(`branding`, `scripts`, `newsletter`, `billing`, `features`), `is_public`. Secrets are encrypted at
rest and never returned by the public endpoint.

### `tickets`

`ulid`, `user_id`, `subject`, `category`, `priority` (`low|normal|high|urgent`),
`status` (`open|pending|on_hold|solved|closed`), `assigned_to`, `first_response_at`, `resolved_at`,
`last_activity_at`. `ticket_messages`: `ticket_id`, `author_id`, `author_type` (`user|staff`),
`body`, `is_internal_note`. Index `(status, priority, last_activity_at)`.

### `notifications`

Laravel's default table plus `category` and `action_url` columns, with
`notification_preferences` (`user_id`, `event_key`, `email`, `in_app`) controlling delivery.

## Migration conventions

- One migration per logical change; never edit a migration that has run in production.
- Additive first: add column → backfill in a queued job → switch reads → drop the old column in a
  later release. Three deploys, zero downtime.
- Any migration touching a table over 1M rows must state its strategy in a comment at the top and be
  runnable with `pt-online-schema-change`.
- Every migration has a working `down()`. CI runs `migrate --pretend`, `migrate`, `migrate:rollback`
  on a seeded database.
