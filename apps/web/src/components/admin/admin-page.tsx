import type * as React from "react";

import { cn } from "@/lib/utils";

/** The one H1 on an admin screen, its explanation, and the page's own actions. */
export function AdminPageHeader({
  eyebrow,
  title,
  description,
  actions,
  className,
}: {
  eyebrow?: string;
  title: string;
  description?: React.ReactNode;
  actions?: React.ReactNode;
  className?: string;
}) {
  return (
    <div className={cn("mb-6 flex flex-wrap items-end justify-between gap-4", className)}>
      <div className="min-w-0">
        {eyebrow && <p className="eyebrow mb-2">{eyebrow}</p>}

        <h1 className="text-[1.625rem] font-bold leading-tight tracking-[-0.02em] text-[var(--color-foreground)]">
          {title}
        </h1>

        {description && (
          <p className="mt-1.5 max-w-2xl text-sm leading-relaxed text-[var(--color-foreground-muted)]">
            {description}
          </p>
        )}
      </div>

      {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
    </div>
  );
}

/** A titled block: header row, optional action, body. */
export function AdminPanel({
  title,
  description,
  action,
  children,
  className,
  bodyClassName,
}: {
  title: string;
  description?: React.ReactNode;
  action?: React.ReactNode;
  children: React.ReactNode;
  className?: string;
  bodyClassName?: string;
}) {
  return (
    <section className={cn("app-card overflow-hidden", className)}>
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--color-border-subtle)] px-4 py-3">
        <div className="min-w-0">
          <h2 className="text-sm font-semibold text-[var(--color-foreground)]">{title}</h2>
          {description && (
            <p className="mt-0.5 text-xs text-[var(--color-foreground-subtle)]">{description}</p>
          )}
        </div>

        {action}
      </div>

      <div className={cn("p-4", bodyClassName)}>{children}</div>
    </section>
  );
}

/** A short, coloured label. The admin's most repeated atom. */
export function StatusPill({
  label,
  tone = "neutral",
  className,
}: {
  label: string;
  tone?: "neutral" | "success" | "warning" | "danger" | "info" | "muted";
  className?: string;
}) {
  const color = {
    neutral: "var(--color-foreground-muted)",
    success: "var(--color-success)",
    warning: "var(--color-warning)",
    danger: "var(--color-danger)",
    info: "var(--color-primary)",
    muted: "var(--color-foreground-subtle)",
  }[tone];

  return (
    <span
      className={cn(
        "inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2 py-0.5 text-[0.6875rem] font-medium",
        className,
      )}
      style={{
        color,
        backgroundColor: `color-mix(in oklab, ${color} 13%, transparent)`,
      }}
    >
      {label}
    </span>
  );
}
