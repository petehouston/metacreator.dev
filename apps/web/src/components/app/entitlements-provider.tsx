"use client";

import * as React from "react";

import { apiData } from "@/lib/http";
import type { Entitlements } from "@/lib/types";

/**
 * The plan, the limits and today's usage — fetched once for the whole shell.
 *
 * Four separate surfaces need this number (the sidebar meter, the overview tiles,
 * the billing page and every paywall hint), and four independent `useEffect`
 * fetches is how a dashboard ends up making the same request four times on a cold
 * navigation. One provider, one request, one source of truth.
 */

interface EntitlementsContextValue {
  entitlements: Entitlements | null;
  loading: boolean;
  error: string | null;
  refresh: () => Promise<void>;
}

const EntitlementsContext = React.createContext<EntitlementsContextValue | null>(null);

export function EntitlementsProvider({ children }: { children: React.ReactNode }) {
  const [entitlements, setEntitlements] = React.useState<Entitlements | null>(null);
  const [loading, setLoading] = React.useState(true);
  const [error, setError] = React.useState<string | null>(null);

  const refresh = React.useCallback(async () => {
    const result = await apiData<Entitlements>("/account/entitlements");

    if (result.ok) {
      setEntitlements(result.data);
      setError(null);
    } else {
      setError(result.error.message);
    }

    setLoading(false);
  }, []);

  React.useEffect(() => {
    let cancelled = false;

    void (async () => {
      const result = await apiData<Entitlements>("/account/entitlements");
      if (cancelled) return;

      if (result.ok) setEntitlements(result.data);
      else setError(result.error.message);

      setLoading(false);
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  const value = React.useMemo<EntitlementsContextValue>(
    () => ({ entitlements, loading, error, refresh }),
    [entitlements, loading, error, refresh],
  );

  return (
    <EntitlementsContext.Provider value={value}>{children}</EntitlementsContext.Provider>
  );
}

export function useEntitlements(): EntitlementsContextValue {
  const context = React.useContext(EntitlementsContext);

  if (context === null) {
    throw new Error("useEntitlements must be used inside <EntitlementsProvider>.");
  }

  return context;
}

/** "pro_monthly" → "Pro Monthly". The API sends the key; humans read the label. */
export function planLabel(plan: string): string {
  return plan.replace(/_/g, " ").replace(/\b\w/g, (character) => character.toUpperCase());
}
