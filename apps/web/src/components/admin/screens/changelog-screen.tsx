"use client";

import { CircleDot, Eye, Plus, Rocket, Trash2 } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import * as React from "react";

import { AdminPageHeader, AdminPanel, StatusPill } from "@/components/admin/admin-page";
import { Can } from "@/components/admin/can";
import { DataTable, Pagination, type Column } from "@/components/admin/data-table";
import { ConfirmDialog, useToast } from "@/components/admin/feedback";
import { LoadError } from "@/components/admin/load-error";
import { tone } from "@/components/admin/status-tone";
import { CountTabs, SearchInput } from "@/components/admin/toolbar";
import { Button } from "@/components/ui/button";
import { adminApi } from "@/lib/admin/api";
import type { AdminChangelogRelease } from "@/lib/admin/types";
import { usePagedFilters } from "@/lib/admin/use-paged-filters";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import { relativeTime } from "@/lib/utils";

/**
 * Every release, in the order an editor cares about.
 *
 * The column that earns its place is "Live": a release can be marked Published and
 * still be invisible because its date is in the future, and an editor who cannot
 * see that difference will spend an afternoon wondering why customers are not
 * seeing an announcement. The status pill says what was *intended*; the live dot
 * says what is *true*.
 */
export function ChangelogScreen() {
  const router = useRouter();
  const { notify, reportError } = useToast();

  const [{ query, status }, setFilters, page, setPage] = usePagedFilters({
    query: "",
    status: "",
  });

  const [deleting, setDeleting] = React.useState<AdminChangelogRelease | null>(null);
  const [pending, setPending] = React.useState(false);

  const { data, error, loading, reload } = useAdminResource(
    () =>
      adminApi.changelog.list({
        q: query || undefined,
        status: status || undefined,
        page,
        per_page: 25,
      }),
    [query, status, page],
  );

  if (error) return <LoadError error={error} onRetry={reload} />;

  const counts = data?.meta.counts ?? {};

  async function publish(release: AdminChangelogRelease) {
    const result = await adminApi.changelog.publish(release.id);

    if (result.ok) {
      notify(`“${release.title}” is live.`);
      reload();
    } else {
      reportError(result.error);
    }
  }

  async function remove() {
    if (!deleting) return;

    setPending(true);
    const result = await adminApi.changelog.remove(deleting.id);
    setPending(false);

    if (result.ok) {
      notify(`“${deleting.title}” was deleted.`);
      setDeleting(null);
      reload();
    } else {
      reportError(result.error);
    }
  }

  const columns: Column<AdminChangelogRelease>[] = [
    {
      key: "title",
      header: "Release",
      cell: (row) => (
        <span className="flex min-w-0 flex-col">
          <span className="flex items-center gap-1.5">
            <Link
              href={`/c0ns0le/changelog/${row.id}`}
              onClick={(event) => event.stopPropagation()}
              className="truncate font-medium text-[var(--color-foreground)] hover:text-[var(--color-primary)]"
            >
              {row.title}
            </Link>

            {row.is_major && (
              <Rocket
                className="size-3.5 shrink-0 text-[var(--color-primary)]"
                aria-label="Major release"
              />
            )}
          </span>

          <span className="truncate font-mono text-[0.6875rem] text-[var(--color-foreground-subtle)]">
            {row.version ? `${row.version} · ` : ""}/{row.slug}
          </span>
        </span>
      ),
    },
    {
      key: "status",
      header: "Status",
      cell: (row) => <StatusPill label={row.status_label} tone={tone.release(row.status)} />,
    },
    {
      key: "live",
      header: "Live",
      hideBelow: "sm",
      cell: (row) => (
        <span className="flex items-center gap-1.5">
          <CircleDot
            className={
              row.is_live
                ? "size-3.5 text-[var(--color-success)]"
                : "size-3.5 text-[var(--color-foreground-subtle)]"
            }
            aria-hidden="true"
          />
          <span className="text-xs text-[var(--color-foreground-muted)]">
            {row.is_live ? "Public" : "Not yet"}
          </span>
        </span>
      ),
    },
    {
      key: "changes",
      header: "Changes",
      numeric: true,
      hideBelow: "md",
      cell: (row) => <span className="tabular text-sm">{row.items_count}</span>,
    },
    {
      key: "released",
      header: "Released",
      numeric: true,
      hideBelow: "sm",
      cell: (row) => (
        <span className="text-xs text-[var(--color-foreground-subtle)]">
          {row.released_at ? relativeTime(row.released_at) : "No date"}
        </span>
      ),
    },
    {
      key: "actions",
      header: "",
      width: "6rem",
      cell: (row) => (
        <span
          className="flex justify-end gap-0.5"
          // The row itself opens the editor; these do something else.
          onClick={(event) => event.stopPropagation()}
        >
          {!row.is_live && (
            <Can permission="changelog.publish">
              <button
                type="button"
                onClick={() => void publish(row)}
                aria-label={`Publish ${row.title} now`}
                title="Publish now"
                className="flex size-7 items-center justify-center rounded-[var(--radius-sm)] text-[var(--color-foreground-subtle)] transition-colors hover:bg-[var(--color-success)]/10 hover:text-[var(--color-success)]"
              >
                <Rocket className="size-3.5" aria-hidden="true" />
              </button>
            </Can>
          )}

          {row.is_live && (
            <Link
              href={`/changelog/${row.slug}`}
              target="_blank"
              rel="noreferrer"
              aria-label={`View ${row.title} on the site`}
              className="flex size-7 items-center justify-center rounded-[var(--radius-sm)] text-[var(--color-foreground-subtle)] transition-colors hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-foreground)]"
            >
              <Eye className="size-3.5" aria-hidden="true" />
            </Link>
          )}

          <Can permission="changelog.delete">
            <button
              type="button"
              onClick={() => setDeleting(row)}
              aria-label={`Delete ${row.title}`}
              className="flex size-7 items-center justify-center rounded-[var(--radius-sm)] text-[var(--color-foreground-subtle)] transition-colors hover:bg-[var(--color-danger)]/10 hover:text-[var(--color-danger)]"
            >
              <Trash2 className="size-3.5" aria-hidden="true" />
            </button>
          </Can>
        </span>
      ),
    },
  ];

  return (
    <>
      <AdminPageHeader
        eyebrow="Content"
        title="Changelog"
        description="What shipped and when. Publishing puts an entry in front of every customer, so a release stays a draft until you say otherwise."
        actions={
          <Can permission="changelog.create">
            <Button size="sm" onClick={() => router.push("/c0ns0le/changelog/new")}>
              <Plus className="size-4" aria-hidden="true" />
              New release
            </Button>
          </Can>
        }
      />

      <AdminPanel
        title="All releases"
        description={data ? `${data.meta.page.total} matching` : "Loading…"}
        bodyClassName="p-0"
        action={
          <SearchInput
            value={query}
            onChange={(next) => setFilters({ query: next })}
            placeholder="Search releases…"
            className="w-52"
          />
        }
      >
        <div className="border-b border-[var(--color-border-subtle)] px-3 py-2">
          <CountTabs
            value={status}
            onChange={(next) => setFilters({ status: next })}
            tabs={[
              { value: "", label: "All", count: counts.all },
              { value: "published", label: "Published", count: counts.published },
              { value: "scheduled", label: "Scheduled", count: counts.scheduled },
              { value: "draft", label: "Drafts", count: counts.draft },
            ]}
          />
        </div>

        <DataTable
          rows={data?.data ?? []}
          columns={columns}
          rowKey={(row) => row.id}
          loading={loading}
          onRowClick={(row) => router.push(`/c0ns0le/changelog/${row.id}`)}
          empty={
            <div className="flex flex-col items-center gap-2 px-4 py-16 text-center">
              <p className="text-sm font-medium text-[var(--color-foreground)]">
                {query === "" && status === ""
                  ? "No releases yet"
                  : "Nothing matches those filters"}
              </p>
              <p className="max-w-sm text-sm text-[var(--color-foreground-muted)]">
                {query === "" && status === ""
                  ? "A changelog is the cheapest retention tool there is. Write up what you shipped last week."
                  : "Try a different status, or clear the search."}
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
        open={deleting !== null}
        title={`Delete “${deleting?.title}”?`}
        description={
          <>
            <p>
              This removes the release and its {deleting?.items_count ?? 0}{" "}
              {deleting?.items_count === 1 ? "entry" : "entries"} permanently. There is no trash to
              recover it from.
            </p>
            {deleting?.is_live && (
              <p className="mt-2">
                It is live right now, so anyone holding a link to it will get a 404.
              </p>
            )}
          </>
        }
        confirmLabel="Delete"
        destructive
        pending={pending}
        onConfirm={() => void remove()}
        onCancel={() => setDeleting(null)}
      />
    </>
  );
}
