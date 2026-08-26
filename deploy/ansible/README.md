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
