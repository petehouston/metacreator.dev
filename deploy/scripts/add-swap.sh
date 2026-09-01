#!/usr/bin/env bash
#
# Add a 2 GB swap file to the droplet. OPTIONAL, and safe to skip.
#
#   deploy/scripts/add-swap.sh
#
# ── Why you might want this ──────────────────────────────────────────────────
# This droplet has 3.9 GB of RAM, no swap, and eleven sites once MetaCreator is
# on it. `next build` is by far the most memory-hungry thing that ever runs
# here. With no swap, a process that asks for memory the kernel cannot find
# triggers the OOM killer — and the OOM killer chooses its victim by a heuristic
# score, not by who asked. It can kill MySQL, or another site's Next.js server.
#
# Swap does not make the build faster. What it does is turn "the kernel kills a
# random production process" into "the build gets slow", which is a much better
# failure mode on a shared box.
#
# deploy.sh already caps Node's heap and refuses to start when memory is tight,
# so this is a second layer, not a requirement.
#
# ── What it changes ──────────────────────────────────────────────────────────
# Creates /swapfile, enables it, adds one line to /etc/fstab so it survives a
# reboot, and sets vm.swappiness=10 (use swap only when genuinely short, so the
# other sites are not paged out during normal operation). It starts no service
# and restarts nothing — existing processes are unaffected.

set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/../config.sh"

SWAP_SIZE_GB=2

log "Adding ${SWAP_SIZE_GB} GB of swap to ${SSH_HOST}"

if remote "swapon --show | grep -q ."; then
    ok "swap is already configured:"
    remote "swapon --show"
    exit 0
fi

avail_gb="$(remote "df -BG --output=avail / | tail -1 | tr -dc '0-9'")"
[[ "${avail_gb}" -gt $((SWAP_SIZE_GB + 10)) ]] \
    || die "only ${avail_gb} GB free on / — not enough to add ${SWAP_SIZE_GB} GB of swap safely"
ok "${avail_gb} GB free on /"

step "Creating /swapfile"
# fallocate is instant; dd would write 2 GB and thrash the disk the other sites
# are reading from.
remote_sudo "fallocate -l ${SWAP_SIZE_GB}G /swapfile"
remote_sudo "chmod 600 /swapfile"
remote_sudo "mkswap /swapfile" 2>&1 | tail -2
remote_sudo "swapon /swapfile"
ok "swap enabled"

step "Persisting across reboots"
if remote "grep -q '^/swapfile' /etc/fstab"; then
    ok "/etc/fstab already has the entry"
else
    remote_sudo "cp /etc/fstab /etc/fstab.bak-$(date -u +%Y%m%d)"
    remote "echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab >/dev/null"
    ok "added to /etc/fstab (backup saved alongside it)"
fi

step "Swappiness"
# 10 = prefer to reclaim page cache before swapping anything out, so the running
# sites stay resident and swap is reserved for genuine pressure.
remote "echo 'vm.swappiness=10' | sudo tee /etc/sysctl.d/99-${APP_NAME}-swap.conf >/dev/null"
remote_sudo "sysctl -q vm.swappiness=10"
ok "vm.swappiness=10"

echo
remote "free -h"
ok "Done. No service was restarted; nothing else on the droplet changed."
