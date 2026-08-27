"use client";

import { ShieldCheck } from "lucide-react";
import * as React from "react";

import { AdminPageHeader, AdminPanel, StatusPill } from "@/components/admin/admin-page";
import { Pagination } from "@/components/admin/data-table";
import { LoadError } from "@/components/admin/load-error";
import { humanise } from "@/components/admin/status-tone";
import { FilterSelect } from "@/components/admin/toolbar";
import { adminApi } from "@/lib/admin/api";
import type { ActivityEntry } from "@/lib/admin/types";
import { usePagedFilters } from "@/lib/admin/use-paged-filters";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import { relativeTime } from "@/lib/utils";

const EVENT_TONES: Record<string, "success" | "warning" | "danger" | "info" | "muted"> = {
  created: "success",
  updated: "info",
  deleted: "danger",
  suspended: "danger",
  reinstated: "success",
  restored: "success",
  granted: "warning",
  revoked: "warning",
  roles_changed: "danger",
  published: "success",
  replied: "info",
  note_added: "muted",
  uploaded: "info",
};

/**
 * The audit trail.
 *
 * Read-only by construction — there is no endpoint that edits or deletes an entry,
 * because a log an administrator can rewrite answers no question worth asking.
 *
 * The diff is the point: "Setting tracking.ga4_id updated" is a fact, but
 * "from G-OLD to G-NEW" is what someone needs at 2am. Secrets are masked at write
 * time, so a rotated key never appears here in plaintext.
 */
export function ActivityScreen() {
  const [{ event }, setFilters, page, setPage] = usePagedFilters({ event: "" });

  const { data, error, loading, reload } = useAdminResource(
    () => adminApi.activity({ "filter[event]": event || undefined, page, per_page: 40 }),
    [event, page],
  );

  if (error) return <LoadError error={error} onRetry={reload} />;

  return (
    <>
      <AdminPageHeader
        eyebrow="Platform"
        title="Audit log"
        description="Every permission-gated write, with the actor, the subject and what changed. Nothing here can be edited or deleted from any screen."
      />

      <AdminPanel
        title="Activity"
        description={data ? `${data.meta.page.total.toLocaleString()} entries` : "Loading…"}
        bodyClassName="p-0"
        action={
          <FilterSelect
            label="Event"
            value={event}
            onChange={(next) => setFilters({ event: next })}
            options={[
              { value: "", label: "Everything" },
              { value: "created", label: "Created" },
              { value: "updated", label: "Updated" },
              { value: "deleted", label: "Deleted" },
              { value: "roles_changed", label: "Role changes" },
              { value: "granted", label: "Grants" },
              { value: "suspended", label: "Suspensions" },
            ]}
          />
        }
      >
        {loading && !data ? (
          <div className="flex flex-col gap-2 p-4">
            {[0, 1, 2, 3, 4].map((row) => (
              <div
                key={row}
                className="h-12 animate-pulse rounded bg-[var(--color-surface-sunken)]"
                aria-hidden="true"
              />
            ))}
          </div>
        ) : (data?.data.length ?? 0) === 0 ? (
          <div className="px-4 py-16 text-center">
            <span className="mx-auto mb-3 flex size-11 items-center justify-center rounded-full bg-[var(--color-surface-sunken)] text-[var(--color-foreground-subtle)]">
              <ShieldCheck className="size-5" aria-hidden="true" />
            </span>
            <p className="text-sm font-semibold text-[var(--color-foreground)]">
              Nothing recorded yet
            </p>
            <p className="mx-auto mt-1 max-w-sm text-sm text-[var(--color-foreground-muted)]">
              Entries appear the first time someone changes something through the
              admin.
            </p>
          </div>
        ) : (
          <ol className="flex flex-col">
            {(data?.data ?? []).map((entry) => (
              <ActivityRow key={entry.id} entry={entry} />
            ))}
          </ol>
        )}

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

function ActivityRow({ entry }: { entry: ActivityEntry }) {
  const changes = Object.entries(entry.changes ?? {});

  return (
    <li className="border-b border-[var(--color-border-subtle)] px-4 py-3 last:border-b-0">
      <div className="flex flex-wrap items-center gap-2">
        <span
          aria-hidden="true"
          className="flex size-7 shrink-0 items-center justify-center rounded-full bg-[var(--color-surface-sunken)] text-[0.625rem] font-semibold text-[var(--color-foreground-muted)]"
        >
          {entry.causer?.initials ?? "—"}
        </span>

        <span className="text-sm text-[var(--color-foreground)]">
          {entry.causer?.display_name ?? "System"}
        </span>

        {entry.event && (
          <StatusPill
            label={humanise(entry.event)}
            tone={EVENT_TONES[entry.event] ?? "neutral"}
          />
        )}

        <span className="text-sm text-[var(--color-foreground-muted)]">{entry.description}</span>

        {entry.subject && (
          <span className="font-mono text-[0.6875rem] text-[var(--color-foreground-subtle)]">
            {entry.subject.type}#{entry.subject.id}
          </span>
        )}

        <span className="ml-auto shrink-0 text-xs text-[var(--color-foreground-subtle)]">
          {entry.created_at ? relativeTime(entry.created_at) : ""}
        </span>
      </div>

      {changes.length > 0 && (
        <dl className="mt-2 flex flex-col gap-1 pl-9">
          {changes.map(([field, change]) => (
            <div key={field} className="flex flex-wrap items-baseline gap-2 text-xs">
              <dt className="font-mono text-[var(--color-foreground-subtle)]">{field}</dt>
              <dd className="flex min-w-0 flex-wrap items-baseline gap-1.5">
                <span className="max-w-xs truncate text-[var(--color-foreground-subtle)] line-through">
                  {display(change.from)}
                </span>
                <span aria-hidden="true" className="text-[var(--color-foreground-subtle)]">
                  →
                </span>
                <span className="max-w-xs truncate font-medium text-[var(--color-foreground)]">
                  {display(change.to)}
                </span>
              </dd>
            </div>
          ))}
        </dl>
      )}
    </li>
  );
}

function display(value: unknown): string {
  if (value === null || value === undefined || value === "") return "empty";
  if (typeof value === "boolean") return value ? "on" : "off";
  if (Array.isArray(value)) return value.length === 0 ? "none" : value.join(", ");
  if (typeof value === "object") return JSON.stringify(value);

  return String(value);
}
