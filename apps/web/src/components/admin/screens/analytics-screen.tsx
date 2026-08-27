"use client";

import { Clock, Lock, TrendingDown } from "lucide-react";
import Link from "next/link";
import * as React from "react";

import { AdminPageHeader, AdminPanel, StatusPill } from "@/components/admin/admin-page";
import { FunnelBars, ShareBar, StackedColumns } from "@/components/admin/charts";
import { DataTable, type Column } from "@/components/admin/data-table";
import { LoadError } from "@/components/admin/load-error";
import { PeriodPicker } from "@/components/admin/period-picker";
import { accessReasonLabel, reasonColor, tone } from "@/components/admin/status-tone";
import { FilterSelect, SearchInput } from "@/components/admin/toolbar";
import { adminApi } from "@/lib/admin/api";
import type { ContentAnalyticsRow, ToolAnalyticsRow } from "@/lib/admin/types";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import { formatNumber, relativeTime } from "@/lib/utils";

type Tab = "tools" | "funnel" | "content";

/**
 * The analytics screen — the panels docs/15 specifies, in three tabs.
 *
 * Tabs rather than three routes because the period selection is shared and losing
 * it on every switch is the fastest way to make someone stop cross-referencing.
 */
export function AnalyticsScreen() {
  const [tab, setTab] = React.useState<Tab>("tools");
  const [period, setPeriod] = React.useState(30);

  return (
    <>
      <AdminPageHeader
        eyebrow="Analytics"
        title="What the product is telling us"
        description="Read from the nightly rollups, never from the live tables — so a year-long window is one indexed scan rather than a hundred million rows."
        actions={<PeriodPicker value={period} options={[7, 14, 30, 90, 365]} onChange={setPeriod} />}
      />

      <div
        role="tablist"
        aria-label="Analytics view"
        className="mb-4 flex gap-1 border-b border-[var(--color-border-subtle)]"
      >
        {(
          [
            ["tools", "Tools"],
            ["funnel", "Funnel"],
            ["content", "Content"],
          ] as const
        ).map(([value, label]) => (
          <button
            key={value}
            type="button"
            role="tab"
            aria-selected={tab === value}
            onClick={() => setTab(value)}
            className={`-mb-px border-b-2 px-3 py-2 text-sm font-medium transition-colors ${
              tab === value
                ? "border-[var(--color-primary)] text-[var(--color-foreground)]"
                : "border-transparent text-[var(--color-foreground-muted)] hover:text-[var(--color-foreground)]"
            }`}
          >
            {label}
          </button>
        ))}
      </div>

      {tab === "tools" && <ToolsTab period={period} />}
      {tab === "funnel" && <FunnelTab period={period} />}
      {tab === "content" && <ContentTab period={period} />}
    </>
  );
}

function ToolsTab({ period }: { period: number }) {
  const [query, setQuery] = React.useState("");
  const [tier, setTier] = React.useState("");
  const [sort, setSort] = React.useState("runs");

  const { data, error, loading, reload } = useAdminResource(
    () => adminApi.analytics.tools({ period, tier: tier || undefined, sort }),
    [period, tier, sort],
  );

  if (error) return <LoadError error={error} onRetry={reload} />;

  // Filtered in the browser rather than server-side: the endpoint returns the top
  // 200 tools in one shot, and a round trip per keystroke to narrow a list that is
  // already in memory would be slower and no more correct.
  const rows = (data?.rows ?? []).filter((row) =>
    query.trim() === ""
      ? true
      : `${row.name} ${row.slug} ${row.category?.name ?? ""}`
          .toLowerCase()
          .includes(query.trim().toLowerCase()),
  );

  const columns: Column<ToolAnalyticsRow>[] = [
    {
      key: "name",
      header: "Tool",
      cell: (row) => (
        <span className="flex min-w-0 flex-col">
          <Link
            href={`/tools/${row.slug}`}
            className="truncate font-medium text-[var(--color-foreground)] hover:text-[var(--color-primary)]"
          >
            {row.name}
          </Link>
          <span className="truncate text-xs text-[var(--color-foreground-subtle)]">
            {row.category?.name ?? "Uncategorised"}
          </span>
        </span>
      ),
    },
    {
      key: "tier",
      header: "Tier",
      hideBelow: "sm",
      cell: (row) => <StatusPill label={row.tier} tone={tone.tier(row.tier)} />,
    },
    {
      key: "views",
      header: "Views",
      numeric: true,
      hideBelow: "lg",
      cell: (row) => formatNumber(row.views),
    },
    {
      key: "runs",
      header: "Runs",
      numeric: true,
      sortKey: "runs",
      cell: (row) => (
        <span className="font-medium text-[var(--color-foreground)]">{formatNumber(row.runs)}</span>
      ),
    },
    {
      key: "start_rate",
      header: "View → run",
      numeric: true,
      hideBelow: "xl",
      cell: (row) =>
        row.start_rate === null ? (
          <span className="text-[var(--color-foreground-subtle)]">—</span>
        ) : (
          `${row.start_rate}%`
        ),
    },
    {
      key: "unique_actors",
      header: "People",
      numeric: true,
      sortKey: "unique_actors",
      hideBelow: "md",
      cell: (row) => formatNumber(row.unique_actors),
    },
    {
      key: "paywall_hits",
      header: "Paywall hits",
      numeric: true,
      sortKey: "paywall_hits",
      hideBelow: "md",
      cell: (row) =>
        row.paywall_hits === 0 ? (
          <span className="text-[var(--color-foreground-subtle)]">—</span>
        ) : (
          <span className="inline-flex items-center gap-1 text-[var(--color-warning)]">
            <Lock className="size-3" aria-hidden="true" />
            {formatNumber(row.paywall_hits)}
          </span>
        ),
    },
    {
      key: "comped",
      header: "Comped",
      numeric: true,
      hideBelow: "xl",
      cell: (row) =>
        row.comped_runs === 0 ? (
          <span className="text-[var(--color-foreground-subtle)]">—</span>
        ) : (
          formatNumber(row.comped_runs)
        ),
    },
    {
      key: "failure_rate",
      header: "Failures",
      numeric: true,
      sortKey: "failure_rate",
      cell: (row) => (
        <span
          style={{
            color: row.failure_rate > 5 ? "var(--color-danger)" : undefined,
            fontWeight: row.failure_rate > 5 ? 600 : undefined,
          }}
        >
          {row.failure_rate}%
        </span>
      ),
    },
    {
      key: "p95",
      header: "p95",
      numeric: true,
      sortKey: "p95",
      hideBelow: "lg",
      cell: (row) => `${formatNumber(row.p95_duration_ms)}ms`,
    },
  ];

  return (
    <>
      <div className="mb-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Totals label="Runs" value={formatNumber(data?.totals.runs ?? 0)} />
        <Totals
          label="Failure rate"
          value={`${data?.totals.failure_rate ?? 0}%`}
          tone={(data?.totals.failure_rate ?? 0) > 5 ? "danger" : undefined}
        />
        <Totals label="Cache hit rate" value={`${data?.totals.cache_hit_rate ?? 0}%`} />
        <Totals
          label="Paywall hits"
          value={formatNumber(data?.totals.paywall_hits ?? 0)}
          hint="Free users who wanted a premium tool"
        />
      </div>

      <div className="mb-4 grid gap-4 lg:grid-cols-3">
        <AdminPanel
          title="Run volume"
          description="Successful and failed, by day"
          className="lg:col-span-2"
        >
          <StackedColumns
            data={(data?.volume ?? []).map((point) => ({
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
            }))}
            height={200}
          />
        </AdminPanel>

        <AdminPanel title="Access reason" description="How much usage is actually paid for">
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
        title="Every tool"
        description="Sort by failures to triage, by paywall hits to price"
        bodyClassName="p-0"
        action={
          <div className="flex flex-wrap items-center gap-2">
            <SearchInput
              value={query}
              onChange={setQuery}
              placeholder="Filter tools…"
              className="w-48"
            />
            <FilterSelect
              label="Tier"
              value={tier}
              onChange={setTier}
              options={[
                { value: "", label: "All" },
                { value: "free", label: "Free" },
                { value: "account", label: "Account" },
                { value: "premium", label: "Premium" },
              ]}
            />
          </div>
        }
      >
        <DataTable
          rows={rows}
          columns={columns}
          rowKey={(row) => row.id}
          loading={loading}
          sort={sort === "runs" ? "-runs" : `-${sort}`}
          onSortChange={(next) => setSort(next.replace(/^-/, ""))}
          empty={
            <p className="px-4 py-12 text-center text-sm text-[var(--color-foreground-subtle)]">
              No tool recorded a run in this period.
            </p>
          }
        />
      </AdminPanel>

      <AdminPanel title="Top errors" description="What to fix first" className="mt-4">
        {data && data.top_errors.length === 0 ? (
          <p className="py-6 text-center text-sm text-[var(--color-foreground-subtle)]">
            No failures recorded in this period.
          </p>
        ) : (
          <ul className="flex flex-col gap-2">
            {(data?.top_errors ?? []).map((row) => (
              <li
                key={row.code}
                className="flex items-center gap-3 rounded-[var(--radius-md)] border border-[var(--color-border-subtle)] px-3 py-2"
              >
                <TrendingDown
                  className="size-4 shrink-0 text-[var(--color-danger)]"
                  aria-hidden="true"
                />
                <span className="min-w-0 flex-1">
                  <span className="block font-mono text-xs text-[var(--color-foreground)]">
                    {row.code}
                  </span>
                  <span className="block truncate text-xs text-[var(--color-foreground-subtle)]">
                    {row.tools.join(" · ")}
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

      {data?.as_of && (
        <p className="mt-4 flex items-center gap-1.5 text-xs text-[var(--color-foreground-subtle)]">
          <Clock className="size-3.5" aria-hidden="true" />
          Rollup last refreshed {relativeTime(data.as_of)}.
        </p>
      )}
    </>
  );
}

function FunnelTab({ period }: { period: number }) {
  const { data, error, reload } = useAdminResource(
    () => adminApi.analytics.funnel(period),
    [period],
  );

  if (error) return <LoadError error={error} onRetry={reload} />;

  return (
    <AdminPanel
      title="Visitor → run → account → paying"
      description="Every step measured against the top of the funnel, so the widths are comparable"
    >
      <FunnelBars steps={data?.steps ?? []} className="max-w-2xl" />

      <p className="mt-6 max-w-2xl text-xs leading-relaxed text-[var(--color-foreground-subtle)]">
        A visitor is a distinct daily actor who ran at least one tool — counted from
        a rotating hash of IP and user agent, never from an IP itself. Someone who
        browses without running anything is not in this funnel, by design: the
        product&rsquo;s first meaningful action is a run.
      </p>
    </AdminPanel>
  );
}

function ContentTab({ period }: { period: number }) {
  const { data, error, loading, reload } = useAdminResource(
    () => adminApi.analytics.content(period),
    [period],
  );

  if (error) return <LoadError error={error} onRetry={reload} />;

  const columns: Column<ContentAnalyticsRow>[] = [
    {
      key: "title",
      header: "Post",
      cell: (row) => (
        <Link
          href={`/blog/${row.slug}`}
          className="font-medium text-[var(--color-foreground)] hover:text-[var(--color-primary)]"
        >
          {row.title}
        </Link>
      ),
    },
    {
      key: "views",
      header: "Views",
      numeric: true,
      cell: (row) => formatNumber(row.views),
    },
    {
      key: "read_through",
      header: "Read through",
      numeric: true,
      hideBelow: "sm",
      cell: (row) => `${row.read_through}%`,
    },
    {
      key: "tool_clicks",
      header: "Tool clicks",
      numeric: true,
      hideBelow: "md",
      cell: (row) => formatNumber(row.tool_clicks),
    },
    {
      key: "signups",
      header: "Signups",
      numeric: true,
      hideBelow: "md",
      cell: (row) => formatNumber(row.newsletter_signups),
    },
  ];

  return (
    <AdminPanel
      title="Posts by reach"
      description="Views, engagement, and what the writing sends people to do"
      bodyClassName="p-0"
    >
      <DataTable
        rows={data?.rows ?? []}
        columns={columns}
        rowKey={(row) => row.slug}
        loading={loading}
        empty={
          <p className="px-4 py-12 text-center text-sm text-[var(--color-foreground-subtle)]">
            No post recorded a view in this period.
          </p>
        }
      />
    </AdminPanel>
  );
}

function Totals({
  label,
  value,
  hint,
  tone: colorTone,
}: {
  label: string;
  value: string;
  hint?: string;
  tone?: "danger";
}) {
  return (
    <div className="app-card p-4">
      <p className="font-mono text-[0.625rem] font-medium uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
        {label}
      </p>
      <p
        className="tabular mt-2 text-2xl font-semibold leading-none tracking-[-0.02em]"
        style={{
          color: colorTone === "danger" ? "var(--color-danger)" : "var(--color-foreground)",
        }}
      >
        {value}
      </p>
      {hint && (
        <p className="mt-2 text-xs leading-snug text-[var(--color-foreground-subtle)]">{hint}</p>
      )}
    </div>
  );
}
