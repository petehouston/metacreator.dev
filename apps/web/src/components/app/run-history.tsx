"use client";

import { Filter, RotateCw, Sparkles } from "lucide-react";
import Link from "next/link";
import * as React from "react";

import { EmptyState } from "@/components/app/empty-state";
import { useEntitlements } from "@/components/app/entitlements-provider";
import { formatDuration } from "@/components/app/overview";
import { RunStatusBadge } from "@/components/app/run-status-badge";
import { FormAlert } from "@/components/auth/form-alert";
import { Button } from "@/components/ui/button";
import { apiFetch } from "@/lib/http";
import type { Paginated, ToolRun } from "@/lib/types";
import { cn } from "@/lib/utils";

const STATUSES: ToolRun["status"][] = ["succeeded", "failed", "running", "queued", "cancelled"];

/**
 * Everything this account has run, windowed by the plan's `history_days`.
 *
 * A row opens `/dashboard/runs/<id>`, which is an address: it survives a refresh,
 * it can be pasted into a support thread, and the back button returns to the list
 * rather than half-closing something on top of it.
 */
export function RunHistory() {
  const { entitlements } = useEntitlements();

  const [page, setPage] = React.useState(1);
  const [status, setStatus] = React.useState<ToolRun["status"] | null>(null);
  const [reloadToken, setReloadToken] = React.useState(0);

  /**
   * One request, identified by everything that shapes it.
   *
   * `loading` is derived from "which request is on screen" rather than held as its
   * own flag: a flag has to be set before the fetch starts, which means setting
   * state inside an effect body and paying for a cascading render on every filter
   * change. Comparing keys costs nothing and cannot fall out of sync.
   */
  const requestKey = `${page}|${status ?? "any"}|${reloadToken}`;

  const [loaded, setLoaded] = React.useState<{
    key: string;
    runs: ToolRun[];
    lastPage: number;
    total: number;
    error: string | null;
  } | null>(null);

  const loading = loaded?.key !== requestKey;
  // The previous page stays on screen while the next one loads, so switching a
  // filter does not blank the table and bounce the scroll position.
  const runs = loaded?.runs ?? [];
  const lastPage = loaded?.lastPage ?? 1;
  const total = loaded?.total ?? 0;
  const error = loaded?.error ?? null;

  React.useEffect(() => {
    let cancelled = false;

    void (async () => {
      const result = await apiFetch<Paginated<ToolRun>>("/account/tool-runs", {
        searchParams: {
          page,
          per_page: 20,
          "filter[status]": status ?? undefined,
        },
      });

      if (cancelled) return;

      setLoaded((previous) =>
        result.ok
          ? {
              key: requestKey,
              runs: result.data.data,
              lastPage: result.data.meta.page.last_page,
              total: result.data.meta.page.total,
              error: null,
            }
          : {
              key: requestKey,
              runs: previous?.runs ?? [],
              lastPage: previous?.lastPage ?? 1,
              total: previous?.total ?? 0,
              error: result.error.message,
            },
      );
    })();

    return () => {
      cancelled = true;
    };
  }, [requestKey, page, status]);

  const historyDays = entitlements?.limits.history_days ?? null;

  return (
    <div className="flex flex-col gap-4">
      {error && <FormAlert>{error}</FormAlert>}

      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex flex-wrap items-center gap-1.5">
          <Filter className="size-3.5 text-[var(--color-foreground-subtle)]" aria-hidden="true" />

          <StatusChip active={status === null} onClick={() => setStatus(null)}>
            All
          </StatusChip>

          {STATUSES.map((option) => (
            <StatusChip
              key={option}
              active={status === option}
              onClick={() => {
                setStatus(status === option ? null : option);
                setPage(1);
              }}
            >
              {option}
            </StatusChip>
          ))}
        </div>

        <div className="flex items-center gap-3">
          <span className="tabular font-mono text-[0.6875rem] text-[var(--color-foreground-subtle)]">
            {total} run{total === 1 ? "" : "s"}
            {historyDays !== null && ` · ${historyDays}-day window`}
          </span>

          <Button
            variant="ghost"
            size="sm"
            onClick={() => setReloadToken((token) => token + 1)}
            aria-label="Refresh"
          >
            <RotateCw className={cn("size-4", loading && "animate-spin")} aria-hidden="true" />
          </Button>
        </div>
      </div>

      {loading && runs.length === 0 ? (
        <div className="app-card h-64 animate-pulse" aria-hidden="true" />
      ) : runs.length === 0 ? (
        <EmptyState
          icon={Sparkles}
          title={status ? `No ${status} runs` : "No runs in your history yet"}
          description={
            status
              ? "Clear the filter to see everything you have run."
              : "Run any tool once and it will be waiting for you here."
          }
          action={
            status ? (
              <Button size="sm" variant="secondary" onClick={() => setStatus(null)}>
                Clear filter
              </Button>
            ) : (
              <Button asChild size="sm">
                <Link href="/tools">Browse tools</Link>
              </Button>
            )
          }
        />
      ) : (
        <div className="app-card overflow-x-auto">
          <table className="w-full min-w-[40rem] text-sm">
            <thead>
              <tr className="border-b border-[var(--color-border-subtle)] text-left font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
                <th scope="col" className="px-4 py-2.5 font-medium">Tool</th>
                <th scope="col" className="px-4 py-2.5 font-medium">When</th>
                <th scope="col" className="px-4 py-2.5 font-medium">Duration</th>
                <th scope="col" className="px-4 py-2.5 font-medium">Status</th>
                <th scope="col" className="px-4 py-2.5 font-medium text-right">Result</th>
              </tr>
            </thead>

            <tbody className="divide-y divide-[var(--color-border-subtle)]">
              {runs.map((run) => (
                <tr
                  key={run.id}
                  className="transition-colors hover:bg-[var(--color-surface-sunken)]"
                >
                  <td className="px-4 py-3">
                    {run.tool ? (
                      <Link
                        href={`/tools/${run.tool.slug}`}
                        className="font-medium text-[var(--color-foreground)] hover:text-[var(--color-primary)]"
                      >
                        {run.tool.name}
                      </Link>
                    ) : (
                      <span className="text-[var(--color-foreground-muted)]">Unknown tool</span>
                    )}
                  </td>

                  <td className="px-4 py-3 text-[var(--color-foreground-muted)]">
                    {formatDateTime(run.created_at)}
                  </td>

                  <td className="tabular px-4 py-3 text-[var(--color-foreground-muted)]">
                    {run.meta.duration_ms === null
                      ? "—"
                      : formatDuration(run.meta.duration_ms)}
                    {run.meta.cache_hit && (
                      <span className="ml-2 font-mono text-[0.625rem] text-[var(--color-foreground-subtle)]">
                        cached
                      </span>
                    )}
                  </td>

                  <td className="px-4 py-3">
                    <RunStatusBadge status={run.status} />
                  </td>

                  <td className="px-4 py-3 text-right">
                    <Button variant="ghost" size="sm" asChild>
                      <Link href={`/dashboard/runs/${run.id}`}>View</Link>
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {lastPage > 1 && (
        <div className="flex items-center justify-between">
          <Button
            variant="secondary"
            size="sm"
            disabled={page <= 1}
            onClick={() => setPage((current) => current - 1)}
          >
            Previous
          </Button>

          <span className="tabular text-sm text-[var(--color-foreground-subtle)]">
            Page {page} of {lastPage}
          </span>

          <Button
            variant="secondary"
            size="sm"
            disabled={page >= lastPage}
            onClick={() => setPage((current) => current + 1)}
          >
            Next
          </Button>
        </div>
      )}
    </div>
  );
}

function StatusChip({
  active,
  onClick,
  children,
}: {
  active: boolean;
  onClick: () => void;
  children: React.ReactNode;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      className={cn(
        "rounded-full border px-2.5 py-1 text-xs font-medium capitalize transition-colors",
        active
          ? "border-[var(--color-primary)] bg-[var(--color-primary-subtle)] text-[var(--color-primary)]"
          : "border-[var(--color-border)] text-[var(--color-foreground-muted)] hover:border-[var(--color-border-strong)] hover:text-[var(--color-foreground)]",
      )}
    >
      {children}
    </button>
  );
}

function formatDateTime(iso: string): string {
  return new Date(iso).toLocaleString(undefined, {
    month: "short",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
}
