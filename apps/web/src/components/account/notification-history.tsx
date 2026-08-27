"use client";

import Link from "next/link";
import * as React from "react";

import { FormAlert } from "@/components/auth/form-alert";
import { Button } from "@/components/ui/button";
import { notificationsApi } from "@/lib/auth-api";
import type { NotificationItem } from "@/lib/types";
import { cn } from "@/lib/utils";

export function NotificationHistory() {
  const [items, setItems] = React.useState<NotificationItem[]>([]);
  const [page, setPage] = React.useState(1);
  const [lastPage, setLastPage] = React.useState(1);
  const [unread, setUnread] = React.useState(0);
  const [unreadOnly, setUnreadOnly] = React.useState(false);
  const [loading, setLoading] = React.useState(true);
  const [error, setError] = React.useState<string | null>(null);

  React.useEffect(() => {
    let cancelled = false;

    void (async () => {
      const result = await notificationsApi.list({ page, unread: unreadOnly });
      if (cancelled) return;

      if (result.ok) {
        setItems(result.data.data);
        setLastPage(result.data.meta.page.last_page);
        setUnread(result.data.meta.unread);
      } else {
        setError(result.error.message);
      }

      setLoading(false);
    })();

    return () => {
      cancelled = true;
    };
  }, [page, unreadOnly]);

  async function markAllRead() {
    const result = await notificationsApi.markAllRead();

    if (result.ok) {
      setUnread(0);
      setItems((current) =>
        current.map((item) => ({ ...item, read_at: item.read_at ?? new Date().toISOString() })),
      );
    }
  }

  return (
    <div className="flex flex-col gap-4">
      {error && <FormAlert>{error}</FormAlert>}

      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex gap-1" role="group" aria-label="Filter notifications">
          <FilterButton
            active={!unreadOnly}
            onClick={() => {
              setUnreadOnly(false);
              setPage(1);
            }}
          >
            All
          </FilterButton>
          <FilterButton
            active={unreadOnly}
            onClick={() => {
              setUnreadOnly(true);
              setPage(1);
            }}
          >
            Unread{unread > 0 ? ` (${unread})` : ""}
          </FilterButton>
        </div>

        {unread > 0 && (
          <Button variant="ghost" size="sm" onClick={markAllRead}>
            Mark all read
          </Button>
        )}
      </div>

      {loading && items.length === 0 && (
        <p className="text-sm text-[var(--color-foreground-subtle)]">Loading…</p>
      )}

      {!loading && items.length === 0 && (
        <div className="rounded-[var(--radius-lg)] border border-dashed border-[var(--color-border)] px-6 py-12 text-center">
          <p className="text-sm text-[var(--color-foreground-muted)]">
            {unreadOnly ? "Nothing unread." : "No notifications yet."}
          </p>
        </div>
      )}

      {items.length > 0 && (
        <ul className="divide-y divide-[var(--color-border-subtle)] panel">
          {items.map((item) => (
            <li key={item.id} className="flex gap-3 px-4 py-4">
              <span
                className={cn(
                  "mt-1.5 size-1.5 shrink-0 rounded-full",
                  item.read_at ? "bg-transparent" : "bg-[var(--color-primary)]",
                )}
                aria-label={item.read_at ? undefined : "Unread"}
              />

              <div className="min-w-0 flex-1">
                <p className="text-sm font-medium text-[var(--color-foreground)]">{item.title}</p>
                <p className="mt-0.5 text-sm leading-relaxed text-[var(--color-foreground-muted)]">
                  {item.body}
                </p>

                <p className="mt-1 flex items-center gap-3 text-xs text-[var(--color-foreground-subtle)]">
                  <span>{formatDateTime(item.created_at)}</span>

                  {item.action && (
                    <Link
                      href={item.action.url}
                      className="font-medium text-[var(--color-primary)] hover:underline"
                    >
                      {item.action.label}
                    </Link>
                  )}
                </p>
              </div>
            </li>
          ))}
        </ul>
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

          <span className="text-sm text-[var(--color-foreground-subtle)]">
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

function FilterButton({
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
        "rounded-[var(--radius-md)] px-3 py-1.5 text-sm font-medium transition-colors",
        active
          ? "bg-[var(--color-primary-subtle)] text-[var(--color-primary)]"
          : "text-[var(--color-foreground-muted)] hover:bg-[var(--color-surface-sunken)]",
      )}
    >
      {children}
    </button>
  );
}

function formatDateTime(iso: string | null): string {
  if (!iso) return "";

  return new Date(iso).toLocaleString(undefined, {
    month: "short",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
}
