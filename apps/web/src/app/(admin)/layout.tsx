import type { Metadata } from "next";
import type * as React from "react";

import { AdminShell } from "@/components/admin/admin-shell";
import { RequireStaff } from "@/components/admin/require-staff";
import { ToastProvider } from "@/components/admin/feedback";

export const metadata: Metadata = {
  title: { default: "Admin", template: "%s | Admin | MetaCreator.dev" },
  // Nothing in the staff area should ever reach an index, and the tracking
  // scripts are never injected here either (docs/15).
  robots: { index: false, follow: false },
};

/**
 * The staff area.
 *
 * `RequireStaff` with no permission list means "any staff role at all" — each page
 * then declares the specific permission its own screen needs. Doing it in two steps
 * keeps the shell from rendering for a customer who guessed the URL, while still
 * letting an editor and an accountant land on different first screens.
 */
export default function AdminLayout({ children }: { children: React.ReactNode }) {
  return (
    <RequireStaff>
      <ToastProvider>
        <AdminShell>{children}</AdminShell>
      </ToastProvider>
    </RequireStaff>
  );
}