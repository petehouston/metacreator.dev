"use client";

import { Menu, Search } from "lucide-react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import * as React from "react";

import { NotificationBell } from "@/components/account/notification-bell";
import { UserMenu } from "@/components/account/user-menu";
import { navItems } from "@/components/app/nav-items";
import { ThemeToggle } from "@/components/site/theme-toggle";

/**
 * The shell's top rail: where am I, find anything, what changed, who am I.
 *
 * The title is derived from the route rather than passed down from each page, so a
 * page cannot render with the wrong one — and adding a screen to `navItems` is all
 * it takes for the rail to name it.
 */
export function AppTopbar({
  onOpenSidebar,
  onOpenCommandPalette,
}: {
  onOpenSidebar: () => void;
  onOpenCommandPalette: () => void;
}) {
  const pathname = usePathname();
  const current = resolveTitle(pathname);

  return (
    <header className="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-2 border-b border-[var(--color-border-subtle)] bg-[var(--app-rail)] px-4 backdrop-blur-xl lg:px-6">
      <button
        type="button"
        onClick={onOpenSidebar}
        aria-label="Open navigation"
        className="flex size-9 items-center justify-center rounded-[var(--radius-md)] text-[var(--color-foreground-muted)] transition-colors hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-foreground)] lg:hidden"
      >
        <Menu className="size-5" aria-hidden="true" />
      </button>

      <div className="min-w-0 flex-1">
        <p className="truncate text-sm font-semibold text-[var(--color-foreground)]">
          {current.title}
        </p>
        {current.parent && (
          <p className="truncate text-xs text-[var(--color-foreground-subtle)]">
            <Link href={current.parent.href} className="hover:text-[var(--color-foreground)]">
              {current.parent.label}
            </Link>
          </p>
        )}
      </div>

      {/* Wide screens get the affordance spelled out; narrow ones get the icon,
          because a 200px search box that opens a dialog is worse than a button. */}
      <button
        type="button"
        onClick={onOpenCommandPalette}
        aria-label="Search tools and screens"
        aria-keyshortcuts="Meta+K Control+K"
        className="hidden h-9 w-56 items-center gap-2 rounded-[var(--radius-md)] border border-[var(--color-border)] bg-[var(--color-surface-sunken)] px-3 text-sm text-[var(--color-foreground-subtle)] transition-colors hover:border-[var(--color-border-strong)] hover:text-[var(--color-foreground-muted)] md:flex"
      >
        <Search className="size-4" aria-hidden="true" />
        <span className="flex-1 text-left">Search…</span>
        <kbd className="rounded border border-[var(--color-border)] px-1.5 font-mono text-[0.625rem]">
          ⌘K
        </kbd>
      </button>

      <button
        type="button"
        onClick={onOpenCommandPalette}
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
  );
}

function resolveTitle(pathname: string): {
  title: string;
  parent?: { href: string; label: string };
} {
  // Longest match wins, so `/dashboard/settings/notifications` resolves to the
  // settings screen rather than to `/dashboard`.
  const match = [...navItems]
    .sort((a, b) => b.href.length - a.href.length)
    .find((item) => pathname === item.href || pathname.startsWith(`${item.href}/`));

  if (!match) return { title: "Dashboard" };

  if (pathname !== match.href) {
    const leaf = pathname.slice(match.href.length + 1).split("/")[0];

    return {
      title: leaf.replace(/-/g, " ").replace(/^\w/, (character) => character.toUpperCase()),
      parent: { href: match.href, label: match.label },
    };
  }

  return { title: match.label };
}
