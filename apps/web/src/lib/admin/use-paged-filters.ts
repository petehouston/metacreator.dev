"use client";

import * as React from "react";

/**
 * Filter state for a paginated list, with the page number kept in the same object.
 *
 * Storing them together is not tidiness. Every management screen needs "changing a
 * filter returns to page one", and the obvious implementation — an effect that
 * calls `setPage(1)` when the filters change — renders once with the new filter and
 * the old page, firing a request for a page that may not exist, before correcting
 * itself. Updating both in one state transition means that request never happens.
 *
 * @example
 * const [filters, set, page, setPage] = usePagedFilters({ q: "", status: "" });
 * set({ status: "active" });   // page returns to 1
 * setPage(3);                  // filters untouched
 */
export function usePagedFilters<T extends Record<string, string>>(
  initial: T,
): [T, (patch: Partial<T>) => void, number, (page: number) => void] {
  const [state, setState] = React.useState<{ filters: T; page: number }>({
    filters: initial,
    page: 1,
  });

  const setFilters = React.useCallback((patch: Partial<T>) => {
    setState((current) => ({ filters: { ...current.filters, ...patch }, page: 1 }));
  }, []);

  const setPage = React.useCallback((page: number) => {
    setState((current) => ({ ...current, page }));
  }, []);

  return [state.filters, setFilters, state.page, setPage];
}
