"use client";

import { CheckCircle2, Inbox, Mail, RotateCcw } from "lucide-react";
import * as React from "react";

import { AdminPageHeader, AdminPanel, StatusPill } from "@/components/admin/admin-page";
import { Can } from "@/components/admin/can";
import { useToast } from "@/components/admin/feedback";
import { LoadError } from "@/components/admin/load-error";
import { Pagination } from "@/components/admin/data-table";
import { CountTabs, SearchInput } from "@/components/admin/toolbar";
import { Button } from "@/components/ui/button";
import { adminApi } from "@/lib/admin/api";
import { usePagedFilters } from "@/lib/admin/use-paged-filters";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import { cn, relativeTime } from "@/lib/utils";

/**
 * The public contact form's inbox.
 *
 * Deliberately not a ticket queue: the sender may have no account, so there is
 * nobody to thread against and nobody to notify. Staff read, triage, and open a
 * real ticket when one is warranted — which is why the only state here is
 * handled / not handled, and why it toggles both ways.
 */
export function MessagesScreen() {
  const { notify, reportError } = useToast();

  const [{ query, state }, setFilters, page, setPage] = usePagedFilters({
    query: "",
    state: "unhandled",
  });

  const [busy, setBusy] = React.useState<number | null>(null);

  const { data, error, loading, reload } = useAdminResource(
    () =>
      adminApi.contact.list({
        q: query || undefined,
        "filter[state]": state || undefined,
        page,
        per_page: 25,
      }),
    [query, state, page],
  );

  if (error) return <LoadError error={error} onRetry={reload} />;

  async function toggle(id: number) {
    setBusy(id);
    const result = await adminApi.contact.toggleHandled(id);
    setBusy(null);

    if (result.ok) {
      notify(result.data.handled_at ? "Marked as handled." : "Reopened.");
      reload();
    } else {
      reportError(result.error);
    }
  }

  const messages = data?.data ?? [];

  return (
    <>
      <AdminPageHeader
        eyebrow="Support"
        title="Contact inbox"
        description="Messages from the public form. Anyone can write in — there is no account behind most of these."
      />

      <AdminPanel
        title="Messages"
        description={data ? `${data.meta.page.total} matching` : "Loading…"}
        bodyClassName="p-0"
        action={
          <SearchInput
            value={query}
            onChange={(next) => setFilters({ query: next })}
            placeholder="Email or subject…"
            className="w-56"
          />
        }
      >
        <div className="border-b border-[var(--color-border-subtle)] px-3 py-2">
          <CountTabs
            value={state}
            onChange={(next) => setFilters({ state: next })}
            tabs={[
              { value: "unhandled", label: "Needs a look", count: data?.meta.counts?.unhandled },
              { value: "handled", label: "Handled" },
              { value: "", label: "All" },
            ]}
          />
        </div>

        {loading && messages.length === 0 ? (
          <div className="flex flex-col gap-3 p-4">
            {[0, 1, 2].map((row) => (
              <div
                key={row}
                className="h-24 animate-pulse rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)]"
                aria-hidden="true"
              />
            ))}
          </div>
        ) : messages.length === 0 ? (
          <div className="px-4 py-16 text-center">
            <span className="mx-auto mb-3 flex size-11 items-center justify-center rounded-full bg-[var(--color-surface-sunken)] text-[var(--color-foreground-subtle)]">
              <Inbox className="size-5" aria-hidden="true" />
            </span>
            <p className="text-sm font-semibold text-[var(--color-foreground)]">
              {state === "unhandled" ? "Inbox zero" : "Nothing here"}
            </p>
            <p className="mx-auto mt-1 max-w-sm text-sm text-[var(--color-foreground-muted)]">
              {state === "unhandled"
                ? "Every message has been dealt with."
                : "Try another tab."}
            </p>
          </div>
        ) : (
          <ul className="flex flex-col">
            {messages.map((message) => (
              <li
                key={message.id}
                className={cn(
                  "border-b border-[var(--color-border-subtle)] px-4 py-3.5 last:border-b-0",
                  message.handled_at && "opacity-70",
                )}
              >
                <div className="mb-1.5 flex flex-wrap items-center gap-2">
                  <span className="text-sm font-medium text-[var(--color-foreground)]">
                    {message.name}
                  </span>

                  <a
                    href={`mailto:${message.email}`}
                    className="inline-flex items-center gap-1 text-xs text-[var(--color-primary)] underline-offset-4 hover:underline"
                  >
                    <Mail className="size-3" aria-hidden="true" />
                    {message.email}
                  </a>

                  <StatusPill label={message.topic} tone="info" />

                  {message.handled_at && (
                    <StatusPill
                      label={`Handled${message.handled_by ? ` by ${message.handled_by}` : ""}`}
                      tone="success"
                    />
                  )}

                  <span className="ml-auto text-xs text-[var(--color-foreground-subtle)]">
                    {message.created_at ? relativeTime(message.created_at) : ""}
                  </span>
                </div>

                {message.subject && (
                  <p className="text-sm font-medium text-[var(--color-foreground)]">
                    {message.subject}
                  </p>
                )}

                {/* Plain text. This is untrusted input from an anonymous form —
                    rendering it as HTML would make the contact inbox the easiest
                    XSS target in the product. */}
                <p className="mt-1 whitespace-pre-wrap text-sm leading-relaxed text-[var(--color-foreground-muted)]">
                  {message.message}
                </p>

                <Can permission="tickets.update">
                  <Button
                    variant="ghost"
                    size="sm"
                    className="mt-2 -ml-2"
                    loading={busy === message.id}
                    onClick={() => void toggle(message.id)}
                  >
                    {message.handled_at ? (
                      <>
                        <RotateCcw className="size-3.5" aria-hidden="true" />
                        Reopen
                      </>
                    ) : (
                      <>
                        <CheckCircle2 className="size-3.5" aria-hidden="true" />
                        Mark handled
                      </>
                    )}
                  </Button>
                </Can>
              </li>
            ))}
          </ul>
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
