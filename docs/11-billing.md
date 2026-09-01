# 11 — Billing & Subscriptions

Stripe is the source of truth; our tables are a projection ([ADR 0004](adr/0004-stripe-as-billing-source-of-truth.md)).
Anything that decides money is decided by Stripe, and anything that decides *access* reads our
projection so a Stripe outage cannot lock out paying customers.

## Plans

| Key | Price | Billing | Notes |
| --- | --- | --- | --- |
| `free` | $0 | — | Implicit; not a Stripe product |
| `pass_7d` | $9 | One-time | 7 days of Pro. Non-renewing, no card kept on file beyond the charge |
| `pro_monthly` | $19 | Monthly | |
| `pro_yearly` | $180 | Yearly | Highlighted as "2 months free" |

Prices live in Stripe; `plans.stripe_price_id` binds them. Changing a price means creating a new
Stripe price and a new plan row — existing subscribers are never silently re-priced.

## Checkout

Stripe Checkout, not a bespoke card form: no card data ever touches our servers, SCA/3DS is handled,
and tax, wallets and local payment methods come free.

```
POST /billing/checkout {plan_key, promo_code?}
  → create/reuse Stripe Customer (idempotent on user ulid)
  → create Checkout Session with metadata {user_ulid, plan_key}
  → return {url}
  → Stripe redirects to /billing/success?session_id=… (a polling page, not a trust boundary)
  → entitlement is granted by the WEBHOOK, never by the redirect
```

The `pass_7d` is a one-time payment that creates a **time-boxed entitlement row** rather than a
subscription; expiry is a scheduled job plus a computed check, so a missed job cannot extend access.

## Webhooks

`POST /webhooks/stripe`, signature-verified, replay-safe via a `stripe_events` table keyed on the
event id. Handled events:

| Event | Effect |
| --- | --- |
| `checkout.session.completed` | Grant entitlement, send receipt, fire `subscription.started` |
| `customer.subscription.updated` | Re-project status, period, cancel-at |
| `customer.subscription.deleted` | Downgrade at period end; notify |
| `invoice.paid` | Upsert invoice, email receipt |
| `invoice.payment_failed` | Enter dunning, notify, keep access during the grace window |
| `charge.refunded` | Revoke or prorate access, audit-log it |

Every handler is idempotent and safe to replay — the reconciliation command re-plays the last 30
days nightly and reports drift.

## Turning billing off

`features.billing_enabled` (Settings → Payments → General) is the master switch for money, and it is
a different question from `payments.enabled`: payments is the till, billing is the shop. Off, the
product has no paid plans at all.

`BillingFeature` owns the switch and everything reads through it, so the API, the catalog and the UI
cannot disagree:

| Surface | Billing on | Billing off |
| --- | --- | --- |
| A `premium` tool | Pro subscribers only | Gated at `account` — signing up is the whole price |
| `free` / `account` tools | Unchanged | Unchanged |
| `EntitlementService::isPaid()` | Reads the projection | Always `false` |
| `limits.export`, `history_days` | Paid only | Unlocked for any account |
| `limits.priority_support` | Paid only | Still paid only — an SLA, not a feature |
| Quota wall's `next_tier` above `account` | `premium` | `null`, so no upgrade is offered |
| `GET /settings` | Publishes `payments.*` | Omits them; keeps `features.billing_enabled` |
| `/pricing`, `/dashboard/billing` | Rendered | 404, and dropped from nav and sitemap |

The `tools.tier` column is **never written**. A premium tool keeps saying `premium`; only its
*effective* tier changes, which is what makes the switch reversible — turning billing back on
restores the paywall with no migration. `Tool::effectiveTier()` is the read every public surface
uses; the admin editor deliberately reads the raw `tier`, because that is the value it exists to
edit. Admin billing screens stay reachable throughout: plans and history are how an operator
prepares to switch it back on.

Asserted end to end in `tests/Feature/BillingDisabledTest.php`, in both directions.

## Entitlements

`EntitlementService` computes, and caches for 60 s:

```
active := subscription.status ∈ {active, trialing}
        OR pass.expires_at > now
        OR admin grant
```

There is a **3-day grace period** on `past_due` so a failed card doesn't instantly break someone's
work, with an in-app banner and email sequence during it.

## Customer-facing billing

`/dashboard/billing` shows the current plan, renewal or expiry date, usage against limits, an
invoice table with PDF links, and buttons for the Stripe Billing Portal (payment method, cancel,
resume). Cancellation is self-serve, immediate to schedule, effective at period end, and asks one
optional question about why — that answer is the most valuable retention data we collect.

## Admin-facing billing

Accountants get: subscription list with filters and MRR/ARR, churn and expansion, invoice search and
export (CSV for the accountant, per-period), refund issuance with a mandatory reason, comped
subscriptions, and a per-user billing timeline. Every action is audit-logged with the actor.

## Reporting

Nightly `billing:snapshot` writes MRR, ARR, active subscriptions by plan, trial and pass conversion,
churn and LTV into `billing_daily_stats`, so dashboards never aggregate over live invoice tables.

## Tax and compliance

Stripe Tax handles calculation and collection. Invoices carry the legal entity, address, tax ID
field for business customers, and sequential numbering. Retention: 7 years for financial records,
independent of account deletion — a deleted user's invoices are retained with the personal fields
redacted.
