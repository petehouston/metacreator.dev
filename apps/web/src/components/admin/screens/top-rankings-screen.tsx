"use client";

import {
  AlertTriangle,
  ImageOff,
  Plus,
  RefreshCw,
  Trash2,
  Trophy,
} from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import * as React from "react";

import { AdminPageHeader, StatusPill } from "@/components/admin/admin-page";
import { Can } from "@/components/admin/can";
import { DataTable, type Column } from "@/components/admin/data-table";
import { ConfirmDialog, useToast } from "@/components/admin/feedback";
import { LoadError } from "@/components/admin/load-error";
import { FilterSelect, SearchInput } from "@/components/admin/toolbar";
import { Button } from "@/components/ui/button";
import { adminApi } from "@/lib/admin/api";
import type { AdminTopRankingPage } from "@/lib/admin/types";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import { usePagedFilters } from "@/lib/admin/use-paged-filters";
import { relativeTime } from "@/lib/utils";

/**
 * Every ranking page, and the two facts that decide whether one needs attention.
 *
 * **Freshness first.** These pages are maintained by a weekly job nobody watches,
 * so the failure mode is not an error on screen — it is a page quietly serving a
 * year-old ranking because Wikipedia renamed its article in March. The sync column
 * says when each page last succeeded and, when the last run had a problem, exactly
 * what it said.
 *
 * **Missing pictures second.** A count rather than a percentage: "12 without a
 * picture" is a number an editor can act on, and it is expected to be non-zero on
 * some platforms rather than a fault to chase to zero.
 */
export function TopRankingsScreen() {
  const router = useRouter();
  const { notify, reportError } = useToast();

  const [{ query, platform }, setFilters] = usePagedFilters({
    query: "",
    platform: "",
  });
  const [deleting, setDeleting] = React.useState<AdminTopRankingPage | null>(
    null,
  );
  const [pending, setPending] = React.useState(false);
  const [syncing, setSyncing] = React.useState<string | null>(null);

  const { data, error, loading, reload } = useAdminResource(
    () =>
      adminApi.topRankings.list({
        q: query || undefined,
        platform: platform || undefined,
      }),
    [query, platform],
  );

  if (error) return <LoadError error={error} onRetry={reload} />;

  const pages = data?.data ?? [];
  const platforms = data?.meta.platforms ?? [];

  async function sync(page: AdminTopRankingPage) {
    setSyncing(page.id);
    const result = await adminApi.topRankings.sync(page.id);
    setSyncing(null);

    if (result.ok) {
      // The message is the server's own summary of what changed, not a generic
      // "done" — a run that removed forty rows is worth reading about.
      notify(result.data.sync_message ?? `“${page.title}” synced.`);
      reload();
    } else {
      reportError(result.error);
    }
  }

  async function remove() {
    if (!deleting) return;

    setPending(true);
    const result = await adminApi.topRankings.remove(deleting.id);
    setPending(false);

    if (result.ok) {
      notify(`“${deleting.title}” was deleted.`);
      setDeleting(null);
      reload();
    } else {
      reportError(result.error);
    }
  }

  const columns: Column<AdminTopRankingPage>[] = [
    {
      key: "title",
      header: "Ranking",
      cell: (row) => (
        <span className="flex min-w-0 items-center gap-2.5">
          <span
            aria-hidden="true"
            className="size-2 shrink-0 rounded-full"
            style={{ backgroundColor: `oklch(${row.platform_accent})` }}
          />

          <span className="flex min-w-0 flex-col">
            <Link
              href={`/c0ns0le/top-rankings/${row.id}`}
              onClick={(event) => event.stopPropagation()}
              className="truncate font-medium text-[var(--color-foreground)] hover:text-[var(--color-primary)]"
            >
              {row.title}
            </Link>

            <span className="truncate font-mono text-[0.6875rem] text-[var(--color-foreground-subtle)]">
              {row.platform_label} · /top-ranking/{row.slug}
            </span>
          </span>
        </span>
      ),
    },
    {
      key: "entries",
      header: "Rows",
      numeric: true,
      cell: (row) => (
        <span className="tabular">
          {row.entries_count}
          <span className="text-[var(--color-foreground-subtle)]">
            {" "}
            / {row.row_limit}
          </span>
        </span>
      ),
    },
    {
      key: "avatars",
      header: "Pictures",
      hideBelow: "md",
      cell: (row) =>
        row.missing_avatars ? (
          <span className="inline-flex items-center gap-1 text-xs text-[var(--color-foreground-muted)]">
            <ImageOff
              className="size-3.5 text-[var(--color-warning)]"
              aria-hidden="true"
            />
            {row.missing_avatars} missing
          </span>
        ) : (
          <span className="text-xs text-[var(--color-foreground-subtle)]">
            All resolved
          </span>
        ),
    },
    {
      key: "sync",
      header: "Last sync",
      hideBelow: "sm",
      cell: (row) => (
        <span className="flex flex-col gap-0.5">
          <span className="flex items-center gap-1.5">
            <StatusPill
              label={row.sync_status_label}
              tone={
                row.sync_status === "ok"
                  ? "success"
                  : row.sync_status === "never"
                    ? "muted"
                    : row.sync_status === "partial"
                      ? "warning"
                      : "danger"
              }
            />

            {row.synced_at && (
              <span className="text-xs text-[var(--color-foreground-subtle)]">
                {relativeTime(row.synced_at)}
              </span>
            )}
          </span>

          {/* Only shown when the last run had something to complain about. A
              successful summary on every row is noise that hides the one that
              matters. */}
          {row.sync_status !== "ok" && row.sync_message && (
            <span className="flex items-start gap-1 text-[0.6875rem] leading-snug text-[var(--color-foreground-muted)]">
              <AlertTriangle
                className="mt-0.5 size-3 shrink-0 text-[var(--color-warning)]"
                aria-hidden="true"
              />
              <span className="line-clamp-2">{row.sync_message}</span>
            </span>
          )}
        </span>
      ),
    },
    {
      key: "state",
      header: "State",
      hideBelow: "lg",
      cell: (row) => (
        <StatusPill
          label={row.is_published ? "Published" : "Hidden"}
          tone={row.is_published ? "success" : "muted"}
        />
      ),
    },
    {
      key: "actions",
      header: "",
      cell: (row) => (
        <span className="flex items-center justify-end gap-1">
          <Can permission="top_rankings.sync">
            <Button
              variant="ghost"
              size="sm"
              disabled={syncing !== null}
              onClick={(event) => {
                event.stopPropagation();
                void sync(row);
              }}
              title="Re-read the Wikipedia article now"
            >
              <RefreshCw
                className={
                  syncing === row.id ? "size-3.5 animate-spin" : "size-3.5"
                }
                aria-hidden="true"
              />
              <span className="sr-only">Sync {row.title}</span>
            </Button>
          </Can>

          <Can permission="top_rankings.delete">
            <Button
              variant="ghost"
              size="sm"
              onClick={(event) => {
                event.stopPropagation();
                setDeleting(row);
              }}
            >
              <Trash2
                className="size-3.5 text-[var(--color-danger)]"
                aria-hidden="true"
              />
              <span className="sr-only">Delete {row.title}</span>
            </Button>
          </Can>
        </span>
      ),
    },
  ];

  return (
    <>
      <AdminPageHeader
        eyebrow="Content"
        title="Top rankings"
        description="Leaderboards built from Wikipedia's community-maintained lists. A scheduled job refreshes every page weekly; the buttons here are for when you need it sooner."
        actions={
          <Can permission="top_rankings.create">
            <Button asChild size="sm">
              <Link href="/c0ns0le/top-rankings/new">
                <Plus className="size-4" aria-hidden="true" />
                New ranking
              </Link>
            </Button>
          </Can>
        }
      />

      <div className="mb-4 flex flex-wrap items-center gap-3">
        <SearchInput
          value={query}
          onChange={(value) => setFilters({ query: value })}
          placeholder="Search rankings…"
          className="w-full sm:max-w-xs"
        />

        <FilterSelect
          label="Platform"
          value={platform}
          onChange={(value) => setFilters({ platform: value })}
          options={[
            { value: "", label: "All" },
            ...platforms.map((option) => ({
              value: option.value,
              label: option.label,
            })),
          ]}
        />
      </div>

      <div className="app-card overflow-hidden">
        <DataTable
          rows={pages}
          columns={columns}
          rowKey={(row) => row.id}
          loading={loading}
          onRowClick={(row) => router.push(`/c0ns0le/top-rankings/${row.id}`)}
          empty={
            <div className="flex flex-col items-center gap-2 px-6 py-16 text-center">
              <Trophy
                className="size-6 text-[var(--color-foreground-subtle)]"
                aria-hidden="true"
              />
              <p className="text-sm font-medium">No rankings yet</p>
              <p className="max-w-sm text-xs text-[var(--color-foreground-muted)]">
                Rankings ship as seeded configuration. If this list is empty,
                run{" "}
                <code className="font-mono">
                  php artisan db:seed --class=TopRankingSeeder
                </code>
                .
              </p>
            </div>
          }
        />
      </div>

      <ConfirmDialog
        open={deleting !== null}
        title="Delete this ranking?"
        description={
          deleting
            ? `“${deleting.title}” and its ${deleting.entries_count} rows will be removed, and /top-ranking/${deleting.slug} will 404. A sync can rebuild the rows, but anything added or reordered by hand is gone.`
            : ""
        }
        confirmLabel="Delete"
        destructive
        pending={pending}
        onConfirm={remove}
        onCancel={() => setDeleting(null)}
      />
    </>
  );
}
