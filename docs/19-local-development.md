# 19 — Local Development

## Requirements

Docker Desktop (or Engine + Compose v2), `make`, Git, ~4 GB free RAM. Nothing else — PHP, Node, Go,
MySQL and Redis all live in containers, so there is no "works on my machine" version skew.

## First run

```bash
git clone git@github.com:metacreator/metacreator.dev.git
cd metacreator.dev
make setup
```

`make setup` performs, in order: copy `.env.example` files, build images, start the stack, wait for
MySQL to be healthy, `composer install`, `npm ci`, generate the app key, run migrations, seed the
demo dataset, link storage, and print the URL table.

A plain `docker compose up -d` gets you to the same place. The `api` and `web` containers run a
dev entrypoint that installs dependencies into the empty named volumes, creates `.env` from
`.env.example`, generates `APP_KEY`, and — on `api` only — migrates and seeds. Everything it does
is idempotent, so restarts are cheap and a fresh clone needs no manual bootstrap step.

| Service | URL / Port | Credentials |
| --- | --- | --- |
| Web | http://localhost:3000 | — |
| API | http://localhost:8080 | — |
| Horizon | http://localhost:8080/horizon | admin login required |
| Mailpit | http://localhost:8025 | — |
| MySQL | localhost:3317 | `metacreator` / `secret` |
| Redis | localhost:6380 | — |
| MinIO (S3 local) | http://localhost:9001 | `minio` / `minio123` |
| Go compute | http://localhost:8090 | — |

Seeded accounts (all password `password`):

| Email | Role |
| --- | --- |
| `admin@metacreator.dev` | Super Admin |
| `editor@metacreator.dev` | Editor |
| `support@metacreator.dev` | Customer Support |
| `accountant@metacreator.dev` | Accountant |
| `pro@metacreator.dev` | User with an active Pro subscription |
| `free@metacreator.dev` | Free user |

## The stack

| Container | Image | Role |
| --- | --- | --- |
| `web` | node:24-alpine | Next.js dev server with HMR |
| `api` | custom php:8.4-cli-alpine | Laravel via `artisan serve` |
| `worker` | same as `api` | Horizon |
| `scheduler` | same as `api` | `schedule:work` |
| `compute` | golang:1.24-alpine | Go service with `air` live reload |
| `mysql` | mysql:8.4 | Data |
| `redis` | redis:7.4-alpine | Cache, queues, sessions |
| `mailpit` | axllent/mailpit | Email inbox |
| `minio` | minio/minio | S3-compatible storage matching Spaces |

Source is bind-mounted; `vendor/` and `node_modules/` live in named volumes so host filesystem
performance does not destroy the container. Those volumes start empty, which is exactly why the
dev entrypoints install into them on first boot.

Startup order is enforced with health checks rather than `sleep`: `api` waits for MySQL and Redis
to be healthy, and `worker`, `scheduler` and `web` wait for `api` to be healthy. Only `api` carries
`RUN_MIGRATIONS=true`, so the schema is migrated in exactly one place and the workers never race
it.

## Daily commands

```bash
make up / down / restart      # lifecycle
make logs SERVICE=api         # tail one service (omit SERVICE for all)
make sh-api / sh-web          # shell in
make artisan CMD="migrate"    # any artisan command
make migrate / fresh          # migrate, or wipe+migrate+seed
make test / test-api / test-web / test-e2e
make lint / format            # Pint + PHPStan + ESLint + tsc + Prettier
make queue-restart            # reload workers after code changes
make ide-helper               # regenerate IDE metadata
```

## Conventions

- **Branches**: `feat/…`, `fix/…`, `chore/…`, `docs/…` off `main`.
- **Commits**: Conventional Commits. The changelog is generated from them.
- **Pre-commit**: Pint, ESLint and Prettier run on staged files via lint-staged; PHPStan runs
  pre-push.
- **Every PR** updates the relevant doc in `docs/` and adds tests for behaviour it changes.

## Troubleshooting

| Symptom | Fix |
| --- | --- |
| `Connection refused` to MySQL on first boot | The healthcheck is still waiting; `make logs SERVICE=mysql` and retry |
| Frontend can't reach the API | Server components must use `API_INTERNAL_URL` (`http://api:8080`), the browser uses `NEXT_PUBLIC_API_URL` (`http://localhost:8080`) |
| 419 / "CSRF token mismatch" | **Use `http://localhost:3000`, not `http://127.0.0.1:3000`.** They are different cookie origins, so a session set on one is invisible to the other — Sanctum's SPA auth needs both apps under the same registered host. Also check `SANCTUM_STATEFUL_DOMAINS` lists your web host:port |
| CORS error mentioning the `*` wildcard | `config/cors.php` must list explicit origins with `supports_credentials: true`; browsers reject a wildcard origin on any credentialed request |
| Queue jobs not running | `make queue-restart`; Horizon does not hot-reload PHP |
| Slow file watching on macOS | Enable VirtioFS in Docker Desktop |
| Port already in use | Override in `.env`: `WEB_PORT`, `API_PORT`, `MYSQL_PORT`, `REDIS_PORT` |
| Stale Next build | `make sh-web` then `rm -rf .next` |

## Troubleshooting

### `apps/api/.env` points at `127.0.0.1`

The file is bind-mounted, so the container and a host-native `php artisan serve` want different
values in it: the container needs the compose service names (`mysql`, `redis`, `mailpit`,
`minio`), the host needs `127.0.0.1` with the published ports. `php artisan serve` re-reads `.env`
and hands it to the request handler, so container environment variables alone are not enough —
the file itself has to agree, or every HTTP request tries to reach MySQL on `127.0.0.1` and fails
with `SQLSTATE[HY000] [2002] Connection refused`.

The `api` entrypoint resolves this by rewriting only the keys compose owns and leaving the rest of
the file alone. Your previous copy is saved once to `apps/api/.env.host.bak`.

Docker is the supported local path. If you also want to run the API natively, use the published
ports from the table above rather than editing the shared file back.

### Port 3000 is already in use

```
Error response from daemon: ports are not available: exposing port TCP 0.0.0.0:3000
```

Something on the host — usually a stray `next dev` — already holds the port. Stop it, or set a
different port for the container:

```bash
WEB_PORT=3001 docker compose up -d
```

### The site loads but every style is missing

A corrupted Turbopack cache serves the CSS chunk as a 500 while the HTML still renders, so you get
unstyled text on a dark background. The build itself is fine; only the cache is bad. Clear it:

```bash
docker compose exec web rm -rf .next && docker compose restart web
```

Running natively, the equivalent is `rm -rf apps/web/.next` before restarting `npm run dev`.
