# 20 — Deployment

> **The operational runbook is [`deploy/README.md`](../deploy/README.md).** It is the
> source of truth for how to deploy, roll back, run commands against production and
> read logs. This page covers *why* the deployment is shaped the way it is; that one
> covers what to type.

Last updated: 2026-09-01, when the app first went live.

## The host

Production is a **shared** DigitalOcean droplet: `164.92.65.201`, Ubuntu 22.04,
2 vCPU / 3.9 GB. It already served ten unrelated websites before MetaCreator
arrived — `petehouston.com`, `laptrinh.io`, `atxtopeatery.com`, `zeroexp.dev`,
`reallook.net` and others — from one nginx, one MySQL, one Redis and five
concurrent PHP versions (5.6 through 8.4, because a legacy site still needs 5.6).

That single fact drives every decision below. This is not a dedicated host, and
treating it like one would take ten live sites down.

## Why not Ansible

`deploy/ansible/` still exists and is **not used**. It was written for a
dedicated host: it installs Caddy (which would fight nginx for ports 80 and
443), rewrites `my.cnf` and `redis.conf` and restarts both, and manages the
shared `www` FPM pool. Any one of those is an outage for the other tenants.

The live deployment is a set of shell scripts under `deploy/scripts/` instead.
The trade — losing Ansible's inventory model and idempotency primitives — is
worth it for something small enough to audit line by line, which is the property
that actually matters when a mistake is someone else's downtime. The scripts are
still idempotent; they just say so in bash.

If MetaCreator ever moves to its own droplet, the Ansible tree is the right
starting point and the scripts can be retired.

## Shape

One vhost serves both halves of the product:

- **`/api/*`, `/up`, `/storage/*`, `/horizon`** → php-fpm → Laravel
- **everything else** → Next.js on `127.0.0.1:3100`

There is no `api.metacreator.dev`. Laravel already namespaces its routes under
`/api/v1` (`bootstrap/app.php`), and the DNS zone only has `@` and `www`
records. Same-origin is also the better security posture: Sanctum's cookie
authentication needs no CORS preflight and no cross-site cookie.

Next.js Server Components reach the API through a **loopback nginx origin**
(`127.0.0.1:8082`) rather than the public hostname. A request to
`https://metacreator.dev` from the droplet itself would travel out to Cloudflare
and back to reach a socket two processes away, and would make page rendering
depend on the edge being up.

A **second** loopback origin (`127.0.0.1:8083`) points at the release currently
being built. `next build` prerenders pages by calling the API, and it must call
the API of the release it is building — on a first deploy `current` does not
exist, and on every later one it still points at the *previous* release.

## Releases

```
/var/www/metacreator.dev/
├── releases/<UTC timestamp>/{api,web}
├── shared/            .env files, storage/, the database password
├── current  -> releases/…      what nginx and systemd serve
└── building -> releases/…      what the build-time origin serves
```

A deploy assembles a complete release, then moves `current` with an atomic
`mv -T`. Until that moment the live site is untouched. Five releases are kept.

`storage/` and `.env` are symlinks into `shared/`, so uploads and secrets
survive both a deploy and a rollback.

**opcache runs with `validate_timestamps=0`** — PHP never stats a file to check
for changes. That is a real speed win on immutable release directories, and it
makes reloading php-fpm after the symlink swap mandatory rather than tidy.

## Rollback

Moving the symlink back, plus a php-fpm reload and a service restart. Seconds.

It does **not** reverse migrations. Follow the expand/contract rule in
[04](04-data-model.md): every migration must leave the previous release able to
run. Every deploy takes a `mysqldump` before migrating, which is the actual
safety net when that rule is broken.

## Isolation

The constraints that let this app share a host safely. `deploy/README.md`
documents each in operational terms; the short version:

| | |
| --- | --- |
| **Dedicated FPM pool** | `open_basedir` confines workers to this app's tree. `/var/www` is deliberately excluded — that is what stops a worker reading another site's `.env`. |
| **Scoped database grant** | `metacreator_dev` has privileges on one database. |
| **Separate Redis databases** | 8 and 9, both empty when claimed, plus a key prefix. Never `flushall` on this box. |
| **Unused ports** | 3100/8082/8083, chosen after checking `ss -tlnp`. |
| **Namespaced globals** | nginx rate-limit zones and SSL session caches share one namespace across vhosts, so both are prefixed. No `default_server`. |
| **Private Composer** | The system `composer` is 1.10 and the default `php` is 5.6. Composer 2 lives in the app's own `bin/` and is always invoked as `php8.4 …/composer`. |
| **Reload, never restart** | Shared processes are reloaded gracefully, and only after a config test passes. |

`deploy/scripts/preflight.sh` re-verifies all of it against the live host,
read-only, and is run automatically before provisioning.

## Memory, and why the build moved off the droplet

3.9 GB, shared. `next build` was the largest thing that ever ran here, and an
out-of-memory event lets the kernel kill *any* process — including another
tenant's. The headroom check fired for real during the first day's work, which
settled the question: the front end is now **cross-compiled in Docker on the
developer's machine** and only the finished bundle is uploaded. The droplet does
not build at all by default.

That is not free. The build container has to match the host exactly, because
Next traces `sharp`'s native binaries into the standalone bundle: `linux/amd64`,
glibc (bookworm — *not* the repo's Alpine dev image), node 22. The deploy
verifies the produced bundle has no darwin/arm64 artefacts before uploading, and
re-checks the droplet's node major version on every run.

Prerendering still needs the API of the release being built, so the deploy opens
an SSH tunnel to the build origin. A build whose fetches all fail still
*succeeds*, emitting pages with no data — an emptied sitemap is the first
symptom, and it happened once before the check existed — so reachability is now
proven from inside a container before the build starts.

`--remote` keeps the old path for when Docker is unavailable. There the
protections remain: a headroom check that aborts, Node's heap capped below free
memory, `nice`/`ionice`, and the 2 GB swap file added by
`deploy/scripts/add-swap.sh`.

## TLS

Cloudflare proxies the domain, so nginx's certificate is only ever presented to
Cloudflare. A **Cloudflare Origin CA** certificate is the right fit: fifteen
years, no renewal job to fail silently. This matches what `atxtopeatery.com`
already does on the same host.

The zone must be set to **Full (strict)**. See
[`deploy/README.md`](../deploy/README.md#1-install-the-tls-certificate).

## Not yet deployed

- **The Go compute service** (`apps/compute`). Nothing in the Laravel code calls
  it — `COMPUTE_URL` appears only in env files. Go is not installed on the host.
- **Email.** `MAIL_MAILER=log`; nothing is delivered. Magic-link sign-in, email
  verification and password reset do not reach an inbox until real credentials
  are set.
- **DO Spaces.** Media is on local disk, served by nginx from `/storage`.
