# 13 — Notifications & Email

One event catalog drives every channel. A feature never sends an ad-hoc email; it fires an event
that is declared in the catalog, and the delivery layer decides the channels.

## Channels

| Channel | Storage | Notes |
| --- | --- | --- |
| **In-app** | `notifications` table | Bell menu, unread badge, grouped by day, deep-linked |
| **Email** | Mailgun (Mailpit locally) | Rendered from the same block renderer as the site |
| **Broadcast** | Reserved | For live updates on long-running tool runs (Phase 3) |

All email is queued on the `mail` queue with backoff `[10s, 60s, 5m]` and a dead-letter alert.

## Event catalog

Declared in `app/Domain/Notifications/EventCatalog.php`. Each entry defines its key, default
channels, whether the user may opt out, the template and the required payload. A test asserts that
every dispatched event exists in the catalog and that every catalog entry has a template.

### Account

| Event | Channels | Opt-out |
| --- | --- | --- |
| `user.welcome` | email, in-app | no |
| `user.email_verify` | email | no |
| `user.password_reset` | email | no |
| `user.password_changed` | email, in-app | no |
| `user.magic_link` | email | no |
| `user.new_device_login` | email, in-app | yes |
| `user.profile_updated` | in-app | yes |
| `user.deletion_scheduled` / `.cancelled` | email, in-app | no |

### Billing

| Event | Channels | Opt-out |
| --- | --- | --- |
| `billing.subscription_started` | email, in-app | no |
| `billing.invoice_paid` | email | no |
| `billing.payment_failed` | email, in-app | no |
| `billing.renewal_reminder` (yearly, −7 d) | email | yes |
| `billing.pass_expiring` (−24 h) | email, in-app | yes |
| `billing.subscription_cancelled` / `.ended` | email, in-app | no |
| `billing.refund_issued` | email | no |

### Tools

| Event | Channels | Opt-out |
| --- | --- | --- |
| `tool.run_completed` (async runs) | in-app | yes |
| `tool.run_failed` | in-app | yes |
| `tool.quota_warning` (80%) | in-app | yes |
| `tool.access_granted` | email, in-app | no |
| `tool.new_tool_published` | email (digest), in-app | yes |

### Support

`support.ticket_created`, `.staff_replied`, `.customer_replied`, `.solved`, `.sla_breach` (staff).

### Staff

`staff.post_scheduled_published`, `staff.new_subscription`, `staff.payment_failed`,
`staff.tool_error_spike`, `staff.new_ticket`.

## Preferences

`notification_preferences` stores per-user, per-event channel toggles. The settings screen groups
events into human categories ("Account security", "Billing", "Product updates") rather than exposing
raw keys. Transactional and security events cannot be disabled — that is expressed by
`opt_out: false` in the catalog, not by hiding the toggle.

Every marketing email carries a one-click `List-Unsubscribe` header and honours it.

## Email infrastructure

| Concern | Approach |
| --- | --- |
| Provider | Mailgun; `MAIL_MAILER=mailgun`, region-configurable |
| Deliverability | SPF, DKIM, DMARC (`p=quarantine` after a two-week monitoring period) on a dedicated subdomain `mg.metacreator.dev` |
| Separation | Transactional and marketing use separate Mailgun domains so a campaign complaint cannot poison password resets |
| Templates | MJML-derived responsive base; dark-mode aware; plain-text alternative generated, not omitted |
| Suppression | Bounces and complaints sync into a local suppression list checked before every send |
| Webhooks | Delivery, open, bounce, complaint → `email_events` for per-template deliverability stats |
| Local | Mailpit at `http://localhost:8025` catches everything |

## In-app notification UX

Bell with an unread count; a panel grouped by Today / Earlier with icons per category; each item has
a title, a one-line body, a relative timestamp and a deep link. "Mark all read", filter by category,
and a full `/dashboard/notifications` history page. Reads are batched client-side and flushed on
panel close so a busy user does not generate a request per item.
