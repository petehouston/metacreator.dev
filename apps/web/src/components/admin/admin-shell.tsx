"use client";

import { Menu, Search, X } from "lucide-react";
import { usePathname } from "next/navigation";
import * as React from "react";

import { AdminCommandPalette } from "@/components/admin/admin-command-palette";
import { adminNavItems } from "@/components/admin/admin-nav";
import { AdminSidebar } from "@/components/admin/admin-sidebar";
import { NotificationBell } from "@/components/account/notification-bell";
import { UserMenu } from "@/components/account/user-menu";
import { useSession } from "@/components/auth/session-provider";
import { ThemeToggle } from "@/components/site/theme-toggle";
import { adminApi } from "@/lib/admin/api";
import { useStoredFlag } from "@/lib/use-stored-flag";
import { cn } from "@/lib/utils";

const COLLAPSE_KEY = "mc:admin-sidebar-collapsed";

/**
 * The staff chrome.
 *
 * Deliberately the same *shape* as the customer app shell — fixed rail, sticky top
 * bar, ⌘K — because staff are also users of the product and re-learning a layout
 * per surface is a tax. What differs is everything that identifies it: a staff mark
 * on the rail, a marker stripe along the top of the viewport, and the accent colour
 * on active navigation.
 */
export function AdminShell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const { can } = useSession();

  const [drawerPath, setDrawerPath] = React.useState<string | null>(null);
  const drawerOpen = drawerPath === pathname;

  const [collapsed, toggle] = useStoredFlag(COLLAPSE_KEY);
  const [paletteOpen, setPaletteOpen] = React.useState(false);
  const [badges, setBadges] = React.useState<Record<string, number>>({});

  React.useEffect(() => {
    document.documentElement.dataset.surface = "app";
    return () => {
      delete document.documentElement.dataset.surface;
    };
  }, []);

  React.useEffect(() => {
    function onKeyDown(event: KeyboardEvent) {
      if (event.key.toLowerCase() === "k" && (event.metaKey || event.ctrlKey)) {
        event.preventDefault();
        setPaletteOpen((open) => !open);
      }
    }

    document.addEventListener("keydown", onKeyDown);
    return () => document.removeEventListener("keydown", onKeyDown);
  }, []);

  // Queue counts on the rail. Fetched once per mount and refreshed on navigation
  // rather than polled: the number matters, but not enough to keep a timer alive
  // in every open tab all day.
  React.useEffect(() => {
    if (!can("tickets.view_any")) return;

    let cancelled = false;

    void (async () => {
      const [tickets, contact] = await Promise.all([
        adminApi.tickets.list({ per_page: 1 }),
        adminApi.contact.list({ "filter[state]": "unhandled", per_page: 1 }),
      ]);

      if (cancelled) return;

      setBadges({
        tickets: tickets.ok ? (tickets.data.meta.counts?.unassigned ?? 0) : 0,
        contact: contact.ok ? (contact.data.meta.counts?.unhandled ?? 0) : 0,
      });
    })();

    return () => {
      cancelled = true;
    };
  }, [can, pathname]);

  const toggleCollapsed = React.useCallback(() => toggle((current) => !current), [toggle]);

  return (
    <>
      <a
        href="#admin-main"
        className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[100] focus:rounded-[var(--radius-md)] focus:bg-[var(--color-primary)] focus:px-4 focus:py-2 focus:text-[var(--color-primary-foreground)]"
      >
        Skip to content
      </a>

      <div className="min-h-dvh bg-[var(--app-ground)]">
        {/* Two pixels of accent along the very top of the viewport. The cheapest
            possible "you are in the staff tool" signal, and the only one that
            survives being cropped out of a screenshot. */}
        <div
          aria-hidden="true"
          className="fixed inset-x-0 top-0 z-50 h-[2px] bg-gradient-to-r from-[var(--color-accent)] via-[var(--color-primary)] to-[var(--color-accent)]"
        />

        <aside
          className={cn(
            "fixed inset-y-0 left-0 z-40 hidden border-r border-[var(--color-border-subtle)] transition-[width] duration-200 lg:block",
            collapsed ? "w-[4.5rem]" : "w-[17rem]",
          )}
        >
          <AdminSidebar
            collapsed={collapsed}
            onToggleCollapsed={toggleCollapsed}
            badges={badges}
          />
        </aside>

        {drawerOpen && (
          <div className="fixed inset-0 z-50 lg:hidden">
            <button
              type="button"
              aria-label="Close navigation"
              onClick={() => setDrawerPath(null)}
              className="animate-fade-in absolute inset-0 bg-[oklch(0.15_0.02_258/0.5)]"
            />

            <div className="absolute inset-y-0 left-0 w-[17rem] border-r border-[var(--color-border)] bg-[var(--app-surface)] shadow-[var(--shadow-popover)]">
              <button
                type="button"
                onClick={() => setDrawerPath(null)}
                aria-label="Close navigation"
                className="absolute right-2 top-4 z-10 flex size-8 items-center justify-center rounded-[var(--radius-md)] text-[var(--color-foreground-muted)]"
              >
                <X className="size-4" aria-hidden="true" />
              </button>

              <AdminSidebar onNavigate={() => setDrawerPath(null)} badges={badges} />
            </div>
          </div>
        )}

        <div
          className={cn(
            "flex min-h-dvh flex-col transition-[padding] duration-200",
            collapsed ? "lg:pl-[4.5rem]" : "lg:pl-[17rem]",
          )}
        >
          <header className="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-2 border-b border-[var(--color-border-subtle)] bg-[var(--app-rail)] px-4 backdrop-blur-xl lg:px-6">
            <button
              type="button"
              onClick={() => setDrawerPath(pathname)}
              aria-label="Open navigation"
              className="flex size-9 items-center justify-center rounded-[var(--radius-md)] text-[var(--color-foreground-muted)] transition-colors hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-foreground)] lg:hidden"
            >
              <Menu className="size-5" aria-hidden="true" />
            </button>

            <div className="min-w-0 flex-1">
              <p className="truncate text-sm font-semibold text-[var(--color-foreground)]">
                {resolveTitle(pathname)}
              </p>
              <p className="truncate font-mono text-[0.625rem] uppercase tracking-[0.14em] text-[var(--color-accent)]">
                Staff area
              </p>
            </div>

            <button
              type="button"
              onClick={() => setPaletteOpen(true)}
              aria-label="Search screens and people"
              aria-keyshortcuts="Meta+K Control+K"
              className="hidden h-9 w-64 items-center gap-2 rounded-[var(--radius-md)] border border-[var(--color-border)] bg-[var(--color-surface-sunken)] px-3 text-sm text-[var(--color-foreground-subtle)] transition-colors hover:border-[var(--color-border-strong)] hover:text-[var(--color-foreground-muted)] md:flex"
            >
              <Search className="size-4" aria-hidden="true" />
              <span className="flex-1 text-left">Search…</span>
              <kbd className="rounded border border-[var(--color-border)] px-1.5 font-mono text-[0.625rem]">
                ⌘K
              </kbd>
            </button>

            <button
              type="button"
              onClick={() => setPaletteOpen(true)}
              aria-label="Search"
              className="flex size-9 items-center justify-center rounded-[var(--radius-md)] text-[var(--color-foreground-muted)] transition-colors hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-foreground)] md:hidden"
            >
              <Search className="size-5" aria-hidden="true" />
            </button>

            <div className="hidden lg:block">
              <ThemeToggle />
            </div>

            <NotificationBell />
            <UserMenu />
          </header>

          <main id="admin-main" className="flex-1 px-4 py-6 lg:px-8 lg:py-8">
            <div className="mx-auto w-full max-w-[90rem]">{children}</div>
          </main>
        </div>
      </div>

      <AdminCommandPalette open={paletteOpen} onOpenChange={setPaletteOpen} />
    </>
  );
}

/**
 * The title comes from the route, not from each page, so a screen cannot render
 * with the wrong one — and adding it to `adminNavItems` is all it takes to name it.
 */
function resolveTitle(pathname: string): string {
  const match = [...adminNavItems]
    .sort((a, b) => b.href.length - a.href.length)
    .find((item) => pathname === item.href || pathname.startsWith(`${item.href}/`));

  if (!match) return "Admin";
  if (pathname === match.href) return match.label;

  const leaf = pathname.slice(match.href.length + 1).split("/")[0];

  // A detail route's leaf is usually an opaque id; naming the parent and letting
  // the page's own H1 carry the specifics reads better than "Usr 01j8…".
  return /^[a-z]{2,4}_[0-9A-Z]{26}$/i.test(leaf) || /^\d+$/.test(leaf)
    ? match.label
    : leaf.replace(/-/g, " ").replace(/^\w/, (character) => character.toUpperCase());
}
