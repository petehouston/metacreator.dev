"use client";

import { Filter, RotateCw, Sparkles, X } from "lucide-react";
import Link from "next/link";
import * as React from "react";

import { EmptyState } from "@/components/app/empty-state";
import { useEntitlements } from "@/components/app/entitlements-provider";
import { formatDuration } from "@/components/app/overview";
import { RunStatusBadge } from "@/components/app/run-status-badge";
import { FormAlert } from "@/components/auth/form-alert";
import { ResultRenderer } from "@/components/tools/results";
import { Button } from "@/components/ui/button";
import { CopyButton } from "@/components/ui/copy-button";
import { apiData, apiFetch } from "@/lib/http";
import type { Paginated, ToolRun } from "@/lib/types";
import { cn } from "@/lib/utils";

const STATUSES: ToolRun["status"][] = ["succeeded", "failed", "running", "queued", "cancelled"];

/**
 * Everything this account has run, windowed by the plan's `history_days`.
 *
 * The row opens a drawer instead of a page: history is a list people scan, and
 * losing the list to look at one entry means losing their place in it. The drawer
 * re-renders the stored result where there is one — only asynchronous runs keep
 * their payload (docs/08), so a tool that answered instantly leaves a record of the
 * run and not the output.
 */
export function RunHistory() {
  const { entitlements } = useEntitlements();

  const [page, setPage] = React.useState(1);
  const [status, setStatus] = React.useState<ToolRun["status"] | null>(null);
  const [selected, setSelected] = React.useState<ToolRun | null>(null);
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
                <Link href="/dashboard/tools">Browse tools</Link>
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
                    <Button variant="ghost" size="sm" onClick={() => setSelected(run)}>
                      View
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

      {selected && <RunDrawer run={selected} onClose={() => setSelected(null)} />}
    </div>
  );
}

/**
 * The stored run, re-hydrated.
 *
 * The list endpoint returns runs without their full payload, so the drawer fetches
 * the single run it is showing. It renders the row it already has first, which is
 * what makes the drawer feel instant even while the body is still arriving.
 */
function RunDrawer({ run, onClose }: { run: ToolRun; onClose: () => void }) {
  const [detail, setDetail] = React.useState<ToolRun>(run);
  const [loading, setLoading] = React.useState(run.result === null);

  React.useEffect(() => {
    let cancelled = false;

    void (async () => {
      const result = await apiData<ToolRun>(`/tools/runs/${run.id}`);
      if (cancelled) return;

      if (result.ok) setDetail(result.data);
      setLoading(false);
    })();

    return () => {
      cancelled = true;
    };
  }, [run.id]);

  React.useEffect(() => {
    function onKeyDown(event: KeyboardEvent) {
      if (event.key === "Escape") onClose();
    }

    document.addEventListener("keydown", onKeyDown);
    return () => document.removeEventListener("keydown", onKeyDown);
  }, [onClose]);

  return (
    <div className="fixed inset-0 z-[60] flex justify-end" role="presentation">
      <button
        type="button"
        aria-label="Close run"
        onClick={onClose}
        className="animate-fade-in absolute inset-0 bg-[oklch(0.15_0.02_258/0.45)]"
      />

      <div
        role="dialog"
        aria-modal="true"
        aria-label={`${detail.tool?.name ?? "Tool"} run`}
        className="animate-fade-in relative flex h-full w-full max-w-[34rem] flex-col border-l border-[var(--color-border)] bg-[var(--app-surface)] shadow-[var(--shadow-popover)]"
      >
        <div className="flex items-start justify-between gap-3 border-b border-[var(--color-border-subtle)] px-5 py-4">
          <div className="min-w-0">
            <p className="truncate text-sm font-semibold text-[var(--color-foreground)]">
              {detail.tool?.name ?? "Tool run"}
            </p>
            <p className="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-[var(--color-foreground-subtle)]">
              <span>{formatDateTime(detail.created_at)}</span>
              {detail.meta.duration_ms !== null && (
                <span className="tabular">· {formatDuration(detail.meta.duration_ms)}</span>
              )}
              {detail.meta.cache_hit && <span>· cached</span>}
            </p>
          </div>

          <div className="flex shrink-0 items-center gap-1">
            <RunStatusBadge status={detail.status} />

            <button
              type="button"
              onClick={onClose}
              aria-label="Close"
              className="flex size-8 items-center justify-center rounded-[var(--radius-md)] text-[var(--color-foreground-muted)] transition-colors hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-foreground)]"
            >
              <X className="size-4" aria-hidden="true" />
            </button>
          </div>
        </div>

        <div className="scrollbar-slim flex-1 overflow-y-auto px-5 py-4">
          {loading && (
            <div className="h-40 animate-pulse rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)]" />
          )}

          {!loading && detail.error && (
            <FormAlert>
              <span className="block font-medium">{detail.error.message}</span>
              <span className="mt-1 block font-mono text-[0.6875rem] opacity-80">
                {detail.error.code}
              </span>
            </FormAlert>
          )}

          {!loading && detail.result && (
            <div className="flex flex-col gap-4">
              {detail.result.summary && (
                <p className="text-sm leading-relaxed text-[var(--color-foreground-muted)]">
                  {detail.result.summary}
                </p>
              )}

              <ResultRenderer result={detail.result} />

              {detail.result.warnings.length > 0 && (
                <ul className="flex flex-col gap-1">
                  {detail.result.warnings.map((warning) => (
                    <li key={warning} className="text-xs text-[var(--color-warning)]">
                      {warning}
                    </li>
                  ))}
                </ul>
              )}
            </div>
          )}

          {!loading && !detail.result && !detail.error && (
            <div className="py-10 text-center">
              <p className="text-sm text-[var(--color-foreground-muted)]">
                No stored output for this run.
              </p>
              <p className="mx-auto mt-1.5 max-w-xs text-xs leading-relaxed text-[var(--color-foreground-subtle)]">
                Tools that answer instantly are recorded but their results are not kept.
                Running it again takes the same input and costs one run.
              </p>
            </div>
          )}
        </div>

        <div className="flex items-center justify-between gap-3 border-t border-[var(--color-border-subtle)] px-5 py-3">
          <span className="truncate font-mono text-[0.625rem] text-[var(--color-foreground-subtle)]">
            {detail.id}
          </span>

          <div className="flex items-center gap-2">
            {detail.result && (
              <CopyButton value={JSON.stringify(detail.result.data, null, 2)} label="Copy JSON" />
            )}

            {detail.tool && (
              <Button asChild size="sm">
                <Link href={`/tools/${detail.tool.slug}`}>Run again</Link>
              </Button>
            )}
          </div>
        </div>
      </div>
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
