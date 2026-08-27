import type { Metadata } from "next";
import * as React from "react";

import { AuthShell } from "@/components/auth/auth-shell";
import { VerifyEmailPanel } from "@/components/auth/verify-email-panel";

export const metadata: Metadata = {
  title: "Confirm your email",
  robots: { index: false, follow: false },
};

export default function VerifyEmailPage() {
  return (
    <AuthShell
      title="Confirm your email"
      subtitle="Confirming keeps receipts and security alerts reaching you."
    >
      <React.Suspense fallback={<p className="text-sm">Confirming…</p>}>
        <VerifyEmailPanel />
      </React.Suspense>
    </AuthShell>
  );
}
