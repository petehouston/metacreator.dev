"use client";

import Link from "next/link";

import { AdminPageHeader, AdminPanel, StatusPill } from "@/components/admin/admin-page";
import { DataTable, Pagination, type Column } from "@/components/admin/data-table";
import { LoadError } from "@/components/admin/load-error";
import { humanise, tone } from "@/components/admin/status-tone";
import { FilterSelect, SearchInput } from "@/components/admin/toolbar";
import { adminApi } from "@/lib/admin/api";
import type { AdminSubscription } from "@/lib/admin/types";
import { usePagedFilters } from "@/lib/admin/use-paged-filters";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import { formatDate, formatMoney, relativeTime } from "@/lib/utils";

/**
 * Who is paying, and what renews when.
 *
 * Read-only, and deliberately so: a subscription's period and status are the
 * gateway's to state. Editing them here would only make the two systems disagree
 * — the writes that matter go to the provider and come back through its webhook.
 */
export function BillingSubscriptionsScreen() {
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
            href={`/c0ns0le/users/${row.user.id}`}
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
    <>
      <AdminPageHeader
        eyebrow="Billing"
        title="Subscriptions"
        description="Every recurring agreement, live or ended. These belong to the payment gateway — they are read here and written by its webhook."
      />

      <AdminPanel
        title="All subscriptions"
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
    </>
  );
}
