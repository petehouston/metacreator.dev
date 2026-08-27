"use client";

import { ExternalLink, Info, Lock, Save } from "lucide-react";
import Link from "next/link";
import * as React from "react";

import { AdminPageHeader, AdminPanel, StatusPill } from "@/components/admin/admin-page";
import { Can, useCan } from "@/components/admin/can";
import { Drawer, useToast } from "@/components/admin/feedback";
import { DataTable, Pagination, type Column } from "@/components/admin/data-table";
import { LoadError } from "@/components/admin/load-error";
import { humanise, tone } from "@/components/admin/status-tone";
import { FilterSelect, SearchInput } from "@/components/admin/toolbar";
import { Button } from "@/components/ui/button";
import { Checkbox, Field, Input, Textarea } from "@/components/ui/field";
import { adminApi } from "@/lib/admin/api";
import type { AdminInvoice, AdminPlan, AdminSubscription } from "@/lib/admin/types";
import { usePagedFilters } from "@/lib/admin/use-paged-filters";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import { cn, formatDate, formatMoney, relativeTime } from "@/lib/utils";

type Tab = "plans" | "subscriptions" | "invoices";

/**
 * The accountant's surface.
 *
 * Everything Stripe owns is read-only here, and the screen says so rather than
 * offering fields that would only make the two systems disagree. What *is* editable
 * is the plan definition — price, features, visibility — because that is ours.
 */
export function BillingScreen() {
  const can = useCan();

  const initial: Tab = can("plans.view_any")
    ? "plans"
    : can("subscriptions.view_any")
      ? "subscriptions"
      : "invoices";

  const [tab, setTab] = React.useState<Tab>(initial);

  const tabs: { value: Tab; label: string; permission: string }[] = [
    { value: "plans", label: "Plans", permission: "plans.view_any" },
    { value: "subscriptions", label: "Subscriptions", permission: "subscriptions.view_any" },
    { value: "invoices", label: "Invoices", permission: "invoices.view_any" },
  ];

  return (
    <>
      <AdminPageHeader
        eyebrow="Commerce"
        title="Billing"
        description="Plans are ours to change. Subscriptions and invoices are Stripe's — they are read here and written through the webhook."
      />

      <div
        role="tablist"
        aria-label="Billing view"
        className="mb-4 flex gap-1 border-b border-[var(--color-border-subtle)]"
      >
        {tabs
          .filter((entry) => can(entry.permission))
          .map((entry) => (
            <button
              key={entry.value}
              type="button"
              role="tab"
              aria-selected={tab === entry.value}
              onClick={() => setTab(entry.value)}
              className={cn(
                "-mb-px border-b-2 px-3 py-2 text-sm font-medium transition-colors",
                tab === entry.value
                  ? "border-[var(--color-primary)] text-[var(--color-foreground)]"
                  : "border-transparent text-[var(--color-foreground-muted)] hover:text-[var(--color-foreground)]",
              )}
            >
              {entry.label}
            </button>
          ))}
      </div>

      {tab === "plans" && <PlansTab />}
      {tab === "subscriptions" && <SubscriptionsTab />}
      {tab === "invoices" && <InvoicesTab />}
    </>
  );
}

function PlansTab() {
  const { data, error, loading, reload } = useAdminResource(() => adminApi.billing.plans(), []);
  const [editing, setEditing] = React.useState<AdminPlan | null>(null);

  if (error) return <LoadError error={error} onRetry={reload} />;

  return (
    <>
      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {(data?.data ?? []).map((plan) => (
          <article
            key={plan.id}
            className={cn(
              "app-card flex flex-col p-4",
              plan.is_highlighted && "ring-1 ring-[var(--color-primary)]/40",
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

            <Can permission="plans.update">
              <Button
                variant="secondary"
                size="sm"
                className="mt-4 self-start"
                onClick={() => setEditing(plan)}
              >
                Edit plan
              </Button>
            </Can>
          </article>
        ))}

        {loading &&
          !data &&
          [0, 1, 2].map((card) => (
            <div key={card} className="app-card h-52 animate-pulse" aria-hidden="true" />
          ))}
      </div>

      {editing && (
        <PlanEditor
          plan={editing}
          onClose={() => setEditing(null)}
          onSaved={() => {
            setEditing(null);
            reload();
          }}
        />
      )}
    </>
  );
}

function PlanEditor({
  plan,
  onClose,
  onSaved,
}: {
  plan: AdminPlan;
  onClose: () => void;
  onSaved: () => void;
}) {
  const { notify, reportError } = useToast();

  const [form, setForm] = React.useState({
    name: plan.name,
    tagline: plan.tagline ?? "",
    amount: plan.amount,
    features: (plan.features ?? []).join("\n"),
    is_active: plan.is_active,
    is_highlighted: plan.is_highlighted,
    sort_order: plan.sort_order,
  });

  const [saving, setSaving] = React.useState(false);

  async function save() {
    setSaving(true);

    const result = await adminApi.billing.updatePlan(plan.id, {
      name: form.name,
      tagline: form.tagline || null,
      amount: form.amount,
      features: form.features.split("\n").map((line) => line.trim()).filter(Boolean),
      is_active: form.is_active,
      is_highlighted: form.is_highlighted,
      sort_order: form.sort_order,
    });

    setSaving(false);

    if (result.ok) {
      notify(`${form.name} saved.`);
      onSaved();
    } else {
      reportError(result.error);
    }
  }

  return (
    <Drawer
      open
      title={plan.name}
      description={plan.key}
      onClose={onClose}
      footer={
        <>
          <Button variant="secondary" size="sm" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button size="sm" onClick={() => void save()} loading={saving}>
            <Save className="size-4" aria-hidden="true" />
            Save plan
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-4">
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
          id="plan-amount"
          label="Price"
          hint={`In minor units — ${form.amount} is ${formatMoney(form.amount, plan.currency)}. Existing subscribers keep the price they signed up at until Stripe re-prices them.`}
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

        <div className="flex flex-col gap-1 rounded-[var(--radius-md)] border border-[var(--color-border-subtle)] p-3">
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
        </div>

        <p className="flex items-start gap-2 rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)] p-3 text-xs leading-relaxed text-[var(--color-foreground-muted)]">
          <Lock className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
          The plan key, its interval and its Stripe price id are fixed here. They are
          the contract with Stripe and with every subscription already on this plan —
          changing them from a form would silently re-price existing customers.
        </p>
      </div>
    </Drawer>
  );
}

function SubscriptionsTab() {
  const [{ query, status }, setFilters, page, setPage] = usePagedFilters({
    query: "",
    status: "",
  });

  const { data, error, loading, reload } = useAdminResource(
    () =>
      adminApi.billing.subscriptions({
        q: query || undefined,
        "filter[status]": status || undefined,
        page,
        per_page: 25,
      }),
    [query, status, page],
  );

  if (error) return <LoadError error={error} onRetry={reload} />;

  const columns: Column<AdminSubscription>[] = [
    {
      key: "user",
      header: "Customer",
      cell: (row) =>
        row.user ? (
          <Link
            href={`/admin/users/${row.user.id}`}
            className="flex min-w-0 flex-col hover:text-[var(--color-primary)]"
          >
            <span className="truncate font-medium text-[var(--color-foreground)]">
              {row.user.display_name}
            </span>
            <span className="truncate text-xs text-[var(--color-foreground-subtle)]">
              {row.user.email}
            </span>
          </Link>
        ) : (
          <span className="text-[var(--color-foreground-subtle)]">Deleted account</span>
        ),
    },
    {
      key: "plan",
      header: "Plan",
      cell: (row) => row.plan?.name ?? "—",
    },
    {
      key: "status",
      header: "Status",
      cell: (row) => (
        <span className="flex flex-wrap gap-1">
          <StatusPill label={humanise(row.status)} tone={tone.subscription(row.status)} />
          {row.is_cancelling && <StatusPill label="Cancelling" tone="warning" />}
        </span>
      ),
    },
    {
      key: "amount",
      header: "Amount",
      numeric: true,
      hideBelow: "sm",
      cell: (row) =>
        row.plan ? `${formatMoney(row.plan.amount, row.plan.currency)}/${row.plan.interval ?? "—"}` : "—",
    },
    {
      key: "renews",
      header: "Renews",
      numeric: true,
      hideBelow: "md",
      cell: (row) => (
        <span className="text-xs text-[var(--color-foreground-subtle)]">
          {row.current_period_end ? formatDate(row.current_period_end) : "—"}
        </span>
      ),
    },
    {
      key: "started",
      header: "Started",
      numeric: true,
      hideBelow: "xl",
      cell: (row) => (
        <span className="text-xs text-[var(--color-foreground-subtle)]">
          {row.created_at ? relativeTime(row.created_at) : "—"}
        </span>
      ),
    },
  ];

  return (
    <AdminPanel
      title="Subscriptions"
      description={data ? `${data.meta.page.total} total` : "Loading…"}
      bodyClassName="p-0"
      action={
        <div className="flex flex-wrap items-center gap-2">
          <SearchInput
            value={query}
            onChange={(next) => setFilters({ query: next })}
            placeholder="Customer email…"
            className="w-48"
          />
          <FilterSelect
            label="Status"
            value={status}
            onChange={(next) => setFilters({ status: next })}
            options={[
              { value: "", label: "All" },
              { value: "active", label: "Active" },
              { value: "trialing", label: "Trialing" },
              { value: "past_due", label: "Past due" },
              { value: "canceled", label: "Canceled" },
            ]}
          />
        </div>
      }
    >
      <DataTable
        rows={data?.data ?? []}
        columns={columns}
        rowKey={(row) => String(row.id)}
        loading={loading}
        empty={
          <p className="px-4 py-12 text-center text-sm text-[var(--color-foreground-subtle)]">
            No subscriptions match those filters. There is no Stripe integration wired
            up yet, so this table is empty on a fresh install by design.
          </p>
        }
      />

      {data && (
        <Pagination
          page={data.meta.page.current}
          lastPage={data.meta.page.last_page}
          total={data.meta.page.total}
          perPage={data.meta.page.per_page}
          onChange={setPage}
        />
      )}
    </AdminPanel>
  );
}

function InvoicesTab() {
  const [{ query, status }, setFilters, page, setPage] = usePagedFilters({
    query: "",
    status: "",
  });

  const { data, error, loading, reload } = useAdminResource(
    () =>
      adminApi.billing.invoices({
        q: query || undefined,
        "filter[status]": status || undefined,
        page,
        per_page: 25,
      }),
    [query, status, page],
  );

  if (error) return <LoadError error={error} onRetry={reload} />;

  const totals = data?.meta.totals;

  const columns: Column<AdminInvoice>[] = [
    {
      key: "number",
      header: "Invoice",
      cell: (row) => (
        <span className="font-mono text-xs text-[var(--color-foreground)]">
          {row.number ?? `#${row.id}`}
        </span>
      ),
    },
    {
      key: "user",
      header: "Customer",
      cell: (row) =>
        row.user ? (
          <Link
            href={`/admin/users/${row.user.id}`}
            className="truncate hover:text-[var(--color-primary)]"
          >
            {row.user.email}
          </Link>
        ) : (
          "—"
        ),
    },
    {
      key: "status",
      header: "Status",
      cell: (row) => <StatusPill label={humanise(row.status)} tone={tone.invoice(row.status)} />,
    },
    {
      key: "total",
      header: "Total",
      numeric: true,
      cell: (row) => (
        <span className="font-medium text-[var(--color-foreground)]">
          {formatMoney(row.total, row.currency)}
        </span>
      ),
    },
    {
      key: "refunded",
      header: "Refunded",
      numeric: true,
      hideBelow: "md",
      cell: (row) =>
        row.amount_refunded > 0 ? (
          <span className="text-[var(--color-warning)]">
            {formatMoney(row.amount_refunded, row.currency)}
          </span>
        ) : (
          <span className="text-[var(--color-foreground-subtle)]">—</span>
        ),
    },
    {
      key: "issued",
      header: "Issued",
      numeric: true,
      hideBelow: "sm",
      cell: (row) => (
        <span className="text-xs text-[var(--color-foreground-subtle)]">
          {row.issued_at ? formatDate(row.issued_at) : "—"}
        </span>
      ),
    },
    {
      key: "link",
      header: "",
      width: "3rem",
      cell: (row) =>
        row.hosted_url ? (
          <a
            href={row.hosted_url}
            target="_blank"
            rel="noreferrer"
            aria-label={`Open invoice ${row.number ?? row.id} in Stripe`}
            className="flex size-7 items-center justify-center rounded-[var(--radius-sm)] text-[var(--color-foreground-subtle)] transition-colors hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-foreground)]"
          >
            <ExternalLink className="size-3.5" aria-hidden="true" />
          </a>
        ) : null,
    },
  ];

  return (
    <>
      {totals && (
        <div className="mb-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <Money label="Collected" value={Number(totals.paid ?? 0)} currency={String(totals.currency)} />
          <Money
            label="Outstanding"
            value={Number(totals.outstanding ?? 0)}
            currency={String(totals.currency)}
            tone="warning"
          />
          <Money
            label="Refunded"
            value={Number(totals.refunded ?? 0)}
            currency={String(totals.currency)}
            tone="danger"
          />
          <div className="app-card p-4">
            <p className="font-mono text-[0.625rem] font-medium uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
              Invoices
            </p>
            <p className="tabular mt-2 text-2xl font-semibold leading-none text-[var(--color-foreground)]">
              {Number(totals.count ?? 0).toLocaleString()}
            </p>
            <p className="mt-2 flex items-start gap-1.5 text-xs leading-snug text-[var(--color-foreground-subtle)]">
              <Info className="mt-px size-3 shrink-0" aria-hidden="true" />
              Totals cover every matching invoice, not just this page.
            </p>
          </div>
        </div>
      )}

      <AdminPanel
        title="Invoices"
        description="Financial records are never deleted"
        bodyClassName="p-0"
        action={
          <div className="flex flex-wrap items-center gap-2">
            <SearchInput
              value={query}
              onChange={(next) => setFilters({ query: next })}
              placeholder="Number or email…"
              className="w-48"
            />
            <FilterSelect
              label="Status"
              value={status}
              onChange={(next) => setFilters({ status: next })}
              options={[
                { value: "", label: "All" },
                { value: "paid", label: "Paid" },
                { value: "open", label: "Open" },
                { value: "void", label: "Void" },
                { value: "uncollectible", label: "Uncollectible" },
              ]}
            />
          </div>
        }
      >
        <DataTable
          rows={data?.data ?? []}
          columns={columns}
          rowKey={(row) => String(row.id)}
          loading={loading}
          empty={
            <p className="px-4 py-12 text-center text-sm text-[var(--color-foreground-subtle)]">
              No invoices match those filters.
            </p>
          }
        />

        {data && (
          <Pagination
            page={data.meta.page.current}
            lastPage={data.meta.page.last_page}
            total={data.meta.page.total}
            perPage={data.meta.page.per_page}
            onChange={setPage}
          />
        )}
      </AdminPanel>
    </>
  );
}

function Money({
  label,
  value,
  currency,
  tone: colorTone,
}: {
  label: string;
  value: number;
  currency: string;
  tone?: "warning" | "danger";
}) {
  return (
    <div className="app-card p-4">
      <p className="font-mono text-[0.625rem] font-medium uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
        {label}
      </p>
      <p
        className="tabular mt-2 text-2xl font-semibold leading-none tracking-[-0.02em]"
        style={{
          color:
            colorTone === "warning"
              ? "var(--color-warning)"
              : colorTone === "danger"
                ? "var(--color-danger)"
                : "var(--color-foreground)",
        }}
      >
        {formatMoney(value, currency)}
      </p>
    </div>
  );
}
