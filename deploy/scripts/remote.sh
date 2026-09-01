#!/usr/bin/env bash
#
# Everyday operations on the droplet, run from this Mac.
#
#   deploy/scripts/remote.sh <command> [args]
#
# Run it with no arguments to see the full list. This is the general-purpose
# companion to artisan.sh: shells, logs, service control, database access,
# cache clearing and backups, each wrapped so it targets ONLY MetaCreator and
# cannot reach the other sites on the host by accident.

set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/../config.sh"

CMD="${1:-help}"; shift || true

db_pass() { remote "cat ${SHARED_DIR}/.db-password"; }

# SSH with a TTY when we have one, for interactive things.
ssh_i() {
    local flags=(-o BatchMode=yes)
    [[ -t 0 && -t 1 ]] && flags=(-t)
    ssh "${flags[@]}" "${SSH_TARGET}" "$@"
}

case "${CMD}" in

# ── Shells ───────────────────────────────────────────────────────────────────
ssh)
    # A plain login shell, dropped straight into the live release.
    exec ssh -t "${SSH_TARGET}" "cd ${CURRENT_LINK} && exec \$SHELL -l" ;;

sh|shell)
    # Run one arbitrary shell command in the live API directory.
    [[ $# -gt 0 ]] || die "usage: remote.sh sh '<command>'"
    exec ssh_i "cd ${CURRENT_LINK}/api && $*" ;;

tinker)
    exec "$(dirname "${BASH_SOURCE[0]}")/artisan.sh" tinker ;;

# ── Logs ─────────────────────────────────────────────────────────────────────
logs)
    exec "$(dirname "${BASH_SOURCE[0]}")/logs.sh" "$@" ;;

# ── Service control ──────────────────────────────────────────────────────────
# Every one of these names an explicit unit. There is deliberately no
# "restart everything" that could touch nginx, mysql or another site's service.
restart-web)     remote_sudo "systemctl restart ${WEB_SERVICE}";     ok "${WEB_SERVICE} restarted" ;;
restart-horizon) remote "cd ${CURRENT_LINK}/api && ${PHP_BIN} artisan horizon:terminate" || true
                 ok "Horizon terminated; systemd restarts it automatically" ;;
restart-php)
    # A reload, not a restart: php8.4-fpm also serves atxtopeatery, and a
    # restart would drop that site's in-flight requests. Reload is graceful.
    remote_sudo "systemctl reload php${PHP_VERSION}-fpm"; ok "php${PHP_VERSION}-fpm reloaded (graceful)" ;;
reload-nginx)
    # Config test first. If it fails, nothing is reloaded and every site keeps
    # running on the config it already had.
    # Captured rather than piped to `grep -q`, which would SIGPIPE the ssh under
    # `pipefail` and report a false failure.
    nginx_test="$(remote_sudo "nginx -t" 2>&1 || true)"
    if grep -q 'test is successful' <<<"${nginx_test}"; then
        remote_sudo "systemctl reload nginx"; ok "nginx reloaded"
    else
        printf '%s\n' "${nginx_test}"; die "nginx config test failed — NOT reloaded"
    fi ;;

reload)
    # What to run after editing the shared .env: rebuild the cached config (the
    # app reads config:cache in production, not .env) and restart the services.
    remote "cd ${CURRENT_LINK}/api && set -e
        ${PHP_BIN} artisan config:cache
        ${PHP_BIN} artisan route:cache
        ${PHP_BIN} artisan event:cache"
    remote_sudo "systemctl reload php${PHP_VERSION}-fpm"
    remote_sudo "systemctl restart ${WEB_SERVICE}"
    remote "cd ${CURRENT_LINK}/api && ${PHP_BIN} artisan horizon:terminate" || true
    ok "config rebuilt and services restarted" ;;

status) exec "$(dirname "${BASH_SOURCE[0]}")/status.sh" ;;

# ── Caches ───────────────────────────────────────────────────────────────────
clear-cache)
    # `cache:clear` removes only this app's prefixed keys. NEVER run
    # `redis-cli flushall` on this droplet — it would wipe every site's cache.
    remote "cd ${CURRENT_LINK}/api && ${PHP_BIN} artisan cache:clear"
    ok "application cache cleared (only ${REDIS_PREFIX}* keys)" ;;

# ── Environment ──────────────────────────────────────────────────────────────
edit-env-api)
    ssh -t "${SSH_TARGET}" "\${EDITOR:-nano} ${SHARED_DIR}/api/.env"
    warn "run 'deploy/scripts/remote.sh reload' to apply the change" ;;
edit-env-web)
    ssh -t "${SSH_TARGET}" "\${EDITOR:-nano} ${SHARED_DIR}/web/.env.production"
    warn "NEXT_PUBLIC_* values are baked in at build time — changing one needs a full deploy."
    warn "For the others, run 'deploy/scripts/remote.sh restart-web'." ;;
show-env-api) remote "cat ${SHARED_DIR}/api/.env" ;;
show-env-web) remote "cat ${SHARED_DIR}/web/.env.production" ;;

# ── Database ─────────────────────────────────────────────────────────────────
db)
    # An interactive MySQL shell scoped to this app's database and user, so a
    # stray query cannot reach another site's tables.
    exec ssh -t "${SSH_TARGET}" "mysql -u${DB_USER} -p'$(db_pass)' ${DB_NAME}" ;;

db-password) db_pass; echo ;;

backup-db)
    out="${DEPLOY_ROOT}/backups/manual-$(date -u +%Y%m%dT%H%M%S).sql.gz"
    remote "mysqldump -u${DB_USER} -p'$(db_pass)' --single-transaction --quick \
        --routines --triggers ${DB_NAME} 2>/dev/null | gzip > ${out}"
    ok "backed up to ${out} ($(remote "du -h ${out} | cut -f1"))" ;;

pull-db)
    # Bring a dump down to this Mac, for restoring into local development.
    out="${1:-metacreator-$(date -u +%Y%m%dT%H%M%S).sql.gz}"
    remote "mysqldump -u${DB_USER} -p'$(db_pass)' --single-transaction --quick \
        --routines --triggers ${DB_NAME} 2>/dev/null | gzip" > "${out}"
    ok "downloaded to ${out} ($(du -h "${out}" | cut -f1))" ;;

list-backups) remote "ls -lht ${DEPLOY_ROOT}/backups/ | head -30" ;;

restore-db)
    src="${1:-}"; [[ -n "${src}" ]] || die "usage: remote.sh restore-db <path-on-droplet.sql.gz>"
    remote "test -f ${src}" || die "no such file on the droplet: ${src}"
    warn "This OVERWRITES the ${DB_NAME} database with ${src}."
    read -rp "Type the database name to confirm: " confirm
    [[ "${confirm}" == "${DB_NAME}" ]] || die "aborted"
    # Take a safety dump of what is about to be replaced.
    safety="${DEPLOY_ROOT}/backups/before-restore-$(date -u +%Y%m%dT%H%M%S).sql.gz"
    remote "mysqldump -u${DB_USER} -p'$(db_pass)' --single-transaction ${DB_NAME} 2>/dev/null | gzip > ${safety}"
    ok "current state saved to ${safety}"
    remote "gunzip -c ${src} | mysql -u${DB_USER} -p'$(db_pass)' ${DB_NAME} 2>/dev/null"
    ok "restored from ${src}"
    remote "cd ${CURRENT_LINK}/api && ${PHP_BIN} artisan cache:clear" ;;

# ── Media ────────────────────────────────────────────────────────────────────
backup-media)
    out="${DEPLOY_ROOT}/backups/media-$(date -u +%Y%m%dT%H%M%S).tar.gz"
    remote "tar -czf ${out} -C ${SHARED_DIR}/api/storage/app public 2>/dev/null || true"
    ok "media archived to ${out} ($(remote "du -h ${out} | cut -f1"))" ;;

# ── Diagnostics ──────────────────────────────────────────────────────────────
releases) exec "$(dirname "${BASH_SOURCE[0]}")/rollback.sh" --list ;;

disk)   remote "df -h /; echo; du -sh ${DEPLOY_ROOT}/* 2>/dev/null | sort -h" ;;
top)    exec ssh -t "${SSH_TARGET}" "top -b -n1 | head -25" ;;

help|*)
    cat <<HELP
${c_blue}MetaCreator.Dev — remote operations${c_reset}
target: ${SSH_TARGET}  ·  live release: ${CURRENT_LINK}

${c_dim}shells${c_reset}
  ssh                    login shell in the release directory
  sh '<cmd>'             run one shell command in apps/api
  tinker                 interactive Laravel REPL against production
  db                     interactive MySQL shell (this app's database only)

${c_dim}services${c_reset}   ${c_dim}(each names one unit; nothing here touches another site)${c_reset}
  status                 health of every MetaCreator service
  restart-web            restart the Next.js server
  restart-horizon        graceful queue-worker restart (finishes in-flight jobs)
  restart-php            reload php-fpm  ${c_dim}(graceful — atxtopeatery unaffected)${c_reset}
  reload-nginx           test then reload nginx
  reload                 rebuild cached config + restart services ${c_dim}(after an .env edit)${c_reset}
  clear-cache            clear the application cache

${c_dim}environment${c_reset}
  edit-env-api           edit the Laravel .env on the server
  edit-env-web           edit the Next.js .env.production on the server
  show-env-api           print it
  show-env-web           print it

${c_dim}database${c_reset}
  db-password            print the generated database password
  backup-db              dump to ${DEPLOY_ROOT}/backups
  pull-db [file]         download a dump to this Mac
  list-backups           list dumps on the droplet
  restore-db <file>      restore a dump ${c_dim}(prompts, and backs up first)${c_reset}
  backup-media           archive the uploads directory

${c_dim}logs & diagnostics${c_reset}
  logs [which] [-f]      see logs.sh --help for the streams
  releases               list releases, marking the live one
  disk                   disk usage for this app
  top                    what is using the droplet right now

${c_dim}artisan${c_reset}
  deploy/scripts/artisan.sh <command>
HELP
    ;;
esac
