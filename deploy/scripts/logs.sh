#!/usr/bin/env bash
#
# Tail MetaCreator's logs from this Mac.
#
#   deploy/scripts/logs.sh              # laravel, last 100 lines
#   deploy/scripts/logs.sh web -f       # follow the Next.js server log
#   deploy/scripts/logs.sh nginx-error -f
#   deploy/scripts/logs.sh all
#
# Streams:
#   laravel       the application log (storage/logs/laravel-*.log)
#   web           Next.js server output
#   horizon       queue workers
#   scheduler     the per-minute scheduler tick
#   php-error     php-fpm errors for THIS pool only
#   php-slow      requests slower than 10s, with backtraces
#   nginx-access  this vhost's access log
#   nginx-error   this vhost's error log
#   all           everything above, interleaved
#
# Every path is scoped to this app. There is no option that tails another
# site's logs or the shared php-fpm log.

set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/../config.sh"

WHICH="${1:-laravel}"
FOLLOW=0
for a in "$@"; do [[ "$a" == "-f" || "$a" == "--follow" ]] && FOLLOW=1; done
[[ "${WHICH}" == "-f" ]] && WHICH="laravel"

L="${SHARED_DIR}/api/storage/logs"

case "${WHICH}" in
    laravel)      paths="${L}/laravel*.log" ;;
    web)          paths="${L}/web.log" ;;
    horizon)      paths="${L}/horizon.log" ;;
    scheduler)    paths="${L}/scheduler.log" ;;
    php-error)    paths="${L}/php-fpm-error.log" ;;
    php-slow)     paths="${L}/php-fpm-slow.log" ;;
    nginx-access) paths="/var/log/nginx/${DOMAIN}.access.log" ;;
    nginx-error)  paths="/var/log/nginx/${DOMAIN}.error.log" ;;
    all)          paths="${L}/*.log /var/log/nginx/${DOMAIN}.*.log" ;;
    -h|--help)    sed -n '2,25p' "${BASH_SOURCE[0]}"; exit 0 ;;
    *)            die "unknown log '${WHICH}'. Run with --help for the list." ;;
esac

if [[ "${FOLLOW}" -eq 1 ]]; then
    log "following ${WHICH} — Ctrl-C to stop"
    # -F rather than -f: reopens the file when logrotate replaces it, and copes
    # with Laravel's `daily` channel rolling over to a new filename at midnight.
    exec ssh -t "${SSH_TARGET}" "sudo tail -n 40 -F ${paths} 2>/dev/null"
else
    log "${WHICH} — last 100 lines"
    exec ssh -o BatchMode=yes "${SSH_TARGET}" "sudo tail -n 100 ${paths} 2>/dev/null"
fi
