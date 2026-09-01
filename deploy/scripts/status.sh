#!/usr/bin/env bash
#
# Health of the MetaCreator deployment, plus a check that the rest of the
# droplet is still happy.
#
#   deploy/scripts/status.sh
#
# The second half is deliberate: this app shares nginx, php-fpm, MySQL and
# Redis with ten other sites, so "is my app up" is only half the question. The
# other half is whether anything we did disturbed them.

set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/../config.sh"

log "MetaCreator.Dev on ${SSH_HOST}"

step "Release"
remote "readlink ${CURRENT_LINK} 2>/dev/null | xargs -r basename | sed 's/^/     live:     /'" || echo "     (none)"
remote "ls -1 ${RELEASES_DIR} 2>/dev/null | wc -l | sed 's/^/     retained: /'" || true

step "Services"
for unit in "${WEB_SERVICE}" "${HORIZON_SERVICE}" "${SCHEDULER_TIMER}"; do
    state="$(remote "systemctl is-active ${unit} 2>/dev/null || echo unknown")"
    since="$(remote "systemctl show ${unit} -p ActiveEnterTimestamp --value 2>/dev/null" || true)"
    if [[ "${state}" == "active" || "${state}" == "waiting" ]]; then
        printf '%s  ✓%s %-32s %s  %s%s%s\n' "$c_green" "$c_reset" "${unit}" "${state}" "$c_dim" "${since}" "$c_reset"
    else
        printf '%s  ✗%s %-32s %s\n' "$c_red" "$c_reset" "${unit}" "${state}"
    fi
done

step "Endpoints"
api="$(remote "curl -s -o /dev/null -w '%{http_code}' --max-time 10 http://127.0.0.1:${NGINX_INTERNAL_PORT}/up")"
[[ "${api}" == "200" ]] && ok "Laravel /up (internal)  200" || printf '%s  ✗%s Laravel /up (internal)  %s\n' "$c_red" "$c_reset" "${api}"

web="$(remote "curl -s -o /dev/null -w '%{http_code}' --max-time 15 http://127.0.0.1:${WEB_PORT}/")"
[[ "${web}" =~ ^(200|30[0-9])$ ]] && ok "Next.js (internal)      ${web}" || printf '%s  ✗%s Next.js (internal)      %s\n' "$c_red" "$c_reset" "${web}"

# Through Cloudflare, from this Mac — the path a real visitor takes.
pub="$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "${APP_URL}/" 2>/dev/null || echo "---")"
[[ "${pub}" =~ ^(200|30[0-9])$ ]] && ok "${APP_URL} (public)  ${pub}" || printf '%s  !%s %s (public)  %s %s(DNS/Cloudflare, or not deployed yet)%s\n' "$c_yellow" "$c_reset" "${APP_URL}" "${pub}" "$c_dim" "$c_reset"

step "Queues"
remote "cd ${CURRENT_LINK}/api && ${PHP_BIN} artisan horizon:status 2>/dev/null" || warn "Horizon is not reporting"
pending="$(remote "redis-cli -n ${REDIS_DB} --scan --pattern '${HORIZON_PREFIX}*' 2>/dev/null | wc -l")"
printf '%s     %s Horizon keys in redis db%s%s\n' "$c_dim" "${pending}" "${REDIS_DB}" "$c_reset"

step "Recent errors (last 20 lines of the application log)"
remote "sudo tail -n 20 ${SHARED_DIR}/api/storage/logs/laravel*.log 2>/dev/null | grep -iE 'error|exception|critical' | tail -8" \
    || printf '%s     none%s\n' "$c_dim" "$c_reset"

# ─────────────────────────────────────────────────────────────────────────────
step "The rest of the droplet — did we disturb anything?"
for svc in nginx mysql redis-server "php${PHP_VERSION}-fpm"; do
    if remote "systemctl is-active --quiet ${svc}"; then ok "${svc} running"
    else printf '%s  ✗%s %s is DOWN\n' "$c_red" "$c_reset" "${svc}"; fi
done
nginx_test="$(remote_sudo "nginx -t" 2>&1 || true)"
if grep -q 'test is successful' <<<"${nginx_test}"; then ok "nginx config is valid"
else printf '%s  ✗%s nginx config is INVALID\n' "$c_red" "$c_reset"; printf '%s%s%s\n' "$c_dim" "${nginx_test}" "$c_reset"; fi

printf '%s     sites enabled: %s%s\n' "$c_dim" "$(remote "ls /etc/nginx/sites-enabled/ | wc -l") vhosts" "$c_reset"
# Spot-check that a neighbouring site still answers.
for host in atxtopeatery.com petehouston.com; do
    code="$(remote "curl -s -o /dev/null -w '%{http_code}' --max-time 10 -H 'Host: ${host}' -k https://127.0.0.1/" 2>/dev/null || echo '---')"
    printf '%s     neighbour %-22s %s%s\n' "$c_dim" "${host}" "${code}" "$c_reset"
done

step "Resources"
printf '%s%s%s\n' "$c_dim" "$(remote 'free -m | head -2 | tail -1; df -h / | tail -1; uptime')" "$c_reset"
