"use client";

import * as React from "react";

import type { SiteFeatures } from "@/lib/site-settings";

/**
 * The site-wide feature switches, handed down from the server layout.
 *
 * A context rather than a fetch: the values come from the public settings endpoint,
 * which the root layout already reads while rendering, so passing them through
 * costs nothing and every client component below sees the same answer on the first
 * paint. A hook that fetched for itself would flash a Pricing link and then remove
 * it, which is worse than not having the switch at all.
 */
const FeaturesContext = React.createContext<SiteFeatures>({
  billingEnabled: true,
  changelogEnabled: true,
  searchEnabled: false,
});

export function FeaturesProvider({
  features,
  children,
}: {
  features: SiteFeatures;
  children: React.ReactNode;
}) {
  // The object identity is stable across renders of the layout, so memoising on
  // the fields it holds keeps every consumer from re-rendering on navigation.
  const value = React.useMemo<SiteFeatures>(
    () => ({
      billingEnabled: features.billingEnabled,
      changelogEnabled: features.changelogEnabled,
      searchEnabled: features.searchEnabled,
    }),
    [features.billingEnabled, features.changelogEnabled, features.searchEnabled],
  );

  return <FeaturesContext.Provider value={value}>{children}</FeaturesContext.Provider>;
}

export function useFeatures(): SiteFeatures {
  return React.useContext(FeaturesContext);
}

/** The common case, on its own so components read as what they are asking. */
export function useBillingEnabled(): boolean {
  return useFeatures().billingEnabled;
}

/** Whether the public changelog is published at all. */
export function useChangelogEnabled(): boolean {
  return useFeatures().changelogEnabled;
}

/** Whether global search is switched on. */
export function useSearchEnabled(): boolean {
  return useFeatures().searchEnabled;
}
