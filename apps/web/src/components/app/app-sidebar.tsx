"use client";

import { ChevronsLeft, ChevronsRight, ExternalLink } from "lucide-react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import * as React from "react";

import { isActive, navSections } from "@/components/app/nav-items";
import { PlanMeter } from "@/components/app/plan-meter";
import { Logo } from "@/components/site/logo";
import { cn } from "@/lib/utils";

/**
 * The shell's spine.
 *
 * Two states, not three: full (17rem) and rail (4.5rem). A rail that still shows
 * labels is just a narrow sidebar, and a sidebar that disappears entirely costs a
 * click on every navigation — so the collapse keeps the icons and their tooltips.
 */
export function AppSidebar({
  collapsed = false,
  onToggleCollapsed,
  onNavigate,
}: {
  collapsed?: boolean;
  onToggleCollapsed?: () => void;
  onNavigate?: () => void;
}) {
  const pathname = usePathname();

  return (
    <div className="flex h-full flex-col gap-1 bg-[var(--app-rail)] backdrop-blur-xl">
      <div
        className={cn(
          "flex h-16 shrink-0 items-center border-b border-[var(--color-border-subtle)]",
          collapsed ? "justify-center px-2" : "justify-between px-4",
        )}
      >
        {collapsed && onToggleCollapsed ? (
          <button
            type="button"
            onClick={onToggleCollapsed}
            aria-label="Expand sidebar"
            title="Expand sidebar"
            className="group flex size-10 items-center justify-center rounded-[var(--radius-md)] transition-colors hover:bg-[var(--color-surface-sunken)]"
          >
            <svg viewBox="0 0 32 32" className="size-8 group-hover:hidden" aria-hidden="true">
              <rect
                x="3"
                y="3"
                width="19"
                height="19"
                rx="6"
                className="fill-[var(--color-brand-500)]"
                opacity="0.85"
              />
              <rect
                x="10"
                y="10"
                width="19"
                height="19"
                rx="6"
                className="fill-[var(--color-signal-400)]"
                opacity="0.9"
                style={{ mixBlendMode: "screen" }}
              />
            </svg>
            <ChevronsRight
              className="hidden size-4 text-[var(--color-foreground-muted)] group-hover:block"
              aria-hidden="true"
            />
          </button>
        ) : collapsed ? (
          <Link href="/dashboard" aria-label="MetaCreator.Dev dashboard">
            <svg viewBox="0 0 32 32" className="size-8" aria-hidden="true">
              <rect
                x="3"
                y="3"
                width="19"
                height="19"
                rx="6"
                className="fill-[var(--color-brand-500)]"
                opacity="0.85"
              />
              <rect
                x="10"
                y="10"
                width="19"
                height="19"
                rx="6"
                className="fill-[var(--color-signal-400)]"
                opacity="0.9"
                style={{ mixBlendMode: "screen" }}
              />
            </svg>
          </Link>
        ) : (
          <Logo href="/dashboard" />
        )}

        {onToggleCollapsed && !collapsed && (
          <button
            type="button"
            onClick={onToggleCollapsed}
            aria-label="Collapse sidebar"
            className="hidden size-8 items-center justify-center rounded-[var(--radius-md)] text-[var(--color-foreground-subtle)] transition-colors hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-foreground)] lg:flex"
          >
            <ChevronsLeft className="size-4" aria-hidden="true" />
          </button>
        )}
      </div>

      <nav
        aria-label="Dashboard"
        className={cn(
          "scrollbar-slim flex-1 overflow-y-auto py-3",
          collapsed ? "px-2" : "px-3",
        )}
      >
        {navSections.map((section) => (
          <div key={section.title} className="mb-4 last:mb-0">
            {collapsed ? (
              <hr className="mx-2 mb-2 border-t border-[var(--color-border-subtle)] first:hidden" />
            ) : (
              <p className="mb-1.5 px-2.5 font-mono text-[0.625rem] font-medium uppercase tracking-[0.14em] text-[var(--color-foreground-subtle)]">
                {section.title}
              </p>
            )}

            <ul className="flex flex-col gap-0.5">
              {section.items.map((item) => {
                const active = isActive(item.href, pathname);
                const Icon = item.icon;

                return (
                  <li key={item.href}>
                    <Link
                      href={item.href}
                      onClick={onNavigate}
                      aria-current={active ? "page" : undefined}
                      title={collapsed ? item.label : undefined}
                      className={cn(
                        "group relative flex items-center rounded-[var(--radius-md)] text-sm font-medium transition-colors",
                        collapsed ? "h-10 justify-center" : "gap-2.5 px-2.5 py-2",
                        active
                          ? "bg-[var(--color-primary-subtle)] text-[var(--color-primary)]"
                          : "text-[var(--color-foreground-muted)] hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-foreground)]",
                      )}
                    >
                      {/* The active marker is a spine on the left edge rather than a
                          heavier fill — it survives both themes and reads at a glance
                          down a column of otherwise identical rows. */}
                      {active && (
                        <span
                          aria-hidden="true"
                          className="absolute inset-y-1.5 left-0 w-[3px] rounded-full bg-[var(--color-primary)]"
                        />
                      )}

                      <Icon className="size-4 shrink-0" aria-hidden="true" />
                      {!collapsed && <span className="truncate">{item.label}</span>}
                      {collapsed && <span className="sr-only">{item.label}</span>}
                    </Link>
                  </li>
                );
              })}
            </ul>
          </div>
        ))}
      </nav>

      <div
        className={cn(
          "shrink-0 border-t border-[var(--color-border-subtle)] py-3",
          collapsed ? "flex flex-col items-center gap-2 px-2" : "px-3",
        )}
      >
        <PlanMeter compact={collapsed} />

        {!collapsed && (
          <Link
            href="/"
            className="mt-3 flex items-center gap-2 px-2.5 text-xs font-medium text-[var(--color-foreground-subtle)] transition-colors hover:text-[var(--color-foreground)]"
          >
            <ExternalLink className="size-3.5" aria-hidden="true" />
            Back to metacreator.dev
          </Link>
        )}
      </div>
    </div>
  );
}
