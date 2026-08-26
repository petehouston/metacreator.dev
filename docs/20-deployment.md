# 20 — Deployment

Target: an **existing DigitalOcean droplet** (Ubuntu 24.04, 4 vCPU / 8 GB recommended). Ansible does
both provisioning and deploys, and both are idempotent — running a playbook twice changes nothing
the second time.

## Host layout

```
/var/www/metacreator/
├── releases/
│   ├── 20260824142300/          each deploy is a timestamped directory
│   └── 20260825091200/
├── current -> releases/20260825091200      atomic symlink swap
└── shared/
    ├── .env                     API secrets (0600, root:deploy)
    ├── .env.web                 frontend runtime env
    ├── storage/                 Laravel storage, symlinked into each release
    └── node_modules_cache/
```

Services are systemd units pointing at `current`, so a rollback is a symlink change plus a reload.

## Ansible layout

```
deploy/ansible/
├── inventories/
│   ├── production/hosts.yml
│   └── staging/hosts.yml
├── group_vars/
│   ├── all/vars.yml
│   └── all/vault.yml            ansible-vault encrypted
├── roles/
│   ├── common/       packages, timezone, unattended-upgrades, swap
│   ├── security/     ufw, fail2ban, ssh hardening, auditd
│   ├── php/          8.4 + extensions, opcache/JIT, php-fpm pools
│   ├── node/         Node 24 via nvm-less apt, pm2-free systemd units
│   ├── mysql/        8.4, tuned my.cnf, backup user
│   ├── redis/        7.4, maxmemory policy, persistence off for cache DB
│   ├── caddy/        TLS, HTTP/3, security headers, rate limits
│   ├── app_api/      release, composer, migrate, horizon, scheduler
│   ├── app_web/      release, npm ci, next build, systemd unit
│   ├── compute/      Go binary build + systemd unit
│   ├── backups/      mysqldump → Spaces, retention, restore verification
│   └── monitoring/   node_exporter, log shipping, Sentry release tagging
├── provision.yml     first-time host setup (run rarely)
├── deploy.yml        the everyday playbook
└── rollback.yml
```

## Deploy sequence

```
1  Preflight        SSH reachable, disk > 20%, git ref exists, migrations reviewed
2  Fetch            clone the ref into releases/<timestamp>
3  Build (API)      composer install --no-dev -o; config/route/view/event cache
4  Build (Web)      npm ci --omit=dev; next build   ← built on the host, not the container
5  Build (Go)       go build -trimpath -ldflags="-s -w"
6  Link shared      .env, storage, media cache
7  Maintenance      php artisan down --render=maintenance --retry=30   (only if migrations exist)
8  Migrate          php artisan migrate --force
9  Swap             ln -sfn releases/<ts> current   (atomic rename)
10 Reload           systemctl reload php-fpm caddy; restart metacreator-web, -horizon, -compute
11 Up               php artisan up
12 Warm             cache warm, ISR prime of the top 50 URLs
13 Verify           /up health check, smoke tests, Sentry release created
14 Prune            keep the last 5 releases
```

Steps 7 and 11 are skipped when a deploy carries no migrations, which makes most deploys
zero-downtime. Migrations that are not backwards compatible are forbidden — see the expand/contract
rule in [04](04-data-model.md).

```bash
make deploy ENV=production REF=v1.4.0
make deploy ENV=staging REF=main
make rollback ENV=production          # to the previous release
make rollback ENV=production TO=20260824142300
```

## Secrets

`ansible-vault` encrypts `group_vars/all/vault.yml`; the vault password comes from the operator's
password manager or CI secret store, never from a file in the repo. Secrets are rendered to
`shared/.env` with mode `0600`. Rotation procedure per secret type is documented in the vault file's
header comments. Nothing secret is ever echoed by a playbook (`no_log: true` on those tasks).

## Caddy

Automatic TLS for `metacreator.dev`, `www.` (redirecting to apex) and `api.metacreator.dev`.
HTTP/3, Brotli/zstd, `Strict-Transport-Security` with preload, the CSP from [21](21-security.md),
`/api/*` rate limits, and long-cache immutable headers on `/_next/static/*`.

## Health and monitoring

| Endpoint | Checks |
| --- | --- |
| `GET /up` (API) | Process alive |
| `GET /health/ready` | MySQL, Redis, Spaces, queue depth, compute service |
| `GET /api/health` (Web) | Build id, API reachability |

Uptime monitoring hits `/health/ready` every minute from two regions. Alerts go to Slack and PagerDuty
for: site down 2 minutes, error rate > 2% over 5 minutes, queue wait over threshold, disk > 85%,
certificate expiring within 14 days, failed backup.

## Backups

Nightly `mysqldump --single-transaction` gzipped to Spaces with 30-day retention, plus Spaces
versioning for media. A weekly job restores the latest dump into a scratch database and runs a row-
count assertion — **an untested backup is not a backup**. Documented RPO 24 h, RTO 1 h.

## CI/CD

GitHub Actions: on PR — lint, PHPStan level 8, Pest, Vitest, `next build`, Playwright on the critical
funnels, Lighthouse budget. On merge to `main` — deploy to staging automatically. On a semver tag —
deploy to production after a manual approval gate.

## Scaling path

When one droplet is not enough, in order: (1) move MySQL to a managed database, (2) move Redis to a
managed instance, (3) move workers to a second droplet, (4) put the web app behind a load balancer
with two app droplets and sticky-free sessions (already in Redis). No application code changes are
required for any of these steps — that is the point of keeping all state external.
