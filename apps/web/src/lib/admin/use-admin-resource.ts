"use client";

import * as React from "react";

import type { ApiFailure, ApiResult } from "@/lib/http";

/**
 * Load an admin resource, keeping the previous value on screen while the next one
 * arrives.
 *
 * The two behaviours that matter, and that every screen would otherwise re-invent:
 *
 * - **Stale-while-loading.** Changing a filter must not blank the table. The old
 *   rows stay, dimmed, until the new ones land — otherwise the page height jumps
 *   on every keystroke and the reader loses their place.
 * - **Last-write-wins is not enough.** Responses can arrive out of order, so each
 *   request carries a sequence number and an older one is discarded rather than
 *   allowed to overwrite a newer result.
 */
export function useAdminResource<T>(
  loader: () => Promise<ApiResult<T>>,
  deps: React.DependencyList,
): {
  data: T | null;
  error: ApiFailure | null;
  loading: boolean;
  reload: () => void;
} {
  const [data, setData] = React.useState<T | null>(null);
  const [error, setError] = React.useState<ApiFailure | null>(null);
  const [loading, setLoading] = React.useState(true);
  const [nonce, setNonce] = React.useState(0);

  const loaderRef = React.useRef(loader);
  const sequence = React.useRef(0);

  // The latest-ref pattern, written the way React sanctions it. Assigning during
  // render is what the rules-of-hooks lint forbids — and it is genuinely wrong
  // under concurrent rendering, where a render can be thrown away after mutating
  // the ref. `useInsertionEffect` runs before every other effect, so the data
  // effect below always sees the current closure.
  React.useInsertionEffect(() => {
    loaderRef.current = loader;
  });

  React.useEffect(() => {
    const ticket = (sequence.current += 1);

    void (async () => {
      setLoading(true);

      const result = await loaderRef.current();

      // A superseded request must not win, however late it arrives.
      if (ticket !== sequence.current) return;

      if (result.ok) {
        setData(result.data);
        setError(null);
      } else {
        setError(result.error);
      }

      setLoading(false);
    })();
    // `loader` is intentionally not a dependency: it is a fresh closure on every
    // render, and depending on it would loop. The caller's `deps` are the contract.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [...deps, nonce]);

  const reload = React.useCallback(() => setNonce((current) => current + 1), []);

  return { data, error, loading, reload };
}
