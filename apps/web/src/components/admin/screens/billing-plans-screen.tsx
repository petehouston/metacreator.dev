"use client";

import { Plus, Power, PowerOff, Trash2 } from "lucide-react";
import Link from "next/link";
import * as React from "react";

import { AdminPageHeader, StatusPill } from "@/components/admin/admin-page";
import { Can } from "@/components/admin/can";
import { ConfirmDialog, useToast } from "@/components/admin/feedback";
import { LoadError } from "@/components/admin/load-error";
import { Button } from "@/components/ui/button";
import { adminApi } from "@/lib/admin/api";
import type { AdminPlan } from "@/lib/admin/types";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import { cn, formatMoney } from "@/lib/utils";

/**
 * What is for sale.
 *
 * Plans are the one part of billing that is ours rather than the gateway's, so
 * this is the only billing screen with write actions on it. Creating and editing
 * happen on their own pages — a plan's price, features and per-gateway identifiers
 * are a form somebody works through, not a thing to squint at in a drawer over the
 * list they were reading.
 */
export function BillingPlansScreen() {
  const { notify, reportError } = useToast();

  const { data, error, loading, reload } = useAdminResource(() => adminApi.billing.plans(), []);

  const [deleting, setDeleting] = React.useState<AdminPlan | null>(null);
  const [pending, setPending] = React.useState(false);
  const [toggling, setToggling] = React.useState<number | null>(null);

  if (error) return <LoadError error={error} onRetry={reload} />;

  /**
   * Enable or disable in one click, without opening the editor.
   *
   * Turning a plan off is the reversible half of removing it, and it is the thing
   * anyone actually wants nine times out of ten: nobody new can buy it, and every
   * existing subscriber keeps exactly what they paid for.
   */
  async function toggle(plan: AdminPlan) {
    setToggling(plan.id);
    const result = await adminApi.billing.updatePlan(plan.id, { is_active: !plan.is_active });
    setToggling(null);

    if (result.ok) {
      notify(
        plan.is_active
          ? `${plan.name} is hidden. Nobody new can buy it; existing subscribers keep it.`
          : `${plan.name} is on sale again.`,
      );
      reload();
    } else {
      reportError(result.error);
    }
  }

  async function remove() {
    if (!deleting) return;

    setPending(true);
    const result = await adminApi.billing.removePlan(deleting.id);
    setPending(false);

    if (result.ok) {
      notify(`${deleting.name} deleted.`);
      setDeleting(null);
      reload();
    } else {
      reportError(result.error);
    }
  }

  const plans = data?.data ?? [];

  return (
    <>
      <AdminPageHeader
        eyebrow="Billing"
        title="Plans"
        description="The catalogue: what a customer can buy, at what price, and on which terms. Plans are defined here and referenced by the payment gateway, not the other way round."
        actions={
          <Can permission="plans.create">
            <Button size="sm" asChild>
              <Link href="/admin/billing/plans/new">
                <Plus className="size-4" aria-hidden="true" />
                New plan
              </Link>
            </Button>
          </Can>
        }
      />

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {plans.map((plan) => (
          <article
            key={plan.id}
            className={cn(
              "app-card flex flex-col p-4",
              plan.is_highlighted && "ring-1 ring-[var(--color-primary)]/40",
              !plan.is_active && "opacity-75",
            )}
          >
            <div className="flex items-start justify-between gap-2">
              <div className="min-w-0">
                <h2 className="text-sm font-semibold text-[var(--color-foreground)]">
                  {plan.name}
                </h2>
                <p className="font-mono text-[0.6875rem] text-[var(--color-foreground-subtle)]">
                  {plan.key}
                </p>
              </div>

              <StatusPill
                label={plan.is_active ? "Live" : "Hidden"}
                tone={plan.is_active ? "success" : "muted"}
              />
            </div>

            <p className="tabular mt-3 text-2xl font-semibold leading-none tracking-[-0.02em] text-[var(--color-foreground)]">
              {formatMoney(plan.amount, plan.currency)}
              <span className="ml-1 text-xs font-normal text-[var(--color-foreground-subtle)]">
                {plan.billing_mode === "one_time"
                  ? `for ${plan.duration_days ?? 0} days`
                  : `/ ${plan.interval ?? "period"}`}
              </span>
            </p>

            {plan.tagline && (
              <p className="mt-2 text-xs leading-relaxed text-[var(--color-foreground-muted)]">
                {plan.tagline}
              </p>
            )}

            <p className="tabular mt-3 text-xs text-[var(--color-foreground-subtle)]">
              {plan.active_subscriptions ?? 0} active{" "}
              {plan.active_subscriptions === 1 ? "subscriber" : "subscribers"}
            </p>

            <div className="mt-4 flex flex-wrap items-center gap-2">
              <Can permission="plans.update">
                <Button variant="secondary" size="sm" asChild>
                  <Link href={`/admin/billing/plans/${plan.id}`}>Edit</Link>
                </Button>

                <Button
                  variant="ghost"
                  size="sm"
                  loading={toggling === plan.id}
                  onClick={() => void toggle(plan)}
                >
                  {plan.is_active ? (
                    <>
                      <PowerOff className="size-4" aria-hidden="true" />
                      Turn off
                    </>
                  ) : (
                    <>
                      <Power className="size-4" aria-hidden="true" />
                      Turn on
                    </>
                  )}
                </Button>
              </Can>

              {/* Deleting is only offered where it is possible: a plan somebody has
                  bought is history, and the screen should not pretend otherwise. */}
              {plan.total_subscriptions === 0 && (
                <Can permission="plans.delete">
                  <button
                    type="button"
                    onClick={() => setDeleting(plan)}
                    className="ml-auto inline-flex items-center gap-1 text-xs text-[var(--color-danger)] hover:underline"
                  >
                    <Trash2 className="size-3.5" aria-hidden="true" />
                    Delete
                  </button>
                </Can>
              )}
            </div>
          </article>
        ))}

        {loading &&
          !data &&
          [0, 1, 2].map((card) => (
            <div key={card} className="app-card h-52 animate-pulse" aria-hidden="true" />
          ))}

        {!loading && plans.length === 0 && (
          <p className="text-sm text-[var(--color-foreground-muted)]">
            No plans yet. Create one to give the pricing page something to sell.
          </p>
        )}
      </div>

      <ConfirmDialog
        open={deleting !== null}
        title={`Delete ${deleting?.name ?? "this plan"}?`}
        description="Nobody has ever subscribed to it, so nothing references it and this is safe. A plan with any history cannot be deleted — turn it off instead."
        confirmLabel="Delete plan"
        destructive
        pending={pending}
        onConfirm={() => void remove()}
        onCancel={() => setDeleting(null)}
      />
    </>
  );
}
