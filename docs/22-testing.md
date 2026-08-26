# 22 — Testing Strategy

## The pyramid

| Level | Count target | Tool | Runs in |
| --- | --- | --- | --- |
| Unit — actions, services, value objects, tool runners | ~70% | Pest / Vitest | < 30 s |
| Integration — HTTP endpoints, policies, jobs, webhooks | ~25% | Pest (`RefreshDatabase`) | < 3 min |
| E2E — critical funnels only | ~5% | Playwright | < 6 min |

We test **behaviour through public entry points**, not private methods. An action's `handle()` is
the unit; its internals are free to change.

## Backend

```
apps/api/tests/
├── Unit/            pure logic: EntitlementService, QuotaService, block sanitiser, money
├── Feature/         HTTP + DB: auth flows, tool runs, admin CRUD, webhooks
├── Tools/           one test class per runner, golden-file based
└── Architecture/    enforced structure (Pest arch presets + custom rules)
```

### Non-negotiable test cases

| Area | Must be covered |
| --- | --- |
| Access control | Every tier × actor combination, including grant expiry at the boundary second |
| Quotas | Exhaustion, reset boundary, cache hits not consuming quota |
| Auth | Magic link single-use and expiry, OAuth email-collision, reset token reuse |
| Billing | Every webhook, out-of-order delivery, replayed events, refunds |
| Post lifecycle | Every legal transition, and every illegal one rejected |
| Sanitisation | A corpus of XSS payloads against the HTML block, asserted neutralised |
| SSRF | Private ranges, redirect-to-private, DNS rebinding shapes, metadata endpoints |
| Permissions | Every admin route rejects an actor lacking its permission |

### Architecture tests

Structure is asserted, not merely documented:

```php
arch('controllers stay thin')
    ->expect('App\Http\Controllers')->toHaveMethodsDocumented()
    ->not->toUse(['Illuminate\Support\Facades\DB', 'App\Domain\*\Models\*']);

arch('domains do not reach into each other')
    ->expect('App\Domain\Blog')->not->toUse('App\Domain\Billing');

arch('no debug leftovers')->expect(['dd', 'dump', 'ray', 'var_dump'])->not->toBeUsed();

arch('every admin route is permission-gated')  // custom rule over the route collection
```

### Tool runner tests

Each runner has a golden-file test: fixture input → recorded expected output. External providers are
replayed from recorded cassettes, never called live. A runner change that alters output must either
update the golden file *and* bump `tools.version`, or the test fails — which is exactly the coupling
we want, since the version is part of the cache key.

## Frontend

| Type | Tool | Scope |
| --- | --- | --- |
| Unit | Vitest | Hooks, formatters, schema→form generation, block schema migrations |
| Component | Testing Library | Rendering, interaction, accessibility (`axe`) |
| Visual | Playwright screenshots | Design-system components in light and dark |
| E2E | Playwright | Funnels below |

Queries are by role and accessible name — never by test id for anything a user can see, because a
test that can find a button the way a screen reader does is also testing accessibility.

### E2E funnels

1. Anonymous visitor runs a free tool and gets a result.
2. Visitor hits an `account` tool → registers → is returned to the tool with the run completing.
3. Free user hits a `premium` tool → paywall → Stripe test checkout → access granted.
4. Editor writes a post with five block types, schedules it, and it appears after the clock advances.
5. User opens a support ticket; staff replies; user sees the reply and the notification.
6. Admin toggles a tool's visibility and it disappears from the public catalog.

## CI gates

A PR cannot merge unless all pass:

| Gate | Threshold |
| --- | --- |
| Pint / Prettier / ESLint | Clean |
| PHPStan (Larastan) | Level 8, zero errors, no new baseline entries |
| TypeScript | `strict`, zero errors |
| Pest | All green; line coverage ≥ 80%, and ≥ 95% in `Domain/Tools` and `Domain/Access` |
| Vitest | All green |
| Playwright | All funnels green |
| Lighthouse CI | Budgets in [16](16-seo.md) |
| `composer audit` / `npm audit` | No high or critical |
| Migration round-trip | `migrate` then `migrate:rollback` succeeds on seeded data |

## Test data

Model factories with meaningful states (`Post::factory()->scheduled()`,
`User::factory()->pro()->withGrant('yt-seo-score')`). Seeders are split: `DemoSeeder` for local
development, `ProductionSeeder` for the reference data production genuinely needs (roles,
permissions, plans, tool catalog). Time is controlled through a `Clock` abstraction plus
`travelTo()` — no test ever sleeps.

## Running the suite in Docker

```bash
make test          # backend (parallel) + frontend
make test-api      # Pest, 10 processes
make test-web      # Vitest
```

Two things about this setup are load-bearing and non-obvious. Both were silent failures rather
than loud ones, which is why they are written down here.

### The test environment lives in `tests/bootstrap.php`

The `api`, `worker` and `scheduler` containers export `APP_ENV`, `DB_DATABASE`, `CACHE_STORE`,
`SESSION_DRIVER` and `QUEUE_CONNECTION` as real environment variables. Laravel resolves
configuration through `$_SERVER` first, and **nothing in the usual places can displace that**:

- `.env.testing` cannot — php-dotenv is immutable and will not overwrite an existing variable.
- `phpunit.xml`'s `<env force="true">` cannot — it writes `$_ENV` and `putenv()`, not `$_SERVER`.

So `apps/api/tests/bootstrap.php` sets them directly, before the autoloader hands control to
Laravel. It is the only place that works for every invocation path — `artisan test`,
`vendor/bin/pest`, and PHPUnit run directly.

Without it the suite runs as `local`, against the **development** database, and every
CSRF-protected `POST` fails with a `419` — because the framework only skips that check when the
environment is `testing`. Read-only tests still pass, so the failure looks like a bug in the
endpoints rather than in the harness.

**Add test-only variables to `tests/bootstrap.php`, not to `phpunit.xml`.** Adding them to
`phpunit.xml` appears to work — right up until the variable name collides with one the container
also sets, at which point it silently does nothing.

### Parallel runs need a database-name grant

`--parallel` gives each process its own `metacreator_test_test_<n>` database and expects to
`CREATE` and `DROP` them itself. `docker/mysql/init/01-app-databases.sql` therefore grants the app
user the whole pattern, not just `metacreator_test`:

```sql
GRANT ALL PRIVILEGES ON `metacreator\_test%`.* TO 'metacreator'@'%';
```

The `\_` escapes the underscore so this is a literal prefix rather than a single-character
wildcard. Init scripts only run on a **fresh** data volume, so on an existing one apply the grant
by hand or `make destroy` first.
