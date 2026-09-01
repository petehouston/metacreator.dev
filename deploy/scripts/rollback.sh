#!/usr/bin/env bash
#
# Roll back to a previous release.
#
#   deploy/scripts/rollback.sh                    # go back one release
#   deploy/scripts/rollback.sh 20260901T164500    # go to a specific release
#   deploy/scripts/rollback.sh --list             # show what is available
#
# ── What a rollback does and does not do ─────────────────────────────────────
# It moves the `current` symlink, reloads php-fpm so opcache picks up the older
# code, and restarts the web and queue services. That is fast and safe.
#
# It does NOT reverse database migrations. If the release you are leaving added
# a column, that column stays. This is why migrations must be backwards
# compatible — the expand/contract rule in docs/04-data-model.md — and why
# deploy.sh takes a dump before every migration. If you genuinely need the old
# schema back, restore that dump:
#
#   deploy/scripts/remote.sh restore-db <path-to-dump.sql.gz>

set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/../config.sh"

TARGET="${1:-}"

available="$(remote "ls -1 ${RELEASES_DIR} | sort -r")"
live="$(remote "basename \$(readlink ${CURRENT_LINK})")"

if [[ "${TARGET}" == "--list" || "${TARGET}" == "-l" ]]; then
    log "Releases on ${SSH_HOST} (newest first)"
    while read -r r; do
        [[ -z "${r}" ]] && continue
        if [[ "${r}" == "${live}" ]]; then
            printf '  %s%s  ← live%s\n' "$c_green" "${r}" "$c_reset"
        else
            printf '  %s\n' "${r}"
        fi
    done <<<"${available}"
    exit 0
fi

if [[ -z "${TARGET}" ]]; then
    # The newest release that is not the live one.
    TARGET="$(grep -v "^${live}$" <<<"${available}" | head -1)"
    [[ -n "${TARGET}" ]] || die "there is no previous release to roll back to"
fi

grep -qx "${TARGET}" <<<"${available}" \
    || die "release '${TARGET}' does not exist on the server. Try --list."
[[ "${TARGET}" != "${live}" ]] || die "release '${TARGET}' is already live"

log "Rolling back: ${live}  →  ${TARGET}"
remote "test -f ${RELEASES_DIR}/${TARGET}/api/public/index.php" \
    || die "release ${TARGET} looks incomplete (no api/public/index.php). Refusing."
remote "test -f ${RELEASES_DIR}/${TARGET}/web/.next/standalone/server.js" \
    || die "release ${TARGET} has no built front end. Refusing."
ok "target release passes a sanity check"

step "Switching"
remote "ln -sfn ${RELEASES_DIR}/${TARGET} ${CURRENT_LINK}.new && mv -Tf ${CURRENT_LINK}.new ${CURRENT_LINK}"
ok "current -> ${TARGET}"

remote_sudo "systemctl reload php${PHP_VERSION}-fpm"
remote_sudo "systemctl restart ${WEB_SERVICE}"
remote "cd ${CURRENT_LINK}/api && ${PHP_BIN} artisan horizon:terminate" 2>/dev/null || true
ok "services restarted on ${TARGET}"

step "Health check"
sleep 3
code="$(remote "curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:${NGINX_INTERNAL_PORT}/up")"
[[ "${code}" == "200" ]] && ok "Laravel /up returned 200" || warn "Laravel /up returned ${code}"
remote "curl -sf -o /dev/null http://127.0.0.1:${WEB_PORT}/" && ok "Next.js is answering" || warn "Next.js is not answering"

echo
ok "Rolled back to ${TARGET}"
warn "Migrations were NOT reversed. If the schema needs to go back too, restore a dump from ${DEPLOY_ROOT}/backups."
