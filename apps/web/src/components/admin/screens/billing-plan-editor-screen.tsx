"use client";

import { ArrowLeft, Lock, Save } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import * as React from "react";

import { AdminPageHeader, AdminPanel, StatusPill } from "@/components/admin/admin-page";
import { useToast } from "@/components/admin/feedback";
import { LoadError } from "@/components/admin/load-error";
import { Button } from "@/components/ui/button";
import { Checkbox, Field, Input, Select, Textarea } from "@/components/ui/field";
import { adminApi } from "@/lib/admin/api";
import type { AdminPlan } from "@/lib/admin/types";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import type { ApiResult } from "@/lib/http";
import { formatMoney } from "@/lib/utils";

/** Which gateways a plan can carry an identifier for. */
const GATEWAYS = [
  { key: "stripe", label: "Stripe price ID", placeholder: "price_…" },
  { key: "paypal", label: "PayPal plan ID", placeholder: "P-…" },
  { key: "braintree", label: "Braintree plan ID", placeholder: "plan-id" },
];

/**
 * Create or edit a plan, on its own page.
 *
 * A page rather than a drawer because a plan is a form of some length — pricing,
 * a feature list, an identifier per gateway — and every one of those decisions
 * deserves the room to be read. It also makes each plan an address: `/admin/billing/
 * plans/4` survives a refresh, can be pasted into a ticket, and is what the browser's
 * back button leaves rather than what it half-closes.
 *
 * The same component serves both jobs, because the fields are identical and the only
 * real difference is which of them a live plan may change. Price, interval and
 * billing mode lock once the plan has subscribers — re-pricing somebody's live
 * subscription from an admin form is a chargeback, not a feature — and the page says
 * so rather than letting the server refuse the save.
 */
export function BillingPlanEditorScreen({ id }: { id?: number }) {
  const isNew = id === undefined;

  // "New" is modelled as a resource that resolves to nothing rather than as a
  // separate branch, so the hook order is identical on both paths — a conditional
  // hook here would be a crash the moment somebody navigated from new to edit.
  const { data, error, loading, reload } = useAdminResource<AdminPlan | null>(
    () =>
      id === undefined
        ? Promise.resolve<ApiResult<AdminPlan | null>>({ ok: true, data: null })
        : adminApi.billing.plan(id),
    [id],
  );

  if (error) return <LoadError error={error} onRetry={reload} />;

  if (loading && !data) {
    return (
      <div className="h-96 animate-pulse rounded-[var(--radius-lg)] bg-[var(--color-surface-sunken)]" aria-hidden="true" />
    );
  }

  if (!isNew && !data) return null;

  // Keyed on the loaded plan so the form state is built once the values exist,
  // rather than initialised empty and then patched by an effect.
  return <PlanForm key={data?.id ?? "new"} plan={data} />;
}

function PlanForm({ plan }: { plan: AdminPlan | null }) {
  const router = useRouter();
  const { notify, reportError } = useToast();

  const isNew = plan === null;
  const locked = (plan?.active_subscriptions ?? 0) > 0;
  const currency = plan?.currency ?? "USD";

  const [form, setForm] = React.useState({
    key: plan?.key ?? "",
    name: plan?.name ?? "",
    tagline: plan?.tagline ?? "",
    billing_mode: plan?.billing_mode ?? "subscription",
    interval: plan?.interval ?? "month",
    amount: plan?.amount ?? 0,
    duration_days: plan?.duration_days ?? 7,
    features: (plan?.features ?? []).join("\n"),
    gateway_ids: (plan?.gateway_ids ?? {}) as Record<string, string | null>,
    is_active: plan?.is_active ?? true,
    is_highlighted: plan?.is_highlighted ?? false,
    sort_order: plan?.sort_order ?? 0,
  });

  const [saving, setSaving] = React.useState(false);

  const oneTime = form.billing_mode === "one_time";

  async function save() {
    setSaving(true);

    const shared = {
      name: form.name,
      tagline: form.tagline || null,
      features: form.features.split("\n").map((line) => line.trim()).filter(Boolean),
      gateway_ids: form.gateway_ids,
      is_active: form.is_active,
      is_highlighted: form.is_highlighted,
      sort_order: form.sort_order,
    };

    // The locked fields are omitted rather than sent unchanged: the server rejects
    // their presence outright, which is what makes "fixed" mean fixed.
    const pricing = locked
      ? {}
      : {
          amount: form.amount,
          billing_mode: form.billing_mode,
          interval: oneTime ? null : form.interval,
          duration_days: oneTime ? form.duration_days : null,
        };

    const result = isNew
      ? await adminApi.billing.createPlan({ key: form.key, ...shared, ...pricing } as Partial<AdminPlan>)
      : await adminApi.billing.updatePlan(plan.id, { ...shared, ...pricing } as Partial<AdminPlan>);

    setSaving(false);

    if (result.ok) {
      notify(isNew ? `${form.name} created.` : `${form.name} saved.`);
      router.push("/admin/billing/plans");
    } else {
      reportError(result.error);
    }
  }

  return (
    <>
      <AdminPageHeader
        eyebrow="Billing · Plans"
        title={isNew ? "New plan" : (plan.name || "Plan")}
        description={
          isNew
            ? "It starts hidden unless you say otherwise, so you can get the pricing right before anybody can buy it."
            : `Key ${plan.key} — fixed for the life of the plan, because every invoice and subscription written against it refers to that string.`
        }
        actions={
          <>
            <Button variant="secondary" size="sm" asChild>
              <Link href="/admin/billing/plans">
                <ArrowLeft className="size-4" aria-hidden="true" />
                Back to plans
              </Link>
            </Button>
            <Button size="sm" onClick={() => void save()} loading={saving}>
              <Save className="size-4" aria-hidden="true" />
              {isNew ? "Create plan" : "Save plan"}
            </Button>
          </>
        }
      />

      <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
        <div className="flex flex-col gap-5">
          <AdminPanel
            title="Identity"
            description="What a customer reads on the pricing card"
          >
            <div className="flex max-w-xl flex-col gap-4">
              {isNew && (
                <Field
                  id="plan-key"
                  label="Key"
                  hint="Lowercase and underscores. This is how the plan is referenced in code, in invoices and in every record written against it — it can never be changed."
                  required
                >
                  {(props) => (
                    <Input
                      {...props}
                      value={form.key}
                      onChange={(event) =>
                        setForm({
                          ...form,
                          key: event.target.value.toLowerCase().replace(/[^a-z0-9_]/g, "_"),
                        })
                      }
                      placeholder="pro_monthly"
                      className="font-mono text-xs"
                    />
                  )}
                </Field>
              )}

              <Field id="plan-name" label="Name" required>
                {(props) => (
                  <Input
                    {...props}
                    value={form.name}
                    onChange={(event) => setForm({ ...form, name: event.target.value })}
                  />
                )}
              </Field>

              <Field id="plan-tagline" label="Tagline" hint="One line on the pricing card.">
                {(props) => (
                  <Input
                    {...props}
                    value={form.tagline}
                    onChange={(event) => setForm({ ...form, tagline: event.target.value })}
                  />
                )}
              </Field>

              <Field
                id="plan-features"
                label="Features"
                hint="One per line, in the order they should read on the pricing card."
              >
                {(props) => (
                  <Textarea
                    {...props}
                    value={form.features}
                    onChange={(event) => setForm({ ...form, features: event.target.value })}
                    className="min-h-32 text-sm"
                  />
                )}
              </Field>
            </div>
          </AdminPanel>

          <AdminPanel
            title="Pricing"
            description="What is charged, and how often"
            action={locked ? <StatusPill label="Fixed — has subscribers" tone="warning" /> : undefined}
          >
            {locked ? (
              <p className="flex max-w-xl items-start gap-2 rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)] p-3 text-xs leading-relaxed text-[var(--color-foreground-muted)]">
                <Lock className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                {plan?.active_subscriptions} live{" "}
                {plan?.active_subscriptions === 1 ? "subscriber is" : "subscribers are"} on this
                plan, so its price, interval and billing mode are fixed at{" "}
                {formatMoney(plan?.amount ?? 0, currency)}. To sell at a different price, create a
                new plan and turn this one off — nobody new can buy it, and nobody already on it
                is re-priced behind their back.
              </p>
            ) : (
              <div className="flex max-w-xl flex-col gap-4">
                <Field id="plan-mode" label="Billing mode" required>
                  {(props) => (
                    <Select
                      {...props}
                      value={form.billing_mode}
                      onChange={(event) => setForm({ ...form, billing_mode: event.target.value })}
                    >
                      <option value="subscription">Subscription — recurring</option>
                      <option value="one_time">One-off pass — time-boxed access</option>
                    </Select>
                  )}
                </Field>

                {oneTime ? (
                  <Field id="plan-duration" label="Days of access" required>
                    {(props) => (
                      <Input
                        {...props}
                        type="number"
                        min={1}
                        value={form.duration_days}
                        onChange={(event) =>
                          setForm({ ...form, duration_days: Number(event.target.value) })
                        }
                      />
                    )}
                  </Field>
                ) : (
                  <Field id="plan-interval" label="Billed every" required>
                    {(props) => (
                      <Select
                        {...props}
                        value={form.interval ?? "month"}
                        onChange={(event) => setForm({ ...form, interval: event.target.value })}
                      >
                        <option value="day">Day</option>
                        <option value="week">Week</option>
                        <option value="month">Month</option>
                        <option value="year">Year</option>
                      </Select>
                    )}
                  </Field>
                )}

                <Field
                  id="plan-amount"
                  label="Price"
                  hint={`In minor units — ${form.amount} is ${formatMoney(form.amount, currency)}.`}
                  required
                >
                  {(props) => (
                    <Input
                      {...props}
                      type="number"
                      min={0}
                      value={form.amount}
                      onChange={(event) => setForm({ ...form, amount: Number(event.target.value) })}
                    />
                  )}
                </Field>
              </div>
            )}
          </AdminPanel>

          <AdminPanel
            title="Gateway identifiers"
            description="What each provider calls this plan on its side"
          >
            <p className="mb-4 max-w-xl text-xs leading-relaxed text-[var(--color-foreground-subtle)]">
              Only the provider selected in Settings → Payments is used; the rest are kept so
              switching back does not mean re-entering them.
            </p>

            <div className="flex max-w-xl flex-col gap-4">
              {GATEWAYS.map((gateway) => (
                <Field key={gateway.key} id={`plan-gateway-${gateway.key}`} label={gateway.label}>
                  {(props) => (
                    <Input
                      {...props}
                      value={form.gateway_ids[gateway.key] ?? ""}
                      onChange={(event) =>
                        setForm({
                          ...form,
                          gateway_ids: { ...form.gateway_ids, [gateway.key]: event.target.value },
                        })
                      }
                      placeholder={gateway.placeholder}
                      className="font-mono text-xs"
                    />
                  )}
                </Field>
              ))}
            </div>
          </AdminPanel>
        </div>

        <AdminPanel
          title="Visibility"
          description="Where this appears, and how"
          className="lg:sticky lg:top-20"
        >
          <div className="flex flex-col gap-4">
            <Checkbox
              label="Available to buy"
              hint="Turning this off removes it from the pricing page. Existing subscribers are unaffected."
              checked={form.is_active}
              onChange={(event) => setForm({ ...form, is_active: event.target.checked })}
            />

            <Checkbox
              label="Highlight on the pricing page"
              hint="The “most popular” treatment. Only one plan should have it."
              checked={form.is_highlighted}
              onChange={(event) => setForm({ ...form, is_highlighted: event.target.checked })}
            />

            <Field
              id="plan-sort"
              label="Sort order"
              hint="Lower numbers sit further left on the pricing page."
            >
              {(props) => (
                <Input
                  {...props}
                  type="number"
                  min={0}
                  value={form.sort_order}
                  onChange={(event) => setForm({ ...form, sort_order: Number(event.target.value) })}
                />
              )}
            </Field>

            {!isNew && (
              <p className="tabular border-t border-[var(--color-border-subtle)] pt-4 text-xs text-[var(--color-foreground-subtle)]">
                {plan.active_subscriptions ?? 0} active of {plan.total_subscriptions} subscriptions
                ever.
              </p>
            )}
          </div>
        </AdminPanel>
      </div>
    </>
  );
}
