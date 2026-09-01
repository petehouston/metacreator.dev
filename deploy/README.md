# Deploying MetaCreator.Dev

Everything needed to run this app on the DigitalOcean droplet at
`164.92.65.201`, and to ship updates to it from your Mac.

> [!IMPORTANT]
> **That droplet is not ours alone.** It serves ten other live websites —
> `petehouston.com`, `laptrinh.io`, `atxtopeatery.com`, `zeroexp.dev` and six
> more — from the same nginx, MySQL, Redis and PHP-FPM. Every script here is
> written so it can only ever touch MetaCreator's own resources. The rules that
> guarantee that are in [Safety model](#safety-model); read it before changing
> anything under `deploy/`.

---

## Contents

- [The 30-second version](#the-30-second-version)
- [How it is put together](#how-it-is-put-together)
- [What lives where on the droplet](#what-lives-where-on-the-droplet)
- [Resource allocations](#resource-allocations)
- [First-time setup](#first-time-setup)
- [Deploying an update](#deploying-an-update)
- [Where the build runs](#where-the-build-runs)
- [Running commands on production](#running-commands-on-production)
- [Rolling back](#rolling-back)
- [Logs and monitoring](#logs-and-monitoring)
- [Database](#database)
- [Configuration and secrets](#configuration-and-secrets)
- [Safety model](#safety-model)
- [Troubleshooting](#troubleshooting)

---

## The 30-second version

You changed some code and want it live:

```bash
make deploy
```

That is the whole thing. It uploads your working tree, builds it on the server,
runs migrations, and switches the site over only if the new release is healthy —
rolling back automatically if it is not.

Check on it afterwards:

```bash
make status
```

---

## How it is put together

```
                        Cloudflare  (proxied — the orange cloud)
                             │  metacreator.dev, www.metacreator.dev
                             │  TLS: Cloudflare Origin CA certificate
                             ▼
   ┌─────────────────────────────────────────────────────────────────┐
   │  nginx :443    server_name metacreator.dev                      │
   │                                                                 │
   │    /api/*  /up  /storage/*  /horizon ──▶ php-fpm  (metacreator  │
   │                                          pool, own socket)      │
   │                                              │                  │
   │    everything else ──────────────────▶ Next.js :3100 (loopback) │
   └──────────────────────────────────────────────┼──────────────────┘
                                                  │
      Next.js Server Components call the API      │
      back through nginx on loopback:             │
        nginx :8082 ──▶ php-fpm ──▶ Laravel ◀─────┘

                          MySQL  metacreator_dev
                          Redis  db 8 (queues, sessions) + db 9 (cache)
                          Horizon + scheduler under systemd
```

**One domain, both halves.** There is no `api.metacreator.dev`. Laravel already
routes everything under `/api/v1`, so a single vhost serves the site and the
API. That is not a compromise — same-origin means Sanctum's cookie
authentication needs no CORS preflight and no cross-site cookie.

**Releases are immutable.** Each deploy builds a complete copy of the app in its
own timestamped directory. The live site is only pointed at it — by moving one
symlink — once the build has succeeded and the health check has passed.

---

## What lives where on the droplet

```
/var/www/metacreator.dev/
├── bin/composer              Composer 2, private to this app (see below)
├── releases/
│   ├── 20260901T171353/      ← current points here
│   │   ├── api/              Laravel  (storage/ and .env are symlinks)
│   │   └── web/.next/standalone/server.js   what systemd runs
│   └── …                     the four previous releases, kept for rollback
├── shared/                   survives every deploy and every rollback
│   ├── .db-password
│   ├── api/.env                        ← the Laravel config
│   ├── api/storage/                    ← uploads, logs, cache
│   └── web/.env.production             ← the Next.js config
├── backups/                  pre-deploy database dumps
├── current  -> releases/20260901T171353
└── building -> releases/20260901T171353   (used during a build)
```

Files owned by the app that live outside that tree:

| Path | What it is |
| --- | --- |
| `/etc/nginx/sites-available/metacreator.dev` | the vhost |
| `/etc/nginx/snippets/metacreator-api.conf` | the Laravel half of it |
| `/etc/php/8.4/fpm/pool.d/metacreator.conf` | a dedicated FPM pool |
| `/etc/systemd/system/metacreator-*.service`, `*.timer` | web, Horizon, scheduler |
| `/etc/ssl/metacreator.dev/origin.{pem,key}` | the Cloudflare origin certificate |
| `/etc/logrotate.d/metacreator` | log rotation |

Every one of those is a **new file**. Nothing that already existed on the
droplet was edited.

---

## Resource allocations

These numbers were chosen by checking what was already in use. They are not
arbitrary, and changing one to something "rounder" is how you take another site
down. They all live in one place: [`deploy/config.sh`](config.sh).

| Resource | MetaCreator | Already taken by |
| --- | --- | --- |
| Next.js port | **3100** | 3000 = atxtopeatery |
| Internal API origin | **8082** | 8080 = atxtopeatery SSR |
| Build-time API origin | **8083** | 8081 = atxtopeatery build |
| PHP-FPM socket | `/run/php/metacreator.sock` | `www.sock`, `atxtopeatery.sock` |
| PHP-FPM pool | `metacreator` | `www`, `atxtopeatery` |
| MySQL database / user | `metacreator_dev` | nine other databases |
| Redis database | **8** (queues, sessions) and **9** (cache) | 0, 3, 4 |
| Redis key prefix | `metacreator-database-` | — |

`make preflight` re-verifies every row of this table against the live host,
read-only. Run it any time you suspect drift.

---

## First-time setup

Already done for this droplet. Written down so it can be repeated on another
host, or audited.

### 1. Install the TLS certificate

The domain is proxied through Cloudflare, so the certificate nginx presents is
only ever seen by Cloudflare — it does not need to be publicly trusted. A
Cloudflare **Origin CA** certificate is the right tool: it lasts fifteen years
and there is no renewal job that can silently fail.

In the Cloudflare dashboard → **metacreator.dev** → **SSL/TLS** → **Origin
Server** → **Create Certificate**:

- let Cloudflare generate the private key and CSR (the default)
- hostnames: `metacreator.dev`, `*.metacreator.dev`
- key format: **PEM**, validity: **15 years**

Cloudflare shows two boxes **once**. Save them, then:

```bash
./deploy/scripts/install-cert.sh ~/Downloads/origin.pem ~/Downloads/origin.key
```

The script verifies the certificate and key match **before** uploading —
a mismatched pair would stop nginx from starting, which on this box is an
outage for eleven sites.

Then set **SSL/TLS → Overview → Full (strict)**.

> [!WARNING]
> The droplet currently holds a **self-signed placeholder** certificate, put
> there so the stack could be provisioned and verified before you had been to
> the dashboard. It works under Cloudflare's *Flexible* and *Full* modes but
> **not** under *Full (strict)*, which would show visitors a 526 error. Install
> the real origin certificate before switching to Full (strict).

### 2. Check the host, then provision it

```bash
make preflight     # read-only; changes nothing
make provision     # idempotent; safe to re-run
```

`provision.sh` creates the directory tree, installs Composer 2 privately, creates
the database and user, writes the environment files, and installs the FPM pool,
the vhost and the systemd units. It reloads php-fpm and nginx — **gracefully**,
and only after a successful config test.

### 3. Deploy, and create an admin

```bash
make deploy
./deploy/scripts/artisan.sh "admin:create you@example.com --name='Your Name'"
```

The password is printed once. Sign in at
[metacreator.dev/login](https://metacreator.dev/login), then open `/c0ns0le`.

---

## Deploying an update

```bash
make deploy
```

What happens, in order:

| # | Step | Notes |
| --- | --- | --- |
| 1 | Checks | Refuses to build if the droplet is short of memory |
| 2 | Upload | `rsync` of `apps/api` (and `apps/web` only when building remotely), minus [the exclusions](rsync-exclude.txt) |
| 3 | Link shared state | `.env` and `storage/` symlinked out of `shared/` |
| 4 | `composer install --no-dev` | plus a fresh package manifest |
| 5 | Point `building` at the new release | so the build reads *its own* API |
| 6 | Build the front end | in Docker **on your Mac** by default — see [Where the build runs](#where-the-build-runs) |
| 7 | Back up the database, migrate, seed reference data | |
| 8 | Build config/route/view/event caches | before the switch, so the first request is warm |
| 9 | Permissions | |
| 10 | **Switch** `current`, reload php-fpm, restart services | atomic |
| 11 | Health check | **rolls back automatically if it fails** |
| 12 | Prune | keeps the newest five releases |

Until step 10, the live site is still serving the previous release. Nothing a
build does can affect it.

**Useful variants**

```bash
make deploy-dry                          # show what would be uploaded, change nothing
./deploy/scripts/deploy.sh --no-migrate  # skip migrations
./deploy/scripts/deploy.sh --remote      # build on the droplet instead of here
```

> [!NOTE]
> Deploys send your **working tree**, not the last commit — uncommitted changes
> go live. The script warns when the tree is dirty. This is deliberate: it makes
> a hotfix a one-liner. Commit first when you want the release to match a commit.

---

## Where the build runs

`next build` is the heaviest thing in a deploy, and this droplet has two cores,
3.9 GB of RAM and ten other live websites on it. So by default the front end is
**cross-compiled in Docker on your Mac** and only the finished bundle — about
83 MB — is rsynced up. The droplet never runs the build at all.

```bash
make deploy                          # build here (default)
./deploy/scripts/deploy.sh --remote  # build on the droplet
```

Set `BUILD_MODE` in [`config.sh`](config.sh) to change the default.

### Why it needs a container rather than plain `npm run build`

The standalone bundle is not pure JavaScript. Next traces **`sharp`** into it for
image optimisation, and sharp ships prebuilt native binaries per platform. Your
Mac is `arm64`/darwin on Node 24; the droplet is `x86_64`/glibc Linux on Node 22.
A plain local build produces `@img/sharp-darwin-arm64`, and the site would 500
the first time `next/image` touched a picture.

So [`Dockerfile.build`](Dockerfile.build) pins **`linux/amd64` + `bookworm`
(glibc) + node 22** to match the droplet. Note that the repo's own
`docker/web/Dockerfile` is Alpine — musl, not glibc — and is for local
development; it is not usable for this.

Two guards keep that honest:

- [`Dockerfile.build.dockerignore`](Dockerfile.build.dockerignore) excludes
  `node_modules` and `.env*`. Without the first, `COPY . .` overwrites the
  container's Linux dependency tree with your Mac's and the build dies on
  `Cannot find module '../lightningcss.linux-x64-gnu.node'`. Without the second,
  Next reads your `.env.local` and silently inlines
  `NEXT_PUBLIC_API_URL=http://localhost:8080` into the production bundle.
- After extraction, the deploy **scans the bundle for any `darwin`/`arm64`
  artefact and refuses to upload one that has them.**

`deploy.sh` also checks the droplet's Node major version still matches
`BUILD_NODE_MAJOR` on every run, so an upgrade there cannot quietly drift.

### The SSH tunnel

`next build` prerenders pages by calling the API — and it must call the API of
*the release being built*, which is on the droplet. So the deploy opens an SSH
tunnel to the build origin for the duration of the build.

The tunnel binds `0.0.0.0`, not loopback, because Docker Desktop runs containers
in a VM: a container cannot reach a port on the Mac's loopback, and `--network
host` is not the Linux behaviour on macOS. The container reaches it via
`host.docker.internal`.

That port is therefore briefly reachable from your local network. The blast
radius is kept at essentially nothing: the port is random, open for roughly the
length of one build, and the build origin serves only `/api`, `/up` and
`/storage` — the same routes already public on the internet. **The Horizon
console is deliberately absent from that port**, because a tunnelled connection
looks like `127.0.0.1` to nginx, which is exactly what Horizon's allow-list
trusts. If you deploy from an untrusted network, use `--remote`.

> [!IMPORTANT]
> **A build whose API fetches all fail still succeeds** — it just emits pages
> with no data. The first symptom is a sitemap that shrinks to a handful of
> static URLs. Because that is silent, the deploy proves the build container can
> reach the API *from inside a container* before starting, and stops if it
> cannot. If you ever change this part, keep that check.

### Rough cost

| | On your Mac | On the droplet |
| --- | --- | --- |
| Build time | ~40 s | ~60 s |
| Droplet RAM used | none | ~1 GB |
| Uploaded | ~83 MB bundle | source, then 400 MB of `node_modules` installed there |
| Needs Docker | yes | no |

## Running commands on production

### artisan

```bash
./deploy/scripts/artisan.sh migrate:status
./deploy/scripts/artisan.sh "admin:create you@example.com"
./deploy/scripts/artisan.sh tinker            # interactive, works properly
make prod-artisan CMD="horizon:status"
```

Runs inside the live release, so it sees production's `.env`, database and
Redis. Interactive commands get a TTY.

**Destructive commands are refused.** `migrate:fresh`, `migrate:reset`,
`migrate:rollback`, `db:wipe` and `db:seed` are blocked, because a typo in a
terminal is all it takes and a symlink rollback cannot undo them. If you truly
mean it, take a backup and force it:

```bash
./deploy/scripts/remote.sh backup-db
ALLOW_DANGEROUS=1 ./deploy/scripts/artisan.sh migrate:rollback
```

### Everything else

```bash
./deploy/scripts/remote.sh          # the full menu
```

| Command | What it does |
| --- | --- |
| `ssh` | login shell, dropped in the release directory |
| `sh '<cmd>'` | run one shell command in `apps/api` |
| `db` | MySQL shell, scoped to this app's database |
| `status` | health of the app *and* of the rest of the droplet |
| `restart-web` / `restart-horizon` | restart one service |
| `restart-php` | **reload** php-fpm (graceful — atxtopeatery unaffected) |
| `reload` | rebuild cached config + restart services, after an `.env` edit |
| `clear-cache` | clear this app's cache keys only |
| `edit-env-api` / `edit-env-web` | edit production config in place |
| `backup-db` / `pull-db` / `restore-db` | database dumps |

---

## Rolling back

```bash
make rollback                        # back one release
make rollback TO=20260901T170955     # to a specific one
make releases                        # list them, marking the live one
```

A rollback moves the symlink, reloads php-fpm and restarts the services —
seconds, not minutes.

> [!WARNING]
> **A rollback does not reverse migrations.** If the release you are leaving
> added a column, that column stays. This is why migrations must be backwards
> compatible (the expand/contract rule in
> [`docs/04-data-model.md`](../docs/04-data-model.md)), and why every deploy
> takes a database dump first. To go back schema and all:
>
> ```bash
> ./deploy/scripts/remote.sh list-backups
> ./deploy/scripts/remote.sh restore-db /var/www/metacreator.dev/backups/pre-deploy-….sql.gz
> ```

---

## Logs and monitoring

```bash
make status                          # the one to run first
./deploy/scripts/logs.sh laravel -f  # follow the application log
./deploy/scripts/logs.sh all         # everything, interleaved
```

| Stream | What it holds |
| --- | --- |
| `laravel` | application log — start here |
| `web` | Next.js server output |
| `horizon` | queue workers |
| `scheduler` | the per-minute tick |
| `php-error` | PHP errors, this pool only |
| `php-slow` | requests over 10s, with backtraces |
| `nginx-access` / `nginx-error` | this vhost only |

`make status` also checks nginx, MySQL, Redis and php-fpm, and spot-checks that
two neighbouring sites still answer — because on a shared box "is my app up" is
only half the question.

### Horizon's dashboard

Deliberately not reachable from the internet. Tunnel to it:

```bash
ssh -L 8088:127.0.0.1:8082 petehouston@164.92.65.201
```

then open <http://127.0.0.1:8088/horizon>.

---

## Database

```
database  metacreator_dev
user      metacreator_dev@localhost
password  ./deploy/scripts/remote.sh db-password
```

The user is granted privileges on `metacreator_dev` **and nothing else**, so
even a full compromise of the app cannot read another site's data.

```bash
make prod-db                    # interactive MySQL shell
make backup                     # dump on the droplet
./deploy/scripts/remote.sh pull-db local-copy.sql.gz    # download a dump
./deploy/scripts/remote.sh list-backups
```

Every deploy dumps the database before migrating, to
`/var/www/metacreator.dev/backups/`, and keeps the newest five.

> [!CAUTION]
> **Never run `redis-cli flushall` on this droplet.** Redis is shared; that
> command would wipe the cache and sessions of every site on the box. Use
> `./deploy/scripts/remote.sh clear-cache`, which removes only
> `metacreator-database-*` keys.

---

## Configuration and secrets

Two files, both on the droplet, both outside any release so they survive deploys
and rollbacks:

| File | Read by |
| --- | --- |
| `shared/api/.env` | Laravel |
| `shared/web/.env.production` | the Next.js systemd unit |

```bash
./deploy/scripts/remote.sh edit-env-api
./deploy/scripts/remote.sh reload        # ← required afterwards
```

The `reload` is not optional: production runs `config:cache`, so Laravel reads
the *compiled* config, not `.env`. Editing `.env` alone changes nothing.

For the web file, note that **`NEXT_PUBLIC_*` values are baked into the browser
bundle at build time** — changing one needs a full `make deploy`, not a restart.
Everything else needs only `restart-web`.

### What is deliberately not configured yet

| | Status | To enable |
| --- | --- | --- |
| **Email** | `MAIL_MAILER=log` — nothing is delivered | Set real SMTP/Mailgun credentials, then `reload`. **Until then magic-link sign-in, email verification and password reset do not reach an inbox.** |
| **Media storage** | Local disk, served from `/storage` | Set `FILESYSTEM_DISK=s3` + the `AWS_*` block for DO Spaces, and copy existing files up |
| **Stripe** | Not implemented upstream ([docs/24](../docs/24-implementation-status.md)) | — |
| **Google OAuth** | Blocked on a Guzzle conflict ([docs/03](../docs/03-tech-stack.md)) | — |
| **Go compute service** | Not deployed — nothing in Laravel calls it | Install Go, add a unit and a port |

---

## Safety model

The rules that keep eleven sites on one droplet from interfering. **If you
change anything under `deploy/`, keep all of these true.**

**1. Add; never edit.** Every file this project owns is one it created. No
shared file — `nginx.conf`, `php.ini`, `my.cnf`, `redis.conf`, the `www` FPM
pool, `ufw` rules — is modified.

**2. Reload, never restart.** nginx and php-fpm are shared processes. A reload
is graceful: in-flight requests for other sites finish normally. A restart drops
them. The scripts only ever reload.

**3. Test before reloading.** `nginx -t` and `php-fpm -t` run first, every time.
If the test fails the reload does not happen — `provision.sh` goes further and
un-links the vhost before bailing out, so a broken config cannot even be
*reachable*. A mistake in this repo fails on your Mac, not on the droplet.

**4. Namespace everything global.** nginx rate-limit zones and SSL session cache
names share one namespace across all vhosts, so both are prefixed
(`metacreator_write`, `SSL_metacreator`). No block is ever `default_server` —
claiming that would silently steal every request with an unrecognised `Host`
header from whichever site holds it today.

**5. Confine the PHP pool.** `open_basedir` limits these workers to
`/var/www/metacreator.dev`. `/var/www` is deliberately *not* on the list — that
omission is what stops a MetaCreator worker reading another site's `.env`.

**6. Scope the database grant.** `metacreator_dev` can see one database.

**7. Leave the system toolchain alone.** The droplet's `composer` is 1.10.26,
far too old for Laravel 13 — and its `php` is **5.6**, because a legacy site
still needs it. Rather than upgrading either and risking nine other deploy
workflows, Composer 2 is installed to `/var/www/metacreator.dev/bin/composer`
and always invoked as `php8.4 …/composer`.

**8. Do not exhaust memory.** The box has 3.9 GB and `next build` is the
hungriest thing that would ever run on it. An OOM would let the kernel kill
*any* process, including another site's. The primary defence is simply not
building there: `BUILD_MODE=local` compiles on your Mac and uploads a finished
bundle. When you do use `--remote`, the build caps Node's heap, runs under
`nice`/`ionice`, and the deploy refuses to start when headroom is short.
`deploy/scripts/add-swap.sh` added 2 GB as a further layer.

**9. Never `flushall`.** See [Database](#database).

---

## Troubleshooting

**`make deploy` says there is not enough memory.**
Something else on the droplet is busy. `./deploy/scripts/remote.sh top` to see
what. Swap is already configured; if this recurs, the box may genuinely be at
capacity.

**`BUILD_MODE=local` needs Docker, which is not running.**
Start Docker Desktop, or deploy with `./deploy/scripts/deploy.sh --remote`.

**"the bundle contains darwin/arm64 artefacts".**
The build container was contaminated by your Mac's files — almost always a
regression in `deploy/Dockerfile.build.dockerignore`. The deploy stopped before
uploading; nothing on the droplet changed.

**"the build container cannot reach the API through the tunnel".**
Docker cannot see the Mac's tunnel port. Restart Docker Desktop, or use
`--remote`. Do not work around it by ignoring the check: the build would appear
to succeed and quietly ship pages with no data.

**The sitemap suddenly lists only a handful of URLs.**
The build could not reach the API and prerendered empty pages. Redeploy; the
reachability check above now catches this before the build starts.

**The build fails on type errors.**
`next build` type-checks the whole project. Reproduce it locally with
`cd apps/web && npm run typecheck` — the server is not doing anything different.

**A deploy rolled itself back.**
The health check failed. The broken release is still on disk for inspection:

```bash
./deploy/scripts/logs.sh laravel
./deploy/scripts/logs.sh web
```

**`Class "cache" does not exist`, or a missing service provider.**
Laravel's compiled package manifest is stale or was built against a different
dependency set. `bootstrap/cache/` is excluded from upload precisely to prevent
this; if you see it, something re-introduced it. `make deploy` rebuilds it.

**A change to `.env` did nothing.**
You skipped `./deploy/scripts/remote.sh reload`. Production reads `config:cache`.

**A code change did nothing.**
`opcache.validate_timestamps=0` means PHP never re-reads a file. Deploys reload
php-fpm to clear it; editing a file on the server by hand will not take effect
until `./deploy/scripts/remote.sh restart-php`. Editing files on the server is
not a supported workflow anyway — the next deploy overwrites them.

**The site shows a Cloudflare 5xx.**
`521` = nginx is down or refusing. `526` = certificate invalid under *Full
(strict)* — you are still on the placeholder certificate; see
[First-time setup](#1-install-the-tls-certificate). `522` = timeout, usually the
Next.js service; check `make status`.

**Did I break another site?**
`make status` ends with exactly that check. To go further:

```bash
./deploy/scripts/remote.sh sh 'sudo nginx -t'
for h in petehouston.com laptrinh.io atxtopeatery.com; do curl -so /dev/null -w "$h %{http_code}\n" https://$h/; done
```
