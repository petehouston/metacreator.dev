# 21 — Security & Privacy

## Threat model

| Asset | Threat | Mitigation |
| --- | --- | --- |
| User sessions | XSS, session theft | HttpOnly/Secure/SameSite cookies, strict CSP, no token in JS-readable storage |
| Admin panel | Privilege escalation | Per-route permission middleware, policies, audit log, re-auth for sensitive actions |
| Custom HTML blocks & tracking scripts | Stored XSS | Sanitisation on save *and* render, separate permission, CSP nonces, audit diff |
| Tool inputs (URLs) | SSRF | Strict URL allow-listing, DNS resolution check, private-range rejection |
| Media uploads | Malicious files | Byte sniffing, allow-list, EXIF strip, SVG sanitisation, isolated origin |
| Payments | Fraud, card exposure | Stripe Checkout only; no PAN ever reaches our servers |
| Personal data | Breach, over-collection | Minimisation, encryption at rest, hashed visitor IDs, retention limits |
| API | Abuse, scraping, enumeration | Rate limits, quotas, ULIDs instead of sequential ids, uniform 404s |

## Application hardening

**Input** — every request passes a Form Request; tool inputs additionally validate against their
JSON Schema. Nothing is trusted because it "came from our own frontend".

**Output** — React escapes by default; `dangerouslySetInnerHTML` is used in exactly one place (the
sanitised HTML block renderer) and is guarded by an ESLint rule that forbids it elsewhere.

**SQL** — Eloquent/query builder with bindings only. Raw SQL requires a reviewed exception and
parameter binding; a CI grep fails on interpolated `DB::raw`.

**CSRF** — Sanctum's stateful guard; all mutating routes require the token.

**CSP** (Caddy, per-response nonce):

```
default-src 'self';
script-src  'self' 'nonce-{{nonce}}' https://www.googletagmanager.com https://connect.facebook.net https://js.stripe.com;
style-src   'self' 'unsafe-inline';
img-src     'self' data: blob: https://cdn.metacreator.dev https://*.digitaloceanspaces.com;
font-src    'self';
connect-src 'self' https://api.metacreator.dev https://*.sentry.io;
frame-src   https://js.stripe.com https://www.youtube-nocookie.com https://player.vimeo.com;
frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none';
upgrade-insecure-requests
```

Plus `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`,
`Permissions-Policy` denying camera/mic/geolocation, and HSTS with preload.

### HTML sanitisation

The `html` block and any admin-entered rich HTML pass through HTMLPurifier with an explicit
allow-list (no `<script>`, no `on*` handlers, no `javascript:` URLs, no `<iframe>` except an
allow-list of embed hosts). Sanitisation happens **on save** (so the stored value is clean) **and on
render** (so a value that predates a policy change is still safe). If sanitisation modifies the
input, the editor shows a diff and requires confirmation rather than silently changing someone's
work.

The tracking-script fields are a deliberate exception — they are raw by necessity. They are
therefore gated behind their own permission, audit-logged with a diff, never injected into
authenticated areas, and reviewed as part of any security incident.

### SSRF protection

Tools that fetch a user-supplied URL use a hardened client: scheme restricted to http/https, DNS
resolved and checked against private/loopback/link-local/metadata ranges *before* connecting and
again on redirect, a maximum of 3 redirects, a 10 s timeout, a 10 MB response cap, and no
credentials forwarded. The check is centralised in `SafeHttpClient`; a direct `Http::get()` on
user input fails code review.

## Data protection

| Category | Handling |
| --- | --- |
| Passwords | Argon2id, never logged, never in exports |
| Email | Stored plain (needed for delivery), immutable, encrypted backups |
| IP addresses | **Not stored.** `tool_runs.visitor_hash` is HMAC(ip+ua, daily-rotating salt) |
| Tool inputs | Hashed by default; retained only with an explicit per-tool declaration and consent |
| Tool artifacts | Private Spaces bucket, signed URLs with 1-hour expiry |
| Payment data | Never touches our infrastructure |
| Secrets | `ansible-vault` at rest, `shared/.env` mode 0600, encrypted `settings` rows for API keys |

Retention: raw tool runs 90 days · activity log 2 years · invoices 7 years (legal) · deleted accounts
30 days then hard purge · session records 90 days · email events 90 days.

## GDPR / CCPA

- **Access** — self-serve export from `/dashboard/privacy` producing a JSON+CSV bundle of profile,
  runs, tickets, invoices and newsletter status.
- **Erasure** — self-serve deletion request → 30-day grace with a cancel link → hard purge, except
  financial records, which are retained with personal fields redacted.
- **Rectification** — profile fields are editable; email changes go through audited support.
- **Portability** — the export is machine-readable.
- **Consent** — granular cookie banner (necessary / analytics / marketing), no non-essential tag
  loads before consent, consent state versioned and stored.
- **Processors** — Stripe, Mailgun, DigitalOcean, Sentry, and the configured newsletter provider are
  listed in the privacy policy with their roles and regions.

## Operational security

SSH keys only, no root login, non-standard port, fail2ban. UFW allows 22/80/443 only; MySQL, Redis
and the compute service bind to localhost or the private interface. Unattended security upgrades.
Weekly `composer audit` and `npm audit` in CI with a build failure on high/critical. Dependabot for
patches. Sentry scrubs `password`, `token`, `secret`, `authorization` and `card` from every payload.

## Incident response

1. **Detect** — alert fires or a report arrives at `security@metacreator.dev`.
2. **Assess** — severity within 30 minutes; SEV1 (data exposure, outage) pages immediately.
3. **Contain** — rotate credentials, revoke sessions, feature-flag off the affected surface.
4. **Eradicate & recover** — patch, deploy, verify, restore from backup if needed.
5. **Notify** — affected users and, for personal-data breaches, the supervisory authority within 72
   hours.
6. **Post-mortem** — blameless, written within 5 working days, with tracked action items.

A public `SECURITY.md` describes responsible disclosure: report to `security@`, 90-day disclosure
window, no legal action for good-faith research.
