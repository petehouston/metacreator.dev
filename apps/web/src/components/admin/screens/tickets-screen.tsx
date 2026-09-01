"use client";

import { AlarmClock, LifeBuoy } from "lucide-react";
import { useRouter } from "next/navigation";
import * as React from "react";

import { AdminPageHeader, AdminPanel, StatusPill } from "@/components/admin/admin-page";
import { DataTable, Pagination, type Column } from "@/components/admin/data-table";
import { LoadError } from "@/components/admin/load-error";
import { humanise, tone } from "@/components/admin/status-tone";
import { CountTabs, FilterSelect, SearchInput } from "@/components/admin/toolbar";
import { adminApi } from "@/lib/admin/api";
import type { AdminTicket } from "@/lib/admin/types";
import { usePagedFilters } from "@/lib/admin/use-paged-filters";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import { relativeTime } from "@/lib/utils";

/**
 * The support queue.
 *
 * Ordered by what is most likely to burn someone — overdue first, then priority,
 * then how long a ticket has sat untouched. A queue sorted by creation date looks
 * tidy and lets the urgent one settle quietly at the bottom.
 */
export function TicketsScreen() {
  const router = useRouter();

  const [{ query, scope, priority }, setFilters, page, setPage] = usePagedFilters({
    query: "",
    scope: "",
    priority: "",
  });

  const { data, error, loading, reload } = useAdminResource(
    () =>
      adminApi.tickets.list({
        q: query || undefined,
        "filter[status]": scope || undefined,
        "filter[priority]": priority || undefined,
        page,
        per_page: 25,
      }),
    [query, scope, priority, page],
  );

  if (error) return <LoadError error={error} onRetry={reload} />;

  const counts = data?.meta.counts ?? {};

  const columns: Column<AdminTicket>[] = [
    {
      key: "subject",
      header: "Ticket",
      cell: (row) => (
        <span className="flex min-w-0 flex-col">
          <span className="flex items-center gap-1.5">
            {row.is_overdue && (
              <AlarmClock
                className="size-3.5 shrink-0 text-[var(--color-danger)]"
                aria-label="Overdue"
              />
            )}
            <span className="truncate font-medium text-[var(--color-foreground)]">
              {row.subject}
            </span>
          </span>
          <span className="truncate font-mono text-[0.6875rem] text-[var(--color-foreground-subtle)]">
            {row.reference} · {row.requester?.email ?? "unknown"}
          </span>
        </span>
      ),
    },
    {
      key: "status",
      header: "Status",
      cell: (row) => <StatusPill label={row.status_label} tone={tone.ticket(row.status)} />,
    },
    {
      key: "priority",
      header: "Priority",
      hideBelow: "sm",
      cell: (row) => (
        <StatusPill label={humanise(row.priority)} tone={tone.priority(row.priority)} />
      ),
    },
    {
      key: "assignee",
      header: "Assigned",
      hideBelow: "lg",
      cell: (row) =>
        row.assignee ? (
          row.assignee.display_name
        ) : (
          <span className="text-[var(--color-warning)]">Unassigned</span>
        ),
    },
    {
      key: "messages",
      header: "Replies",
      numeric: true,
      hideBelow: "xl",
      cell: (row) => row.messages_count ?? 0,
    },
    {
      key: "activity",
      header: "Last activity",
      numeric: true,
      hideBelow: "md",
      cell: (row) => (
        <span className="text-xs text-[var(--color-foreground-subtle)]">
          {row.last_activity_at ? relativeTime(row.last_activity_at) : "—"}
        </span>
      ),
    },
  ];

  return (
    <>
      <AdminPageHeader
        eyebrow="Support"
        title="Tickets"
        description="Overdue first, then priority, then whatever has waited longest."
      />

      {(counts.overdue ?? 0) > 0 && (
        <div
          role="status"
          className="mb-4 flex items-start gap-2.5 rounded-[var(--radius-md)] border border-[var(--color-danger)]/30 bg-[var(--color-danger)]/8 px-3 py-2.5"
        >
          <AlarmClock
            className="mt-0.5 size-4 shrink-0 text-[var(--color-danger)]"
            aria-hidden="true"
          />
          <p className="text-sm leading-relaxed text-[var(--color-foreground-muted)]">
            {counts.overdue} {counts.overdue === 1 ? "ticket is" : "tickets are"} past
            their due time without a first response.
          </p>
        </div>
      )}

      <AdminPanel
        title="Queue"
        description={data ? `${data.meta.page.total} matching` : "Loading…"}
        bodyClassName="p-0"
        action={
          <div className="flex flex-wrap items-center gap-2">
            <SearchInput
              value={query}
              onChange={(next) => setFilters({ query: next })}
              placeholder="Reference, subject or email…"
              className="w-56"
            />
            <FilterSelect
              label="Priority"
              value={priority}
              onChange={(next) => setFilters({ priority: next })}
              options={[
                { value: "", label: "Any" },
                { value: "urgent", label: "Urgent" },
                { value: "high", label: "High" },
                { value: "normal", label: "Normal" },
                { value: "low", label: "Low" },
              ]}
            />
          </div>
        }
      >
        <div className="border-b border-[var(--color-border-subtle)] px-3 py-2">
          <CountTabs
            value={scope}
            onChange={(next) => setFilters({ scope: next })}
            tabs={[
              { value: "", label: "All" },
              { value: "mine", label: "Mine", count: counts.mine },
              { value: "unassigned", label: "Unassigned", count: counts.unassigned },
              { value: "open", label: "Open", count: counts.open },
              { value: "pending", label: "Waiting on customer", count: counts.pending },
              { value: "on_hold", label: "On hold", count: counts.on_hold },
              { value: "solved", label: "Solved" },
              { value: "closed", label: "Closed" },
            ]}
          />
        </div>

        <DataTable
          rows={data?.data ?? []}
          columns={columns}
          rowKey={(row) => row.id}
          loading={loading}
          onRowClick={(row) => router.push(`/c0ns0le/tickets/${row.id}`)}
          empty={
            <div className="px-4 py-12 text-center">
              <span className="mx-auto mb-3 flex size-11 items-center justify-center rounded-full bg-[var(--color-surface-sunken)] text-[var(--color-foreground-subtle)]">
                <LifeBuoy className="size-5" aria-hidden="true" />
              </span>
              <p className="text-sm font-semibold text-[var(--color-foreground)]">
                {scope === "" ? "No tickets" : "Nothing in this view"}
              </p>
              <p className="mx-auto mt-1 max-w-sm text-sm text-[var(--color-foreground-muted)]">
                {scope === ""
                  ? "Nobody has written in. Enjoy it."
                  : "Try another tab — the queue may just be assigned elsewhere."}
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
