"use client";

import * as React from "react";

import type { RankingNavItem } from "@/lib/top-ranking-nav";

/**
 * The ranking pages, handed down from the server layout.
 *
 * The same contract as {@see FeaturesProvider}, and for the same reason: the site
 * layout already knows this list while it renders, so passing it through costs
 * nothing and the header draws its menu on the first paint. A header that fetched
 * for itself would render without the menu, then pop it in — which on a sticky
 * header is a visible layout shift on every cold navigation.
 */
const RankingNavContext = React.createContext<RankingNavItem[]>([]);

export function RankingNavProvider({
  items,
  children,
}: {
  items: RankingNavItem[];
  children: React.ReactNode;
}) {
  // Keyed on the hrefs rather than on the array identity: the layout builds a new
  // array on every render, which would otherwise re-render every consumer on each
  // navigation for a list that has not changed.
  const key = items.map((item) => item.href).join("|");
  const value = React.useMemo(() => items, [key]); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <RankingNavContext.Provider value={value}>
      {children}
    </RankingNavContext.Provider>
  );
}

export function useRankingNav(): RankingNavItem[] {
  return React.useContext(RankingNavContext);
}
