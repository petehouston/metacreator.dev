"use client";

import {
  AlertTriangle,
  CheckCircle2,
  ExternalLink,
  Info,
  Map as MapIcon,
  RefreshCw,
} from "lucide-react";
import * as React from "react";

import { AdminPageHeader, AdminPanel, StatusPill } from "@/components/admin/admin-page";
import { DataTable, type Column } from "@/components/admin/data-table";
import { LoadError } from "@/components/admin/load-error";
import { useToast } from "@/components/admin/feedback";
import { SearchInput } from "@/components/admin/toolbar";
import { Button } from "@/components/ui/button";
import { adminApi } from "@/lib/admin/api";
import type { SitemapEntry, SitemapIssue, SitemapReport } from "@/lib/admin/types";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import { cn, formatBytes, formatNumber, relativeTime } from "@/lib/utils";

/** Enough rows to scan; the search box is how you find one in a catalog this size. */
const VISIBLE_ROWS = 100;

/**
 * What search engines are currently being told this site contains.
 *
 * The screen is built around one comparison, because it is the only one that can go
 * wrong quietly: the file being *served* against the list the generator would
 * produce *now*. `/sitemap.xml` is a cached route, re-rendered at most hourly, so
 * between a publish and the next render it advertises a site that has moved on —
 * and nothing anywhere else in the admin would ever say so.
 */
export function SitemapScreen() {
  const { notify, reportError } = useToast();
  const [query, setQuery] = React.useState("");
  const [refreshing, setRefreshing] = React.useState(false);

  const { data, error, loading, reload } = useAdminResource(() => adminApi.sitemap.get(), []);
  const [report, setReport] = React.useState<SitemapReport | null>(null);

  // The refresh answers with a report of its own, which must win over the one the
  // loader fetched — otherwise pressing the button leaves the pre-refresh numbers
  // on screen and it reads as having done nothing.
  const current = report ?? data;

  const refresh = async () => {
    setRefreshing(true);

    const result = await adminApi.sitemap.refresh();

    setRefreshing(false);

    if (!result.ok) {
      reportError(result.error);
      return;
    }

    setReport(result.data);

    notify(
      result.data.missing.length === 0 && result.data.stale.length === 0
        ? "Sitemap re-rendered. It now matches the live catalog."
        : "Sitemap re-rendered, but it still differs from the live catalog — see below.",
      result.data.missing.length === 0 && result.data.stale.length === 0 ? "success" : "info",
    );
  };

  if (error && current === null) return <LoadError error={error} onRetry={reload} />;

  const entries = current?.entries ?? [];
  const term = query.trim().toLowerCase();
  const matches = term === "" ? entries : entries.filter((row) => row.path.toLowerCase().includes(term));

  const columns: Column<SitemapEntry>[] = [
    {
      key: "path",
      header: "URL",
      cell: (row) => (
        <a
          href={row.loc}
          target="_blank"
          rel="noreferrer"
          className="truncate font-medium text-[var(--color-foreground)] underline-offset-4 hover:underline"
        >
          {row.path}
        </a>
      ),
    },
    {
      key: "changefreq",
      header: "Change frequency",
      hideBelow: "md",
      cell: (row) => (
        <span className="text-xs text-[var(--color-foreground-subtle)]">
          {row.changefreq ?? "—"}
        </span>
      ),
    },
    {
      key: "priority",
      header: "Priority",
      numeric: true,
      hideBelow: "sm",
      cell: (row) => (row.priority === null ? "—" : row.priority.toFixed(1)),
    },
    {
      key: "lastmod",
      header: "Last modified",
      numeric: true,
      hideBelow: "lg",
      cell: (row) => (
        <span className="text-xs text-[var(--color-foreground-subtle)]">
          {row.lastmod ? relativeTime(row.lastmod) : "—"}
        </span>
      ),
    },
  ];

  return (
    <>
      <AdminPageHeader
        eyebrow="Platform"
        title="Sitemap"
        description={
          <>
            <code className="font-mono text-[0.8125rem]">/sitemap.xml</code> is rendered by
            the site itself and re-rendered automatically
            {current ? ` every ${Math.round(current.revalidate_seconds / 60)} minutes` : ""}, on
            the first request after it expires. Everything below describes the file a crawler
            would be handed right now.
          </>
        }
        actions={
          <>
            {current && (
              <Button asChild variant="secondary" size="sm">
                <a href={current.url} target="_blank" rel="noreferrer">
                  <ExternalLink className="size-4" aria-hidden="true" />
                  View XML
                </a>
              </Button>
            )}

            <Button size="sm" onClick={refresh} loading={refreshing} disabled={refreshing}>
              <RefreshCw className="size-4" aria-hidden="true" />
              Refresh now
            </Button>
          </>
        }
      />

      {current === null ? (
        <SitemapSkeleton />
      ) : (
        <div className={cn("flex flex-col gap-4", loading && "opacity-60")}>
          <IssueList issues={current.issues} />

          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <Tile label="URLs served" value={formatNumber(current.served_total)} />
            <Tile
              label="URLs expected"
              value={formatNumber(current.expected_total)}
              hint={
                current.expected_total === current.served_total
                  ? "In step with the live catalog"
                  : "The generator would produce a different list"
              }
              tone={current.expected_total === current.served_total ? "success" : "warning"}
            />
            <Tile
              label="Rendered"
              value={current.generated_at ? relativeTime(current.generated_at) : "Unknown"}
              hint={current.generated_at ? new Date(current.generated_at).toLocaleString() : undefined}
            />
            <Tile label="File size" value={formatBytes(current.bytes)} />
          </div>

          <AdminPanel
            title="What is in the file"
            description="Served against what the generator would produce right now."
            bodyClassName="p-0"
          >
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-[var(--color-border-subtle)] text-left">
                  <th className="px-4 py-2 font-medium text-[var(--color-foreground-subtle)]">
                    Section
                  </th>
                  <th className="px-4 py-2 text-right font-medium text-[var(--color-foreground-subtle)]">
                    Served
                  </th>
                  <th className="px-4 py-2 text-right font-medium text-[var(--color-foreground-subtle)]">
                    Expected
                  </th>
                </tr>
              </thead>

              <tbody>
                {current.sections.map((section) => (
                  <tr
                    key={section.key}
                    className="border-b border-[var(--color-border-subtle)] last:border-0"
                  >
                    <td className="px-4 py-2 text-[var(--color-foreground)]">{section.label}</td>
                    <td className="tabular px-4 py-2 text-right">{formatNumber(section.served)}</td>
                    <td
                      className={cn(
                        "tabular px-4 py-2 text-right",
                        section.served !== section.expected &&
                          "font-semibold text-[var(--color-warning)]",
                      )}
                    >
                      {formatNumber(section.expected)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </AdminPanel>

          {(current.missing.length > 0 || current.stale.length > 0) && (
            <div className="grid gap-4 lg:grid-cols-2">
              <DiffPanel
                title="Missing from the file"
                description="Published, but crawlers have not been told yet. Refreshing publishes them."
                paths={current.missing}
              />
              <DiffPanel
                title="Still listed, no longer generated"
                description="Unpublished, hidden or renamed since the file was rendered."
                paths={current.stale}
              />
            </div>
          )}

          <AdminPanel
            title="Every URL"
            description={
              term === ""
                ? `${formatNumber(entries.length)} in the served file`
                : `${formatNumber(matches.length)} of ${formatNumber(entries.length)} matching`
            }
            bodyClassName="p-0"
            action={
              <SearchInput
                value={query}
                onChange={setQuery}
                placeholder="Filter by path…"
                className="w-48"
              />
            }
          >
            <DataTable
              rows={matches.slice(0, VISIBLE_ROWS)}
              columns={columns}
              rowKey={(row) => row.loc}
              empty={
                <div className="px-4 py-12 text-center">
                  <span className="mx-auto mb-3 flex size-11 items-center justify-center rounded-full bg-[var(--color-surface-sunken)] text-[var(--color-foreground-subtle)]">
                    <MapIcon className="size-5" aria-hidden="true" />
                  </span>
                  <p className="text-sm font-semibold text-[var(--color-foreground)]">
                    {entries.length === 0 ? "The sitemap is empty" : "Nothing matches that"}
                  </p>
                  <p className="mx-auto mt-1 max-w-sm text-sm text-[var(--color-foreground-muted)]">
                    {entries.length === 0
                      ? "Publish a tool or a post, then refresh — a sitemap with no URLs tells a crawler this site has no pages."
                      : "Try a shorter path fragment."}
                  </p>
                </div>
              }
            />

            {matches.length > VISIBLE_ROWS && (
              <p className="border-t border-[var(--color-border-subtle)] px-4 py-3 text-xs text-[var(--color-foreground-subtle)]">
                Showing the first {VISIBLE_ROWS} of {formatNumber(matches.length)}. Search to
                narrow it, or open the XML for the whole file.
              </p>
            )}
          </AdminPanel>
        </div>
      )}
    </>
  );
}

/**
 * The problems, stated as consequences.
 *
 * "37 URLs missing" is a number; "37 published pages crawlers cannot see" is the
 * thing someone would act on, so the messages come from the server already phrased
 * that way and this only picks the colour.
 */
function IssueList({ issues }: { issues: SitemapIssue[] }) {
  if (issues.length === 0) {
    return (
      <div
        role="status"
        className="flex items-start gap-2.5 rounded-[var(--radius-md)] border border-[var(--color-success)]/30 bg-[var(--color-success)]/8 px-3 py-2.5"
      >
        <CheckCircle2
          className="mt-0.5 size-4 shrink-0 text-[var(--color-success)]"
          aria-hidden="true"
        />
        <p className="text-sm leading-relaxed text-[var(--color-foreground-muted)]">
          The served sitemap matches the live catalog and is within every limit search
          engines impose.
        </p>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-2">
      {issues.map((issue, index) => {
        const color =
          issue.level === "danger"
            ? "var(--color-danger)"
            : issue.level === "warning"
              ? "var(--color-warning)"
              : "var(--color-primary)";

        const Icon = issue.level === "info" ? Info : AlertTriangle;

        return (
          <div
            key={index}
            role={issue.level === "danger" ? "alert" : "status"}
            className="flex items-start gap-2.5 rounded-[var(--radius-md)] border px-3 py-2.5"
            style={{
              borderColor: `color-mix(in oklab, ${color} 30%, transparent)`,
              backgroundColor: `color-mix(in oklab, ${color} 8%, transparent)`,
            }}
          >
            <Icon className="mt-0.5 size-4 shrink-0" style={{ color }} aria-hidden="true" />
            <p className="text-sm leading-relaxed text-[var(--color-foreground-muted)]">
              {issue.message}
            </p>
          </div>
        );
      })}
    </div>
  );
}

function DiffPanel({
  title,
  description,
  paths,
}: {
  title: string;
  description: string;
  paths: string[];
}) {
  return (
    <AdminPanel
      title={title}
      description={description}
      action={<StatusPill label={String(paths.length)} tone={paths.length > 0 ? "warning" : "muted"} />}
      bodyClassName="max-h-72 overflow-y-auto p-0"
    >
      {paths.length === 0 ? (
        <p className="px-4 py-6 text-sm text-[var(--color-foreground-muted)]">Nothing here.</p>
      ) : (
        <ul className="divide-y divide-[var(--color-border-subtle)]">
          {paths.map((path) => (
            <li
              key={path}
              className="truncate px-4 py-2 font-mono text-xs text-[var(--color-foreground-muted)]"
            >
              {path}
            </li>
          ))}
        </ul>
      )}
    </AdminPanel>
  );
}

function Tile({
  label,
  value,
  hint,
  tone,
}: {
  label: string;
  value: string;
  hint?: string;
  tone?: "success" | "warning";
}) {
  const color =
    tone === "success"
      ? "var(--color-success)"
      : tone === "warning"
        ? "var(--color-warning)"
        : "var(--color-foreground-subtle)";

  return (
    <div className="app-card flex flex-col gap-1 p-4">
      <p className="font-mono text-[0.625rem] font-medium uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
        {label}
      </p>

      <p className="tabular text-xl font-semibold text-[var(--color-foreground)]">{value}</p>

      {hint && (
        <p className="text-xs leading-snug" style={{ color }}>
          {hint}
        </p>
      )}
    </div>
  );
}

function SitemapSkeleton() {
  return (
    <div className="flex flex-col gap-4">
      <div className="h-12 animate-pulse rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)]" />
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        {[0, 1, 2, 3].map((tile) => (
          <div
            key={tile}
            className="h-24 animate-pulse rounded-[var(--radius-lg)] bg-[var(--color-surface-sunken)]"
          />
        ))}
      </div>
      <div className="h-64 animate-pulse rounded-[var(--radius-lg)] bg-[var(--color-surface-sunken)]" />
    </div>
  );
}
