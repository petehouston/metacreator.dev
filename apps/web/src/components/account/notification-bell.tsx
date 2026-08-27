"use client";

import { Bell } from "lucide-react";
import Link from "next/link";
import * as React from "react";

import { useSession } from "@/components/auth/session-provider";
import { notificationsApi } from "@/lib/auth-api";
import type { NotificationItem } from "@/lib/types";
import { cn } from "@/lib/utils";

/**
 * Bell, unread badge, and a panel grouped Today / Earlier (docs/13).
 *
 * Reads are batched and flushed when the panel closes rather than sent per row —
 * a user scrolling twenty notifications should cost one request, not twenty.
 */
export function NotificationBell() {
  const { user } = useSession();

  const [open, setOpen] = React.useState(false);
  const [items, setItems] = React.useState<NotificationItem[]>([]);
  const [unread, setUnread] = React.useState(0);
  const [loading, setLoading] = React.useState(false);

  const containerRef = React.useRef<HTMLDivElement>(null);
  const pendingReads = React.useRef<Set<string>>(new Set());

  const load = React.useCallback(async () => {
    setLoading(true);
    const result = await notificationsApi.list();
    setLoading(false);

    if (result.ok) {
      setItems(result.data.data);
      setUnread(result.data.meta.unread);
    }
  }, []);

  // One fetch on mount for the badge count. No polling: a badge that is a few
  // minutes stale is not worth a request every thirty seconds from every open tab.
  //
  // This deliberately does not go through `load()` — nothing is on screen yet, so
  // there is no spinner to drive, and touching `loading` synchronously here would be
  // a cascading render for no visible benefit.
  React.useEffect(() => {
    if (!user) return;

    let cancelled = false;

    void (async () => {
      const result = await notificationsApi.list();
      if (cancelled || !result.ok) return;

      setItems(result.data.data);
      setUnread(result.data.meta.unread);
    })();

    return () => {
      cancelled = true;
    };
  }, [user]);

  const flushReads = React.useCallback(async () => {
    const ids = Array.from(pendingReads.current);
    if (ids.length === 0) return;

    pendingReads.current.clear();
    const result = await notificationsApi.markRead(ids);

    if (result.ok) setUnread(result.data.unread);
  }, []);

  React.useEffect(() => {
    if (!open) return;

    function onPointerDown(event: MouseEvent) {
      if (!containerRef.current?.contains(event.target as Node)) setOpen(false);
    }

    function onKeyDown(event: KeyboardEvent) {
      if (event.key === "Escape") setOpen(false);
    }

    document.addEventListener("mousedown", onPointerDown);
    document.addEventListener("keydown", onKeyDown);

    return () => {
      document.removeEventListener("mousedown", onPointerDown);
      document.removeEventListener("keydown", onKeyDown);
    };
  }, [open]);

  function toggle() {
    if (open) {
      void flushReads();
      setOpen(false);
      return;
    }

    setOpen(true);
    void load();
  }

  function markVisibleRead(item: NotificationItem) {
    if (item.read_at) return;

    pendingReads.current.add(item.id);
    setItems((current) =>
      current.map((entry) =>
        entry.id === item.id ? { ...entry, read_at: new Date().toISOString() } : entry,
      ),
    );
    setUnread((count) => Math.max(0, count - 1));
  }

  async function markAllRead() {
    pendingReads.current.clear();
    const result = await notificationsApi.markAllRead();

    if (result.ok) {
      setUnread(0);
      setItems((current) =>
        current.map((entry) => ({ ...entry, read_at: entry.read_at ?? new Date().toISOString() })),
      );
    }
  }

  if (!user) return null;

  const { today, earlier } = splitByDay(items);

  return (
    <div className="relative" ref={containerRef}>
      <button
        type="button"
        onClick={toggle}
        aria-expanded={open}
        aria-label={unread > 0 ? `Notifications, ${unread} unread` : "Notifications"}
        className="relative flex size-9 items-center justify-center rounded-[var(--radius-md)] text-[var(--color-foreground-muted)] transition-colors hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-foreground)]"
      >
        <Bell className="size-5" aria-hidden="true" />

        {unread > 0 && (
          <span className="absolute right-1 top-1 flex min-w-4 items-center justify-center rounded-full bg-[var(--color-primary)] px-1 text-[10px] font-semibold leading-4 text-[var(--color-primary-foreground)]">
            {unread > 9 ? "9+" : unread}
          </span>
        )}
      </button>

      {open && (
        <div className="absolute right-0 top-full z-50 mt-2 flex max-h-[28rem] w-[22rem] flex-col overflow-hidden rounded-[var(--radius-md)] border border-[var(--color-border)] bg-[var(--color-surface)] shadow-[var(--shadow-popover,0_12px_32px_rgba(0,0,0,0.14))]">
          <div className="flex items-center justify-between border-b border-[var(--color-border-subtle)] px-4 py-3">
            <p className="text-sm font-semibold text-[var(--color-foreground)]">Notifications</p>

            {unread > 0 && (
              <button
                type="button"
                onClick={markAllRead}
                className="text-xs font-medium text-[var(--color-primary)] hover:underline"
              >
                Mark all read
              </button>
            )}
          </div>

          <div className="flex-1 overflow-y-auto">
            {loading && items.length === 0 && (
              <p className="px-4 py-8 text-center text-sm text-[var(--color-foreground-subtle)]">
                Loading…
              </p>
            )}

            {!loading && items.length === 0 && (
              <p className="px-4 py-10 text-center text-sm text-[var(--color-foreground-subtle)]">
                Nothing yet. We&apos;ll tell you when something happens.
              </p>
            )}

            {today.length > 0 && <GroupHeading>Today</GroupHeading>}
            {today.map((item) => (
              <NotificationRow key={item.id} item={item} onSeen={markVisibleRead} />
            ))}

            {earlier.length > 0 && <GroupHeading>Earlier</GroupHeading>}
            {earlier.map((item) => (
              <NotificationRow key={item.id} item={item} onSeen={markVisibleRead} />
            ))}
          </div>

          <div className="border-t border-[var(--color-border-subtle)] px-4 py-2.5">
            <Link
              href="/dashboard/notifications"
              onClick={() => {
                void flushReads();
                setOpen(false);
              }}
              className="text-xs font-medium text-[var(--color-primary)] hover:underline"
            >
              See all notifications
            </Link>
          </div>
        </div>
      )}
    </div>
  );
}

function GroupHeading({ children }: { children: React.ReactNode }) {
  return (
    <p className="sticky top-0 bg-[var(--color-surface)] px-4 pb-1 pt-3 text-[11px] font-semibold uppercase tracking-wider text-[var(--color-foreground-subtle)]">
      {children}
    </p>
  );
}

function NotificationRow({
  item,
  onSeen,
}: {
  item: NotificationItem;
  onSeen: (item: NotificationItem) => void;
}) {
  const body = (
    <>
      <span className="flex items-start gap-2.5">
        {!item.read_at && (
          <span
            className="mt-1.5 size-1.5 shrink-0 rounded-full bg-[var(--color-primary)]"
            aria-label="Unread"
          />
        )}

        <span className={cn("flex flex-col gap-0.5", item.read_at && "pl-4")}>
          <span className="text-sm font-medium leading-snug text-[var(--color-foreground)]">
            {item.title}
          </span>
          <span className="text-xs leading-relaxed text-[var(--color-foreground-muted)]">
            {item.body}
          </span>
          <span className="text-[11px] text-[var(--color-foreground-subtle)]">
            {relativeTime(item.created_at)}
          </span>
        </span>
      </span>
    </>
  );

  const className =
    "block w-full px-4 py-2.5 text-left transition-colors hover:bg-[var(--color-surface-sunken)]";

  if (item.action) {
    return (
      <Link href={item.action.url} className={className} onClick={() => onSeen(item)}>
        {body}
      </Link>
    );
  }

  return (
    <button type="button" className={className} onClick={() => onSeen(item)}>
      {body}
    </button>
  );
}

function splitByDay(items: NotificationItem[]) {
  const startOfToday = new Date();
  startOfToday.setHours(0, 0, 0, 0);

  const today: NotificationItem[] = [];
  const earlier: NotificationItem[] = [];

  for (const item of items) {
    const at = item.created_at ? new Date(item.created_at) : null;
    (at && at >= startOfToday ? today : earlier).push(item);
  }

  return { today, earlier };
}

function relativeTime(iso: string | null): string {
  if (!iso) return "";

  const seconds = Math.round((Date.now() - new Date(iso).getTime()) / 1000);
  const formatter = new Intl.RelativeTimeFormat(undefined, { numeric: "auto" });

  const units: [Intl.RelativeTimeFormatUnit, number][] = [
    ["second", 60],
    ["minute", 60],
    ["hour", 24],
    ["day", 7],
    ["week", 4.35],
    ["month", 12],
  ];

  let value = seconds;

  for (const [unit, size] of units) {
    if (Math.abs(value) < size) return formatter.format(-Math.round(value), unit);
    value /= size;
  }

  return formatter.format(-Math.round(value), "year");
}
