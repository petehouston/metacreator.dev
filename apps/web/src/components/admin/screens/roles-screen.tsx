"use client";

import { Lock, Plus, ShieldAlert, Trash2, Users } from "lucide-react";
import Link from "next/link";
import * as React from "react";

import { AdminPageHeader, AdminPanel, StatusPill } from "@/components/admin/admin-page";
import { Can } from "@/components/admin/can";
import { ConfirmDialog, useToast } from "@/components/admin/feedback";
import { LoadError } from "@/components/admin/load-error";
import { Button } from "@/components/ui/button";
import { adminApi } from "@/lib/admin/api";
import type { AdminRole } from "@/lib/admin/types";
import { useAdminResource } from "@/lib/admin/use-admin-resource";

/**
 * Where "some editors can view only, some can edit but not delete" stops being a
 * feature request and becomes a checkbox.
 *
 * The cards are the index; composing a role happens on its own page, where the
 * full grid of declared permissions has room to be read.
 */
export function RolesScreen() {
  const { notify, reportError } = useToast();

  const roles = useAdminResource(() => adminApi.roles.list(), []);
  const catalog = useAdminResource(() => adminApi.roles.permissions(), []);

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
            <Button size="sm" asChild>
              <Link href="/c0ns0le/roles/new">
                <Plus className="size-4" aria-hidden="true" />
                New role
              </Link>
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
                  <Button variant="secondary" size="sm" asChild>
                    <Link href={`/c0ns0le/roles/${role.id}`}>View permissions</Link>
                  </Button>
                }
              >
                {role.is_super_admin ? (
                  <Button
                    variant="secondary"
                    size="sm"
                    disabled
                    title="Super admin bypasses permission checks entirely; there is nothing to edit."
                  >
                    <Lock className="size-3.5" aria-hidden="true" />
                    Not editable
                  </Button>
                ) : (
                  <Button variant="secondary" size="sm" asChild>
                    <Link href={`/c0ns0le/roles/${role.id}`}>Edit permissions</Link>
                  </Button>
                )}

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
