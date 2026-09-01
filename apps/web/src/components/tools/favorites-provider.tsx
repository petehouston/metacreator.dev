"use client";

import * as React from "react";

import { useSession } from "@/components/auth/session-provider";
import { apiFetch } from "@/lib/http";

/**
 * The signed-in member's saved tools, for the whole client tree.
 *
 * One provider rather than per-card state, for two reasons. A grid of sixty cards
 * must not be sixty requests asking whether each one is saved — the whole list is
 * a few dozen slugs, so it is fetched once. And the catalog's Favourites sort has
 * to read the same set the hearts render from, or the two disagree on screen.
 *
 * Toggling is optimistic and reverts on failure. Saving a tool is a small,
 * reversible thing; making someone wait for a round trip to see a heart fill is a
 * worse trade than very occasionally showing one that un-fills.
 */

interface FavoritesContextValue {
  /** Saved slugs, newest save first. Empty for a guest and while loading. */
  slugs: string[];
  /** True until the first fetch settles, so a heart can avoid flashing empty. */
  loading: boolean;
  /** False for a guest — the UI offers sign-in instead of a dead control. */
  enabled: boolean;
  isFavorite: (slug: string) => boolean;
  toggle: (slug: string) => Promise<void>;
}

const FavoritesContext = React.createContext<FavoritesContextValue | null>(null);

export function FavoritesProvider({ children }: { children: React.ReactNode }) {
  const { user, loading: sessionLoading } = useSession();

  const userId = user?.id ?? null;
  const enabled = userId !== null;

  /**
   * The list, tagged with the account it belongs to.
   *
   * Tagged rather than reset on sign-out, because clearing it would mean writing
   * state from the effect body on every render where nobody is signed in — a
   * cascading render for a value that is derivable. Comparing the tag costs
   * nothing and cannot fall out of step with the session.
   */
  const [loaded, setLoaded] = React.useState<{ userId: string; slugs: string[] } | null>(null);

  const slugs = React.useMemo(
    () => (userId !== null && loaded?.userId === userId ? loaded.slugs : []),
    [loaded, userId],
  );

  // Loading until the account's own list has arrived. A guest has nothing to
  // wait for, so they are never loading.
  const loading = sessionLoading || (enabled && loaded?.userId !== userId);

  React.useEffect(() => {
    // Nothing to fetch for a guest, and nothing to fetch before the session has
    // settled — asking early would 401 a member whose cookie was simply not read
    // yet, and cache the answer as "no favourites".
    if (sessionLoading || userId === null) return;

    let cancelled = false;

    void (async () => {
      const result = await apiFetch<{ meta: { slugs: string[] } }>("/account/favorites");
      if (cancelled) return;

      setLoaded({ userId, slugs: result.ok ? result.data.meta.slugs : [] });
    })();

    return () => {
      cancelled = true;
    };
  }, [userId, sessionLoading]);

  const toggle = React.useCallback(
    async (slug: string) => {
      if (userId === null) return;

      const wasFavorite = slugs.includes(slug);

      // Optimistic: saving is small and reversible, and waiting on a round trip
      // to see a heart fill is the worse trade.
      setLoaded({
        userId,
        slugs: wasFavorite ? slugs.filter((s) => s !== slug) : [slug, ...slugs],
      });

      const result = await apiFetch(`/account/favorites/${slug}`, {
        method: wasFavorite ? "DELETE" : "PUT",
      });

      if (result.ok) return;

      // Revert against whatever is current rather than against the list this
      // toggle started from: another toggle may have landed in between, and its
      // state is more recent than anything derived here.
      setLoaded((current) => {
        if (current?.userId !== userId) return current;

        return {
          userId,
          slugs: wasFavorite
            ? [...current.slugs, slug]
            : current.slugs.filter((s) => s !== slug),
        };
      });
    },
    [slugs, userId],
  );

  const value = React.useMemo<FavoritesContextValue>(
    () => ({
      slugs,
      loading,
      enabled,
      isFavorite: (slug: string) => slugs.includes(slug),
      toggle,
    }),
    [slugs, loading, enabled, toggle],
  );

  return <FavoritesContext.Provider value={value}>{children}</FavoritesContext.Provider>;
}

/**
 * Falls back to an inert, disabled value outside the provider rather than
 * throwing: a tool card is rendered in enough places that a missing provider
 * should degrade to "no hearts", not to a blank page.
 */
export function useFavorites(): FavoritesContextValue {
  return (
    React.useContext(FavoritesContext) ?? {
      slugs: [],
      loading: false,
      enabled: false,
      isFavorite: () => false,
      toggle: async () => {},
    }
  );
}
