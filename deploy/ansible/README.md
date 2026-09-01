> [!CAUTION]
> **SUPERSEDED — DO NOT RUN THESE PLAYBOOKS AGAINST 164.92.65.201.**
>
> These playbooks were written for a dedicated host. The actual production
> droplet is shared with ten unrelated websites, and running `provision.yml`
> there would take all of them down:
>
> | Role | What it would do to the shared droplet |
> | --- | --- |
> | `caddy` | Installs Caddy and binds ports 80 and 443 — which nginx already owns. Caddy fails to start, or nginx is stopped to make room. Either way every site goes dark. |
> | `mysql` | Rewrites `my.cnf` and restarts MySQL, interrupting nine other databases. |
> | `redis` | Overwrites `redis.conf` and restarts Redis, flushing state for every app using it. |
> | `php` | Rewrites the shared `www` pool rather than adding a dedicated one. |
> | `security` | Applies a UFW ruleset that does not know about the other sites' needs. |
> | `common` | Expects a `deploy` user that does not exist on this host. |
>
> The live deployment uses **`deploy/scripts/`** instead, which adds only new,
> namespaced resources and never edits shared configuration. Start at
> [`deploy/README.md`](../README.md).
>
> This directory is kept for reference, and would be the right starting point if
> MetaCreator ever moves to a droplet of its own.

---

# Ansible deployment

Provisioning and deploys for MetaCreator.Dev. Everything here is idempotent —
running a playbook twice changes nothing the second time.

## Prerequisites

```bash
pip install ansible
ansible-galaxy collection install community.general community.mysql ansible.posix
```

You also need SSH access as the `deploy` user and the ansible-vault password.

## First-time setup

```bash
cp group_vars/all/vault.yml.example group_vars/all/vault.yml
$EDITOR group_vars/all/vault.yml          # fill in every REPLACE_ME
ansible-vault encrypt group_vars/all/vault.yml

make provision ENV=production
```

## Everyday use

```bash
make deploy ENV=staging REF=main
make deploy ENV=production REF=v1.4.0
make rollback ENV=production
make rollback ENV=production TO=20260824142300
```

## Before you touch anything

- **Migrations must be backwards compatible.** A rollback swaps a symlink; it does
  not reverse migrations. Follow the expand/contract rule in `docs/04-data-model.md`.
- **The scheduler runs on one host only** (`is_primary: true`). A second would
  double-send every email.
- **Never commit `vault.yml`.** It is git-ignored; keep the vault password in a
  password manager or the CI secret store.

Full detail, including the deploy sequence and the rollback procedure, is in
[`docs/20-deployment.md`](../../docs/20-deployment.md).
