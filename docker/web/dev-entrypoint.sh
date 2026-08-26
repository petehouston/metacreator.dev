#!/bin/sh
# node_modules lives in a named volume, so it starts empty on a fresh checkout.
# Install into it before handing over to `next dev`.
set -e

cd /app

# package-lock.json is the source of truth; re-install when it is newer than the
# last install so a dependency bump does not need a manual `npm ci`.
if [ ! -d node_modules/next ] || [ package-lock.json -nt node_modules/.install-stamp ]; then
    echo "▸ Installing web dependencies"
    npm ci --no-audit --no-fund
    touch node_modules/.install-stamp
fi

if [ ! -f .env.local ] && [ -f .env.example ]; then
    echo "▸ .env.local missing — copying .env.example"
    cp .env.example .env.local
fi

exec "$@"
