"use client";

import { Search, X } from "lucide-react";
import * as React from "react";

import { cn } from "@/lib/utils";

/**
 * The row of controls above a management table.
 *
 * The search input is debounced *inside* the component, so every screen gets the
 * same behaviour without each one remembering to add a timer — a list that fires a
 * request per keystroke is the most common performance mistake in an admin tool.
 */
export function SearchInput({
  value,
  onChange,
  placeholder = "Search…",
  className,
  delay = 250,
}: {
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  className?: string;
  delay?: number;
}) {
  const [draft, setDraft] = React.useState(value);
  const [lastValue, setLastValue] = React.useState(value);

  const onChangeRef = React.useRef(onChange);

  React.useInsertionEffect(() => {
    onChangeRef.current = onChange;
  });

  // Re-sync when the owner resets the query — a cleared filter chip, a route
  // change — adjusting state during render rather than in an effect. React
  // documents this exact pattern: it re-renders immediately without committing the
  // stale value, where an effect would paint a term nothing is filtered by first.
  if (value !== lastValue) {
    setLastValue(value);
    setDraft(value);
  }

  React.useEffect(() => {
    if (draft === value) return;

    const timer = setTimeout(() => onChangeRef.current(draft), delay);

    return () => clearTimeout(timer);
  }, [draft, value, delay]);

  return (
    <div className={cn("relative min-w-0", className)}>
      <Search
        className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[var(--color-foreground-subtle)]"
        aria-hidden="true"
      />

      <input
        type="search"
        value={draft}
        onChange={(event) => setDraft(event.target.value)}
        placeholder={placeholder}
        aria-label={placeholder}
        className="h-9 w-full min-w-0 rounded-[var(--radius-md)] border border-[var(--color-border)] bg-[var(--color-surface-raised)] pl-9 pr-8 text-sm text-[var(--color-foreground)] transition-colors placeholder:text-[var(--color-foreground-subtle)] hover:border-[var(--color-border-strong)] focus:border-[var(--color-ring)] focus:outline-none focus:ring-2 focus:ring-[var(--color-ring)]/25"
      />

      {draft !== "" && (
        <button
          type="button"
          onClick={() => setDraft("")}
          aria-label="Clear search"
          className="absolute right-2 top-1/2 flex size-5 -translate-y-1/2 items-center justify-center rounded-full text-[var(--color-foreground-subtle)] transition-colors hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-foreground)]"
        >
          <X className="size-3.5" aria-hidden="true" />
        </button>
      )}
    </div>
  );
}

/** A compact labelled `<select>` sized to sit beside the search input. */
export function FilterSelect({
  label,
  value,
  options,
  onChange,
  className,
}: {
  label: string;
  value: string;
  options: { value: string; label: string }[];
  onChange: (value: string) => void;
  className?: string;
}) {
  const id = React.useId();

  return (
    <div className={cn("flex min-w-0 items-center gap-2", className)}>
      <label
        htmlFor={id}
        className="whitespace-nowrap font-mono text-[0.625rem] font-medium uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]"
      >
        {label}
      </label>

      <select
        id={id}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        className="h-9 min-w-0 rounded-[var(--radius-md)] border border-[var(--color-border)] bg-[var(--color-surface-raised)] px-2.5 pr-7 text-sm text-[var(--color-foreground)] transition-colors hover:border-[var(--color-border-strong)] focus:border-[var(--color-ring)] focus:outline-none focus:ring-2 focus:ring-[var(--color-ring)]/25"
      >
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
    </div>
  );
}

/**
 * Status tabs with counts — the WordPress "All (412) | Published (380) | Draft (32)"
 * row, which turns out to be the fastest filter anyone has invented for a CMS.
 */
export function CountTabs({
  value,
  onChange,
  tabs,
  className,
}: {
  value: string;
  onChange: (value: string) => void;
  tabs: { value: string; label: string; count?: number }[];
  className?: string;
}) {
  return (
    <div
      role="tablist"
      aria-label="Filter by status"
      className={cn("scrollbar-slim flex items-center gap-1 overflow-x-auto", className)}
    >
      {tabs.map((tab) => (
        <button
          key={tab.value}
          type="button"
          role="tab"
          aria-selected={tab.value === value}
          onClick={() => onChange(tab.value)}
          className={cn(
            "flex shrink-0 items-center gap-1.5 rounded-[var(--radius-md)] px-2.5 py-1.5 text-sm font-medium transition-colors",
            tab.value === value
              ? "bg-[var(--color-primary-subtle)] text-[var(--color-primary)]"
              : "text-[var(--color-foreground-muted)] hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-foreground)]",
          )}
        >
          {tab.label}
          {tab.count !== undefined && (
            <span className="tabular text-xs text-[var(--color-foreground-subtle)]">
              {tab.count}
            </span>
          )}
        </button>
      ))}
    </div>
  );
}

/**
 * The bar that appears when rows are selected.
 *
 * Anchored to the bottom of the viewport rather than pushed in above the table:
 * selecting a row at the bottom of a long list must not scroll the thing you just
 * clicked out from under the cursor.
 */
export function BulkBar({
  count,
  onClear,
  children,
}: {
  count: number;
  onClear: () => void;
  children: React.ReactNode;
}) {
  if (count === 0) return null;

  return (
    <div className="pointer-events-none fixed inset-x-0 bottom-4 z-40 flex justify-center px-4">
      <div className="pointer-events-auto flex flex-wrap items-center gap-2 rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-[var(--app-surface)] px-3 py-2 shadow-[var(--shadow-popover)]">
        <span
          className="tabular rounded-full bg-[var(--color-primary)] px-2 py-0.5 text-xs font-semibold text-[var(--color-primary-foreground)]"
          role="status"
        >
          {count} selected
        </span>

        {children}

        <button
          type="button"
          onClick={onClear}
          className="ml-1 rounded-[var(--radius-sm)] px-2 py-1 text-xs text-[var(--color-foreground-subtle)] transition-colors hover:text-[var(--color-foreground)]"
        >
          Clear
        </button>
      </div>
    </div>
  );
}
