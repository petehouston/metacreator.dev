"use client";

import { useRouter } from "next/navigation";
import * as React from "react";

import { authApi } from "@/lib/auth-api";
import type { AuthUser } from "@/lib/types";

/**
 * Who is signed in, for the whole client tree.
 *
 * The session is fetched once on mount and then only re-fetched when something
 * changed it. It deliberately holds no permissions logic of its own: `can()` is a
 * rendering convenience, and every route re-checks server-side — the UI hiding a
 * button is never what keeps an action safe.
 */

interface SessionContextValue {
  user: AuthUser | null;
  /** True until the first session fetch settles, so the UI can avoid a sign-in flash. */
  loading: boolean;
  refresh: () => Promise<AuthUser | null>;
  setUser: (user: AuthUser | null) => void;
  signOut: () => Promise<void>;
  can: (permission: string) => boolean;
}

const SessionContext = React.createContext<SessionContextValue | null>(null);

export function SessionProvider({
  children,
  initialUser = null,
}: {
  children: React.ReactNode;
  initialUser?: AuthUser | null;
}) {
  const router = useRouter();
  const [user, setUser] = React.useState<AuthUser | null>(initialUser);
  const [loading, setLoading] = React.useState(initialUser === null);

  const refresh = React.useCallback(async () => {
    const result = await authApi.session();
    const next = result.ok ? result.data : null;

    setUser(next);
    setLoading(false);

    return next;
  }, []);

  React.useEffect(() => {
    if (initialUser !== null) return;

    // The effect body starts the fetch and returns; state is only touched once the
    // response is in, and never after unmount.
    let cancelled = false;

    void (async () => {
      const result = await authApi.session();
      if (cancelled) return;

      setUser(result.ok ? result.data : null);
      setLoading(false);
    })();

    return () => {
      cancelled = true;
    };
  }, [initialUser]);

  const signOut = React.useCallback(async () => {
    await authApi.logout();
    setUser(null);

    // Server Components may have rendered signed-in content; refreshing the router
    // is what actually clears it from the cache.
    router.refresh();
    router.push("/");
  }, [router]);

  const can = React.useCallback(
    (permission: string) =>
      user?.roles.includes("super-admin") === true ||
      user?.permissions.includes(permission) === true,
    [user],
  );

  const value = React.useMemo<SessionContextValue>(
    () => ({ user, loading, refresh, setUser, signOut, can }),
    [user, loading, refresh, signOut, can],
  );

  return <SessionContext.Provider value={value}>{children}</SessionContext.Provider>;
}

export function useSession(): SessionContextValue {
  const context = React.useContext(SessionContext);

  if (context === null) {
    throw new Error("useSession must be used inside <SessionProvider>.");
  }

  return context;
}
