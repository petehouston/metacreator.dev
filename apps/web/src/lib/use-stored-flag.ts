"use client";

import * as React from "react";

/**
 * A boolean the browser remembers, read the hydration-safe way.
 *
 * `localStorage` is not available during the server render, so reading it in an
 * effect and calling `setState` would both flash the wrong value and trigger a
 * cascading render. `useSyncExternalStore` is the supported answer: the server
 * snapshot is the default, the client snapshot is the stored value, and writes
 * notify every subscriber so two components sharing a key never disagree.
 */

const listeners = new Set<() => void>();

function subscribe(listener: () => void): () => void {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

function read(key: string): boolean {
  try {
    return window.localStorage.getItem(key) === "1";
  } catch {
    // Private mode, or storage disabled. The default is fine.
    return false;
  }
}

export function useStoredFlag(
  key: string,
  fallback = false,
): [boolean, (next: boolean | ((current: boolean) => boolean)) => void] {
  const value = React.useSyncExternalStore(
    subscribe,
    () => read(key),
    () => fallback,
  );

  const set = React.useCallback(
    (next: boolean | ((current: boolean) => boolean)) => {
      const resolved = typeof next === "function" ? next(read(key)) : next;

      try {
        window.localStorage.setItem(key, resolved ? "1" : "0");
      } catch {
        // The preference is a convenience, never a requirement.
      }

      for (const listener of listeners) listener();
    },
    [key],
  );

  return [value, set];
}
