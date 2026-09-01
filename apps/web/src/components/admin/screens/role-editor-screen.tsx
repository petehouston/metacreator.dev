"use client";

import { ArrowLeft, Save } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import * as React from "react";

import { AdminPageHeader, AdminPanel } from "@/components/admin/admin-page";
import { useCan } from "@/components/admin/can";
import { useToast } from "@/components/admin/feedback";
import { LoadError } from "@/components/admin/load-error";
import { Button } from "@/components/ui/button";
import { Field, Input } from "@/components/ui/field";
import { adminApi } from "@/lib/admin/api";
import type { AdminRole, PermissionCatalogResource } from "@/lib/admin/types";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import { cn } from "@/lib/utils";

/**
 * Compose a role, on its own page.
 *
 * The editor is deliberately a grid of every declared permission grouped by
 * resource, not a curated set of presets — presets are what force a deploy the
 * first time somebody needs a combination nobody anticipated. That grid is also
 * why this is a page: a hundred-odd checkboxes read badly in a column half the
 * width of the screen, and `/c0ns0le/roles/3` is a link a colleague can be sent.
 */
export function RoleEditorScreen({ id }: { id?: number }) {
  const isNew = id === undefined;

  // Roles are returned as one unpaginated list, so the row is picked out of it
  // rather than fetched on its own — one request either way, and the list is the
  // request the screen would already be making.
  const roles = useAdminResource(() => adminApi.roles.list(), []);
  const catalog = useAdminResource(() => adminApi.roles.permissions(), []);

  if (roles.error) return <LoadError error={roles.error} onRetry={roles.reload} />;
  if (catalog.error) return <LoadError error={catalog.error} onRetry={catalog.reload} />;

  if (!catalog.data || (!isNew && !roles.data)) {
    return (
      <div
        className="h-96 animate-pulse rounded-[var(--radius-lg)] bg-[var(--color-surface-sunken)]"
        aria-hidden="true"
      />
    );
  }

  const role = isNew ? undefined : roles.data?.data.find((entry) => entry.id === id);

  if (!isNew && !role) {
    return (
      <>
        <AdminPageHeader
          eyebrow="People · Roles"
          title="No such role"
          description="It may have been deleted since this link was made."
          actions={
            <Button variant="secondary" size="sm" asChild>
              <Link href="/c0ns0le/roles">
                <ArrowLeft className="size-4" aria-hidden="true" />
                Back to roles
              </Link>
            </Button>
          }
        />
      </>
    );
  }

  // Keyed on the loaded role so the form state is built once the values exist.
  return <RoleForm key={role?.id ?? "new"} role={role} catalog={catalog.data.resources} />;
}

function RoleForm({ role, catalog }: { role?: AdminRole; catalog: PermissionCatalogResource[] }) {
  const router = useRouter();
  const can = useCan();
  const { notify, reportError } = useToast();

  // `roles.view_any` gets somebody onto this screen; only `roles.manage` may
  // change what it shows. Reading a role's permissions is a legitimate reason to
  // be here — answering "why can this person do that?" — so the screen renders
  // rather than refusing, with every control inert.
  const manageable = can("roles.manage");

  const [name, setName] = React.useState(role?.name ?? "");
  const [selected, setSelected] = React.useState<string[]>(role?.permissions ?? []);
  const [saving, setSaving] = React.useState(false);
  const [fieldError, setFieldError] = React.useState<string | undefined>();

  const total = catalog.reduce((sum, entry) => sum + entry.permissions.length, 0);

  function toggle(permission: string) {
    setSelected((current) =>
      current.includes(permission)
        ? current.filter((entry) => entry !== permission)
        : [...current, permission],
    );
  }

  function toggleResource(resource: PermissionCatalogResource) {
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
      router.push("/c0ns0le/roles");
    } else {
      setFieldError(result.error.fieldErrors?.name?.[0]);
      reportError(result.error);
    }
  }

  return (
    <>
      <AdminPageHeader
        eyebrow="People · Roles"
        title={role ? `Edit ${role.name}` : "New role"}
        description={
          role
            ? `${selected.length} of ${total} permissions. A role is a set of permissions, nothing more — there is no hierarchy behind it.`
            : "Give it a name, then tick exactly what the job needs. Nothing is implied by anything else."
        }
        actions={
          <>
            <Button variant="secondary" size="sm" asChild>
              <Link href="/c0ns0le/roles">
                <ArrowLeft className="size-4" aria-hidden="true" />
                Back to roles
              </Link>
            </Button>

            {manageable && (
              <Button
                size="sm"
                onClick={() => void save()}
                loading={saving}
                disabled={!role && name.trim() === ""}
              >
                <Save className="size-4" aria-hidden="true" />
                {role ? "Save permissions" : "Create role"}
              </Button>
            )}
          </>
        }
      />

      <div className="flex flex-col gap-5">
        {!role && (
          <AdminPanel title="Identity" description="Fixed once the role exists">
            <Field
              id="role-name"
              label="Role name"
              hint="Lowercase words separated by hyphens, e.g. editor-restricted. A role cannot be renamed later — code and audit history reference it by name."
              error={fieldError}
              required
              className="max-w-md"
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
          </AdminPanel>
        )}

        <AdminPanel
          title="Permissions"
          description={`${selected.length} of ${total} selected`}
        >
          <fieldset disabled={!manageable} className="grid gap-4 lg:grid-cols-2">
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
          </fieldset>
        </AdminPanel>
      </div>
    </>
  );
}
