#!/usr/bin/env bash
#
# One-time host provisioning for MetaCreator.Dev.
#
#   deploy/scripts/provision.sh
#
# Idempotent: running it twice changes nothing the second time, except that
# rendered config files are rewritten from their templates. Safe to re-run after
# editing anything in deploy/templates or deploy/config.sh.
#
# ── What this touches on a droplet that serves ten other sites ───────────────
# CREATES (all new, none shared):
#   /var/www/metacreator.dev/…                     directory tree
#   /etc/php/8.4/fpm/pool.d/metacreator.conf       a NEW pool, not an edit
#   /etc/nginx/snippets/metacreator-api.conf       new file
#   /etc/nginx/sites-available|enabled/metacreator.dev
#   /etc/systemd/system/metacreator-*.service|timer
#   MySQL database + user metacreator_dev
#
# RELOADS (graceful, in-flight requests finish — never restarts):
#   php8.4-fpm, nginx
#
# NEVER TOUCHES:
#   any other vhost, pool, database, systemd unit, or Redis database;
#   the system-wide composer, php.ini, my.cnf, redis.conf or ufw rules.
#
# Every reload is preceded by a config test, and nginx is only reloaded if
# `nginx -t` passes — so a mistake in this repo cannot take the other sites
# down; it fails here instead.

set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/../config.sh"

# ─────────────────────────────────────────────────────────────────────────────
# Renders a template: substitutes every __PLACEHOLDER__ and prints the result.
# Values are passed through `sed` as replacement text, so any `&`, `|` or
# backslash in a secret would corrupt the output. They are escaped here.
render() {
    local template="$1"
    local sed_args=()
    local key value
    for key in "${!TPL_@}"; do
        value="${!key}"
        value="${value//\\/\\\\}"; value="${value//|/\\|}"; value="${value//&/\\&}"
        sed_args+=(-e "s|__${key#TPL_}__|${value}|g")
    done
    sed "${sed_args[@]}" "${template}"
}

# Uploads rendered content to a root-owned destination, but ONLY if it differs —
# so a re-run does not churn file mtimes or trigger needless reloads.
# Returns 0 if the file changed, 1 if it was already correct.
install_root_file() {
    local content="$1" dest="$2" mode="${3:-0644}"
    local tmp="/tmp/.mc-provision.$$"
    printf '%s' "${content}" | ssh -o BatchMode=yes "${SSH_TARGET}" "cat > ${tmp}"
    if remote "sudo test -f ${dest} && sudo cmp -s ${tmp} ${dest}"; then
        remote "rm -f ${tmp}"
        printf '%s     unchanged: %s%s\n' "$c_dim" "${dest}" "$c_reset"
        return 1
    fi
    remote_sudo "install -m ${mode} -o root -g root ${tmp} ${dest}"
    remote "rm -f ${tmp}"
    ok "wrote ${dest}"
    return 0
}

# ─────────────────────────────────────────────────────────────────────────────
log "Provisioning ${DOMAIN} on ${SSH_TARGET}"

step "0. Preflight"
# Refuse to proceed if the host does not look the way config.sh assumes.
"$(dirname "${BASH_SOURCE[0]}")/preflight.sh" >/dev/null 2>&1 \
    || die "preflight.sh failed — run it directly to see why. Nothing has been changed."
ok "host matches the assumptions in deploy/config.sh"

remote "sudo test -f ${SSL_CERT}" \
    || die "no TLS certificate at ${SSL_CERT}. Run deploy/scripts/install-cert.sh first."

# ─────────────────────────────────────────────────────────────────────────────
step "1. Directory tree"
# setgid (2775) on the shared directories so every file created inside inherits
# the www-data group, which is how nginx keeps being able to read new releases
# without anyone remembering to chgrp.
remote "sudo install -d -m 2775 -o ${APP_USER} -g ${APP_GROUP} \
    ${DEPLOY_ROOT} ${RELEASES_DIR} ${SHARED_DIR} ${BIN_DIR} \
    ${SHARED_DIR}/api ${SHARED_DIR}/web \
    ${SHARED_DIR}/web/image-cache \
    ${SHARED_DIR}/api/storage \
    ${SHARED_DIR}/api/storage/app \
    ${SHARED_DIR}/api/storage/app/public \
    ${SHARED_DIR}/api/storage/app/private \
    ${SHARED_DIR}/api/storage/framework \
    ${SHARED_DIR}/api/storage/framework/cache \
    ${SHARED_DIR}/api/storage/framework/cache/data \
    ${SHARED_DIR}/api/storage/framework/sessions \
    ${SHARED_DIR}/api/storage/framework/views \
    ${SHARED_DIR}/api/storage/logs \
    ${DEPLOY_ROOT}/backups"
ok "tree created under ${DEPLOY_ROOT}"

# ─────────────────────────────────────────────────────────────────────────────
step "2. Composer 2 (private to this app)"
# The system composer is 1.10.26 — it cannot resolve Laravel 13. Rather than
# upgrading it and risking whatever the other nine sites' toolchains expect, a
# current Composer is installed into this app's own bin directory and used by
# path everywhere in these scripts.
if remote "test -x ${BIN_DIR}/composer && ${COMPOSER_CMD} -V 2>/dev/null | grep -q 'version 2'"; then
    printf '%s     already installed: %s%s\n' "$c_dim" "$(remote "${COMPOSER_CMD} -V | head -1")" "$c_reset"
else
    remote "curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php"
    # Verify the installer against Composer's published checksum before running it.
    remote "expected=\$(curl -sS https://composer.github.io/installer.sig); \
            actual=\$(php${PHP_VERSION} -r \"echo hash_file('sha384', '/tmp/composer-setup.php');\"); \
            [ \"\$expected\" = \"\$actual\" ] || { echo 'CHECKSUM MISMATCH'; exit 1; }"
    remote "php${PHP_VERSION} /tmp/composer-setup.php --install-dir=${BIN_DIR} --filename=composer --quiet"
    remote "rm -f /tmp/composer-setup.php"
    ok "installed $(remote "${COMPOSER_CMD} -V | head -1")"
fi
printf '%s     system composer left untouched: %s%s\n' "$c_dim" "$(remote 'composer -V 2>/dev/null | head -1')" "$c_reset"

# ─────────────────────────────────────────────────────────────────────────────
step "3. MySQL database and user"
DB_PASSWORD_FILE="${DEPLOY_ROOT}/shared/.db-password"
if remote "test -f ${DB_PASSWORD_FILE}"; then
    DB_PASSWORD="$(remote "cat ${DB_PASSWORD_FILE}")"
    ok "reusing the existing database password"
else
    # openssl rather than `tr </dev/urandom | head -c`: head closes the pipe as
    # soon as it has enough bytes, tr dies of SIGPIPE, and `set -o pipefail`
    # then aborts the whole script silently at this line.
    DB_PASSWORD="$(openssl rand -hex 16)"
    remote "umask 077 && printf '%s' '${DB_PASSWORD}' > ${DB_PASSWORD_FILE}"
    ok "generated a new 32-character database password"
fi

# CREATE ... IF NOT EXISTS and a scoped GRANT. The grant names this database
# explicitly — this user can see nothing else on the server, so a compromise of
# the app cannot read another site's data.
remote "mysql -uroot -psecret 2>/dev/null <<'SQL'
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL"
ok "database '${DB_NAME}' and user '${DB_USER}'@'localhost' ready (utf8mb4)"
printf '%s     the user is granted rights on %s and nothing else%s\n' "$c_dim" "${DB_NAME}" "$c_reset"

# ─────────────────────────────────────────────────────────────────────────────
step "4. Environment files"
# Written once and then left alone forever: they are shared state that must
# survive both deploys and rollbacks.
APP_KEY_EXISTING="$(remote "grep -m1 '^APP_KEY=' ${SHARED_DIR}/api/.env 2>/dev/null | cut -d= -f2-" || true)"
REVALIDATE_EXISTING="$(remote "grep -m1 '^REVALIDATE_SECRET=' ${SHARED_DIR}/api/.env 2>/dev/null | cut -d= -f2-" || true)"
COMPUTE_EXISTING="$(remote "grep -m1 '^COMPUTE_SHARED_SECRET=' ${SHARED_DIR}/api/.env 2>/dev/null | cut -d= -f2-" || true)"

# APP_KEY encrypts sessions and every encrypted column. Regenerating it would
# log every user out and make existing encrypted data unreadable, so it is
# generated exactly once and preserved forever after.
TPL_APP_KEY="${APP_KEY_EXISTING:-base64:$(openssl rand -base64 32)}"
TPL_REVALIDATE_SECRET="${REVALIDATE_EXISTING:-$(openssl rand -hex 24)}"
TPL_COMPUTE_SECRET="${COMPUTE_EXISTING:-$(openssl rand -hex 24)}"

TPL_APP_NAME="${APP_NAME}"; TPL_DOMAIN="${DOMAIN}"; TPL_WWW_DOMAIN="${WWW_DOMAIN}"
TPL_APP_URL="${APP_URL}"; TPL_APP_USER="${APP_USER}"; TPL_APP_GROUP="${APP_GROUP}"
TPL_DEPLOY_ROOT="${DEPLOY_ROOT}"; TPL_SHARED_DIR="${SHARED_DIR}"
TPL_CURRENT_LINK="${CURRENT_LINK}"; TPL_BUILDING_LINK="${BUILDING_LINK}"
TPL_WEB_PORT="${WEB_PORT}"; TPL_NGINX_INTERNAL_PORT="${NGINX_INTERNAL_PORT}"
TPL_NGINX_BUILDING_PORT="${NGINX_BUILDING_PORT}"; TPL_COMPUTE_PORT="8090"
TPL_PHP_VERSION="${PHP_VERSION}"; TPL_PHP_BIN="${PHP_BIN}"
TPL_FPM_POOL="${FPM_POOL}"; TPL_FPM_SOCKET="${FPM_SOCKET}"
TPL_DB_NAME="${DB_NAME}"; TPL_DB_USER="${DB_USER}"; TPL_DB_PASSWORD="${DB_PASSWORD}"
TPL_DB_HOST="${DB_HOST}"; TPL_DB_PORT="${DB_PORT}"
TPL_REDIS_HOST="${REDIS_HOST}"; TPL_REDIS_PORT="${REDIS_PORT}"
TPL_REDIS_DB="${REDIS_DB}"; TPL_REDIS_CACHE_DB="${REDIS_CACHE_DB}"
TPL_REDIS_PREFIX="${REDIS_PREFIX}"; TPL_HORIZON_PREFIX="${HORIZON_PREFIX}"
TPL_SSL_CERT="${SSL_CERT}"; TPL_SSL_KEY="${SSL_KEY}"
TPL_SSH_USER="${SSH_USER}"; TPL_SSH_HOST="${SSH_HOST}"
TPL_SCHEDULER_SERVICE="${SCHEDULER_SERVICE}"

if remote "test -f ${SHARED_DIR}/api/.env"; then
    printf '%s     %s/api/.env already exists — left exactly as it is%s\n' "$c_dim" "${SHARED_DIR}" "$c_reset"
else
    render "${TEMPLATES_DIR}/env.api" | ssh -o BatchMode=yes "${SSH_TARGET}" \
        "umask 027 && cat > ${SHARED_DIR}/api/.env && chgrp ${APP_GROUP} ${SHARED_DIR}/api/.env"
    ok "created ${SHARED_DIR}/api/.env (0640)"
fi

if remote "test -f ${SHARED_DIR}/web/.env.production"; then
    printf '%s     %s/web/.env.production already exists — left as it is%s\n' "$c_dim" "${SHARED_DIR}" "$c_reset"
else
    render "${TEMPLATES_DIR}/env.web" | ssh -o BatchMode=yes "${SSH_TARGET}" \
        "umask 027 && cat > ${SHARED_DIR}/web/.env.production && chgrp ${APP_GROUP} ${SHARED_DIR}/web/.env.production"
    ok "created ${SHARED_DIR}/web/.env.production (0640)"
fi

# ─────────────────────────────────────────────────────────────────────────────
step "5. php-fpm pool"
fpm_changed=0
install_root_file "$(render "${TEMPLATES_DIR}/php-fpm-pool.conf")" \
    "/etc/php/${PHP_VERSION}/fpm/pool.d/${FPM_POOL}.conf" 0644 && fpm_changed=1

if [[ "${fpm_changed}" -eq 1 ]]; then
    # Test before reloading. php8.4-fpm also serves atxtopeatery, so a bad pool
    # file that stopped the service would take that site down too.
    remote_sudo "php-fpm${PHP_VERSION} -t" 2>&1 | tail -2
    remote_sudo "systemctl reload php${PHP_VERSION}-fpm"
    ok "php${PHP_VERSION}-fpm reloaded (graceful — atxtopeatery unaffected)"
fi

# ─────────────────────────────────────────────────────────────────────────────
step "6. nginx"
nginx_changed=0
install_root_file "$(render "${TEMPLATES_DIR}/nginx-api.conf")" \
    "/etc/nginx/snippets/${APP_NAME}-api.conf" 0644 && nginx_changed=1
install_root_file "$(render "${TEMPLATES_DIR}/nginx-horizon.conf")" \
    "/etc/nginx/snippets/${APP_NAME}-horizon.conf" 0644 && nginx_changed=1
install_root_file "$(render "${TEMPLATES_DIR}/nginx-site.conf")" \
    "/etc/nginx/sites-available/${DOMAIN}" 0644 && nginx_changed=1

# The vhost references ${CURRENT_LINK} and ${BUILDING_LINK} as document roots.
# nginx refuses to start if a root's parent is missing, so both symlinks must
# exist before it is enabled — even on a first provision with no release yet.
remote "test -e ${CURRENT_LINK}  || ln -sfn ${SHARED_DIR}/placeholder ${CURRENT_LINK}"
remote "test -e ${BUILDING_LINK} || ln -sfn ${SHARED_DIR}/placeholder ${BUILDING_LINK}"
remote "install -d -m 2775 ${SHARED_DIR}/placeholder/api/public"

if ! remote "test -L /etc/nginx/sites-enabled/${DOMAIN}"; then
    remote_sudo "ln -sfn /etc/nginx/sites-available/${DOMAIN} /etc/nginx/sites-enabled/${DOMAIN}"
    ok "enabled the vhost"
    nginx_changed=1
fi

if [[ "${nginx_changed}" -eq 1 ]]; then
    # THE critical gate. nginx serves ten other sites from one process: if this
    # config is invalid, a reload is refused and everything keeps running on the
    # old config — but only because we test first and abort rather than reload
    # blindly.
    # Captured, not piped into `grep -q`: under `set -o pipefail` a `grep -q`
    # exits on its first match, ssh takes SIGPIPE, and the pipeline reports
    # failure even when nginx said the config was fine.
    nginx_test="$(remote_sudo "nginx -t" 2>&1 || true)"
    if grep -q 'test is successful' <<<"${nginx_test}"; then
        ok "nginx -t passed"
        remote_sudo "systemctl reload nginx"
        ok "nginx reloaded (graceful — no other site dropped a request)"
    else
        # Take the vhost back out so the NEXT reload — by us, by certbot, or by
        # a reboot — cannot pick up a config that does not parse. Every other
        # site keeps running on the configuration already loaded in memory.
        remote_sudo "rm -f /etc/nginx/sites-enabled/${DOMAIN}"
        printf '%s%s%s\n' "$c_dim" "${nginx_test}" "$c_reset"
        die "nginx config test FAILED — the error is above. The vhost has been disabled again and nginx was NOT reloaded. Your other sites are untouched."
    fi
fi

# ─────────────────────────────────────────────────────────────────────────────
step "7. systemd units"
units_changed=0
install_root_file "$(render "${TEMPLATES_DIR}/web.service")"       "/etc/systemd/system/${WEB_SERVICE}"       0644 && units_changed=1
install_root_file "$(render "${TEMPLATES_DIR}/horizon.service")"   "/etc/systemd/system/${HORIZON_SERVICE}"   0644 && units_changed=1
install_root_file "$(render "${TEMPLATES_DIR}/scheduler.service")" "/etc/systemd/system/${SCHEDULER_SERVICE}" 0644 && units_changed=1
install_root_file "$(render "${TEMPLATES_DIR}/scheduler.timer")"   "/etc/systemd/system/${SCHEDULER_TIMER}"   0644 && units_changed=1

if [[ "${units_changed}" -eq 1 ]]; then
    remote_sudo "systemctl daemon-reload"
    ok "systemd reloaded"
fi

# Enabled (start on boot) but NOT started: there is no release to run yet.
# deploy.sh starts them once the first release is in place.
remote_sudo "systemctl enable ${WEB_SERVICE} ${HORIZON_SERVICE} ${SCHEDULER_TIMER}" 2>&1 | grep -i created || true
ok "units enabled for boot (started by the first deploy, not here)"

# ─────────────────────────────────────────────────────────────────────────────
step "8. Log rotation"
# This droplet's disk is shared. Laravel's `daily` channel prunes its own files,
# but the systemd-appended service logs grow without limit, so they get a
# logrotate entry of their own.
install_root_file "$(cat <<ROTATE
# MetaCreator.Dev service logs. Written by deploy/scripts/provision.sh.
${SHARED_DIR}/api/storage/logs/*.log {
    daily
    rotate 14
    missingok
    notifempty
    compress
    delaycompress
    copytruncate
    su ${APP_USER} ${APP_GROUP}
}
ROTATE
)" "/etc/logrotate.d/${APP_NAME}" 0644 || true

# ─────────────────────────────────────────────────────────────────────────────
echo
ok "Provisioning complete."
cat <<SUMMARY

  ${c_dim}Database${c_reset}    ${DB_NAME} / ${DB_USER}@localhost
               password stored on the droplet at ${DB_PASSWORD_FILE}
               read it with: deploy/scripts/remote.sh db-password

  ${c_dim}Next step${c_reset}   deploy/scripts/deploy.sh

SUMMARY
