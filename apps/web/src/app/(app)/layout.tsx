import type { Metadata } from "next";
import type * as React from "react";

import { AppShell } from "@/components/app/app-shell";
import { RequireSession } from "@/components/auth/require-session";

export const metadata: Metadata = {
  // Nothing behind the session should ever reach an index.
  robots: { index: false, follow: false },
};

/**
 * Everything signed in lives under this layout, and it is the only place the app
 * shell is mounted — so the sidebar, the ⌘K palette and the entitlements request
 * survive navigation between screens instead of remounting on each one.
 */
export default function AppLayout({ children }: { children: React.ReactNode }) {
  return (
    <RequireSession>
      <AppShell>{children}</AppShell>
    </RequireSession>
  );
}
