#!/usr/bin/env bash
#
# Read-only safety check. Changes NOTHING on the droplet.
#
# Run this before provisioning, and again any time you are unsure whether the
# host has drifted. It re-verifies every assumption deploy/config.sh is built
# on: that the ports we claim are free, that the Redis databases we claim are
# empty, that the database and MySQL user do not already exist, that the FPM
# pool name is unused, and that no other nginx vhost answers to our domain.
#
# Every one of those checks exists because getting it wrong would damage a site
# that is already running. This script is cheap — run it whenever.

set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/../config.sh"

failures=0
check_fail() { printf '%s  ✗%s %s\n' "$c_red" "$c_reset" "$*"; failures=$((failures + 1)); }

log "Preflight against ${SSH_TARGET} — read-only, nothing will be changed"

step "Connectivity"
if remote 'true' 2>/dev/null; then
    ok "SSH to ${SSH_TARGET} works without a password"
else
    die "Cannot SSH to ${SSH_TARGET}. Fix that first."
fi
if remote 'sudo -n true' 2>/dev/null; then
    ok "Passwordless sudo available"
else
    check_fail "sudo needs a password — provisioning cannot run unattended"
fi

step "Ports we intend to claim"
# Once MetaCreator is installed its own services hold these ports, which is
# correct rather than a conflict — provision.sh is meant to be re-runnable. So
# a port in use is only a failure when something OTHER than us owns it.
#
# `ss -ltnp` needs root to show process names; without them we cannot tell the
# two cases apart, so the check is deliberately strict when it cannot see.
INSTALLED=0
remote "test -d ${DEPLOY_ROOT}" && INSTALLED=1
[[ "${INSTALLED}" -eq 1 ]] && printf '%s     %s exists — checking ports are held by US, not by someone else%s\n' \
    "$c_dim" "${DEPLOY_ROOT}" "$c_reset"

listeners="$(remote "sudo ss -ltnpH" 2>/dev/null || true)"

# The Next.js port is matched by PID, not by process name: the server renames
# itself to "next-server (v16.x)", so grepping for "node" fails and would report
# our own front end as a stranger. The unit's MainPID cannot be mistaken.
WEB_MAIN_PID="$(remote "systemctl show ${WEB_SERVICE} -p MainPID --value" 2>/dev/null | tr -dc '0-9')"

# Returns 0 if the given `ss` line belongs to us.
line_is_ours() {
    local port="$1" line="$2"
    case "${port}" in
        "${WEB_PORT}")
            [[ -n "${WEB_MAIN_PID}" && "${WEB_MAIN_PID}" != "0" ]] \
                && grep -q "pid=${WEB_MAIN_PID}," <<<"${line}" ;;
        "${NGINX_INTERNAL_PORT}"|"${NGINX_BUILDING_PORT}")
            # nginx is nginx; that these two ports exist at all is our doing.
            grep -q '"nginx"' <<<"${line}" ;;
        *) return 1 ;;
    esac
}

for entry in "${WEB_PORT}:Next.js" "${NGINX_INTERNAL_PORT}:nginx internal origin" "${NGINX_BUILDING_PORT}:nginx build origin"; do
    port="${entry%%:*}"; what="${entry#*:}"
    # Match the port only in the local-address column, so 8082 does not match a
    # peer address or a pid that happens to contain those digits.
    line="$(awk -v p=":${port}\$" '$4 ~ p' <<<"${listeners}")"

    if [[ -z "${line}" ]]; then
        ok "port ${port} is free (${what})"
    elif [[ "${INSTALLED}" -eq 1 ]] && line_is_ours "${port}" "${line}"; then
        ok "port ${port} held by our own service (${what}) — expected"
    else
        check_fail "port ${port} (${what}) is in use by something else — change it in deploy/config.sh"
        printf '%s     %s%s\n' "$c_dim" "$(tr -s ' ' <<<"${line}")" "$c_reset"
    fi
done

step "Services that must already be running"
for svc in nginx mysql redis-server "php${PHP_VERSION}-fpm"; do
    if remote "systemctl is-active --quiet ${svc}"; then
        ok "${svc} is running"
    else
        check_fail "${svc} is NOT running"
    fi
done

step "Redis databases we intend to claim"
# A non-empty database means another application is using that number.
keyspace="$(remote "redis-cli info keyspace" 2>/dev/null || true)"
for db in "${REDIS_DB}" "${REDIS_CACHE_DB}"; do
    if ! grep -q "^db${db}:" <<<"${keyspace}"; then
        ok "Redis db${db} is empty"
        continue
    fi
    # Non-empty is fine if every key is ours. Both our prefixes — the cache/queue
    # prefix and Horizon's — begin with the app name, so one pattern covers them.
    total="$(remote "redis-cli -n ${db} dbsize" | tr -dc '0-9')"
    ours="$(remote "redis-cli -n ${db} --scan --pattern '${APP_NAME}*' | wc -l" | tr -dc '0-9')"
    if [[ "${total}" -gt 0 && "${total}" -eq "${ours}" ]]; then
        ok "Redis db${db} holds ${total} keys, all ours — expected"
    else
        check_fail "Redis db${db} holds $((total - ours)) key(s) that are NOT ours — another site is using it. Change it in deploy/config.sh"
    fi
done
printf '%s     in use today: %s%s\n' "$c_dim" "$(grep -o '^db[0-9]*' <<<"${keyspace}" | tr '\n' ' ')" "$c_reset"

step "MySQL"
if remote "mysql -uroot -psecret -N -B -e 'SELECT 1' >/dev/null 2>&1"; then
    ok "root login works"
    dbs="$(remote "mysql -uroot -psecret -N -B -e 'SHOW DATABASES' 2>/dev/null")"
    if grep -qx "${DB_NAME}" <<<"${dbs}"; then
        warn "database '${DB_NAME}' already exists — provisioning will leave it untouched"
    else
        ok "database '${DB_NAME}' does not exist yet"
    fi
    users="$(remote "mysql -uroot -psecret -N -B -e \"SELECT user FROM mysql.user\" 2>/dev/null")"
    if grep -qx "${DB_USER}" <<<"${users}"; then
        warn "MySQL user '${DB_USER}' already exists — its password will be reset"
    else
        ok "MySQL user '${DB_USER}' does not exist yet"
    fi
else
    check_fail "cannot log in to MySQL as root with the configured password"
fi

step "php-fpm pool name"
if remote "test -f /etc/php/${PHP_VERSION}/fpm/pool.d/${FPM_POOL}.conf"; then
    warn "pool '${FPM_POOL}' already exists — it will be rewritten from the template"
else
    ok "pool name '${FPM_POOL}' is unused"
fi
printf '%s     existing pools: %s%s\n' "$c_dim" \
    "$(remote "ls /etc/php/${PHP_VERSION}/fpm/pool.d/ | tr '\n' ' '")" "$c_reset"

step "nginx — is our domain claimed by another vhost?"
# The real danger: if another site's server_name already matches our domain,
# adding ours creates a duplicate and nginx picks whichever loads first.
if remote "grep -rl 'server_name.*${DOMAIN}' /etc/nginx/sites-enabled/ 2>/dev/null" | grep -qv "${DOMAIN}$"; then
    check_fail "another enabled vhost already claims ${DOMAIN}"
else
    ok "no other vhost claims ${DOMAIN}"
fi
if remote "test -f /etc/nginx/snippets/cloudflare-realip.conf"; then
    ok "snippets/cloudflare-realip.conf exists (needed for real visitor IPs)"
else
    check_fail "snippets/cloudflare-realip.conf is missing — the vhost includes it"
fi
printf '%s     sites currently enabled: %s%s\n' "$c_dim" \
    "$(remote "ls /etc/nginx/sites-enabled/ | tr '\n' ' '")" "$c_reset"

step "TLS certificate"
if remote "sudo test -f ${SSL_CERT} && sudo test -f ${SSL_KEY}"; then
    ok "origin certificate and key are in place"
    printf '%s     %s%s\n' "$c_dim" "$(remote "sudo openssl x509 -in ${SSL_CERT} -noout -subject -dates 2>&1 | tr '\n' ' '")" "$c_reset"
else
    warn "no certificate at ${SSL_CERT} yet — install-cert.sh will ask for it during provisioning"
fi

step "Toolchain"
printf '%s     node    %s\n' "$c_dim" "$(remote 'node -v 2>/dev/null || echo MISSING')"
printf '     php     %s\n' "$(remote "php${PHP_VERSION} -v 2>/dev/null | head -1 || echo MISSING")"
printf '     composer(global, unused by us) %s%s\n' "$(remote 'composer -V 2>/dev/null | head -1 || echo MISSING')" "$c_reset"
# The system composer is 1.10, far too old for Laravel 13. We never touch it —
# provision.sh installs Composer 2 privately under the app's own bin directory,
# so the other sites' toolchain is left exactly as it is.
ok "Composer 2 will be installed privately to ${BIN_DIR}/composer (system composer untouched)"

step "Disk and memory headroom"
printf '%s%s%s\n' "$c_dim" "$(remote 'df -h / | tail -1; free -m | head -2 | tail -1')" "$c_reset"

echo
if [[ "${failures}" -gt 0 ]]; then
    die "${failures} check(s) failed. Resolve them before provisioning."
fi
ok "All preflight checks passed. Safe to run deploy/scripts/provision.sh"
