# 13 — Notifications & Email

One event catalog drives every channel. A feature never sends an ad-hoc email; it fires an event
that is declared in the catalog, and the delivery layer decides the channels.

## Channels

| Channel | Storage | Notes |
| --- | --- | --- |
| **In-app** | `notifications` table | Bell menu, unread badge, grouped by day, deep-linked |
| **Email** | Provider chosen in admin (Mailpit locally) | Rendered from the same block renderer as the site |
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
| Provider | Chosen in admin (Settings → Email), not baked into a deploy — see below |
| Deliverability | SPF, DKIM, DMARC (`p=quarantine` after a two-week monitoring period) on a dedicated subdomain `mg.metacreator.dev` |
| Separation | Transactional and marketing use separate sending domains so a campaign complaint cannot poison password resets |
| Templates | MJML-derived responsive base; dark-mode aware; plain-text alternative generated, not omitted |
| Suppression | Bounces and complaints sync into a local suppression list checked before every send |
| Webhooks | Delivery, open, bounce, complaint → `email_events` for per-template deliverability stats |
| Local | Mailpit at `http://localhost:8025` catches everything |

### Choosing a provider

Mail settings live in the `mail` group of the settings table and are edited under
**Settings → Email** in the admin console. `App\Domain\Notifications\Mail\MailConfigurator`
applies them over `config/mail.php` the first time anything resolves the mailer — not on
every request, because the overwhelming majority of requests this application serves never
send an email.

| Provider | Transport | Notes |
| --- | --- | --- |
| `smtp` | Laravel | Works with anything that speaks SMTP, including a provider's own relay |
| `mailgun` | `MailgunTransport` | Posts the assembled MIME message to `messages.mime`; US or EU endpoint |
| `postmark` | `PostmarkTransport` | JSON `/email`; message stream matters — Postmark refuses a send to the wrong one |
| `resend` | `ResendTransport` | JSON `/emails` |
| `ses` | Laravel | Reads `services.ses`; the identity must be verified in the configured region |
| `klaviyo` | `KlaviyoTransport` | **Not a relay** — see the caveat below |
| `sendmail`, `log` | Laravel | The local binary, and "render but never deliver" |

Mailgun, Postmark, Resend and Klaviyo would each normally arrive as a Symfony bridge or a
vendor package. They are implemented here against the framework's HTTP client instead: four
dependencies to let one dropdown change is a poor trade when each provider's send endpoint is
a single POST, and it means every provider in the dropdown is live on a fresh
`composer install` with `Http::fake()` covering them all in tests.

Two rules govern how the settings interact with the environment:

- **Blank means "not configured here", not "blank."** An empty setting leaves the
  deployment's own `MAIL_*` value in place, so local development keeps pointing at Mailpit
  with nothing seeded, and saving the screen once cannot wipe a working configuration.
- **The `array` mailer is never displaced.** `tests/bootstrap.php` pins it so no suite can
  send real mail, and a seeded row must not be able to override that.

Credentials are `is_encrypted`, so they are encrypted at rest, never returned to the browser
(only whether one is set), and changing one needs `settings.secrets.update` — which `admin`
deliberately does not hold.

### Klaviyo is not a sending provider

Klaviyo has no endpoint that takes a rendered message and delivers it. Its transactional path
is an event posted against a metric, which triggers a flow that renders and sends the email.
So selecting Klaviyo means:

- **A flow must exist.** Until someone builds one on the configured metric, every send
  succeeds at the API and delivers nothing. Nothing in this codebase can detect that, so the
  admin screen says so instead.
- **Klaviyo owns delivery** — timing, throttling and suppression are decided there.
- **Attachments are rejected rather than silently dropped**, because an invoice email
  delivered without the invoice is worse than one that failed loudly.

It suits lifecycle mail an operator already runs in Klaviyo. Password resets and receipts want
a dedicated sending provider.

### The delivery check

`GET /api/v1/admin/settings/mail` reports what the *saved* settings add up to, naming the keys
still empty rather than a bare "not configured". `POST /api/v1/admin/settings/mail/test` sends
a real message through the configured transport and returns the provider's own error verbatim —
"domain not found" and "invalid API key" need different fixes, and a paraphrase loses which one
you have. The send deliberately bypasses the queue: the point is the transport's answer, and a
queued send would return 200 whatever happened.

This exists because mail is the one integration whose breakage is invisible from outside. The
site looks fine while nobody can reset a password, and the failure surfaces days later as a
support ticket.

## In-app notification UX

Bell with an unread count; a panel grouped by Today / Earlier with icons per category; each item has
a title, a one-line body, a relative timestamp and a deep link. "Mark all read", filter by category,
and a full `/dashboard/notifications` history page. Reads are batched client-side and flushed on
panel close so a busy user does not generate a request per item.
