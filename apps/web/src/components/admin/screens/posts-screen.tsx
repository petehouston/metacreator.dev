"use client";

import { Eye, FileText, Plus, Star } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import * as React from "react";

import { AdminPageHeader, AdminPanel, StatusPill } from "@/components/admin/admin-page";
import { Can, useCan } from "@/components/admin/can";
import { ConfirmDialog, useToast } from "@/components/admin/feedback";
import { DataTable, Pagination, type Column } from "@/components/admin/data-table";
import { LoadError } from "@/components/admin/load-error";
import { tone } from "@/components/admin/status-tone";
import { BulkBar, CountTabs, FilterSelect, SearchInput } from "@/components/admin/toolbar";
import { Button } from "@/components/ui/button";
import { adminApi } from "@/lib/admin/api";
import type { AdminPost } from "@/lib/admin/types";
import { usePagedFilters } from "@/lib/admin/use-paged-filters";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import { formatNumber, relativeTime } from "@/lib/utils";

const BULK_ACTIONS = [
  { value: "publish", label: "Publish", destructive: false },
  { value: "unpublish", label: "Unpublish", destructive: false },
  { value: "draft", label: "Move to draft", destructive: false },
  { value: "feature", label: "Feature", destructive: false },
  { value: "unfeature", label: "Unfeature", destructive: false },
  { value: "archive", label: "Archive", destructive: false },
  { value: "delete", label: "Move to trash", destructive: true },
] as const;

/**
 * The WordPress-shaped post list: status tabs with counts, search, and bulk
 * actions on a selection.
 *
 * The one place it deliberately differs is the bulk result. WordPress tells you
 * "7 posts updated"; this reports what was *skipped* too, because a contributor
 * selecting forty posts can only affect their own, and silently applying to
 * eleven of them is how someone concludes the button is broken.
 */
export function PostsScreen() {
  const router = useRouter();
  const can = useCan();
  const { notify, reportError } = useToast();

  const [{ query, status, category, sort }, applyFilters, page, setPage] = usePagedFilters({
    query: "",
    status: "",
    category: "",
    sort: "-updated_at",
  });

  const [selected, setSelected] = React.useState<string[]>([]);
  const [confirming, setConfirming] = React.useState<(typeof BULK_ACTIONS)[number] | null>(null);
  const [pending, setPending] = React.useState(false);

  /**
   * Clearing the selection alongside the filter is not tidiness — it is what stops
   * a bulk action from landing on rows that are no longer on screen.
   */
  function setFilters(patch: Partial<Record<"query" | "status" | "category" | "sort", string>>) {
    applyFilters(patch);
    setSelected([]);
  }

  const { data, error, loading, reload } = useAdminResource(
    () =>
      adminApi.posts.list({
        q: query || undefined,
        "filter[status]": status || undefined,
        "filter[category]": category || undefined,
        sort,
        page,
        per_page: 25,
      }),
    [query, status, category, sort, page],
  );

  const categories = useAdminResource(() => adminApi.taxonomy.categories(), []);

  if (error) return <LoadError error={error} onRetry={reload} />;

  const counts = data?.meta.counts ?? {};

  async function runBulk(action: string) {
    setPending(true);
    const result = await adminApi.posts.bulk(selected, action);
    setPending(false);
    setConfirming(null);

    if (!result.ok) {
      reportError(result.error);
      return;
    }

    const { applied, skipped } = result.data;

    notify(
      skipped.length === 0
        ? `${applied.length} ${applied.length === 1 ? "post" : "posts"} updated.`
        : `${applied.length} updated. ${skipped.length} skipped — either not yours to change, or the status transition is not allowed.`,
      skipped.length > 0 && applied.length === 0 ? "error" : "success",
    );

    setSelected([]);
    reload();
  }

  const columns: Column<AdminPost>[] = [
    {
      key: "title",
      header: "Title",
      cell: (row) => (
        <span className="flex min-w-0 flex-col">
          <span className="flex items-center gap-1.5">
            <Link
              href={`/c0ns0le/posts/${row.id}`}
              onClick={(event) => event.stopPropagation()}
              className="truncate font-medium text-[var(--color-foreground)] hover:text-[var(--color-primary)]"
            >
              {row.title}
            </Link>
            {row.is_featured && (
              <Star
                className="size-3.5 shrink-0 fill-[var(--color-warning)] text-[var(--color-warning)]"
                aria-label="Featured"
              />
            )}
          </span>
          <span className="truncate font-mono text-[0.6875rem] text-[var(--color-foreground-subtle)]">
            /{row.slug}
          </span>
        </span>
      ),
    },
    {
      key: "author",
      header: "Author",
      hideBelow: "lg",
      cell: (row) => row.author?.display_name ?? "—",
    },
    {
      key: "category",
      header: "Category",
      hideBelow: "xl",
      cell: (row) => row.category?.name ?? "—",
    },
    {
      key: "status",
      header: "Status",
      cell: (row) => (
        <span className="flex flex-col gap-0.5">
          <StatusPill
            label={row.deleted_at ? "Trashed" : row.status_label}
            tone={row.deleted_at ? "danger" : tone.post(row.status)}
          />
          {row.status === "scheduled" && row.scheduled_for && (
            <span className="text-[0.625rem] text-[var(--color-foreground-subtle)]">
              {relativeTime(row.scheduled_for)}
            </span>
          )}
        </span>
      ),
    },
    {
      key: "views",
      header: "Views",
      numeric: true,
      sortKey: "view_count",
      hideBelow: "md",
      cell: (row) => formatNumber(row.view_count),
    },
    {
      key: "updated",
      header: "Updated",
      numeric: true,
      sortKey: "updated_at",
      hideBelow: "sm",
      cell: (row) => (
        <span className="text-xs text-[var(--color-foreground-subtle)]">
          {row.updated_at ? relativeTime(row.updated_at) : "—"}
        </span>
      ),
    },
    {
      key: "actions",
      header: "",
      width: "3rem",
      cell: (row) =>
        row.status === "published" ? (
          <Link
            href={`/blog/${row.slug}`}
            target="_blank"
            rel="noreferrer"
            onClick={(event) => event.stopPropagation()}
            aria-label={`View ${row.title} on the site`}
            className="flex size-7 items-center justify-center rounded-[var(--radius-sm)] text-[var(--color-foreground-subtle)] transition-colors hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-foreground)]"
          >
            <Eye className="size-3.5" aria-hidden="true" />
          </Link>
        ) : null,
    },
  ];

  return (
    <>
      <AdminPageHeader
        eyebrow="Content"
        title="Posts"
        description="Everything written, at every stage of its life."
        actions={
          <Can permission="posts.create">
            <Button size="sm" onClick={() => router.push("/c0ns0le/posts/new")}>
              <Plus className="size-4" aria-hidden="true" />
              New post
            </Button>
          </Can>
        }
      />

      <AdminPanel
        title="All posts"
        description={data ? `${data.meta.page.total} matching` : "Loading…"}
        bodyClassName="p-0"
        action={
          <div className="flex flex-wrap items-center gap-2">
            <SearchInput
              value={query}
              onChange={(next) => setFilters({ query: next })}
              placeholder="Search titles…"
              className="w-48"
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
        <div className="border-b border-[var(--color-border-subtle)] px-3 py-2">
          <CountTabs
            value={status}
            onChange={(next) => setFilters({ status: next })}
            tabs={[
              { value: "", label: "All" },
              { value: "published", label: "Published", count: counts.published },
              { value: "draft", label: "Drafts", count: counts.draft },
              { value: "scheduled", label: "Scheduled", count: counts.scheduled },
              { value: "unpublished", label: "Unpublished", count: counts.unpublished },
              { value: "archived", label: "Archived", count: counts.archived },
              { value: "trashed", label: "Trash", count: counts.trashed },
            ]}
          />
        </div>

        <DataTable
          rows={data?.data ?? []}
          columns={columns}
          rowKey={(row) => row.id}
          loading={loading}
          selectable={can(["posts.update", "posts.update.own"])}
          selected={selected}
          onSelectedChange={setSelected}
          sort={sort}
          onSortChange={(next) => setFilters({ sort: next })}
          onRowClick={(row) => router.push(`/c0ns0le/posts/${row.id}`)}
          empty={
            <div className="px-4 py-12 text-center">
              <span className="mx-auto mb-3 flex size-11 items-center justify-center rounded-full bg-[var(--color-surface-sunken)] text-[var(--color-foreground-subtle)]">
                <FileText className="size-5" aria-hidden="true" />
              </span>
              <p className="text-sm font-semibold text-[var(--color-foreground)]">
                {status === "trashed" ? "The trash is empty" : "Nothing here yet"}
              </p>
              <p className="mx-auto mt-1 max-w-sm text-sm text-[var(--color-foreground-muted)]">
                {status === "trashed"
                  ? "Deleted posts stay recoverable for thirty days."
                  : "Write the first one — it will show up here the moment it is saved as a draft."}
              </p>
              {status !== "trashed" && (
                <Can permission="posts.create">
                  <Button size="sm" className="mt-4" onClick={() => router.push("/c0ns0le/posts/new")}>
                    <Plus className="size-4" aria-hidden="true" />
                    New post
                  </Button>
                </Can>
              )}
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

      <BulkBar count={selected.length} onClear={() => setSelected([])}>
        {status === "trashed" ? (
          <Button size="sm" variant="secondary" onClick={() => void runBulk("restore")}>
            Restore
          </Button>
        ) : (
          BULK_ACTIONS.map((action) => (
            <Button
              key={action.value}
              size="sm"
              variant={action.destructive ? "danger" : "secondary"}
              onClick={() =>
                action.destructive ? setConfirming(action) : void runBulk(action.value)
              }
            >
              {action.label}
            </Button>
          ))
        )}
      </BulkBar>

      <ConfirmDialog
        open={confirming !== null}
        title={`Move ${selected.length} ${selected.length === 1 ? "post" : "posts"} to the trash?`}
        description="Trashed posts are recoverable for thirty days and their URLs return 410 in the meantime, so search engines drop them promptly rather than re-crawling for months."
        confirmLabel="Move to trash"
        destructive
        pending={pending}
        onConfirm={() => void runBulk("delete")}
        onCancel={() => setConfirming(null)}
      />
    </>
  );
}
