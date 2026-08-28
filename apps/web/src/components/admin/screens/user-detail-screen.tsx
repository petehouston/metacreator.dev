"use client";

import {
  ArrowLeft,
  Ban,
  CheckCircle2,
  Mail,
  Shield,
  Sparkles,
  Trash2,
} from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import * as React from "react";

import { AdminPageHeader, AdminPanel, StatusPill } from "@/components/admin/admin-page";
import { Can } from "@/components/admin/can";
import { ConfirmDialog, useToast } from "@/components/admin/feedback";
import { LoadError } from "@/components/admin/load-error";
import { humanise, tone } from "@/components/admin/status-tone";
import { useSession } from "@/components/auth/session-provider";
import { Button } from "@/components/ui/button";
import { adminApi } from "@/lib/admin/api";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import { formatDate, formatMoney, formatNumber, relativeTime } from "@/lib/utils";

/**
 * Everything about one person, on one screen.
 *
 * Built around what a support agent needs mid-conversation: who they are, what they
 * are paying for, what they have been given, and the two buttons that resolve most
 * complaints. Their email is displayed prominently and is not editable anywhere —
 * it is the account's identity, and a transfer is a separate audited action.
 */
export function UserDetailScreen({ id }: { id: string }) {
  const router = useRouter();
  const { notify, reportError } = useToast();
  const { user: actor } = useSession();

  const { data, error, loading, reload } = useAdminResource(() => adminApi.users.get(id), [id]);

  // Always requested. Someone without `roles.view_any` simply gets a 403 the hook
  // stores and this screen ignores — the role panel falls back to read-only, which
  // is the same outcome as branching on the permission, with one code path.
  const roles = useAdminResource(() => adminApi.roles.list(), []);

  const [confirm, setConfirm] = React.useState<"suspend" | "delete" | null>(null);
  const [pending, setPending] = React.useState(false);

  if (error) return <LoadError error={error} onRetry={reload} />;

  if (loading && !data) {
    return (
      <div className="grid gap-4 lg:grid-cols-3">
        <div className="h-64 animate-pulse rounded-[var(--radius-lg)] bg-[var(--color-surface-sunken)] lg:col-span-2" />
        <div className="h-64 animate-pulse rounded-[var(--radius-lg)] bg-[var(--color-surface-sunken)]" />
      </div>
    );
  }

  if (!data) return null;

  const suspended = data.status === "suspended";
  const isSelf = actor?.id === data.id;

  async function toggleSuspension() {
    if (!data) return;

    setPending(true);
    const result = await adminApi.users.suspend(data.id);
    setPending(false);
    setConfirm(null);

    if (result.ok) {
      notify(
        result.data.status === "suspended"
          ? `${data.display_name} is suspended and can no longer sign in.`
          : `${data.display_name} is active again.`,
      );
      reload();
    } else {
      reportError(result.error);
    }
  }

  async function remove() {
    if (!data) return;

    setPending(true);
    const result = await adminApi.users.remove(data.id);
    setPending(false);
    setConfirm(null);

    if (result.ok) {
      notify(`${data.display_name} was deleted.`);
      router.push("/admin/users");
    } else {
      reportError(result.error);
    }
  }

  async function setRoles(next: string[]) {
    if (!data) return;

    const result = await adminApi.users.setRoles(data.id, next);

    if (result.ok) {
      notify("Roles updated.");
      reload();
    } else {
      reportError(result.error);
    }
  }

  return (
    <>
      <Button asChild variant="ghost" size="sm" className="mb-3 -ml-2">
        <Link href="/admin/users">
          <ArrowLeft className="size-4" aria-hidden="true" />
          All users
        </Link>
      </Button>

      <AdminPageHeader
        eyebrow={data.is_staff ? "Staff account" : "Customer"}
        title={data.display_name}
        description={
          <span className="flex flex-wrap items-center gap-x-3 gap-y-1">
            <span className="inline-flex items-center gap-1.5">
              <Mail className="size-3.5" aria-hidden="true" />
              {data.email}
            </span>
            <span className="font-mono text-xs text-[var(--color-foreground-subtle)]">
              {data.id}
            </span>
          </span>
        }
        actions={
          <>
            <Can permission="users.suspend">
              <Button
                variant={suspended ? "secondary" : "danger"}
                size="sm"
                onClick={() => setConfirm("suspend")}
                disabled={isSelf}
                title={isSelf ? "You cannot suspend your own account" : undefined}
              >
                {suspended ? (
                  <>
                    <CheckCircle2 className="size-4" aria-hidden="true" />
                    Reinstate
                  </>
                ) : (
                  <>
                    <Ban className="size-4" aria-hidden="true" />
                    Suspend
                  </>
                )}
              </Button>
            </Can>

            <Can permission="users.delete">
              <Button
                variant="ghost"
                size="sm"
                onClick={() => setConfirm("delete")}
                disabled={isSelf}
              >
                <Trash2 className="size-4" aria-hidden="true" />
                Delete
              </Button>
            </Can>
          </>
        }
      />

      <div className="grid gap-4 lg:grid-cols-3">
        <div className="flex flex-col gap-4 lg:col-span-2">
          <AdminPanel title="Account" description="What this person is, right now">
            <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2">
              <Fact label="Status">
                <StatusPill label={humanise(data.status)} tone={tone.user(data.status)} />
              </Fact>
              <Fact label="Email verified">
                {data.email_verified ? "Yes" : <span className="text-[var(--color-warning)]">No</span>}
              </Fact>
              <Fact label="Sign-in method">
                {data.has_password ? "Password" : "Magic link only"}
              </Fact>
              <Fact label="Marketing opt-in">{data.marketing_opt_in ? "Yes" : "No"}</Fact>
              <Fact label="Timezone">{data.timezone}</Fact>
              <Fact label="Joined">{data.created_at ? formatDate(data.created_at) : "—"}</Fact>
              <Fact label="Last seen">
                {data.last_seen_at ? relativeTime(data.last_seen_at) : "Never"}
              </Fact>
              <Fact label="Tool runs">{formatNumber(data.runs_count ?? 0)}</Fact>
            </dl>

            {data.deletion_requested_at && (
              <p className="mt-4 rounded-[var(--radius-md)] border border-[var(--color-warning)]/30 bg-[var(--color-warning)]/8 px-3 py-2 text-sm text-[var(--color-foreground-muted)]">
                This person requested deletion {relativeTime(data.deletion_requested_at)}.
              </p>
            )}
          </AdminPanel>

          <Can permission="invoices.view_any">
            <AdminPanel
              title="Invoices"
              description="The twelve most recent"
              bodyClassName={data.invoices && data.invoices.length > 0 ? "p-0" : undefined}
            >
              {!data.invoices || data.invoices.length === 0 ? (
                <p className="py-4 text-center text-sm text-[var(--color-foreground-subtle)]">
                  No invoices. This account has never been billed.
                </p>
              ) : (
                <table className="w-full border-collapse text-sm">
                  <tbody>
                    {data.invoices.map((invoice) => (
                      <tr
                        key={invoice.number ?? invoice.issued_at}
                        className="border-b border-[var(--color-border-subtle)] last:border-b-0"
                      >
                        <td className="px-4 py-2.5 font-mono text-xs text-[var(--color-foreground-muted)]">
                          {invoice.number ?? "—"}
                        </td>
                        <td className="px-4 py-2.5">
                          <StatusPill
                            label={humanise(invoice.status)}
                            tone={tone.invoice(invoice.status)}
                          />
                        </td>
                        <td className="tabular px-4 py-2.5 text-right font-medium text-[var(--color-foreground)]">
                          {formatMoney(invoice.total, invoice.currency)}
                        </td>
                        <td className="px-4 py-2.5 text-right text-xs text-[var(--color-foreground-subtle)]">
                          {invoice.issued_at ? formatDate(invoice.issued_at) : "—"}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </AdminPanel>
          </Can>
        </div>

        <div className="flex flex-col gap-4">
          <AdminPanel title="Plan" description="What they are paying for">
            {data.subscription ? (
              <dl className="flex flex-col gap-3">
                <Fact label="Plan">{data.subscription.plan ?? "—"}</Fact>
                <Fact label="Status">
                  <StatusPill
                    label={humanise(data.subscription.status)}
                    tone={tone.subscription(data.subscription.status)}
                  />
                </Fact>
                {data.subscription.renews_at && (
                  <Fact label="Renews">{formatDate(data.subscription.renews_at)}</Fact>
                )}
                {data.subscription.cancels_at && (
                  <Fact label="Cancels">
                    <span className="text-[var(--color-warning)]">
                      {formatDate(data.subscription.cancels_at)}
                    </span>
                  </Fact>
                )}
              </dl>
            ) : (
              <p className="text-sm text-[var(--color-foreground-muted)]">
                On the free plan. No active subscription or pass.
              </p>
            )}
          </AdminPanel>

          <AdminPanel
            title="Comped tools"
            description="Access given by hand"
            action={
              <Can permission="tool_grants.create">
                <Button asChild variant="ghost" size="sm">
                  <Link href={`/admin/grants/new?user=${encodeURIComponent(data.email)}`}>
                    <Sparkles className="size-3.5" aria-hidden="true" />
                    Grant
                  </Link>
                </Button>
              </Can>
            }
          >
            {!data.grants || data.grants.length === 0 ? (
              <p className="text-sm text-[var(--color-foreground-muted)]">
                Nothing comped. Their access comes entirely from their plan.
              </p>
            ) : (
              <ul className="flex flex-col gap-2.5">
                {data.grants.map((grant) => (
                  <li key={grant.id} className="flex items-start gap-2">
                    <span className="min-w-0 flex-1">
                      <span className="block truncate text-sm font-medium text-[var(--color-foreground)]">
                        {grant.tool.name ?? grant.tool.slug}
                      </span>
                      {grant.reason && (
                        <span className="block truncate text-xs text-[var(--color-foreground-subtle)]">
                          {grant.reason}
                        </span>
                      )}
                    </span>
                    <StatusPill
                      label={
                        grant.is_active
                          ? grant.expires_at
                            ? `Until ${formatDate(grant.expires_at)}`
                            : "Permanent"
                          : "Expired"
                      }
                      tone={grant.is_active ? "success" : "muted"}
                    />
                  </li>
                ))}
              </ul>
            )}
          </AdminPanel>

          <Can
            permission="roles.manage"
            fallback={
              data.roles.length > 0 ? (
                <AdminPanel title="Roles" description="Read only — needs roles.manage to change">
                  <span className="flex flex-wrap gap-1.5">
                    {data.roles.map((name) => (
                      <StatusPill key={name} label={name} tone="info" />
                    ))}
                  </span>
                </AdminPanel>
              ) : null
            }
          >
            <AdminPanel
              title="Roles"
              description="Staff access. Changing this is audited."
              action={
                <Shield className="size-4 text-[var(--color-accent)]" aria-hidden="true" />
              }
            >
              <div className="flex flex-col gap-1.5">
                {(roles.data?.data ?? []).map((role) => {
                  const held = data.roles.includes(role.name);

                  return (
                    <label
                      key={role.id}
                      className="flex cursor-pointer items-start gap-2.5 rounded-[var(--radius-md)] px-1 py-1"
                    >
                      <input
                        type="checkbox"
                        checked={held}
                        onChange={() =>
                          void setRoles(
                            held
                              ? data.roles.filter((name) => name !== role.name)
                              : [...data.roles, role.name],
                          )
                        }
                        className="mt-0.5 size-4 shrink-0 rounded border-[var(--color-border-strong)] accent-[var(--color-primary)]"
                      />
                      <span className="flex min-w-0 flex-col">
                        <span className="text-sm text-[var(--color-foreground)]">{role.name}</span>
                        {role.description && (
                          <span className="text-xs leading-snug text-[var(--color-foreground-subtle)]">
                            {role.description}
                          </span>
                        )}
                      </span>
                    </label>
                  );
                })}
              </div>
            </AdminPanel>
          </Can>
        </div>
      </div>

      <ConfirmDialog
        open={confirm === "suspend"}
        title={suspended ? `Reinstate ${data.display_name}?` : `Suspend ${data.display_name}?`}
        description={
          suspended
            ? "They will be able to sign in again immediately. Their history and billing are unchanged."
            : "They will be signed out and unable to sign back in. Their history, invoices and subscription are kept, and this is reversible."
        }
        confirmLabel={suspended ? "Reinstate" : "Suspend"}
        destructive={!suspended}
        pending={pending}
        onConfirm={() => void toggleSuspension()}
        onCancel={() => setConfirm(null)}
      />

      <ConfirmDialog
        open={confirm === "delete"}
        title={`Delete ${data.display_name}?`}
        description={
          <>
            <p>
              The account is soft-deleted: invoices keep a payer and tool runs keep an
              owner, so financial and audit history stay intact.
            </p>
            <p className="mt-2">
              If you only need to stop them signing in, suspend instead — it is
              reversible from this screen.
            </p>
          </>
        }
        confirmLabel="Delete account"
        destructive
        pending={pending}
        onConfirm={() => void remove()}
        onCancel={() => setConfirm(null)}
      />
    </>
  );
}

function Fact({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="min-w-0">
      <dt className="font-mono text-[0.625rem] font-medium uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
        {label}
      </dt>
      <dd className="mt-0.5 truncate text-sm text-[var(--color-foreground)]">{children}</dd>
    </div>
  );
}
