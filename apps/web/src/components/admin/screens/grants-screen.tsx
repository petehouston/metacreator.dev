"use client";

import { Plus, Sparkles, Trash2 } from "lucide-react";
import Link from "next/link";
import * as React from "react";

import { AdminPageHeader, AdminPanel, StatusPill } from "@/components/admin/admin-page";
import { Can } from "@/components/admin/can";
import { ConfirmDialog, useToast } from "@/components/admin/feedback";
import { DataTable, Pagination, type Column } from "@/components/admin/data-table";
import { LoadError } from "@/components/admin/load-error";
import { FilterSelect, SearchInput } from "@/components/admin/toolbar";
import { Button } from "@/components/ui/button";
import { adminApi } from "@/lib/admin/api";
import type { ToolGrant } from "@/lib/admin/types";
import { usePagedFilters } from "@/lib/admin/use-paged-filters";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import { formatDate, relativeTime } from "@/lib/utils";

/**
 * Comped access, made visible.
 *
 * The requirement is "an admin can grant a specific user access to a specific
 * tool". The discipline around it is this screen: a comp that nobody can see is
 * revenue leaking out through support conversations, so every grant is listed,
 * attributed to whoever gave it, and expirable.
 *
 * Giving one happens at `/admin/grants/new`, which a user's detail screen deep-links
 * with their email already filled in.
 */
export function GrantsScreen() {
  const { notify, reportError } = useToast();

  const [{ query, state }, setFilters, page, setPage] = usePagedFilters({
    query: "",
    state: "active",
  });

  const [revoking, setRevoking] = React.useState<ToolGrant | null>(null);
  const [pending, setPending] = React.useState(false);

  const { data, error, loading, reload } = useAdminResource(
    () =>
      adminApi.grants.list({
        q: query || undefined,
        "filter[state]": state || undefined,
        page,
        per_page: 25,
      }),
    [query, state, page],
  );

  if (error) return <LoadError error={error} onRetry={reload} />;

  async function revoke() {
    if (!revoking) return;

    setPending(true);
    const result = await adminApi.grants.remove(revoking.id);
    setPending(false);

    if (result.ok) {
      notify(`Access to ${revoking.tool?.name ?? "the tool"} was revoked.`);
      setRevoking(null);
      reload();
    } else {
      reportError(result.error);
    }
  }

  const columns: Column<ToolGrant>[] = [
    {
      key: "user",
      header: "Person",
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
      key: "tool",
      header: "Tool",
      cell: (row) => (
        <span className="flex min-w-0 items-center gap-2">
          <span className="truncate text-[var(--color-foreground)]">{row.tool?.name ?? "—"}</span>
          {row.tool && <StatusPill label={row.tool.tier} tone="warning" />}
        </span>
      ),
    },
    {
      key: "reason",
      header: "Reason",
      hideBelow: "md",
      cell: (row) => (
        <span className="block max-w-xs truncate text-xs text-[var(--color-foreground-muted)]">
          {row.reason ?? "—"}
        </span>
      ),
    },
    {
      key: "granted_by",
      header: "Given by",
      hideBelow: "lg",
      cell: (row) => row.granted_by ?? "—",
    },
    {
      key: "expires",
      header: "Expires",
      cell: (row) => (
        <StatusPill
          label={
            row.is_active
              ? row.expires_at
                ? formatDate(row.expires_at)
                : "Never"
              : "Expired"
          }
          tone={row.is_active ? (row.expires_at ? "success" : "warning") : "muted"}
        />
      ),
    },
    {
      key: "created",
      header: "Given",
      numeric: true,
      hideBelow: "xl",
      cell: (row) => (
        <span className="text-xs text-[var(--color-foreground-subtle)]">
          {row.created_at ? relativeTime(row.created_at) : "—"}
        </span>
      ),
    },
    {
      key: "actions",
      header: "",
      width: "3rem",
      cell: (row) => (
        <Can permission="tool_grants.delete">
          <button
            type="button"
            onClick={() => setRevoking(row)}
            aria-label={`Revoke access for ${row.user?.email ?? "this person"}`}
            className="flex size-7 items-center justify-center rounded-[var(--radius-sm)] text-[var(--color-foreground-subtle)] transition-colors hover:bg-[var(--color-danger)]/10 hover:text-[var(--color-danger)]"
          >
            <Trash2 className="size-3.5" aria-hidden="true" />
          </button>
        </Can>
      ),
    },
  ];

  return (
    <>
      <AdminPageHeader
        eyebrow="Product"
        title="Tool grants"
        description="Access given by hand, to one person, for one tool. Every grant is attributed and audited — and a grant with no expiry is a subscription you are not being paid for."
        actions={
          <Can permission="tool_grants.create">
            <Button size="sm" asChild>
              <Link href="/admin/grants/new">
                <Plus className="size-4" aria-hidden="true" />
                Grant access
              </Link>
            </Button>
          </Can>
        }
      />

      <AdminPanel
        title="Grants"
        description={data ? `${data.meta.page.total} total` : "Loading…"}
        bodyClassName="p-0"
        action={
          <div className="flex flex-wrap items-center gap-2">
            <SearchInput
              value={query}
              onChange={(next) => setFilters({ query: next })}
              placeholder="Filter by email…"
              className="w-48"
            />
            <FilterSelect
              label="State"
              value={state}
              onChange={(next) => setFilters({ state: next })}
              options={[
                { value: "", label: "All" },
                { value: "active", label: "Active" },
                { value: "expired", label: "Expired" },
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
            <div className="px-4 py-12 text-center">
              <span className="mx-auto mb-3 flex size-11 items-center justify-center rounded-full bg-[var(--color-surface-sunken)] text-[var(--color-foreground-subtle)]">
                <Sparkles className="size-5" aria-hidden="true" />
              </span>
              <p className="text-sm font-semibold text-[var(--color-foreground)]">
                Nothing is comped
              </p>
              <p className="mx-auto mt-1 max-w-sm text-sm text-[var(--color-foreground-muted)]">
                Everyone&rsquo;s access comes from their plan. That is the healthy
                state — grants are for apologies and trials, not for pricing.
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

      <ConfirmDialog
        open={revoking !== null}
        title="Revoke this grant?"
        description={
          <>
            <p>
              {revoking?.user?.display_name ?? "This person"} loses access to{" "}
              {revoking?.tool?.name ?? "the tool"} immediately, unless their plan
              covers it anyway.
            </p>
            <p className="mt-2">They are not notified.</p>
          </>
        }
        confirmLabel="Revoke access"
        destructive
        pending={pending}
        onConfirm={() => void revoke()}
        onCancel={() => setRevoking(null)}
      />
    </>
  );
}
