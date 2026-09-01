"use client";

import { AlertTriangle, ArrowRight, Clock } from "lucide-react";
import Link from "next/link";
import * as React from "react";

import { AdminPageHeader, AdminPanel, StatusPill } from "@/components/admin/admin-page";
import { FunnelBars, ShareBar, StackedColumns } from "@/components/admin/charts";
import { LoadError } from "@/components/admin/load-error";
import { MetricCard, MetricCardSkeleton } from "@/components/admin/metric-card";
import { PeriodPicker } from "@/components/admin/period-picker";
import { accessReasonLabel, reasonColor, tone } from "@/components/admin/status-tone";
import { Button } from "@/components/ui/button";
import { adminApi } from "@/lib/admin/api";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import { formatNumber, relativeTime } from "@/lib/utils";

/**
 * `/c0ns0le` — the health of the product in one screen.
 *
 * Ordered by what someone opening it at nine in the morning needs, in order: are
 * the numbers moving, is anything on fire, and where is the funnel leaking. The
 * "what is broken" panel is deliberately above the fold on a laptop, because an
 * error spike found at 09:01 is a different day from one found at 15:00.
 */
export function OverviewScreen() {
  const [period, setPeriod] = React.useState(30);

  const { data, error, loading, reload } = useAdminResource(
    () => adminApi.overview(period),
    [period],
  );

  if (error) return <LoadError error={error} onRetry={reload} />;

  const volume = (data?.volume ?? []).map((point) => ({
    date: point.date,
    values: [
      {
        key: "ok",
        label: "Succeeded",
        value: Math.max(0, point.runs - point.failed),
        color: "var(--color-brand-500)",
      },
      { key: "failed", label: "Failed", value: point.failed, color: "var(--color-danger)" },
    ],
  }));

  return (
    <>
      <AdminPageHeader
        eyebrow="Overview"
        title="How the product is doing"
        description={
          data
            ? `${data.period.label}, compared against the ${data.period.days} days before it.`
            : "Loading the numbers…"
        }
        actions={
          <PeriodPicker
            value={period}
            options={data?.periods ?? [7, 30, 90]}
            onChange={setPeriod}
          />
        }
      />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {data
          ? data.metrics.map((metric) => <MetricCard key={metric.key} metric={metric} />)
          : [0, 1, 2, 3, 4, 5, 6, 7].map((tile) => <MetricCardSkeleton key={tile} />)}
      </div>

      <div className="mt-4 grid gap-4 lg:grid-cols-3">
        <AdminPanel
          title="Run volume"
          description="Successful and failed executions, by day"
          className="lg:col-span-2"
        >
          <StackedColumns data={volume} height={220} />
        </AdminPanel>

        <AdminPanel
          title="What is failing"
          description="Ranked by how often it happens"
          action={
            <Button asChild variant="ghost" size="sm">
              <Link href="/c0ns0le/analytics">
                All errors
                <ArrowRight className="size-3.5" aria-hidden="true" />
              </Link>
            </Button>
          }
        >
          {data && data.top_errors.length === 0 ? (
            <p className="py-6 text-center text-sm text-[var(--color-foreground-subtle)]">
              Nothing has failed in this period. That is the correct number.
            </p>
          ) : (
            <ul className="flex flex-col gap-2.5">
              {(data?.top_errors ?? []).map((row) => (
                <li key={row.code} className="flex items-start gap-2.5">
                  <AlertTriangle
                    className="mt-0.5 size-3.5 shrink-0 text-[var(--color-warning)]"
                    aria-hidden="true"
                  />
                  <span className="min-w-0 flex-1">
                    <span className="block truncate font-mono text-xs text-[var(--color-foreground)]">
                      {row.code}
                    </span>
                    <span className="block truncate text-xs text-[var(--color-foreground-subtle)]">
                      {row.tools.join(", ")}
                    </span>
                  </span>
                  <span className="tabular text-sm font-semibold text-[var(--color-foreground)]">
                    {formatNumber(row.count)}
                  </span>
                </li>
              ))}
            </ul>
          )}
        </AdminPanel>
      </div>

      <div className="mt-4 grid gap-4 lg:grid-cols-3">
        <AdminPanel
          title="Visitor to paying"
          description="Where the funnel leaks"
          className="lg:col-span-2"
        >
          <FunnelBars steps={data?.funnel ?? []} />
        </AdminPanel>

        <AdminPanel
          title="Where runs come from"
          description="How much usage is paid for"
        >
          <ShareBar
            slices={(data?.access_reasons ?? []).map((slice) => ({
              key: slice.reason,
              label: accessReasonLabel(slice.reason),
              value: slice.runs,
              share: slice.share,
              color: reasonColor(slice.reason),
            }))}
          />
        </AdminPanel>
      </div>

      <AdminPanel
        title="Busiest tools"
        description="What people actually use"
        className="mt-4"
        bodyClassName="p-0"
        action={
          <Button asChild variant="ghost" size="sm">
            <Link href="/c0ns0le/analytics">
              Full tool analytics
              <ArrowRight className="size-3.5" aria-hidden="true" />
            </Link>
          </Button>
        }
      >
        <div className="scrollbar-slim overflow-x-auto">
          <table className="w-full border-collapse text-sm">
            <thead>
              <tr className="border-b border-[var(--color-border)]">
                {["Tool", "Tier", "Runs", "People", "Failure rate", "p95"].map((header, index) => (
                  <th
                    key={header}
                    scope="col"
                    className={`whitespace-nowrap px-4 py-2 font-mono text-[0.625rem] font-medium uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)] ${
                      index > 1 ? "text-right" : "text-left"
                    } ${index === 3 || index === 5 ? "hidden sm:table-cell" : ""}`}
                  >
                    {header}
                  </th>
                ))}
              </tr>
            </thead>

            <tbody>
              {(data?.top_tools ?? []).map((row) => (
                <tr
                  key={row.id}
                  className="border-b border-[var(--color-border-subtle)] last:border-b-0"
                >
                  <td className="px-4 py-2.5">
                    <Link
                      href={`/c0ns0le/analytics?tool=${row.slug}`}
                      className="font-medium text-[var(--color-foreground)] hover:text-[var(--color-primary)]"
                    >
                      {row.name}
                    </Link>
                  </td>
                  <td className="px-4 py-2.5">
                    <StatusPill label={row.tier} tone={tone.tier(row.tier)} />
                  </td>
                  <td className="tabular px-4 py-2.5 text-right text-[var(--color-foreground)]">
                    {formatNumber(row.runs)}
                  </td>
                  <td className="tabular hidden px-4 py-2.5 text-right text-[var(--color-foreground-muted)] sm:table-cell">
                    {formatNumber(row.unique_actors)}
                  </td>
                  <td className="tabular px-4 py-2.5 text-right">
                    <span
                      style={{
                        color:
                          row.failure_rate > 5
                            ? "var(--color-danger)"
                            : "var(--color-foreground-muted)",
                      }}
                    >
                      {row.failure_rate}%
                    </span>
                  </td>
                  <td className="tabular hidden px-4 py-2.5 text-right text-[var(--color-foreground-muted)] sm:table-cell">
                    {row.p95_duration_ms}ms
                  </td>
                </tr>
              ))}

              {loading && !data && (
                <tr>
                  <td colSpan={6} className="px-4 py-8">
                    <div className="h-4 animate-pulse rounded bg-[var(--color-surface-sunken)]" />
                  </td>
                </tr>
              )}

              {data && data.top_tools.length === 0 && (
                <tr>
                  <td
                    colSpan={6}
                    className="px-4 py-10 text-center text-sm text-[var(--color-foreground-subtle)]"
                  >
                    No runs have been recorded in this period.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </AdminPanel>

      {data && (
        <p className="mt-4 flex items-center gap-1.5 text-xs text-[var(--color-foreground-subtle)]">
          <Clock className="size-3.5" aria-hidden="true" />
          Rollups refresh every fifteen minutes. Point-in-time figures — MRR, open
          tickets — are read live.
          {data.volume.length > 0 && (
            <span className="hidden sm:inline">
              {" "}
              Period ends {relativeTime(data.period.end)}.
            </span>
          )}
        </p>
      )}
    </>
  );
}
