# 06 — Authentication & Authorization

## Authentication

Four ways in, one account. Email is the identity and is **immutable** — a user who needs a different
email opens a support ticket, and staff perform an audited transfer.

| Method | Flow |
| --- | --- |
| **Email + password** | Argon2id hashing, `password_timeout` re-auth for sensitive actions |
| **Magic link** | `POST /auth/magic-link` → signed, single-use, 15-minute token emailed → `GET` consumes it and creates a session. Rate-limited per email and per IP; consuming invalidates all outstanding links |
| **Google OAuth** | Socialite. Matches on verified email; links to an existing account only when Google reports the email as verified, otherwise requires password confirmation |
| **Password reset** | Standard signed token, 60-minute expiry, single use, invalidates other sessions on success |

### Session strategy

Sanctum's SPA mode: `HttpOnly`, `Secure`, `SameSite=Lax` session cookie on the apex domain, CSRF
cookie for mutations. No JWT in `localStorage` — XSS should not be able to exfiltrate a session.

Sessions live in Redis DB 1, are listed in the user's security settings (device, IP city, last
active), and can be revoked individually or all at once.

### Account security rules

- 5 failed logins in 15 minutes → throttle by email+IP with exponential backoff.
- New-device login → notification email.
- Password change, email-adjacent change, or subscription cancellation → requires recent
  authentication (< 15 min) and sends a notification.
- Registration and magic-link endpoints are behind a light bot check (honeypot + timing +
  Cloudflare Turnstile if abuse appears).

## Authorization

Two layers that must both pass:

1. **RBAC** — can this *staff* actor perform this administrative action?
2. **Entitlements** — can this *user* access this tool right now?

### RBAC

Built on `spatie/laravel-permission`, wrapped so that call sites never touch the package directly.
Permissions are named `<resource>.<action>` and are declared once in
`app/Domain/Access/PermissionCatalog.php` — the seeder and the tests both read from it, so a
permission cannot exist without being declared.

Actions: `view`, `view_any`, `create`, `update`, `delete`, `restore`, `publish`, `manage`,
`export`, `impersonate` — only where meaningful for the resource.

#### Default roles

| Role | Permission set |
| --- | --- |
| **Super Admin** | Everything; bypasses checks via `Gate::before`. Cannot be deleted; at least one must exist |
| **Admin** | Everything except `roles.manage`, `settings.secrets.update`, `users.impersonate` |
| **Editor** | `posts.*` (incl. `publish`), `post_categories.*`, `tags.*`, `media.*`, `seo.update` |
| **Contributor** | `posts.view_any`, `posts.create`, `posts.update` (own only), `media.create` — **no** `posts.publish`, **no** `posts.delete` |
| **Customer Support** | `tickets.*`, `users.view_any`, `users.view`, `tool_grants.create` (time-boxed only) |
| **Accountant** | `invoices.*`, `subscriptions.view_any`, `subscriptions.update`, `refunds.create`, `reports.export` |
| **Analyst** | `analytics.view`, `tool_analytics.view`, `reports.export` |

Roles are editable in the admin UI; the seeded defaults are a starting point, not a hard-coded
hierarchy. This satisfies the "some editors can view only, some can edit but not delete" requirement
directly: those are just different permission sets, and an admin can create *Editor (read-only)* by
composing them.

#### Ownership scoping

Some permissions are scoped, expressed as `posts.update.own`. Policies resolve the broadest match:

```php
public function update(User $actor, Post $post): bool
{
    return $actor->can('posts.update')
        || ($actor->can('posts.update.own') && $post->author_id === $actor->id);
}
```

#### Enforcement

- **Policies** for every model; `AuthServiceProvider` maps them explicitly.
- **Route middleware** `can:posts.update,post` on every admin route.
- **A test** iterates every registered admin route and fails if any lacks a permission middleware.
- **Field-level**: API Resources omit fields the actor cannot see (e.g. a Support agent sees a
  user's plan but not their invoice PDF URLs).
- **Audit**: every permission-gated write is recorded in `activity_log` with actor, subject, before
  and after.

### Entitlements

For end users, access is computed by `EntitlementService` and returned wholesale from
`GET /account/entitlements` so the UI has one source of truth:

```json
{
  "plan": "pro_monthly",
  "status": "active",
  "renews_at": "2026-09-24T00:00:00Z",
  "limits": { "runs_per_day": 1000, "history_days": null, "export": true },
  "usage":  { "runs_today": 42 },
  "tool_access": { "default_tier": "premium", "grants": ["yt-thumbnail-ab-tester"] }
}
```

Resolution order for a given tool — first match wins:

1. Staff with `tools.bypass_access` → `admin`
2. Active, non-expired grant for that tool → `grant`
3. Active subscription and tool tier is `premium` or lower → `subscription`
4. Authenticated and tool tier is `account` or lower → `account`
5. Tool tier is `free` → `free`
6. Otherwise deny with `tool.account_required` or `tool.subscription_required`

The reason is persisted on every `tool_run`, which makes "how much of our premium usage comes from
comped grants?" a one-line query.
