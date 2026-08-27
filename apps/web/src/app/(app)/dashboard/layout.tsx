import type { Metadata } from "next";
import type * as React from "react";

export const metadata: Metadata = {
  title: { default: "Dashboard", template: "%s | Dashboard | MetaCreator.dev" },
};

/** The chrome lives in `(app)/layout.tsx`; this only carries the title template. */
export default function DashboardLayout({ children }: { children: React.ReactNode }) {
  return <>{children}</>;
}
