"use client";

import Link from "next/link";
import * as React from "react";

import { AdminPageHeader, AdminPanel, StatusPill } from "@/components/admin/admin-page";
import { ShareBar, StackedColumns } from "@/components/admin/charts";
import { LoadError } from "@/components/admin/load-error";
import { MetricCard, MetricCardSkeleton } from "@/components/admin/metric-card";
import { PeriodPicker } from "@/components/admin/period-picker";
import { humanise, tone } from "@/components/admin/status-tone";
import { adminApi } from "@/lib/admin/api";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import { formatDate, formatMoney, formatNumber } from "@/lib/utils";

/**
 * Where the money comes from, and what is leaking.
 *
 * Separate from the overview, which asks whether the *product* is healthy. This
 * asks a finance question, and the answer is mostly breakdowns rather than
 * headlines: which plans earn, which gateway takes them, who the revenue is
 * concentrated in, and what got refunded.
 *
 * Two things hold across every figure, and the screen says so where it matters:
 * revenue is net of refunds, and recurring revenue is normalised to a month, so a
 * yearly plan contributes a twelfth rather than a year of cash in one column.
 */
export function BillingReportScreen() {
  const [period, setPeriod] = React.useState(30);

  const { data, error, loading, reload } = useAdminResource(
    () => adminApi.billing.report(period),
    [period],
  );

  if (error) return <LoadError error={error} onRetry={reload} />;

  const currency = data?.currency ?? "USD";

  return (
    <>
      <AdminPageHeader
        eyebrow="Billing"
        title="Report"
        description="Revenue, subscriptions and churn over a window you choose. Revenue is always net of refunds — a charge that came back was never revenue."
        actions={
          data && (
            <PeriodPicker value={period} options={data.periods} onChange={setPeriod} />
          )
        }
      />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        {data
          ? data.metrics.map((metric) => <MetricCard key={metric.key} metric={metric} compact />)
          : [0, 1, 2, 3, 4].map((card) => <MetricCardSkeleton key={card} />)}
      </div>

      {data && (
        <div className="mt-5 flex flex-col gap-5">
          <AdminPanel
            title="Revenue"
            description={`Net of refunds, by the day the money landed · ${data.period.label}`}
          >
            <StackedColumns
              data={data.revenue_series.map((point) => ({
                date: point.date,
                values: [
                  {
                    key: "revenue",
                    label: "Net revenue",
                    value: point.value,
                    color: "var(--color-primary)",
                  },
                ],
              }))}
              height={200}
            />

            <p className="mt-3 text-xs text-[var(--color-foreground-subtle)]">
              Totalling{" "}
              <span className="tabular font-medium text-[var(--color-foreground)]">
                {formatMoney(
                  data.revenue_series.reduce((sum, point) => sum + point.value, 0),
                  currency,
                )}
              </span>{" "}
              across {data.period.label.toLowerCase()}.
            </p>
          </AdminPanel>

          <AdminPanel
            title="Subscriptions"
            description="New against cancelled — the shape that matters is whether the two are converging"
          >
            <StackedColumns
              data={data.subscription_series.map((point) => ({
                date: point.date,
                values: [
                  {
                    key: "new",
                    label: "New",
                    value: point.new,
                    color: "var(--color-success)",
                  },
                  {
                    key: "cancelled",
                    label: "Cancelled",
                    value: point.cancelled,
                    color: "var(--color-danger)",
                  },
                ],
              }))}
              height={180}
            />
          </AdminPanel>

          <div className="grid gap-5 xl:grid-cols-2">
            <AdminPanel title="Revenue by plan" description="And how many subscribers each holds" bodyClassName="p-0">
              {data.by_plan.length === 0 ? (
                <Empty>No plans defined yet.</Empty>
              ) : (
                <ul className="divide-y divide-[var(--color-border-subtle)]">
                  {data.by_plan.map((plan) => (
                    <li key={plan.id} className="flex flex-wrap items-center gap-3 px-4 py-3">
                      <div className="min-w-0 flex-1">
                        <Link
                          href={`/admin/billing/plans/${plan.id}`}
                          className="truncate text-sm font-medium text-[var(--color-foreground)] hover:text-[var(--color-primary)]"
                        >
                          {plan.name}
                        </Link>
                        <p className="tabular text-xs text-[var(--color-foreground-subtle)]">
                          {plan.active_subscriptions} active ·{" "}
                          {plan.invoices} {plan.invoices === 1 ? "invoice" : "invoices"}
                        </p>
                      </div>

                      {!plan.is_active && <StatusPill label="Hidden" tone="muted" />}

                      <div className="text-right">
                        <p className="tabular text-sm font-semibold text-[var(--color-foreground)]">
                          {formatMoney(plan.revenue, currency)}
                        </p>
                        <p className="tabular text-xs text-[var(--color-foreground-subtle)]">
                          {plan.share}% of revenue
                        </p>
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </AdminPanel>

            <div className="flex flex-col gap-5">
              <AdminPanel title="Revenue by gateway" description="Which provider took the money">
                <ShareBar
                  slices={data.by_gateway.map((row, index) => ({
                    key: row.gateway,
                    label: humanise(row.gateway),
                    value: Math.round(row.revenue),
                    share: row.share,
                    color: PALETTE[index % PALETTE.length],
                  }))}
                />
              </AdminPanel>

              <AdminPanel title="Invoices by status" description="Issued in this window" bodyClassName="p-0">
                {data.by_status.length === 0 ? (
                  <Empty>Nothing was issued in this window.</Empty>
                ) : (
                  <ul className="divide-y divide-[var(--color-border-subtle)]">
                    {data.by_status.map((row) => (
                      <li
                        key={row.status}
                        className="flex items-center justify-between gap-3 px-4 py-2.5"
                      >
                        <StatusPill label={humanise(row.status)} tone={tone.invoice(row.status)} />
                        <span className="tabular text-xs text-[var(--color-foreground-subtle)]">
                          {formatNumber(row.invoices)}{" "}
                          {row.invoices === 1 ? "invoice" : "invoices"}
                        </span>
                        <span className="tabular text-sm font-medium text-[var(--color-foreground)]">
                          {formatMoney(row.total, currency)}
                        </span>
                      </li>
                    ))}
                  </ul>
                )}
              </AdminPanel>
            </div>
          </div>

          <div className="grid gap-5 xl:grid-cols-2">
            <AdminPanel
              title="Top customers"
              description="By net revenue in this window"
              bodyClassName="p-0"
            >
              {data.top_customers.length === 0 ? (
                <Empty>Nobody paid anything in this window.</Empty>
              ) : (
                <ul className="divide-y divide-[var(--color-border-subtle)]">
                  {data.top_customers.map((customer) => (
                    <li
                      key={customer.id}
                      className="flex items-center justify-between gap-3 px-4 py-2.5"
                    >
                      <Link
                        href={`/admin/users/${customer.id}`}
                        className="min-w-0 flex-1 truncate text-sm text-[var(--color-foreground-muted)] hover:text-[var(--color-primary)]"
                      >
                        {customer.display_name}
                        <span className="ml-2 text-xs text-[var(--color-foreground-subtle)]">
                          {customer.email}
                        </span>
                      </Link>
                      <span className="tabular text-sm font-medium text-[var(--color-foreground)]">
                        {formatMoney(customer.revenue, currency)}
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </AdminPanel>

            <AdminPanel
              title="Refunds"
              description="A refund total nobody can act on is trivia — these are the rows behind it"
              bodyClassName="p-0"
            >
              {data.recent_refunds.length === 0 ? (
                <Empty>Nothing has been refunded.</Empty>
              ) : (
                <ul className="divide-y divide-[var(--color-border-subtle)]">
                  {data.recent_refunds.map((refund) => (
                    <li key={refund.id} className="flex flex-wrap items-baseline gap-x-3 gap-y-1 px-4 py-3">
                      <Link
                        href={`/admin/billing/invoices/${refund.id}`}
                        className="font-mono text-xs font-medium text-[var(--color-foreground)] hover:text-[var(--color-primary)]"
                      >
                        {refund.number ?? `#${refund.id}`}
                      </Link>

                      <span className="min-w-0 flex-1 truncate text-xs text-[var(--color-foreground-subtle)]">
                        {refund.email ?? "deleted account"}
                      </span>

                      <span className="tabular text-sm font-medium text-[var(--color-warning)]">
                        {formatMoney(refund.amount, refund.currency)}
                      </span>

                      <span className="tabular w-full text-xs text-[var(--color-foreground-subtle)] sm:w-auto">
                        {refund.refunded_at ? formatDate(refund.refunded_at) : "date not recorded"}
                      </span>

                      {refund.reason && (
                        <p className="w-full text-xs leading-relaxed text-[var(--color-foreground-muted)]">
                          {refund.reason}
                        </p>
                      )}
                    </li>
                  ))}
                </ul>
              )}
            </AdminPanel>
          </div>
        </div>
      )}

      {loading && !data && (
        <div
          className="mt-5 h-72 animate-pulse rounded-[var(--radius-lg)] bg-[var(--color-surface-sunken)]"
          aria-hidden="true"
        />
      )}
    </>
  );
}

/** Enough distinct hues for the gateways a deployment might plausibly run. */
const PALETTE = [
  "var(--color-primary)",
  "var(--color-accent)",
  "var(--color-success)",
  "var(--color-warning)",
];

function Empty({ children }: { children: React.ReactNode }) {
  return (
    <p className="px-4 py-10 text-center text-sm text-[var(--color-foreground-subtle)]">
      {children}
    </p>
  );
}
