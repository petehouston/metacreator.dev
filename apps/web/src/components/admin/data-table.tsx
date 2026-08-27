"use client";

import { ArrowDown, ArrowUp, ChevronLeft, ChevronRight, Loader2 } from "lucide-react";
import * as React from "react";

import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

/**
 * The table every management screen is built from.
 *
 * Three things it does that a plain `<table>` does not, and each exists because a
 * management screen without it becomes unusable at scale:
 *
 * - **Selection lives with the rows**, so a bulk action can never be applied to a
 *   different page than the one the user was looking at.
 * - **The loading state keeps the previous rows on screen**, dimmed. Replacing a
 *   table with a spinner on every filter change makes the whole screen jump and
 *   loses the reader's place.
 * - **Wide content scrolls inside the table**, never the page.
 */

export interface Column<T> {
  key: string;
  header: React.ReactNode;
  /** Right-align and tabular-figure numeric columns. */
  numeric?: boolean;
  /** Sort key sent to the API; omit for columns that cannot be sorted. */
  sortKey?: string;
  /** Hide below this breakpoint rather than squeezing every column onto a phone. */
  hideBelow?: "sm" | "md" | "lg" | "xl";
  width?: string;
  cell: (row: T) => React.ReactNode;
}

export function DataTable<T>({
  rows,
  columns,
  rowKey,
  loading = false,
  empty,
  selectable = false,
  selected = [],
  onSelectedChange,
  sort,
  onSortChange,
  onRowClick,
  className,
}: {
  rows: T[];
  columns: Column<T>[];
  rowKey: (row: T) => string;
  loading?: boolean;
  empty?: React.ReactNode;
  selectable?: boolean;
  selected?: string[];
  onSelectedChange?: (ids: string[]) => void;
  /** `-field` for descending, matching the API's convention. */
  sort?: string;
  onSortChange?: (sort: string) => void;
  onRowClick?: (row: T) => void;
  className?: string;
}) {
  const ids = rows.map(rowKey);
  const allSelected = ids.length > 0 && ids.every((id) => selected.includes(id));
  const someSelected = selected.length > 0 && !allSelected;

  const headerCheckbox = React.useRef<HTMLInputElement>(null);

  React.useEffect(() => {
    // `indeterminate` is a DOM property with no HTML attribute, so it has to be
    // set imperatively — there is no React prop for it.
    if (headerCheckbox.current) headerCheckbox.current.indeterminate = someSelected;
  }, [someSelected]);

  function toggleAll() {
    onSelectedChange?.(allSelected ? [] : ids);
  }

  function toggleOne(id: string) {
    onSelectedChange?.(
      selected.includes(id) ? selected.filter((current) => current !== id) : [...selected, id],
    );
  }

  if (!loading && rows.length === 0 && empty) {
    return <div className={className}>{empty}</div>;
  }

  return (
    <div className={cn("relative", className)}>
      {loading && rows.length > 0 && (
        <div className="pointer-events-none absolute right-3 top-3 z-10">
          <Loader2
            className="size-4 animate-spin text-[var(--color-foreground-subtle)]"
            aria-hidden="true"
          />
        </div>
      )}

      <div className="scrollbar-slim overflow-x-auto">
        <table
          className={cn(
            "w-full min-w-full border-collapse text-sm transition-opacity",
            loading && "opacity-60",
          )}
        >
          <thead>
            <tr className="border-b border-[var(--color-border)]">
              {selectable && (
                <th scope="col" className="w-10 py-2 pl-4 pr-2">
                  <input
                    ref={headerCheckbox}
                    type="checkbox"
                    checked={allSelected}
                    onChange={toggleAll}
                    aria-label={allSelected ? "Clear selection" : "Select every row on this page"}
                    className="size-4 rounded border-[var(--color-border-strong)] accent-[var(--color-primary)]"
                  />
                </th>
              )}

              {columns.map((column) => {
                const active = sort === column.sortKey || sort === `-${column.sortKey}`;
                const descending = sort === `-${column.sortKey}`;

                return (
                  <th
                    key={column.key}
                    scope="col"
                    style={column.width ? { width: column.width } : undefined}
                    className={cn(
                      "whitespace-nowrap px-3 py-2 text-left font-mono text-[0.625rem] font-medium uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]",
                      column.numeric && "text-right",
                      column.hideBelow === "sm" && "hidden sm:table-cell",
                      column.hideBelow === "md" && "hidden md:table-cell",
                      column.hideBelow === "lg" && "hidden lg:table-cell",
                      column.hideBelow === "xl" && "hidden xl:table-cell",
                    )}
                  >
                    {column.sortKey && onSortChange ? (
                      <button
                        type="button"
                        onClick={() =>
                          onSortChange(
                            active && descending ? column.sortKey! : `-${column.sortKey}`,
                          )
                        }
                        className={cn(
                          "inline-flex items-center gap-1 uppercase tracking-[0.12em] transition-colors hover:text-[var(--color-foreground)]",
                          active && "text-[var(--color-foreground)]",
                        )}
                        aria-label={`Sort by ${typeof column.header === "string" ? column.header : column.key}`}
                      >
                        {column.header}
                        {active &&
                          (descending ? (
                            <ArrowDown className="size-3" aria-hidden="true" />
                          ) : (
                            <ArrowUp className="size-3" aria-hidden="true" />
                          ))}
                      </button>
                    ) : (
                      column.header
                    )}
                  </th>
                );
              })}
            </tr>
          </thead>

          <tbody>
            {rows.map((row) => {
              const id = rowKey(row);
              const isSelected = selected.includes(id);

              return (
                <tr
                  key={id}
                  onClick={onRowClick ? () => onRowClick(row) : undefined}
                  className={cn(
                    "border-b border-[var(--color-border-subtle)] last:border-b-0 transition-colors",
                    isSelected
                      ? "bg-[var(--color-primary-subtle)]"
                      : "hover:bg-[var(--color-surface-sunken)]",
                    onRowClick && "cursor-pointer",
                  )}
                >
                  {selectable && (
                    <td className="py-2 pl-4 pr-2" onClick={(event) => event.stopPropagation()}>
                      <input
                        type="checkbox"
                        checked={isSelected}
                        onChange={() => toggleOne(id)}
                        aria-label={`Select row ${id}`}
                        className="size-4 rounded border-[var(--color-border-strong)] accent-[var(--color-primary)]"
                      />
                    </td>
                  )}

                  {columns.map((column) => (
                    <td
                      key={column.key}
                      className={cn(
                        "px-3 py-2.5 align-middle text-[var(--color-foreground-muted)]",
                        column.numeric && "tabular text-right",
                        column.hideBelow === "sm" && "hidden sm:table-cell",
                        column.hideBelow === "md" && "hidden md:table-cell",
                        column.hideBelow === "lg" && "hidden lg:table-cell",
                        column.hideBelow === "xl" && "hidden xl:table-cell",
                      )}
                    >
                      {column.cell(row)}
                    </td>
                  ))}
                </tr>
              );
            })}

            {loading && rows.length === 0 &&
              [0, 1, 2, 3, 4].map((row) => (
                <tr key={row} className="border-b border-[var(--color-border-subtle)]">
                  <td colSpan={columns.length + (selectable ? 1 : 0)} className="px-3 py-3">
                    <div className="h-4 animate-pulse rounded bg-[var(--color-surface-sunken)]" />
                  </td>
                </tr>
              ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

/**
 * Page controls.
 *
 * States the range and the total rather than only the page number: "26–50 of 412"
 * tells someone whether it is worth paging at all, which "page 2 of 17" does not.
 */
export function Pagination({
  page,
  lastPage,
  total,
  perPage,
  onChange,
  className,
}: {
  page: number;
  lastPage: number;
  total: number;
  perPage: number;
  onChange: (page: number) => void;
  className?: string;
}) {
  if (total === 0) return null;

  const first = (page - 1) * perPage + 1;
  const last = Math.min(page * perPage, total);

  return (
    <div
      className={cn(
        "flex flex-wrap items-center justify-between gap-3 border-t border-[var(--color-border-subtle)] px-4 py-3",
        className,
      )}
    >
      <p className="tabular text-xs text-[var(--color-foreground-subtle)]">
        {first.toLocaleString()}–{last.toLocaleString()} of {total.toLocaleString()}
      </p>

      {lastPage > 1 && (
        <div className="flex items-center gap-1">
          <Button
            variant="secondary"
            size="sm"
            disabled={page <= 1}
            onClick={() => onChange(page - 1)}
            aria-label="Previous page"
          >
            <ChevronLeft className="size-4" aria-hidden="true" />
            Previous
          </Button>

          <span className="tabular px-2 text-xs text-[var(--color-foreground-subtle)]">
            {page} / {lastPage}
          </span>

          <Button
            variant="secondary"
            size="sm"
            disabled={page >= lastPage}
            onClick={() => onChange(page + 1)}
            aria-label="Next page"
          >
            Next
            <ChevronRight className="size-4" aria-hidden="true" />
          </Button>
        </div>
      )}
    </div>
  );
}
