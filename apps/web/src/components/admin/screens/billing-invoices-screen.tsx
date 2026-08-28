"use client";

import { ChevronRight, Info } from "lucide-react";
import Link from "next/link";

import { AdminPageHeader, AdminPanel, StatusPill } from "@/components/admin/admin-page";
import { DataTable, Pagination, type Column } from "@/components/admin/data-table";
import { LoadError } from "@/components/admin/load-error";
import { humanise, tone } from "@/components/admin/status-tone";
import { FilterSelect, SearchInput } from "@/components/admin/toolbar";
import { adminApi } from "@/lib/admin/api";
import type { AdminInvoice } from "@/lib/admin/types";
import { usePagedFilters } from "@/lib/admin/use-paged-filters";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import { formatDate, formatMoney } from "@/lib/utils";

/**
 * Every charge, refund and outstanding balance.
 *
 * The row is a link into the invoice rather than a drawer over the list: somebody
 * opening one is answering a question about a specific charge, usually with a
 * customer waiting, and that page has to survive a refresh and be pasteable into a
 * ticket.
 */
export function BillingInvoicesScreen() {
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
        <Link
          href={`/admin/billing/invoices/${row.id}`}
          className="font-mono text-xs font-medium text-[var(--color-foreground)] hover:text-[var(--color-primary)]"
        >
          {row.number ?? `#${row.id}`}
        </Link>
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
      key: "method",
      header: "Method",
      hideBelow: "lg",
      cell: (row) => (
        <span className="text-xs text-[var(--color-foreground-subtle)]">
          {describeMethod(row)}
        </span>
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
      key: "open",
      header: "",
      width: "3rem",
      cell: (row) => (
        <Link
          href={`/admin/billing/invoices/${row.id}`}
          aria-label={`Open invoice ${row.number ?? row.id}`}
          className="flex size-7 items-center justify-center rounded-[var(--radius-sm)] text-[var(--color-foreground-subtle)] transition-colors hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-foreground)]"
        >
          <ChevronRight className="size-4" aria-hidden="true" />
        </Link>
      ),
    },
  ];

  return (
    <>
      <AdminPageHeader
        eyebrow="Billing"
        title="Invoices"
        description="Financial records, never deleted and never edited here. Open one to see its lines, the transaction behind it and any refund."
      />

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
        title="All invoices"
        description={data ? `${data.meta.page.total} total` : "Loading…"}
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
                { value: "refunded", label: "Refunded" },
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

/** "Visa ···· 4242", "PayPal", or an em dash for an invoice nothing has paid. */
export function describeMethod(invoice: Pick<AdminInvoice, "payment_method">): string {
  const method = invoice.payment_method;

  if (!method) return "—";

  if (method.brand && method.last4) {
    return `${humanise(method.brand)} ···· ${method.last4}`;
  }

  return method.type ? humanise(method.type) : "—";
}

export function Money({
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
