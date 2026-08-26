#!/bin/sh
# Prepares the bind-mounted Laravel app for local development, then execs the
# service command.
#
# api, worker and scheduler all share this image and start at the same time, so
# the shared setup runs under a lock: the first container in does the work, the
# others wait for vendor/ to appear instead of racing composer.
set -e

cd /app

LOCK=/app/storage/.dev-setup.lock

prepare() {
    if [ ! -f vendor/autoload.php ]; then
        echo "▸ vendor/ is empty — running composer install"
        composer install --no-interaction --prefer-dist
    fi

    if [ ! -f .env ]; then
        echo "▸ .env missing — copying .env.example"
        cp .env.example .env
    fi

    if ! grep -qE '^APP_KEY=base64:' .env; then
        echo "▸ Generating APP_KEY"
        php artisan key:generate --force
    fi

    sync_env
}

# apps/api/.env is bind-mounted, and a developer running the app natively on the
# host needs 127.0.0.1 there while the container needs the compose service names
# (mysql, redis, mailpit, minio). `php artisan serve` re-reads that file and
# feeds it to the request handler, so container env vars alone are not enough:
# the file itself has to agree. Rewrite exactly the keys compose owns and leave
# every other line — secrets, API keys, local overrides — untouched.
sync_env() {
    # Keep one copy of whatever was here first. A developer who had pointed this
    # file at 127.0.0.1 for a host-native run can get those values back from
    # .env.host.bak instead of rebuilding them from memory.
    [ -f .env.host.bak ] || cp .env .env.host.bak

    for key in DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD \
               REDIS_HOST REDIS_PORT CACHE_STORE SESSION_DRIVER QUEUE_CONNECTION \
               MAIL_MAILER MAIL_HOST MAIL_PORT COMPUTE_URL \
               AWS_ENDPOINT AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY AWS_BUCKET \
               AWS_USE_PATH_STYLE_ENDPOINT; do
        # Only keys compose actually set; anything unset stays as the file has it.
        value=$(printenv "$key" || true)
        [ -n "$value" ] || continue

        if grep -qE "^${key}=" .env; then
            # `#` as the delimiter: values contain URLs with slashes.
            sed -i "s#^${key}=.*#${key}=${value}#" .env
        else
            printf '%s=%s\n' "$key" "$value" >> .env
        fi
    done
    echo "▸ Synced container service addresses into .env"
}

mkdir -p storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/logs bootstrap/cache

if mkdir "$LOCK" 2>/dev/null; then
    trap 'rmdir "$LOCK" 2>/dev/null || true' EXIT INT TERM
    prepare
    rmdir "$LOCK" 2>/dev/null || true
    trap - EXIT INT TERM
else
    echo "▸ Waiting for another container to finish dependency setup"
    i=0
    while [ -d "$LOCK" ] && [ "$i" -lt 300 ]; do
        sleep 2
        i=$((i + 2))
    done
    [ -f vendor/autoload.php ] || { echo "vendor/ still missing after wait"; exit 1; }
fi

# The api container owns the schema: one place runs migrations so workers never
# race them. RUN_MIGRATIONS is set on that service only.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "▸ Running migrations"
    php artisan migrate --force

    # Seed once per volume. Re-running seeders on every boot would duplicate
    # demo content, so a marker records that the first pass already happened.
    if [ ! -f storage/.seeded ]; then
        echo "▸ Seeding the database"
        php artisan db:seed --force && touch storage/.seeded
    fi
fi

exec "$@"
