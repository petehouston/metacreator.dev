#!/usr/bin/env bash
#
# Deploy a new release of MetaCreator.Dev.
#
#   deploy/scripts/deploy.sh              # deploy the current working tree
#   deploy/scripts/deploy.sh --no-migrate # skip migrations
#   deploy/scripts/deploy.sh --dry-run    # show what would be sent, change nothing
#   deploy/scripts/deploy.sh --remote     # build on the droplet instead of here
#
# ── How a release works ──────────────────────────────────────────────────────
# Each deploy builds a complete, self-contained release in its own timestamped
# directory and only then moves the `current` symlink. Until that final move,
# the live site is still serving the previous release and nothing a build does
# can affect it. Switching is a symlink rename — atomic, so no request ever sees
# a half-updated tree.
#
# If the health check after the switch fails, the symlink is moved back
# automatically and the deploy exits non-zero.
#
# ── Where the front end is built ─────────────────────────────────────────────
# By default (BUILD_MODE=local) `next build` runs in a Docker container on THIS
# Mac and only the finished bundle is rsynced up. The droplet has two cores,
# 3.9 GB and ten other live websites; the build is by far the heaviest thing
# that would ever run there, so keeping it off the box is the single biggest
# favour we can do those neighbours.
#
# The container is linux/amd64 + glibc + node 22 to match the droplet exactly.
# That is not fussiness: Next traces `sharp` into the standalone bundle for
# image optimisation, and sharp ships per-platform native binaries. A plain
# `npm run build` on this Mac yields @img/sharp-darwin-arm64, which the droplet
# cannot load. The bundle is verified for stray darwin/arm64 artefacts before
# anything is uploaded.
#
# Prerendering still needs the API of the release being built, which lives on
# the droplet — so an SSH tunnel is opened to the build origin for the duration.
#
# `--remote` restores the old behaviour of building on the droplet. It needs no
# Docker, and there the protections are: Node's heap capped below free memory,
# `nice`/`ionice` so the build yields to sites serving traffic, and a headroom
# check that aborts rather than risking the OOM killer picking another tenant.

set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/../config.sh"

NO_MIGRATE=0
DRY_RUN=0
for arg in "$@"; do
    case "${arg}" in
        --no-migrate) NO_MIGRATE=1 ;;
        --dry-run)    DRY_RUN=1 ;;
        --local)      BUILD_MODE=local ;;
        --remote)     BUILD_MODE=remote ;;
        -h|--help)    sed -n '2,20p' "${BASH_SOURCE[0]}"; exit 0 ;;
        *)            die "unknown option: ${arg}" ;;
    esac
done

RELEASE="$(date -u +%Y%m%dT%H%M%S)"
RELEASE_DIR="${RELEASES_DIR}/${RELEASE}"

# Cap Node's heap below what is actually free, leaving room for the OS and the
# other sites. 1024 MB is enough for this app's build and safe on this droplet.
NODE_HEAP_MB=1024

log "Deploying ${DOMAIN} — release ${RELEASE}"
printf '%s     from %s%s\n' "$c_dim" "${REPO_ROOT}" "$c_reset"
printf '%s     build %s%s\n' "$c_dim" \
    "$([[ "${BUILD_MODE}" == "local" ]] && echo 'locally in Docker, then rsync the bundle' || echo 'on the droplet')" "$c_reset"
printf '%s     git  %s (%s)%s\n' "$c_dim" \
    "$(git -C "${REPO_ROOT}" rev-parse --short HEAD 2>/dev/null || echo 'not a repo')" \
    "$(git -C "${REPO_ROOT}" symbolic-ref --short HEAD 2>/dev/null || echo '-')" "$c_reset"
if ! git -C "${REPO_ROOT}" diff --quiet 2>/dev/null || ! git -C "${REPO_ROOT}" diff --cached --quiet 2>/dev/null; then
    warn "working tree has uncommitted changes — they WILL be deployed"
fi

# ─────────────────────────────────────────────────────────────────────────────
step "1. Checks"
remote "test -d ${DEPLOY_ROOT}" || die "${DEPLOY_ROOT} does not exist. Run deploy/scripts/provision.sh first."
remote "test -f ${SHARED_DIR}/api/.env" || die "no shared .env. Run deploy/scripts/provision.sh first."

# Only relevant when the droplet does the building. A local build leaves its
# memory alone entirely, which is rather the point.
if [[ "${BUILD_MODE}" == "remote" ]]; then
# Refuse to start a build on a box that is already short of memory — that is
# exactly the condition in which the OOM killer picks a victim at random.
# Headroom is available RAM plus free swap. Swap counts because it is what turns
# "the kernel OOM-kills one of the other ten sites" into "the build gets slow",
# which is the whole reason deploy/scripts/add-swap.sh exists.
read -r avail_mb swap_free_mb <<<"$(remote "free -m | awk '/^Mem:/ {m=\$7} /^Swap:/ {s=\$4} END {print m, s+0}'")"
headroom_mb=$((avail_mb + swap_free_mb))
printf '%s     %s MB RAM available + %s MB swap free = %s MB headroom%s\n' \
    "$c_dim" "${avail_mb}" "${swap_free_mb}" "${headroom_mb}" "$c_reset"

# Refuse if even RAM+swap is short, and warn separately when RAM alone is tight —
# a build that leans on swap is slow and hits the disk the other sites read from.
if [[ "${headroom_mb}" -lt $((NODE_HEAP_MB + 400)) ]]; then
    die "only ${headroom_mb} MB of headroom — too little to build safely alongside the other sites.
     Wait for load to drop, or add swap with deploy/scripts/add-swap.sh, then retry."
fi
if [[ "${avail_mb}" -lt $((NODE_HEAP_MB + 200)) ]]; then
    warn "only ${avail_mb} MB of real RAM — the build will lean on swap and be slower than usual"
fi
ok "enough headroom to build on the droplet"
fi

if [[ "${BUILD_MODE}" == "local" ]]; then
    command -v docker >/dev/null 2>&1 \
        || die "BUILD_MODE=local needs Docker, which is not installed.
     Start Docker Desktop, or build on the droplet with: deploy/scripts/deploy.sh --remote"
    docker info >/dev/null 2>&1 \
        || die "Docker is installed but not running. Start Docker Desktop, or use --remote."
    ok "Docker is running — the front end will be built here, not on the droplet"

    # The container's node major must match the droplet's, because the native
    # modules it bakes into the bundle are compiled against that ABI.
    droplet_node_major="$(remote "node -v" | sed 's/^v//; s/\..*//')"
    if [[ "${droplet_node_major}" != "${BUILD_NODE_MAJOR}" ]]; then
        die "the droplet runs node ${droplet_node_major} but deploy/config.sh builds against node ${BUILD_NODE_MAJOR}.
     Update BUILD_NODE_MAJOR and the FROM line in deploy/Dockerfile.build to match, then retry."
    fi
    ok "build image node ${BUILD_NODE_MAJOR} matches the droplet"
fi

if [[ "${DRY_RUN}" -eq 1 ]]; then
    step "Dry run — files that would be sent"
    rsync -az --dry-run --itemize-changes --delete \
        --exclude-from="${REPO_ROOT}/deploy/rsync-exclude.txt" \
        "${REPO_ROOT}/apps/api/" "${SSH_TARGET}:/tmp/.mc-dryrun-api/" | head -40
    ok "dry run complete — nothing was changed"
    exit 0
fi

# ─────────────────────────────────────────────────────────────────────────────
step "2. Upload the code"
remote "install -d -m 2775 ${RELEASE_DIR}/api ${RELEASE_DIR}/web"

# `--delete` inside a brand-new empty release directory is harmless and keeps
# the transfer honest. vendor/, node_modules/, .next/ and every .env are
# excluded: dependencies are installed on the server against the lockfile, and
# environment files are shared state that lives outside any release.
rsync -az --delete --stats \
    --exclude-from="${REPO_ROOT}/deploy/rsync-exclude.txt" \
    "${REPO_ROOT}/apps/api/" "${SSH_TARGET}:${RELEASE_DIR}/api/" | grep -E 'Number of files transferred|Total transferred' 
if [[ "${BUILD_MODE}" == "remote" ]]; then
    rsync -az --delete --stats \
        --exclude-from="${REPO_ROOT}/deploy/rsync-exclude.txt" \
        "${REPO_ROOT}/apps/web/" "${SSH_TARGET}:${RELEASE_DIR}/web/" | grep -E 'Number of files transferred|Total transferred'
else
    # Nothing to send yet: a local build uploads the finished bundle in step 6,
    # so the release never carries the front end's source or its node_modules.
    printf '%s     web sources not uploaded — building locally%s\n' "$c_dim" "$c_reset"
fi
ok "code uploaded to ${RELEASE_DIR}"

# ─────────────────────────────────────────────────────────────────────────────
step "3. Link shared state into the release"
# storage/ and .env are replaced by symlinks into shared/, so uploads and
# secrets survive a deploy and are not reverted by a rollback.
remote "set -e
    rm -rf ${RELEASE_DIR}/api/storage
    ln -sfn ${SHARED_DIR}/api/storage ${RELEASE_DIR}/api/storage
    ln -sfn ${SHARED_DIR}/api/.env    ${RELEASE_DIR}/api/.env"
ok "storage/ and .env linked to shared"

# ─────────────────────────────────────────────────────────────────────────────
step "4. PHP dependencies"
# --no-dev omits Pest, PHPStan and friends. --optimize-autoloader builds a
# classmap, which matters because opcache.validate_timestamps=0 means autoload
# misses are expensive. --no-scripts is deliberate: package:discover needs a
# working .env and is run explicitly in step 7 instead.
remote "set -eo pipefail; cd ${RELEASE_DIR}/api && nice -n 10 ${COMPOSER_CMD} install \
    --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts --prefer-dist 2>&1 | tail -5"
ok "composer install complete"

# bootstrap/cache is excluded from the upload (see deploy/rsync-exclude.txt), so
# it must be recreated and repopulated here — before anything tries to serve a
# request out of this release, which the build origin below does.
remote "set -e
    install -d -m 2775 ${RELEASE_DIR}/api/bootstrap/cache
    cd ${RELEASE_DIR}/api && ${PHP_BIN} artisan package:discover --ansi" 2>&1 | tail -3
ok "package manifest regenerated against the --no-dev vendor tree"

# ─────────────────────────────────────────────────────────────────────────────
step "5. Point the build origin at this release"
# `next build` prerenders pages by calling the API. It must call the API of the
# release being built, not the live one — see the comment on the build origin in
# deploy/templates/nginx-site.conf.
remote "ln -sfn ${RELEASE_DIR} ${BUILDING_LINK}"
# The build origin's document root is a symlink target that just changed, and
# nginx caches resolved roots per worker. A reload re-resolves it.
# `sudo` binds to the first command only, so the whole chain runs under one
# root shell — otherwise `systemctl reload` would run unprivileged and fail.
remote_sudo "bash -c 'nginx -t >/dev/null 2>&1 && systemctl reload nginx'"
ok "building -> ${RELEASE} (nginx reloaded)"

# Verify the build origin actually answers before spending minutes on a build
# that would otherwise prerender a wall of fetch failures.
if remote "curl -sf -o /dev/null -w '%{http_code}' http://127.0.0.1:${NGINX_BUILDING_PORT}/up" | grep -q '200'; then
    ok "build origin is answering on 127.0.0.1:${NGINX_BUILDING_PORT}"
else
    warn "build origin did not return 200 on /up — prerendering may fall back to empty data"
fi

# ─────────────────────────────────────────────────────────────────────────────
step "6. Build the front end"

if [[ "${BUILD_MODE}" == "local" ]]; then
    # ── Tunnel to the build origin ───────────────────────────────────────────
    # Prerendering calls the API of the release being built. That release lives
    # on the droplet, reachable only on its loopback (127.0.0.1:8083), so the
    # build reaches it through an SSH tunnel.
    #
    # The tunnel is bound to 0.0.0.0, not 127.0.0.1, and that is deliberate:
    # Docker Desktop runs containers inside a VM, so a container cannot reach a
    # port bound to the Mac's loopback. `--network host` does not help either —
    # on macOS it is not the Linux behaviour and resolves nothing. The container
    # reaches the Mac through `host.docker.internal`, which requires the port to
    # be bound on a non-loopback interface.
    #
    # That means the port is briefly reachable from your local network. Two
    # things keep the blast radius at essentially zero:
    #   * the port is random and open only for the ~40 seconds of a build;
    #   * the build origin serves /api, /up and /storage and 404s everything
    #     else — the same routes already public at https://metacreator.dev. The
    #     Horizon console is specifically NOT on this port, precisely because of
    #     this tunnel (see deploy/templates/nginx-site.conf).
    # If you build on an untrusted network and would rather not, use --remote.
    TUNNEL_PORT="$(python3 -c 'import socket; s=socket.socket(); s.bind(("",0)); print(s.getsockname()[1]); s.close()')"

    ssh -o BatchMode=yes -o ExitOnForwardFailure=yes -N \
        -L "0.0.0.0:${TUNNEL_PORT}:127.0.0.1:${NGINX_BUILDING_PORT}" "${SSH_TARGET}" &
    TUNNEL_PID=$!
    # Always tear the tunnel down, including when the build fails or you Ctrl-C.
    trap 'kill "${TUNNEL_PID}" 2>/dev/null || true' EXIT INT TERM

    for _ in $(seq 1 15); do
        curl -sf -o /dev/null "http://127.0.0.1:${TUNNEL_PORT}/up" && break
        sleep 1
    done
    curl -sf -o /dev/null "http://127.0.0.1:${TUNNEL_PORT}/up" \
        || die "SSH tunnel to the build origin never came up. Retry, or use --remote."
    ok "tunnel open: :${TUNNEL_PORT} -> droplet 127.0.0.1:${NGINX_BUILDING_PORT} (build origin, API only)"

    # Verify from inside a container, not just from the Mac. The two are not the
    # same test, and a build whose fetches all fail still SUCCEEDS — it just
    # emits pages with no data (an emptied sitemap is the usual first symptom),
    # which is far worse than a loud failure.
    # Uses node's built-in fetch rather than curl — the slim image ships no curl,
    # and this is the same image the build itself runs in, so the check exercises
    # the real network path.
    if ! docker run --rm --platform "${BUILD_IMAGE_PLATFORM}" \
            --add-host host.docker.internal:host-gateway \
            "node:${BUILD_NODE_MAJOR}-bookworm-slim" \
            node -e "fetch('http://host.docker.internal:${TUNNEL_PORT}/up').then(r => process.exit(r.ok ? 0 : 1)).catch(() => process.exit(1))" 2>/dev/null; then
        die "the build container cannot reach the API through the tunnel.
     A build would silently produce pages with no data, so it is stopped here.
     Check Docker Desktop is running normally, or build on the droplet with --remote."
    fi
    ok "build container can reach the API through the tunnel"

    # ── Build ────────────────────────────────────────────────────────────────
    # NEXT_PUBLIC_* are inlined into the browser bundle at build time, so they
    # are read from the server's own environment file rather than guessed here —
    # one source of truth, and no chance of building a bundle that points
    # somewhere the running app does not.
    web_env="$(remote "cat ${SHARED_DIR}/web/.env.production")"
    env_value() { grep -m1 "^$1=" <<<"${web_env}" | cut -d= -f2- ; }

    # host.docker.internal is how a container reaches a port on the Mac, which
    # is where the tunnel's near end is. host-gateway makes the name resolve
    # during a build as well as at run time.
    docker build \
        --platform "${BUILD_IMAGE_PLATFORM}" \
        --add-host host.docker.internal:host-gateway \
        -f "${REPO_ROOT}/deploy/Dockerfile.build" \
        --target build \
        -t "${APP_NAME}-web-build:${RELEASE}" \
        --build-arg NEXT_PUBLIC_APP_URL="$(env_value NEXT_PUBLIC_APP_URL)" \
        --build-arg NEXT_PUBLIC_API_URL="$(env_value NEXT_PUBLIC_API_URL)" \
        --build-arg REVALIDATE_SECRET="$(env_value REVALIDATE_SECRET)" \
        --build-arg API_INTERNAL_URL="http://host.docker.internal:${TUNNEL_PORT}" \
        "${REPO_ROOT}/apps/web" 2>&1 | grep -vE '^#[0-9]+ (sha256|extracting|transferring)' | tail -25
    ok "cross-build complete (${BUILD_IMAGE_PLATFORM}, node ${BUILD_NODE_MAJOR})"

    kill "${TUNNEL_PID}" 2>/dev/null || true
    trap - EXIT INT TERM

    # ── Extract ──────────────────────────────────────────────────────────────
    STAGE_DIR="$(mktemp -d)"
    trap 'rm -rf "${STAGE_DIR}"' EXIT
    cid="$(docker create --platform "${BUILD_IMAGE_PLATFORM}" "${APP_NAME}-web-build:${RELEASE}")"
    docker cp "${cid}:/app/.next/standalone" "${STAGE_DIR}/standalone" >/dev/null
    docker rm "${cid}" >/dev/null
    ok "bundle extracted ($(du -sh "${STAGE_DIR}/standalone" | cut -f1))"

    # ── Verify before shipping ───────────────────────────────────────────────
    # The whole hazard of building on one platform for another is a native
    # module compiled for the wrong one. This is the gate that catches it:
    # anything darwin- or arm64-shaped in the bundle means the container was
    # contaminated by the host (usually a .dockerignore regression), and the
    # deploy stops here rather than shipping a front end that crashes the first
    # time next/image touches a picture.
    if find "${STAGE_DIR}/standalone" \( -iname '*darwin*' -o -iname '*arm64*' \) | grep -q .; then
        find "${STAGE_DIR}/standalone" \( -iname '*darwin*' -o -iname '*arm64*' \) | head -5
        die "the bundle contains darwin/arm64 artefacts — it would not run on the droplet.
     Check deploy/Dockerfile.build.dockerignore still excludes node_modules."
    fi
    natives="$(find "${STAGE_DIR}/standalone" -name '*.node' | wc -l | tr -d ' ')"
    ok "no darwin/arm64 artefacts; ${natives} native module(s), all linux-x64"
    test -f "${STAGE_DIR}/standalone/server.js" || die "bundle has no server.js"
    test -d "${STAGE_DIR}/standalone/.next/static" || die "bundle has no static assets"
    test -d "${STAGE_DIR}/standalone/public" || die "bundle has no public/ directory"
    ok "server.js, static assets and public/ all present"

    # ── Upload ───────────────────────────────────────────────────────────────
    remote "install -d -m 2775 ${RELEASE_DIR}/web/.next"
    rsync -az --delete "${STAGE_DIR}/standalone/" "${SSH_TARGET}:${RELEASE_DIR}/web/.next/standalone/"
    ok "bundle uploaded to ${RELEASE_DIR}/web/.next/standalone"

    rm -rf "${STAGE_DIR}"; trap - EXIT
    # Keep only the last few build images; they are ~1.5 GB each.
    docker image rm "${APP_NAME}-web-build:${RELEASE}" >/dev/null 2>&1 || true

else
    # ── Remote build (--remote) ──────────────────────────────────────────────
    # npm ci, not npm install: it installs exactly the lockfile and fails if
    # package.json and the lock have drifted, which is what you want on a server.
    remote "set -eo pipefail; cd ${RELEASE_DIR}/web && nice -n 10 ionice -c2 -n7 npm ci --no-audit --no-fund 2>&1 | tail -3"
    ok "npm ci complete"

    # `set -a` exports everything sourced after it, so the shared .env.production
    # is loaded as real environment variables — the file is KEY=value with no
    # quoting (systemd EnvironmentFile format), which is valid shell to source.
    #
    # API_INTERNAL_URL is overridden AFTER sourcing so prerendering reads the API
    # of the release being built rather than the live one.
    #
    # `set -o pipefail` on the remote side is essential: without it ssh returns
    # the exit status of `tail`, and a failed build is reported as a success.
    remote "set -eo pipefail
        cd ${RELEASE_DIR}/web
        set -a; . ${SHARED_DIR}/web/.env.production; set +a
        export NODE_ENV=production
        export NEXT_TELEMETRY_DISABLED=1
        export NODE_OPTIONS=--max-old-space-size=${NODE_HEAP_MB}
        export API_INTERNAL_URL=http://127.0.0.1:${NGINX_BUILDING_PORT}
        nice -n 10 ionice -c2 -n7 npm run build 2>&1 | tail -25"
    ok "next build complete"

    # `output: standalone` emits a minimal server bundle, but Next deliberately
    # does NOT copy static assets or public/ into it — that is left to the
    # deployment, so they can be served from a CDN instead. We serve them from
    # the same process, so they are copied in here.
    remote "set -e
        test -f ${RELEASE_DIR}/web/.next/standalone/server.js \
            || { echo 'standalone build missing — is output:\"standalone\" set in next.config.ts?'; exit 1; }
        cp -r ${RELEASE_DIR}/web/.next/static ${RELEASE_DIR}/web/.next/standalone/.next/static
        cp -r ${RELEASE_DIR}/web/public       ${RELEASE_DIR}/web/.next/standalone/public"
    ok "static assets and public/ copied into the standalone bundle"

    # node_modules is 400+ MB and the standalone bundle already contains
    # everything the server imports. Dropping it keeps five retained releases
    # affordable.
    remote "rm -rf ${RELEASE_DIR}/web/node_modules"
    ok "build-only node_modules removed"
fi

# ─────────────────────────────────────────────────────────────────────────────
step "7. Database"
if [[ "${NO_MIGRATE}" -eq 1 ]]; then
    warn "--no-migrate: skipping migrations"
else
    # Back up first. A migration is the one step in a deploy that a symlink
    # rollback cannot undo, so this dump is the actual safety net.
    backup="${DEPLOY_ROOT}/backups/pre-deploy-${RELEASE}.sql.gz"
    db_pass="$(remote "cat ${SHARED_DIR}/.db-password")"
    remote "mysqldump -u${DB_USER} -p'${db_pass}' --single-transaction --quick \
        --routines --triggers ${DB_NAME} 2>/dev/null | gzip > ${backup}"
    ok "database backed up to ${backup} ($(remote "du -h ${backup} | cut -f1"))"

    remote "set -eo pipefail; cd ${RELEASE_DIR}/api && ${PHP_BIN} artisan migrate --force --no-interaction 2>&1 | tail -20"
    ok "migrations applied"

    # ProductionSeeder is reference data, not demo data: roles and the ~120
    # permissions, default settings, plans, tool categories and the tool catalog
    # itself. Every seeder it calls is an upsert keyed on a stable identifier, so
    # running it on each deploy is how a newly added tool or permission reaches
    # production. DatabaseSeeder is NOT used here — it pulls in the demo seeders
    # outside production, which on a live site would be a data-integrity incident.
    remote "set -eo pipefail; cd ${RELEASE_DIR}/api && \
        ${PHP_BIN} artisan db:seed --class=Database\\\\Seeders\\\\ProductionSeeder --force --no-interaction 2>&1 | tail -8"
    ok "reference data seeded (roles, permissions, settings, plans, tool catalog)"
fi

# ─────────────────────────────────────────────────────────────────────────────
step "8. Laravel caches"
# Built against the new release BEFORE the switch, so the first request after
# the switch is already warm rather than compiling config on the hot path.
remote "cd ${RELEASE_DIR}/api && set -e
    ${PHP_BIN} artisan config:cache
    ${PHP_BIN} artisan route:cache
    ${PHP_BIN} artisan view:cache
    ${PHP_BIN} artisan event:cache" 2>&1 | tail -6
ok "config, route, view and event caches built"

# public/storage -> shared storage/app/public, so uploaded media is reachable at
# /storage/... Re-created per release because public/ is rsynced fresh.
remote "cd ${RELEASE_DIR}/api && rm -f public/storage && ${PHP_BIN} artisan storage:link --quiet" || true
ok "storage symlink in place"

# ─────────────────────────────────────────────────────────────────────────────
step "9. Permissions"
# nginx (www-data) must read the release; the app must write only to storage.
remote "set -e
    chgrp -R ${APP_GROUP} ${RELEASE_DIR} 2>/dev/null || true
    chmod -R u=rwX,g=rX,o= ${RELEASE_DIR}
    # Only files this user owns. php-fpm's master runs as root and owns its own
    # php-fpm-error.log / php-fpm-slow.log inside this directory; a blanket
    # chmod -R would fail on them and abort the deploy after the build.
    find ${SHARED_DIR}/api/storage -user ${APP_USER} -exec chmod u=rwX,g=rwX,o= {} +"
ok "ownership and modes set"

# ─────────────────────────────────────────────────────────────────────────────
step "10. Switch"
PREVIOUS="$(remote "readlink ${CURRENT_LINK} 2>/dev/null || echo ''")"
printf '%s     previous release: %s%s\n' "$c_dim" "${PREVIOUS:-none}" "$c_reset"

# `ln -sfn` via a temp name + `mv -T` is the atomic form: a plain `ln -sfn` over
# an existing symlink is briefly non-atomic and a request can land in the gap.
remote "ln -sfn ${RELEASE_DIR} ${CURRENT_LINK}.new && mv -Tf ${CURRENT_LINK}.new ${CURRENT_LINK}"
ok "current -> ${RELEASE}"

# opcache.validate_timestamps=0 means PHP will keep serving the OLD release's
# compiled code until the pool is recycled. This reload is not optional.
remote_sudo "systemctl reload php${PHP_VERSION}-fpm"
ok "php-fpm reloaded — opcache now sees the new release"

remote_sudo "systemctl restart ${WEB_SERVICE}"
ok "${WEB_SERVICE} restarted"

# horizon:terminate lets each worker finish its current job and exit; systemd
# then restarts Horizon against the new release. Nothing in flight is lost.
if remote "systemctl is-active --quiet ${HORIZON_SERVICE}"; then
    remote "cd ${CURRENT_LINK}/api && ${PHP_BIN} artisan horizon:terminate" || true
    ok "Horizon terminated gracefully; systemd will restart it on the new release"
else
    remote_sudo "systemctl start ${HORIZON_SERVICE}"
    ok "${HORIZON_SERVICE} started"
fi

remote_sudo "systemctl start ${SCHEDULER_TIMER}"

# ─────────────────────────────────────────────────────────────────────────────
step "11. Health check"
healthy=1
# Give the Next server a moment to bind its port.
for i in $(seq 1 20); do
    if remote "curl -sf -o /dev/null http://127.0.0.1:${WEB_PORT}/" 2>/dev/null; then break; fi
    [[ "$i" -eq 20 ]] && healthy=0
    sleep 1
done
if [[ "${healthy}" -eq 1 ]]; then ok "Next.js is answering on 127.0.0.1:${WEB_PORT}"
else warn "Next.js did NOT answer on 127.0.0.1:${WEB_PORT}"; fi

api_code="$(remote "curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:${NGINX_INTERNAL_PORT}/up")"
if [[ "${api_code}" == "200" ]]; then ok "Laravel /up returned 200"
else warn "Laravel /up returned ${api_code}"; healthy=0; fi

if [[ "${healthy}" -eq 0 && -n "${PREVIOUS}" ]]; then
    warn "health check failed — rolling back to ${PREVIOUS##*/}"
    remote "ln -sfn ${PREVIOUS} ${CURRENT_LINK}.new && mv -Tf ${CURRENT_LINK}.new ${CURRENT_LINK}"
    remote_sudo "systemctl reload php${PHP_VERSION}-fpm"
    remote_sudo "systemctl restart ${WEB_SERVICE}"
    die "deploy FAILED and was rolled back. The release is still at ${RELEASE_DIR} for inspection.
     Logs: deploy/scripts/logs.sh"
fi

# ─────────────────────────────────────────────────────────────────────────────
step "12. Prune old releases"
# Never prune the live release or the one `building` points at.
remote "cd ${RELEASES_DIR} && \
    keep=\$(ls -1 | sort -r | head -n ${KEEP_RELEASES}); \
    for d in \$(ls -1 | sort -r | tail -n +\$(( ${KEEP_RELEASES} + 1 ))); do \
        [ \"${RELEASES_DIR}/\$d\" = \"\$(readlink ${CURRENT_LINK})\" ] && continue; \
        echo \"  removing \$d\"; rm -rf \"\$d\"; \
    done"
# Prune deploy backups on the same retention as releases.
remote "cd ${DEPLOY_ROOT}/backups && ls -1t pre-deploy-*.sql.gz 2>/dev/null | tail -n +$((KEEP_RELEASES + 1)) | xargs -r rm -f"
ok "kept the newest ${KEEP_RELEASES} releases"

echo
ok "Deployed ${RELEASE} to ${APP_URL}"
printf '%s     rollback with: deploy/scripts/rollback.sh%s\n' "$c_dim" "$c_reset"
