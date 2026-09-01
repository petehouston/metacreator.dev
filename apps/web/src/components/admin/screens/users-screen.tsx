"use client";

import { ShieldCheck } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import * as React from "react";

import { AdminPageHeader, AdminPanel, StatusPill } from "@/components/admin/admin-page";
import { DataTable, Pagination, type Column } from "@/components/admin/data-table";
import { LoadError } from "@/components/admin/load-error";
import { humanise, tone } from "@/components/admin/status-tone";
import { FilterSelect, SearchInput } from "@/components/admin/toolbar";
import { adminApi } from "@/lib/admin/api";
import type { AdminUser } from "@/lib/admin/types";
import { usePagedFilters } from "@/lib/admin/use-paged-filters";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import { formatNumber, relativeTime } from "@/lib/utils";

/**
 * Find anyone.
 *
 * The search matches email, display name *and* public id, because the three ways
 * staff arrive at this screen are a support email, a name someone said out loud,
 * and an id pasted from a log line.
 */
export function UsersScreen() {
  const router = useRouter();

  // Filters and the page number move together: staying on page 7 of a list that
  // now has two pages shows an empty table and looks like a bug.
  const [{ query, status, role, plan, sort }, setFilters, page, setPage] = usePagedFilters({
    query: "",
    status: "",
    role: "",
    plan: "",
    sort: "-created_at",
  });

  const { data, error, loading, reload } = useAdminResource(
    () =>
      adminApi.users.list({
        q: query || undefined,
        "filter[status]": status || undefined,
        "filter[role]": role || undefined,
        "filter[plan]": plan || undefined,
        sort,
        page,
        per_page: 25,
      }),
    [query, status, role, plan, sort, page],
  );

  const roles = useAdminResource(() => adminApi.roles.list(), []);

  if (error) return <LoadError error={error} onRetry={reload} />;

  const columns: Column<AdminUser>[] = [
    {
      key: "person",
      header: "Person",
      cell: (row) => (
        <span className="flex min-w-0 items-center gap-2.5">
          <span
            aria-hidden="true"
            className="flex size-8 shrink-0 items-center justify-center rounded-full bg-[var(--color-primary-subtle)] text-[0.6875rem] font-semibold text-[var(--color-primary)]"
          >
            {row.initials}
          </span>
          <span className="flex min-w-0 flex-col">
            <span className="flex items-center gap-1.5">
              <span className="truncate font-medium text-[var(--color-foreground)]">
                {row.display_name}
              </span>
              {row.is_staff && (
                <ShieldCheck
                  className="size-3.5 shrink-0 text-[var(--color-accent)]"
                  aria-label="Staff account"
                />
              )}
            </span>
            <span className="truncate text-xs text-[var(--color-foreground-subtle)]">
              {row.email}
            </span>
          </span>
        </span>
      ),
    },
    {
      key: "status",
      header: "Status",
      cell: (row) => (
        <span className="flex flex-wrap gap-1">
          <StatusPill label={humanise(row.status)} tone={tone.user(row.status)} />
          {row.deleted_at && <StatusPill label="Deleted" tone="danger" />}
          {!row.email_verified && <StatusPill label="Unverified" tone="warning" />}
        </span>
      ),
    },
    {
      key: "roles",
      header: "Roles",
      hideBelow: "lg",
      cell: (row) =>
        row.roles.length === 0 ? (
          <span className="text-[var(--color-foreground-subtle)]">Customer</span>
        ) : (
          <span className="flex flex-wrap gap-1">
            {row.roles.map((name) => (
              <StatusPill key={name} label={name} tone="info" />
            ))}
          </span>
        ),
    },
    {
      key: "runs",
      header: "Runs",
      numeric: true,
      hideBelow: "md",
      cell: (row) => formatNumber(row.runs_count ?? 0),
    },
    {
      key: "created_at",
      header: "Joined",
      sortKey: "created_at",
      numeric: true,
      hideBelow: "sm",
      cell: (row) => (
        <span className="text-xs text-[var(--color-foreground-subtle)]">
          {row.created_at ? relativeTime(row.created_at) : "—"}
        </span>
      ),
    },
    {
      key: "last_seen_at",
      header: "Last seen",
      sortKey: "last_seen_at",
      numeric: true,
      hideBelow: "xl",
      cell: (row) => (
        <span className="text-xs text-[var(--color-foreground-subtle)]">
          {row.last_seen_at ? relativeTime(row.last_seen_at) : "Never"}
        </span>
      ),
    },
  ];

  return (
    <>
      <AdminPageHeader
        eyebrow="People"
        title="Users"
        description="Every account, what they are entitled to, and what they have been doing."
      />

      <AdminPanel
        title="All accounts"
        description={
          data ? `${data.meta.page.total.toLocaleString()} matching` : "Loading…"
        }
        bodyClassName="p-0"
        action={
          <div className="flex flex-wrap items-center gap-2">
            <SearchInput
              value={query}
              onChange={(next) => setFilters({ query: next })}
              placeholder="Email, name or id…"
              className="w-56"
            />
            <FilterSelect
              label="Status"
              value={status}
              onChange={(next) => setFilters({ status: next })}
              options={[
                { value: "", label: "Any" },
                { value: "active", label: "Active" },
                { value: "suspended", label: "Suspended" },
                { value: "pending", label: "Pending" },
              ]}
            />
            <FilterSelect
              label="Plan"
              value={plan}
              onChange={(next) => setFilters({ plan: next })}
              options={[
                { value: "", label: "Any" },
                { value: "paid", label: "Paying" },
                { value: "free", label: "Free" },
              ]}
            />
            <FilterSelect
              label="Role"
              value={role}
              onChange={(next) => setFilters({ role: next })}
              options={[
                { value: "", label: "Any" },
                ...(roles.data?.data ?? []).map((entry) => ({
                  value: entry.name,
                  label: entry.name,
                })),
              ]}
            />
          </div>
        }
      >
        <DataTable
          rows={data?.data ?? []}
          columns={columns}
          rowKey={(row) => row.id}
          loading={loading}
          sort={sort}
          onSortChange={(next) => setFilters({ sort: next })}
          onRowClick={(row) => router.push(`/c0ns0le/users/${row.id}`)}
          empty={
            <div className="px-4 py-12 text-center">
              <p className="text-sm font-medium text-[var(--color-foreground)]">
                Nobody matches those filters
              </p>
              <p className="mt-1 text-sm text-[var(--color-foreground-muted)]">
                Try a partial email, or{" "}
                <button
                  type="button"
                  onClick={() => setFilters({ query: "", status: "", role: "", plan: "" })}
                  className="text-[var(--color-primary)] underline-offset-4 hover:underline"
                >
                  clear every filter
                </button>
                .
              </p>
            </div>
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

      <p className="mt-4 text-xs text-[var(--color-foreground-subtle)]">
        Looking for who can do what?{" "}
        <Link
          href="/c0ns0le/roles"
          className="text-[var(--color-primary)] underline-offset-4 hover:underline"
        >
          Roles and permissions
        </Link>{" "}
        is where access is composed.
      </p>
    </>
  );
}
