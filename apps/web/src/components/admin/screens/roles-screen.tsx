"use client";

import { Lock, Plus, Save, ShieldAlert, Trash2, Users } from "lucide-react";
import * as React from "react";

import { AdminPageHeader, AdminPanel, StatusPill } from "@/components/admin/admin-page";
import { Can } from "@/components/admin/can";
import { ConfirmDialog, Drawer, useToast } from "@/components/admin/feedback";
import { LoadError } from "@/components/admin/load-error";
import { Button } from "@/components/ui/button";
import { Field, Input } from "@/components/ui/field";
import { adminApi } from "@/lib/admin/api";
import type { AdminRole } from "@/lib/admin/types";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import { cn } from "@/lib/utils";

/**
 * Where "some editors can view only, some can edit but not delete" stops being a
 * feature request and becomes a checkbox.
 *
 * The editor is deliberately a grid of every declared permission grouped by
 * resource, not a curated set of presets. Presets are what force a deploy the first
 * time someone needs a combination nobody anticipated.
 */
export function RolesScreen() {
  const { notify, reportError } = useToast();

  const roles = useAdminResource(() => adminApi.roles.list(), []);
  const catalog = useAdminResource(() => adminApi.roles.permissions(), []);

  const [editing, setEditing] = React.useState<AdminRole | null>(null);
  const [creating, setCreating] = React.useState(false);
  const [deleting, setDeleting] = React.useState<AdminRole | null>(null);
  const [pending, setPending] = React.useState(false);

  if (roles.error) return <LoadError error={roles.error} onRetry={roles.reload} />;

  async function remove() {
    if (!deleting) return;

    setPending(true);
    const result = await adminApi.roles.remove(deleting.id);
    setPending(false);

    if (result.ok) {
      notify(`The ${deleting.name} role was deleted.`);
      setDeleting(null);
      roles.reload();
    } else {
      reportError(result.error);
    }
  }

  return (
    <>
      <AdminPageHeader
        eyebrow="People"
        title="Roles & permissions"
        description="A role is a set of permissions, nothing more. There is no hierarchy in the code — compose exactly the access a job needs."
        actions={
          <Can permission="roles.manage">
            <Button size="sm" onClick={() => setCreating(true)}>
              <Plus className="size-4" aria-hidden="true" />
              New role
            </Button>
          </Can>
        }
      />

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {(roles.data?.data ?? []).map((role) => (
          <article key={role.id} className="app-card flex flex-col p-4">
            <div className="flex items-start justify-between gap-2">
              <div className="min-w-0">
                <h2 className="flex items-center gap-1.5 text-sm font-semibold text-[var(--color-foreground)]">
                  {role.name}
                  {role.is_super_admin && (
                    <ShieldAlert
                      className="size-3.5 text-[var(--color-accent)]"
                      aria-label="Bypasses every check"
                    />
                  )}
                </h2>

                <p className="mt-1 text-xs leading-relaxed text-[var(--color-foreground-subtle)]">
                  {role.description ?? "Created in this admin."}
                </p>
              </div>

              {role.is_system && <StatusPill label="Built in" tone="muted" />}
            </div>

            <div className="mt-3 flex flex-wrap items-center gap-2 text-xs text-[var(--color-foreground-muted)]">
              <span className="inline-flex items-center gap-1">
                <Users className="size-3.5" aria-hidden="true" />
                {role.users_count ?? 0} {role.users_count === 1 ? "person" : "people"}
              </span>
              <span aria-hidden="true">·</span>
              <span className="tabular">{role.permissions.length} permissions</span>
            </div>

            <div className="mt-4 flex gap-2">
              <Can
                permission="roles.manage"
                fallback={
                  <Button variant="secondary" size="sm" onClick={() => setEditing(role)}>
                    View permissions
                  </Button>
                }
              >
                <Button
                  variant="secondary"
                  size="sm"
                  onClick={() => setEditing(role)}
                  disabled={role.is_super_admin}
                  title={
                    role.is_super_admin
                      ? "Super admin bypasses permission checks entirely; there is nothing to edit."
                      : undefined
                  }
                >
                  {role.is_super_admin ? (
                    <>
                      <Lock className="size-3.5" aria-hidden="true" />
                      Not editable
                    </>
                  ) : (
                    "Edit permissions"
                  )}
                </Button>

                {!role.is_system && (
                  <Button variant="ghost" size="sm" onClick={() => setDeleting(role)}>
                    <Trash2 className="size-3.5" aria-hidden="true" />
                    Delete
                  </Button>
                )}
              </Can>
            </div>
          </article>
        ))}

        {roles.loading &&
          !roles.data &&
          [0, 1, 2, 3, 4, 5].map((card) => (
            <div
              key={card}
              className="app-card h-40 animate-pulse"
              aria-hidden="true"
            />
          ))}
      </div>

      <AdminPanel
        title="Why an admin cannot promote itself"
        className="mt-6"
      >
        <p className="max-w-3xl text-sm leading-relaxed text-[var(--color-foreground-muted)]">
          The <code className="font-mono text-xs">admin</code> role holds everything
          except four permissions:{" "}
          {(catalog.data?.admin_exclusions ?? []).map((permission, index) => (
            <React.Fragment key={permission}>
              {index > 0 && ", "}
              <code className="font-mono text-xs text-[var(--color-foreground)]">
                {permission}
              </code>
            </React.Fragment>
          ))}
          . That separation is what keeps <code className="font-mono text-xs">super-admin</code>{" "}
          meaningful — an administrator can run the platform day to day without being
          able to grant itself more power, read provider secrets, or sign in as a
          customer.
        </p>
      </AdminPanel>

      {editing && catalog.data && (
        <RoleEditor
          role={editing}
          catalog={catalog.data.resources}
          onClose={() => setEditing(null)}
          onSaved={() => {
            setEditing(null);
            roles.reload();
          }}
        />
      )}

      {creating && catalog.data && (
        <RoleEditor
          catalog={catalog.data.resources}
          onClose={() => setCreating(false)}
          onSaved={() => {
            setCreating(false);
            roles.reload();
          }}
        />
      )}

      <ConfirmDialog
        open={deleting !== null}
        title={`Delete the ${deleting?.name} role?`}
        description="Anyone holding it loses that access immediately. A role that is still assigned to someone cannot be deleted — move those people first."
        confirmLabel="Delete role"
        destructive
        pending={pending}
        onConfirm={() => void remove()}
        onCancel={() => setDeleting(null)}
      />
    </>
  );
}

function RoleEditor({
  role,
  catalog,
  onClose,
  onSaved,
}: {
  role?: AdminRole;
  catalog: { resource: string; label: string; permissions: { name: string; action: string }[] }[];
  onClose: () => void;
  onSaved: () => void;
}) {
  const { notify, reportError } = useToast();

  const [name, setName] = React.useState(role?.name ?? "");
  const [selected, setSelected] = React.useState<string[]>(role?.permissions ?? []);
  const [saving, setSaving] = React.useState(false);
  const [fieldError, setFieldError] = React.useState<string | undefined>();

  function toggle(permission: string) {
    setSelected((current) =>
      current.includes(permission)
        ? current.filter((entry) => entry !== permission)
        : [...current, permission],
    );
  }

  function toggleResource(resource: { permissions: { name: string }[] }) {
    const names = resource.permissions.map((permission) => permission.name);
    const all = names.every((permission) => selected.includes(permission));

    setSelected((current) =>
      all
        ? current.filter((permission) => !names.includes(permission))
        : [...new Set([...current, ...names])],
    );
  }

  async function save() {
    setSaving(true);
    setFieldError(undefined);

    const result = role
      ? await adminApi.roles.update(role.id, selected)
      : await adminApi.roles.create({ name, permissions: selected });

    setSaving(false);

    if (result.ok) {
      notify(role ? `Permissions updated for ${role.name}.` : `The ${name} role was created.`);
      onSaved();
    } else {
      setFieldError(result.error.fieldErrors?.name?.[0]);
      reportError(result.error);
    }
  }

  return (
    <Drawer
      open
      title={role ? `Edit ${role.name}` : "New role"}
      description={
        role
          ? `${selected.length} of ${catalog.reduce((sum, entry) => sum + entry.permissions.length, 0)} permissions`
          : "Give it a name, then tick exactly what the job needs"
      }
      onClose={onClose}
      footer={
        <>
          <Button variant="secondary" size="sm" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button size="sm" onClick={() => void save()} loading={saving}>
            <Save className="size-4" aria-hidden="true" />
            {role ? "Save permissions" : "Create role"}
          </Button>
        </>
      }
    >
      {!role && (
        <Field
          id="role-name"
          label="Role name"
          hint="Lowercase words separated by hyphens, e.g. editor-restricted. A role cannot be renamed later — code and audit history reference it by name."
          error={fieldError}
          required
          className="mb-5"
        >
          {(props) => (
            <Input
              {...props}
              value={name}
              onChange={(event) => setName(event.target.value)}
              placeholder="editor-restricted"
              autoFocus
            />
          )}
        </Field>
      )}

      <div className="flex flex-col gap-4">
        {catalog.map((resource) => {
          const names = resource.permissions.map((permission) => permission.name);
          const all = names.every((permission) => selected.includes(permission));
          const some = !all && names.some((permission) => selected.includes(permission));

          return (
            <fieldset key={resource.resource}>
              <legend className="mb-1.5 flex w-full items-center justify-between gap-2">
                <span className="font-mono text-[0.625rem] font-medium uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
                  {resource.label}
                </span>

                <button
                  type="button"
                  onClick={() => toggleResource(resource)}
                  className="text-xs text-[var(--color-primary)] underline-offset-4 hover:underline"
                >
                  {all ? "None" : "All"}
                </button>
              </legend>

              <div
                className={cn(
                  "flex flex-wrap gap-1.5 rounded-[var(--radius-md)] border p-2",
                  some || all
                    ? "border-[var(--color-primary)]/30 bg-[var(--color-primary-subtle)]/30"
                    : "border-[var(--color-border-subtle)]",
                )}
              >
                {resource.permissions.map((permission) => {
                  const held = selected.includes(permission.name);

                  return (
                    <label
                      key={permission.name}
                      className={cn(
                        "flex cursor-pointer items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs transition-colors",
                        held
                          ? "border-[var(--color-primary)] bg-[var(--color-primary)] text-[var(--color-primary-foreground)]"
                          : "border-[var(--color-border)] text-[var(--color-foreground-muted)] hover:border-[var(--color-border-strong)]",
                      )}
                    >
                      <input
                        type="checkbox"
                        checked={held}
                        onChange={() => toggle(permission.name)}
                        className="sr-only"
                      />
                      {permission.action}
                    </label>
                  );
                })}
              </div>
            </fieldset>
          );
        })}
      </div>
    </Drawer>
  );
}
