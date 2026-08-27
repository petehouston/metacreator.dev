"use client";

import { CalendarRange } from "lucide-react";

import { cn } from "@/lib/utils";

const LABELS: Record<number, string> = {
  7: "7d",
  14: "14d",
  30: "30d",
  90: "90d",
  365: "12m",
};

/**
 * The window every number on the screen is measured over.
 *
 * A segmented control rather than a dropdown: there are five options, switching is
 * the most common interaction on an analytics screen, and a dropdown makes the
 * current selection cost a click to even read.
 */
export function PeriodPicker({
  value,
  options,
  onChange,
  className,
}: {
  value: number;
  options: number[];
  onChange: (days: number) => void;
  className?: string;
}) {
  return (
    <div
      role="group"
      aria-label="Reporting period"
      className={cn(
        "inline-flex items-center gap-0.5 rounded-[var(--radius-md)] border border-[var(--color-border)] bg-[var(--color-surface-sunken)] p-0.5",
        className,
      )}
    >
      <CalendarRange
        className="ml-1.5 mr-0.5 size-3.5 text-[var(--color-foreground-subtle)]"
        aria-hidden="true"
      />

      {options.map((days) => (
        <button
          key={days}
          type="button"
          onClick={() => onChange(days)}
          aria-pressed={days === value}
          className={cn(
            "tabular rounded-[var(--radius-sm)] px-2.5 py-1 text-xs font-medium transition-colors",
            days === value
              ? "bg-[var(--app-surface)] text-[var(--color-foreground)] shadow-[var(--shadow-card)]"
              : "text-[var(--color-foreground-subtle)] hover:text-[var(--color-foreground)]",
          )}
        >
          {LABELS[days] ?? `${days}d`}
          <span className="sr-only"> period</span>
        </button>
      ))}
    </div>
  );
}
