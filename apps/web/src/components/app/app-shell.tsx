"use client";

import { X } from "lucide-react";
import { usePathname } from "next/navigation";
import * as React from "react";

import { AppSidebar } from "@/components/app/app-sidebar";
import { AppTopbar } from "@/components/app/app-topbar";
import { CommandPalette } from "@/components/app/command-palette";
import { EntitlementsProvider } from "@/components/app/entitlements-provider";
import { useStoredFlag } from "@/lib/use-stored-flag";
import { cn } from "@/lib/utils";

const COLLAPSE_KEY = "mc:sidebar-collapsed";

/**
 * The signed-in chrome: a fixed rail, a sticky top bar, and a scrolling content
 * column. Deliberately *not* the marketing layout with a sidebar added — the two
 * surfaces answer different questions, so they get different frames.
 *
 * `data-surface="app"` on <html> is what swaps the painted marketing canvas for a
 * flat workspace ground (see globals.css). It is set here rather than in CSS
 * because the attribute has to follow the route, not the stylesheet.
 */
export function AppShell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();

  // The drawer is stored as "the path it was opened on" rather than as a bare
  // boolean, so navigating away closes it by derivation instead of by an effect
  // that resets state after the new page has already rendered with it open.
  const [drawerPath, setDrawerPath] = React.useState<string | null>(null);
  const drawerOpen = drawerPath === pathname;

  const [collapsed, toggle] = useStoredFlag(COLLAPSE_KEY);
  const [paletteOpen, setPaletteOpen] = React.useState(false);

  React.useEffect(() => {
    document.documentElement.dataset.surface = "app";
    return () => {
      delete document.documentElement.dataset.surface;
    };
  }, []);

  const toggleCollapsed = React.useCallback(
    () => toggle((current) => !current),
    [toggle],
  );

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

  return (
    <EntitlementsProvider>
      <a
        href="#app-main"
        className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[100] focus:rounded-[var(--radius-md)] focus:bg-[var(--color-primary)] focus:px-4 focus:py-2 focus:text-[var(--color-primary-foreground)]"
      >
        Skip to content
      </a>

      <div className="min-h-dvh bg-[var(--app-ground)]">
        {/* Desktop rail. Fixed rather than sticky: the nav must not scroll away
            from someone who is deep in a long run history. */}
        <aside
          className={cn(
            "fixed inset-y-0 left-0 z-40 hidden border-r border-[var(--color-border-subtle)] transition-[width] duration-200 lg:block",
            collapsed ? "w-[4.5rem]" : "w-[17rem]",
          )}
        >
          <AppSidebar collapsed={collapsed} onToggleCollapsed={toggleCollapsed} />
        </aside>

        {/* Mobile drawer. */}
        {drawerOpen && (
          <div className="fixed inset-0 z-50 lg:hidden">
            <button
              type="button"
              aria-label="Close navigation"
              onClick={() => setDrawerPath(null)}
              className="animate-fade-in absolute inset-0 bg-[oklch(0.15_0.02_258/0.5)]"
            />

            {/* Opaque, unlike the desktop rail: an overlay you can read the page
                through is an overlay that is hard to read. */}
            <div className="absolute inset-y-0 left-0 w-[17rem] border-r border-[var(--color-border)] bg-[var(--app-surface)] shadow-[var(--shadow-popover)]">
              <button
                type="button"
                onClick={() => setDrawerPath(null)}
                aria-label="Close navigation"
                className="absolute right-2 top-4 z-10 flex size-8 items-center justify-center rounded-[var(--radius-md)] text-[var(--color-foreground-muted)]"
              >
                <X className="size-4" aria-hidden="true" />
              </button>

              <AppSidebar onNavigate={() => setDrawerPath(null)} />
            </div>
          </div>
        )}

        <div
          className={cn(
            "flex min-h-dvh flex-col transition-[padding] duration-200",
            collapsed ? "lg:pl-[4.5rem]" : "lg:pl-[17rem]",
          )}
        >
          <AppTopbar
            onOpenSidebar={() => setDrawerPath(pathname)}
            onOpenCommandPalette={() => setPaletteOpen(true)}
          />

          <main id="app-main" className="flex-1 px-4 py-6 lg:px-8 lg:py-8">
            <div className="mx-auto w-full max-w-[80rem]">{children}</div>
          </main>
        </div>
      </div>

      <CommandPalette open={paletteOpen} onOpenChange={setPaletteOpen} />
    </EntitlementsProvider>
  );
}
