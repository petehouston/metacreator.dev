"use client";

import { ExternalLink, EyeOff, Star } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import * as React from "react";

import { AdminPageHeader, AdminPanel, StatusPill } from "@/components/admin/admin-page";
import { DataTable, Pagination, type Column } from "@/components/admin/data-table";
import { LoadError } from "@/components/admin/load-error";
import { humanise, tone } from "@/components/admin/status-tone";
import { FilterSelect, SearchInput } from "@/components/admin/toolbar";
import { Button } from "@/components/ui/button";
import { adminApi } from "@/lib/admin/api";
import type { AdminTool } from "@/lib/admin/types";
import { usePagedFilters } from "@/lib/admin/use-paged-filters";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import { formatNumber } from "@/lib/utils";

/**
 * The catalog, as an admin changes it.
 *
 * The list stays a list: clicking a row navigates to `/admin/tools/<slug>`, where
 * the whole tool is on screen at once and the URL is something that can be shared,
 * refreshed and bookmarked.
 */
export function ToolsScreen() {
  const router = useRouter();

  const [{ query, tier, status, category }, setFilters, page, setPage] = usePagedFilters({
    query: "",
    tier: "",
    status: "",
    category: "",
  });

  const { data, error, loading, reload } = useAdminResource(
    () =>
      adminApi.tools.list({
        q: query || undefined,
        "filter[tier]": tier || undefined,
        "filter[status]": status || undefined,
        "filter[category]": category || undefined,
        page,
        per_page: 40,
      }),
    [query, tier, status, category, page],
  );

  const categories = useAdminResource(() => adminApi.tools.categories(), []);

  if (error) return <LoadError error={error} onRetry={reload} />;

  const columns: Column<AdminTool>[] = [
    {
      key: "name",
      header: "Tool",
      cell: (row) => (
        <span className="flex min-w-0 flex-col">
          <span className="flex items-center gap-1.5">
            <span className="truncate font-medium text-[var(--color-foreground)]">{row.name}</span>
            {row.is_featured && (
              <Star
                className="size-3.5 shrink-0 fill-[var(--color-warning)] text-[var(--color-warning)]"
                aria-label="Featured"
              />
            )}
          </span>
          <span className="truncate font-mono text-[0.6875rem] text-[var(--color-foreground-subtle)]">
            {row.key}
          </span>
        </span>
      ),
    },
    {
      key: "category",
      header: "Category",
      hideBelow: "lg",
      cell: (row) => row.category?.name ?? "—",
    },
    {
      key: "tier",
      header: "Tier",
      cell: (row) => <StatusPill label={row.tier} tone={tone.tier(row.tier)} />,
    },
    {
      key: "status",
      header: "Status",
      cell: (row) => (
        <span className="flex flex-wrap items-center gap-1">
          <StatusPill label={humanise(row.status)} tone={tone.tool(row.status)} />
          {!row.is_visible && (
            <span title="Hidden from the catalog">
              <EyeOff className="size-3.5 text-[var(--color-foreground-subtle)]" aria-hidden="true" />
              <span className="sr-only">Hidden</span>
            </span>
          )}
        </span>
      ),
    },
    {
      key: "runs",
      header: "Lifetime runs",
      numeric: true,
      hideBelow: "sm",
      cell: (row) => formatNumber(row.stats.runs),
    },
    {
      key: "success",
      header: "Success",
      numeric: true,
      hideBelow: "md",
      cell: (row) => (
        <span
          style={{ color: row.stats.success_rate < 95 ? "var(--color-danger)" : undefined }}
        >
          {row.stats.success_rate}%
        </span>
      ),
    },
    {
      key: "grants",
      header: "Comped",
      numeric: true,
      hideBelow: "xl",
      cell: (row) =>
        row.stats.grants ? (
          <Link
            href="/admin/grants"
            className="text-[var(--color-primary)] hover:underline"
            onClick={(event) => event.stopPropagation()}
          >
            {row.stats.grants}
          </Link>
        ) : (
          <span className="text-[var(--color-foreground-subtle)]">—</span>
        ),
    },
    {
      key: "actions",
      header: "",
      width: "5rem",
      cell: (row) => (
        <span className="flex justify-end gap-1">
          <Link
            href={`/tools/${row.slug}`}
            target="_blank"
            rel="noreferrer"
            onClick={(event) => event.stopPropagation()}
            aria-label={`Open ${row.name} on the site`}
            className="flex size-7 items-center justify-center rounded-[var(--radius-sm)] text-[var(--color-foreground-subtle)] transition-colors hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-foreground)]"
          >
            <ExternalLink className="size-3.5" aria-hidden="true" />
          </Link>
        </span>
      ),
    },
  ];

  return (
    <>
      <AdminPageHeader
        eyebrow="Product"
        title="Tools"
        description="Tiering, visibility and catalog copy. Behaviour lives in the runner bound to each tool's key, which is fixed at deploy time."
        actions={
          <Button asChild variant="secondary" size="sm">
            <Link href="/admin/analytics">Tool analytics</Link>
          </Button>
        }
      />

      <AdminPanel
        title="Catalog"
        description={data ? `${data.meta.page.total} tools` : "Loading…"}
        bodyClassName="p-0"
        action={
          <div className="flex flex-wrap items-center gap-2">
            <SearchInput
              value={query}
              onChange={(next) => setFilters({ query: next })}
              placeholder="Find a tool…"
              className="w-48"
            />
            <FilterSelect
              label="Tier"
              value={tier}
              onChange={(next) => setFilters({ tier: next })}
              options={[
                { value: "", label: "All" },
                { value: "free", label: "Free" },
                { value: "account", label: "Account" },
                { value: "premium", label: "Premium" },
              ]}
            />
            <FilterSelect
              label="Status"
              value={status}
              onChange={(next) => setFilters({ status: next })}
              options={[
                { value: "", label: "All" },
                { value: "published", label: "Published" },
                { value: "draft", label: "Draft" },
                { value: "hidden", label: "Hidden" },
                { value: "deprecated", label: "Deprecated" },
              ]}
            />
            <FilterSelect
              label="Category"
              value={category}
              onChange={(next) => setFilters({ category: next })}
              options={[
                { value: "", label: "All" },
                ...(categories.data?.data ?? []).map((entry) => ({
                  value: entry.slug,
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
          onRowClick={(row) => router.push(`/admin/tools/${row.slug}`)}
          empty={
            <p className="px-4 py-12 text-center text-sm text-[var(--color-foreground-subtle)]">
              No tool matches those filters.
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
