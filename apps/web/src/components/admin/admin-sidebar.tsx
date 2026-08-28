"use client";

import { ChevronsLeft, ChevronsRight, ExternalLink, LayoutDashboard } from "lucide-react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import * as React from "react";

import { isAdminActive, visibleSections } from "@/components/admin/admin-nav";
import { useSession } from "@/components/auth/session-provider";
import { cn } from "@/lib/utils";

/**
 * The admin rail.
 *
 * Visually a sibling of the customer dashboard's sidebar, not a copy: the mark
 * carries a "Staff" tag and the active spine is the accent rather than the brand
 * blue, so a screenshot from the admin and a screenshot from a customer's
 * dashboard can never be mistaken for one another in a support thread.
 */
function Mark({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 32 32" className={className} aria-hidden="true">
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
  );
}

export function AdminSidebar({
  collapsed = false,
  onToggleCollapsed,
  onNavigate,
  badges = {},
}: {
  collapsed?: boolean;
  onToggleCollapsed?: () => void;
  onNavigate?: () => void;
  badges?: Partial<Record<string, number>>;
}) {
  const pathname = usePathname();
  const { can } = useSession();

  // Recomputed from the session rather than stored: a role change that arrives on
  // the next session refresh should change the navigation with it.
  const sections = React.useMemo(() => visibleSections(can), [can]);

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
            <Mark className="size-8 shrink-0 group-hover:hidden" />
            <ChevronsRight
              className="hidden size-4 text-[var(--color-foreground-muted)] group-hover:block"
              aria-hidden="true"
            />
          </button>
        ) : (
          <Link
            href="/admin"
            className="flex min-w-0 items-center gap-2.5"
            aria-label="MetaCreator.Dev admin"
          >
            <Mark className="size-8 shrink-0" />

            <span className="flex min-w-0 flex-col leading-tight">
              <span className="truncate text-sm font-semibold text-[var(--color-foreground)]">
                MetaCreator
              </span>
              <span className="font-mono text-[0.625rem] font-medium uppercase tracking-[0.16em] text-[var(--color-accent)]">
                Staff
              </span>
            </span>
          </Link>
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
        aria-label="Admin"
        className={cn("scrollbar-slim flex-1 overflow-y-auto py-3", collapsed ? "px-2" : "px-3")}
      >
        {sections.map((section) => (
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
                const active = isAdminActive(item.href, pathname);
                const Icon = item.icon;
                const badge = item.badgeKey ? badges[item.badgeKey] : undefined;

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
                          ? "bg-[var(--color-accent-surface)] text-[var(--color-foreground)]"
                          : "text-[var(--color-foreground-muted)] hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-foreground)]",
                      )}
                    >
                      {active && (
                        <span
                          aria-hidden="true"
                          className="absolute inset-y-1.5 left-0 w-[3px] rounded-full bg-[var(--color-accent)]"
                        />
                      )}

                      <Icon className="size-4 shrink-0" aria-hidden="true" />

                      {!collapsed && <span className="flex-1 truncate">{item.label}</span>}
                      {collapsed && <span className="sr-only">{item.label}</span>}

                      {badge !== undefined && badge > 0 && (
                        <span
                          className={cn(
                            "tabular rounded-full bg-[var(--color-primary)] px-1.5 text-[0.625rem] font-semibold leading-[1.35] text-[var(--color-primary-foreground)]",
                            collapsed && "absolute right-1 top-1 px-1",
                          )}
                        >
                          {badge > 99 ? "99+" : badge}
                          <span className="sr-only"> waiting</span>
                        </span>
                      )}
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
          collapsed ? "flex flex-col items-center gap-2 px-2" : "flex flex-col gap-1 px-3",
        )}
      >
        <Link
          href="/dashboard"
          title={collapsed ? "Your dashboard" : undefined}
          className={cn(
            "flex items-center gap-2 rounded-[var(--radius-md)] text-xs font-medium text-[var(--color-foreground-subtle)] transition-colors hover:text-[var(--color-foreground)]",
            collapsed ? "size-10 justify-center" : "px-2.5 py-1.5",
          )}
        >
          <LayoutDashboard className="size-3.5 shrink-0" aria-hidden="true" />
          {!collapsed && "Your dashboard"}
          {collapsed && <span className="sr-only">Your dashboard</span>}
        </Link>

        {!collapsed && (
          <Link
            href="/"
            className="flex items-center gap-2 rounded-[var(--radius-md)] px-2.5 py-1.5 text-xs font-medium text-[var(--color-foreground-subtle)] transition-colors hover:text-[var(--color-foreground)]"
          >
            <ExternalLink className="size-3.5" aria-hidden="true" />
            View the site
          </Link>
        )}
      </div>
    </div>
  );
}
