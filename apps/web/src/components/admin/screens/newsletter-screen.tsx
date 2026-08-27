"use client";

import { AlertTriangle, Download, Mail } from "lucide-react";
import * as React from "react";

import { AdminPageHeader, AdminPanel, StatusPill } from "@/components/admin/admin-page";
import { Can } from "@/components/admin/can";
import { DataTable, Pagination, type Column } from "@/components/admin/data-table";
import { LoadError } from "@/components/admin/load-error";
import { humanise, tone } from "@/components/admin/status-tone";
import { CountTabs, FilterSelect, SearchInput } from "@/components/admin/toolbar";
import { Button } from "@/components/ui/button";
import { adminApi } from "@/lib/admin/api";
import type { NewsletterSubscriber } from "@/lib/admin/types";
import { usePagedFilters } from "@/lib/admin/use-paged-filters";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import { relativeTime } from "@/lib/utils";

/**
 * The list, and whether it is actually reaching the provider.
 *
 * `sync_status` is promoted to its own tab and its own banner because a provider
 * integration that has been failing quietly for a week is indistinguishable from
 * one that is working — right up until a campaign goes out to two thirds of the
 * list and nobody can explain the drop.
 */
export function NewsletterScreen() {
  const [{ query, status, sync }, setFilters, page, setPage] = usePagedFilters({
    query: "",
    status: "",
    sync: "",
  });

  const { data, error, loading, reload } = useAdminResource(
    () =>
      adminApi.newsletter.list({
        q: query || undefined,
        "filter[status]": status || undefined,
        "filter[sync]": sync || undefined,
        page,
        per_page: 30,
      }),
    [query, status, sync, page],
  );

  if (error) return <LoadError error={error} onRetry={reload} />;

  const counts = data?.meta.counts ?? {};

  const columns: Column<NewsletterSubscriber>[] = [
    {
      key: "email",
      header: "Subscriber",
      cell: (row) => (
        <span className="flex min-w-0 flex-col">
          <span className="truncate font-medium text-[var(--color-foreground)]">{row.email}</span>
          {row.name && (
            <span className="truncate text-xs text-[var(--color-foreground-subtle)]">
              {row.name}
            </span>
          )}
        </span>
      ),
    },
    {
      key: "status",
      header: "Status",
      cell: (row) => <StatusPill label={humanise(row.status)} tone={tone.subscriber(row.status)} />,
    },
    {
      key: "sync",
      header: "Provider sync",
      hideBelow: "sm",
      cell: (row) => (
        <span className="flex min-w-0 flex-col gap-0.5">
          <StatusPill label={humanise(row.sync_status)} tone={tone.sync(row.sync_status)} />
          {row.sync_error && (
            <span className="truncate text-[0.625rem] text-[var(--color-danger)]">
              {row.sync_error}
            </span>
          )}
        </span>
      ),
    },
    {
      key: "source",
      header: "Source",
      hideBelow: "lg",
      cell: (row) => row.source ?? "—",
    },
    {
      key: "joined",
      header: "Joined",
      numeric: true,
      hideBelow: "md",
      cell: (row) => (
        <span className="text-xs text-[var(--color-foreground-subtle)]">
          {row.created_at ? relativeTime(row.created_at) : "—"}
        </span>
      ),
    },
  ];

  return (
    <>
      <AdminPageHeader
        eyebrow="Platform"
        title="Newsletter"
        description="Consent is recorded with every subscriber — what was agreed to, when, and from where. Which provider the list syncs to is set in Settings."
        actions={
          <Can permission="newsletter.export">
            {/* A real link, not a fetch: the response is a stream, and letting the
                browser handle the download is both simpler and the only way it works
                for a list large enough to matter. */}
            <Button asChild variant="secondary" size="sm">
              <a href={adminApi.newsletter.exportUrl()} download>
                <Download className="size-4" aria-hidden="true" />
                Export CSV
              </a>
            </Button>
          </Can>
        }
      />

      {(counts.sync_failed ?? 0) > 0 && (
        <div
          role="status"
          className="mb-4 flex items-start gap-2.5 rounded-[var(--radius-md)] border border-[var(--color-danger)]/30 bg-[var(--color-danger)]/8 px-3 py-2.5"
        >
          <AlertTriangle
            className="mt-0.5 size-4 shrink-0 text-[var(--color-danger)]"
            aria-hidden="true"
          />
          <p className="text-sm leading-relaxed text-[var(--color-foreground-muted)]">
            {counts.sync_failed} {counts.sync_failed === 1 ? "subscriber has" : "subscribers have"}{" "}
            failed to reach the provider. They exist here and not there — a campaign
            sent from the provider will miss them.{" "}
            <button
              type="button"
              onClick={() => setFilters({ sync: "failed" })}
              className="text-[var(--color-primary)] underline-offset-4 hover:underline"
            >
              Show them
            </button>
            .
          </p>
        </div>
      )}

      <AdminPanel
        title="Subscribers"
        description={data ? `${data.meta.page.total} matching` : "Loading…"}
        bodyClassName="p-0"
        action={
          <div className="flex flex-wrap items-center gap-2">
            <SearchInput
              value={query}
              onChange={(next) => setFilters({ query: next })}
              placeholder="Email or name…"
              className="w-48"
            />
            <FilterSelect
              label="Sync"
              value={sync}
              onChange={(next) => setFilters({ sync: next })}
              options={[
                { value: "", label: "Any" },
                { value: "synced", label: "Synced" },
                { value: "pending", label: "Pending" },
                { value: "failed", label: "Failed" },
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
              { value: "subscribed", label: "Subscribed", count: counts.subscribed },
              { value: "pending", label: "Unconfirmed", count: counts.pending },
              { value: "unsubscribed", label: "Unsubscribed", count: counts.unsubscribed },
              { value: "bounced", label: "Bounced", count: counts.bounced },
            ]}
          />
        </div>

        <DataTable
          rows={data?.data ?? []}
          columns={columns}
          rowKey={(row) => String(row.id)}
          loading={loading}
          empty={
            <div className="px-4 py-12 text-center">
              <span className="mx-auto mb-3 flex size-11 items-center justify-center rounded-full bg-[var(--color-surface-sunken)] text-[var(--color-foreground-subtle)]">
                <Mail className="size-5" aria-hidden="true" />
              </span>
              <p className="text-sm font-semibold text-[var(--color-foreground)]">
                Nobody here yet
              </p>
              <p className="mx-auto mt-1 max-w-sm text-sm text-[var(--color-foreground-muted)]">
                Sign-up forms sit on the blog, the footer and the tool pages. The first
                subscriber appears the moment one is used.
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
    </>
  );
}
