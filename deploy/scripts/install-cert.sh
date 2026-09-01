#!/usr/bin/env bash
#
# Installs the Cloudflare Origin CA certificate for metacreator.dev.
#
#   deploy/scripts/install-cert.sh <cert.pem> <key.pem>
#
# ── Where the two files come from ────────────────────────────────────────────
# Cloudflare dashboard → your domain → SSL/TLS → Origin Server →
# "Create Certificate":
#
#   * Let Cloudflare generate a private key and CSR (the default)
#   * Hostnames: metacreator.dev, *.metacreator.dev
#   * Key format: PEM
#   * Validity: 15 years
#
# Cloudflare then shows two boxes ONCE. Save each to a file on this Mac:
#   "Origin Certificate" → origin.pem
#   "Private Key"        → origin.key      ← shown only at creation time
#
# Then, still in SSL/TLS → Overview, set the encryption mode to
# **Full (strict)**. Anything less and Cloudflare will accept a forged or
# expired origin certificate, which defeats the point of installing one.
#
# ── Why an origin certificate and not Let's Encrypt ──────────────────────────
# The domain is proxied, so this certificate is only ever presented to
# Cloudflare — never to a browser. It does not need to be publicly trusted, it
# lasts fifteen years instead of ninety days, and there is no renewal job that
# can quietly fail. This is also what the atxtopeatery site on this droplet
# already does, so the host stays consistent.

set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/../config.sh"

# ── Placeholder mode ─────────────────────────────────────────────────────────
# `--self-signed` generates a throwaway certificate so the vhost can be
# provisioned, deployed and verified before you have been to the Cloudflare
# dashboard. It is NOT a production certificate.
#
# It works today only if the zone's SSL mode is "Flexible" or "Full". Under
# "Full (strict)" — which is what you actually want — Cloudflare validates the
# origin certificate and will serve a 526 error to visitors. So: use this to get
# the stack up, then run this script again with the real Cloudflare files and
# switch the zone to Full (strict). The swap needs no redeploy, just an
# nginx reload.
if [[ "${1:-}" == "--self-signed" ]]; then
    step "Generating a throwaway self-signed certificate"
    warn "This is a PLACEHOLDER. Replace it with a Cloudflare Origin certificate before launch."
    tmpdir="$(mktemp -d)"
    trap 'rm -rf "${tmpdir}"' EXIT
    openssl req -x509 -nodes -newkey rsa:2048 -days 3650 \
        -keyout "${tmpdir}/origin.key" -out "${tmpdir}/origin.pem" \
        -subj "/CN=${DOMAIN}/O=MetaCreator placeholder" \
        -addext "subjectAltName=DNS:${DOMAIN},DNS:${WWW_DOMAIN}" 2>/dev/null
    ok "generated for ${DOMAIN} and ${WWW_DOMAIN}"
    set -- "${tmpdir}/origin.pem" "${tmpdir}/origin.key"
fi

CERT_SRC="${1:-}"
KEY_SRC="${2:-}"

if [[ -z "${CERT_SRC}" || -z "${KEY_SRC}" ]]; then
    cat >&2 <<USAGE
usage: deploy/scripts/install-cert.sh <origin.pem> <origin.key>
       deploy/scripts/install-cert.sh --self-signed    # throwaway placeholder

Get both files from the Cloudflare dashboard first — see the comment block at
the top of this script for the exact steps.
USAGE
    exit 64
fi

[[ -f "${CERT_SRC}" ]] || die "no such file: ${CERT_SRC}"
[[ -f "${KEY_SRC}"  ]] || die "no such file: ${KEY_SRC}"

step "Validating the pair locally before it touches the server"
grep -q 'BEGIN CERTIFICATE'  "${CERT_SRC}" || die "${CERT_SRC} does not look like a PEM certificate"
grep -q 'BEGIN.*PRIVATE KEY' "${KEY_SRC}"  || die "${KEY_SRC} does not look like a PEM private key"

# A mismatched cert/key pair makes nginx fail to start. Since nginx serves ten
# other sites, a failed start is an outage for all of them — so the pair is
# verified here, on this Mac, before anything is uploaded.
cert_mod="$(openssl x509 -noout -modulus -in "${CERT_SRC}" | openssl md5)"
key_mod="$(openssl rsa  -noout -modulus -in "${KEY_SRC}"  2>/dev/null | openssl md5 || true)"
if [[ -z "${key_mod}" ]]; then
    # Cloudflare can issue ECC keys, for which -modulus does not apply.
    key_mod="$(openssl ec -noout -text -in "${KEY_SRC}" >/dev/null 2>&1 && echo "ec-ok" || true)"
    [[ -n "${key_mod}" ]] || die "could not read ${KEY_SRC} as an RSA or EC private key"
    warn "EC key — skipping modulus comparison, nginx will be the final check"
elif [[ "${cert_mod}" != "${key_mod}" ]]; then
    die "certificate and key DO NOT MATCH. Re-download both from Cloudflare."
else
    ok "certificate and key match"
fi

subject="$(openssl x509 -in "${CERT_SRC}" -noout -subject -issuer -dates)"
printf '%s%s%s\n' "$c_dim" "${subject}" "$c_reset"

step "Installing to ${SSL_DIR}"
remote_sudo "install -d -m 0755 -o root -g root ${SSL_DIR}"

# Written through a root-owned temp file rather than scp'd directly, because the
# deploy user cannot write to /etc/ssl.
scp -q "${CERT_SRC}" "${SSH_TARGET}:/tmp/.mc-origin.pem"
scp -q "${KEY_SRC}"  "${SSH_TARGET}:/tmp/.mc-origin.key"
remote_sudo "install -m 0644 -o root -g root /tmp/.mc-origin.pem ${SSL_CERT}"
remote_sudo "install -m 0600 -o root -g root /tmp/.mc-origin.key ${SSL_KEY}"
remote "rm -f /tmp/.mc-origin.pem /tmp/.mc-origin.key"

ok "certificate installed at ${SSL_CERT} (0644)"
ok "private key installed at ${SSL_KEY} (0600, root only)"

echo
ok "Done. Next: deploy/scripts/provision.sh"
