# ADR 0004 — Stripe as the source of truth for billing, MySQL as a projection

- **Status:** Accepted
- **Date:** 2026-08-24

## Context

Subscription state can be changed in several places: our checkout, the Stripe Billing Portal, a
Stripe dashboard action by support, or automatically by dunning. Two writable copies of that state
will diverge, and divergence in billing means either giving away product or locking out paying
customers.

## Decision

Stripe owns subscription and invoice state. Our tables are a **projection** maintained by
idempotent, signature-verified webhooks and repaired by a nightly reconciliation command.
Entitlement checks read the local projection.

## Rationale

- One writer means no merge conflicts; every change flows through Stripe and back.
- Reading entitlements locally means a Stripe outage cannot lock out paying users — access keeps
  working from the projection.
- Webhook handlers keyed on the Stripe event id are replay-safe, which makes out-of-order and
  duplicate delivery a non-event.
- Nightly replay of the last 30 days surfaces drift as a report rather than as a support ticket.

## Consequences

- A short propagation delay exists between payment and entitlement. The success page polls the
  entitlements endpoint rather than assuming; the redirect is never a trust boundary.
- We must never mutate subscription state locally except through a webhook handler. This is enforced
  by keeping those writes in a single class and asserting it in an architecture test.
- Refunds and comps must be issued through Stripe (via our admin UI calling Stripe), not by editing
  rows.

## Alternatives rejected

- **Local source of truth with Stripe as a payment rail** — full control, but reimplements proration,
  dunning, tax and SCA, and diverges the moment support clicks anything in the Stripe dashboard.
- **No local projection, query Stripe live** — correct but far too slow for a per-request entitlement
  check, and it makes a Stripe outage a full product outage.
