#!/usr/bin/env bash
#
# Single source of truth for the MetaCreator.Dev production host.
#
# Every script in deploy/scripts sources this file. If a value needs to change,
# change it here — not in a template and not on the server, where the next
# provision run would overwrite it.
#
# ─────────────────────────────────────────────────────────────────────────────
# WHY THESE PARTICULAR NUMBERS
#
# This droplet already serves ten other websites. Every port, socket path,
# Redis database and pool name below was chosen by first checking what was
# already taken (`ss -tlnp`, `redis-cli info keyspace`, the php-fpm pool.d
# directory) and then picking something outside it. Changing one to a "rounder"
# number is how you take another site down. The conflicts avoided are noted
# inline; re-check them with `deploy/scripts/preflight.sh` before provisioning.
# ─────────────────────────────────────────────────────────────────────────────

set -euo pipefail

# ── Host ─────────────────────────────────────────────────────────────────────
SSH_HOST="${SSH_HOST:-164.92.65.201}"
SSH_USER="${SSH_USER:-petehouston}"
SSH_TARGET="${SSH_USER}@${SSH_HOST}"

# The app runs as this user, in this group. `www-data` is the group nginx runs
# as, which is how nginx is allowed to read the release directory.
APP_USER="petehouston"
APP_GROUP="www-data"

# ── Identity ─────────────────────────────────────────────────────────────────
APP_NAME="metacreator"          # pool name, service name prefix, log prefix
DOMAIN="metacreator.dev"
WWW_DOMAIN="www.metacreator.dev"
APP_URL="https://${DOMAIN}"

# ── Paths ────────────────────────────────────────────────────────────────────
# Mirrors the layout already used by /var/www/atxtopeatery.com on this host:
#   releases/<timestamp>/   an immutable, complete checkout+build
#   shared/                 things that must survive a release (.env, storage)
#   current -> releases/…   what nginx and systemd actually serve
#   building -> releases/…  the release being assembled, for build-time SSR
DEPLOY_ROOT="/var/www/${DOMAIN}"
RELEASES_DIR="${DEPLOY_ROOT}/releases"
SHARED_DIR="${DEPLOY_ROOT}/shared"
CURRENT_LINK="${DEPLOY_ROOT}/current"
BUILDING_LINK="${DEPLOY_ROOT}/building"
BIN_DIR="${DEPLOY_ROOT}/bin"


# How many old releases to keep for rollback. Each is a full node_modules-free
# build plus a vendor/ tree — roughly 300-400 MB. Five is ~2 GB of the 59 GB free.
KEEP_RELEASES=5

# ── Ports ────────────────────────────────────────────────────────────────────
# CONFLICT CHECK (2026-09-01): 3000 = atxtopeatery Next.js, 8080 = atxtopeatery
# internal SSR origin, 8081 = atxtopeatery build origin, 3306 = MySQL,
# 6379 = Redis, 33060 = MySQL X protocol. All of the below were free.
WEB_PORT=3100                   # Next.js standalone server, loopback only
NGINX_INTERNAL_PORT=8082        # nginx origin the SSR uses to reach the API
NGINX_BUILDING_PORT=8083        # same, but pointed at the release being built

# ── PHP ──────────────────────────────────────────────────────────────────────
PHP_VERSION="8.4"
PHP_BIN="/usr/bin/php${PHP_VERSION}"
# A dedicated pool, not the shared `www` one. This is what stops a MetaCreator
# PHP worker from being able to read another site's .env (see open_basedir in
# the pool template) and stops a traffic burst here starving the other sites.
FPM_POOL="${APP_NAME}"
FPM_SOCKET="/run/php/${APP_NAME}.sock"
# Composer is ALWAYS invoked through php8.4 explicitly. The droplet's default
# `php` is 5.6 (it still serves a legacy site), so running the phar by its
# shebang picks up PHP 5.6 and Composer aborts. This is not optional.
COMPOSER_CMD="${PHP_BIN:-/usr/bin/php8.4} ${DEPLOY_ROOT}/bin/composer"


# ── Database ─────────────────────────────────────────────────────────────────
# Follows the host's existing convention of dots-to-underscores
# (zeroexp_dev, dailyhabitlab_com, …). Verified not to exist before use.
DB_NAME="metacreator_dev"
DB_USER="metacreator_dev"
DB_HOST="127.0.0.1"
DB_PORT=3306

# ── Redis ────────────────────────────────────────────────────────────────────
# CONFLICT CHECK (2026-09-01): databases 0, 3 and 4 hold live keys belonging to
# other sites. 8 and 9 were empty. The key prefixes below are a second layer of
# isolation, so even a mistaken database number cannot collide with another app.
REDIS_HOST="127.0.0.1"
REDIS_PORT=6379
REDIS_DB=8                      # sessions, queues, the default connection
REDIS_CACHE_DB=9                # application cache
REDIS_PREFIX="metacreator-database-"
HORIZON_PREFIX="metacreator-horizon:"

# ── TLS ──────────────────────────────────────────────────────────────────────
# Cloudflare Origin CA certificate, matching the convention already on this box
# (/etc/ssl/atxtopeatery.com/origin.pem). Cloudflare proxies the domain, so this
# certificate is only ever presented to Cloudflare — it is not publicly trusted
# and does not need to be. Set the zone's SSL mode to "Full (strict)".
SSL_DIR="/etc/ssl/${DOMAIN}"
SSL_CERT="${SSL_DIR}/origin.pem"
SSL_KEY="${SSL_DIR}/origin.key"

# ── systemd unit names ───────────────────────────────────────────────────────
WEB_SERVICE="${APP_NAME}-web.service"
HORIZON_SERVICE="${APP_NAME}-horizon.service"
SCHEDULER_SERVICE="${APP_NAME}-scheduler.service"
SCHEDULER_TIMER="${APP_NAME}-scheduler.timer"

# ── Where the front end is built ─────────────────────────────────────────────
# "local"  cross-compile in Docker on this Mac, then rsync the bundle up.
#          Default. The droplet has 2 vCPU, 3.9 GB and ten other live sites on
#          it; moving the heaviest step off the box is the single best thing we
#          can do for those neighbours. Requires Docker Desktop running.
# "remote" build on the droplet, as before. Needs no Docker; costs the droplet
#          roughly a gigabyte of RAM and both cores for a minute.
#
# Override per run with `deploy.sh --local` / `deploy.sh --remote`.
BUILD_MODE="${BUILD_MODE:-local}"

# The build container must match the droplet, or the native modules Next traces
# into the bundle (sharp, for image optimisation) will not load there.
# Verified against the droplet on 2026-09-01: Ubuntu 22.04, x86_64, node v22.23.2.
# `deploy.sh` re-checks the node major version on every local build.
BUILD_IMAGE_PLATFORM="linux/amd64"
BUILD_NODE_MAJOR="22"

# ── Local paths ──────────────────────────────────────────────────────────────
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TEMPLATES_DIR="${REPO_ROOT}/deploy/templates"

# ── Shared helpers ───────────────────────────────────────────────────────────
c_reset=$'\033[0m'; c_red=$'\033[31m'; c_green=$'\033[32m'
c_yellow=$'\033[33m'; c_blue=$'\033[34m'; c_dim=$'\033[2m'

log()   { printf '%s==>%s %s\n' "$c_blue"  "$c_reset" "$*"; }
ok()    { printf '%s  ✓%s %s\n' "$c_green" "$c_reset" "$*"; }
warn()  { printf '%s  !%s %s\n' "$c_yellow" "$c_reset" "$*" >&2; }
die()   { printf '%s ✗ %s%s %s\n' "$c_red" "$*" "$c_reset" "" >&2; exit 1; }
step()  { printf '\n%s── %s %s%s\n' "$c_dim" "$*" "$(printf '─%.0s' $(seq 1 $((60 - ${#1} > 0 ? 60 - ${#1} : 0)) ))" "$c_reset"; }

# Run a command on the droplet. Quoting note: the argument is passed to a remote
# `bash -c`, so it is the remote shell that expands variables in it.
remote() { ssh -o BatchMode=yes "${SSH_TARGET}" "$@"; }

# Run a command on the droplet as root.
remote_sudo() { ssh -o BatchMode=yes "${SSH_TARGET}" "sudo $*"; }
