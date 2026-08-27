"use client";

import { usePathname, useRouter } from "next/navigation";
import * as React from "react";

import { useSession } from "@/components/auth/session-provider";

/**
 * Client-side gate for the dashboard.
 *
 * This is routing, not security: every endpoint behind it re-checks the session
 * server-side. Its job is to send a signed-out visitor somewhere useful instead of
 * letting them watch a permanently empty page.
 */
export function RequireSession({ children }: { children: React.ReactNode }) {
  const { user, loading } = useSession();
  const router = useRouter();
  const pathname = usePathname();

  React.useEffect(() => {
    if (!loading && !user) {
      // Carry the destination so the user lands where they were headed.
      router.replace(`/login?next=${encodeURIComponent(pathname)}`);
    }
  }, [loading, user, router, pathname]);

  if (loading) {
    // A shell-shaped skeleton, not a page-shaped one: the app chrome is what
    // arrives first once the session settles, so this is the shape that does not
    // shift when it does.
    return (
      <div className="flex min-h-dvh bg-[var(--app-ground)]">
        <div className="hidden w-[17rem] shrink-0 border-r border-[var(--color-border-subtle)] p-4 lg:block">
          <div className="h-8 w-40 animate-pulse rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)]" />
          <div className="mt-6 flex flex-col gap-2">
            {[0, 1, 2, 3, 4].map((row) => (
              <div
                key={row}
                className="h-9 animate-pulse rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)]"
              />
            ))}
          </div>
        </div>

        <div className="min-w-0 flex-1 p-6 lg:p-8">
          <div className="h-8 w-48 animate-pulse rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)]" />
          <div className="mt-6 h-64 animate-pulse rounded-[var(--radius-lg)] bg-[var(--color-surface-sunken)]" />
        </div>
      </div>
    );
  }

  if (!user) return null;

  return <>{children}</>;
}
