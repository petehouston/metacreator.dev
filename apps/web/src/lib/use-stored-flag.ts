"use client";

import * as React from "react";

/**
 * A boolean the browser remembers.
 *
 * `localStorage` is not available during the server render, so the first client
 * render has to match the server's default and only then adopt the stored value.
 * Writes go through a module-level cache so every component sharing a key sees
 * the same value and re-renders together.
 */

const cache = new Map<string, boolean>();
const listeners = new Map<string, Set<() => void>>();

function read(key: string): boolean {
  const cached = cache.get(key);
  if (cached !== undefined) return cached;

  let stored = false;
  try {
    stored = window.localStorage.getItem(key) === "1";
  } catch {
    // Private mode, or storage disabled. The default is fine.
  }

  cache.set(key, stored);
  return stored;
}

export function useStoredFlag(
  key: string,
  fallback = false,
): [boolean, (next: boolean | ((current: boolean) => boolean)) => void] {
  const [value, setValue] = React.useState(fallback);

  // Adopt the stored value after hydration, and stay in step with any other
  // component using the same key.
  React.useEffect(() => {
    const listener = () => setValue(read(key));
    listener();

    let forKey = listeners.get(key);
    if (!forKey) {
      forKey = new Set();
      listeners.set(key, forKey);
    }
    forKey.add(listener);

    return () => {
      forKey.delete(listener);
    };
  }, [key]);

  const set = React.useCallback(
    (next: boolean | ((current: boolean) => boolean)) => {
      const resolved = typeof next === "function" ? next(read(key)) : next;

      cache.set(key, resolved);
      try {
        window.localStorage.setItem(key, resolved ? "1" : "0");
      } catch {
        // The preference is a convenience, never a requirement.
      }

      for (const listener of listeners.get(key) ?? []) listener();
    },
    [key],
  );

  return [value, set];
}
