import type * as React from "react";

import { cn } from "@/lib/utils";

/**
 * One number, named, with the context that makes it mean something.
 *
 * The optional meter is drawn from the same tone as the value, so a tile that is
 * running out of headroom says so twice — in the colour and in the hint — which is
 * the only way a warning survives being glanced at.
 */
export function StatTile({
  label,
  value,
  hint,
  icon: Icon,
  progress,
  tone = "neutral",
  className,
}: {
  label: string;
  value: React.ReactNode;
  hint?: React.ReactNode;
  icon?: React.ComponentType<{ className?: string }>;
  /** 0–1. Renders a meter under the value. */
  progress?: number;
  tone?: "neutral" | "primary" | "accent" | "warning" | "danger";
  className?: string;
}) {
  const toneColor = {
    neutral: "var(--color-foreground-subtle)",
    primary: "var(--color-primary)",
    accent: "var(--color-accent)",
    warning: "var(--color-warning)",
    danger: "var(--color-danger)",
  }[tone];

  return (
    <div className={cn("app-card p-4", className)}>
      <div className="flex items-center justify-between gap-2">
        <p className="font-mono text-[0.625rem] font-medium uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
          {label}
        </p>

        {Icon && (
          <span
            className="flex size-7 items-center justify-center rounded-[var(--radius-sm)]"
            style={{
              backgroundColor: `color-mix(in oklab, ${toneColor} 14%, transparent)`,
              color: toneColor,
            }}
          >
            <Icon className="size-3.5" aria-hidden="true" />
          </span>
        )}
      </div>

      <p className="tabular mt-2.5 text-2xl font-semibold leading-none tracking-[-0.02em] text-[var(--color-foreground)]">
        {value}
      </p>

      {progress !== undefined && (
        <div
          className="mt-3 h-1.5 overflow-hidden rounded-full bg-[var(--color-surface-sunken)]"
          role="progressbar"
          aria-valuenow={Math.round(Math.min(1, Math.max(0, progress)) * 100)}
          aria-valuemin={0}
          aria-valuemax={100}
          aria-label={label}
        >
          <div
            className="h-full rounded-full transition-[width] duration-500"
            style={{
              width: `${Math.min(100, Math.round(progress * 100))}%`,
              backgroundColor: toneColor,
            }}
          />
        </div>
      )}

      {hint && (
        <p className="mt-2 text-xs leading-snug text-[var(--color-foreground-subtle)]">{hint}</p>
      )}
    </div>
  );
}

export function StatTileSkeleton() {
  return <div className="app-card h-[7.5rem] animate-pulse" aria-hidden="true" />;
}
