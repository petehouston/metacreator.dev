#!/usr/bin/env bash
#
# Run `php artisan` on the droplet, from this Mac.
#
#   deploy/scripts/artisan.sh migrate:status
#   deploy/scripts/artisan.sh tinker
#   deploy/scripts/artisan.sh horizon:status
#   deploy/scripts/artisan.sh "user:promote pete@example.com --role=super-admin"
#
# Runs inside the LIVE release directory, so it sees production's .env, database
# and Redis. Interactive commands (tinker, anything that prompts) work: the SSH
# session is allocated a TTY whenever this script is run from a terminal.
#
# ── Commands that are refused ────────────────────────────────────────────────
# A short list of artisan commands would destroy production data, and a typo in
# a terminal is all it takes. They are blocked here and can be forced past with
# ALLOW_DANGEROUS=1 if you genuinely mean it. `migrate:fresh` on a live database
# is not a thing you do by accident twice.

set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/../config.sh"

[[ $# -gt 0 ]] || { sed -n '2,12p' "${BASH_SOURCE[0]}"; exit 64; }

CMD="$*"

# ── Guard rails ──────────────────────────────────────────────────────────────
DANGEROUS='migrate:fresh|migrate:reset|migrate:rollback|db:wipe|cache:forget|schema:dump'
if [[ "${CMD}" =~ ${DANGEROUS} ]] && [[ "${ALLOW_DANGEROUS:-0}" != "1" ]]; then
    cat >&2 <<WARNING
${c_red}✗ Refusing to run a destructive command against production.${c_reset}

    ${CMD}

  This would drop or rewind live data, and a symlink rollback cannot undo it.

  If you are certain, take a backup first and then force it:

    deploy/scripts/remote.sh backup-db
    ALLOW_DANGEROUS=1 deploy/scripts/artisan.sh ${CMD}
WARNING
    exit 1
fi

# `db:seed` is not destructive by definition, but this app's seeders create demo
# content, and on production that is indistinguishable from someone's real data.
if [[ "${CMD}" =~ ^db:seed ]] && [[ "${ALLOW_DANGEROUS:-0}" != "1" ]]; then
    die "db:seed writes demo content into production. Force with ALLOW_DANGEROUS=1 if you mean it."
fi

# ── Run ──────────────────────────────────────────────────────────────────────
# -t allocates a TTY so tinker, prompts and progress bars behave. Only when this
# script itself has one, so CI and scripted use stay clean.
ssh_flags=(-o BatchMode=yes)
[[ -t 0 && -t 1 ]] && ssh_flags=(-t)

printf '%s==>%s artisan %s%s%s  %son %s%s\n' \
    "$c_blue" "$c_reset" "$c_green" "${CMD}" "$c_reset" "$c_dim" "${SSH_HOST}" "$c_reset"

exec ssh "${ssh_flags[@]}" "${SSH_TARGET}" \
    "cd ${CURRENT_LINK}/api && ${PHP_BIN} artisan ${CMD}"
