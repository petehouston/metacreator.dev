import type { Metadata } from "next";
import * as React from "react";

import { AuthShell } from "@/components/auth/auth-shell";
import { NewsletterConfirmPanel } from "@/components/site/newsletter-confirm-panel";

export const metadata: Metadata = {
  title: "Confirm your subscription",
  robots: { index: false, follow: false },
};

export default function NewsletterConfirmPage() {
  return (
    <AuthShell
      title="Confirm your subscription"
      subtitle="One click and the weekly issue starts arriving."
    >
      <React.Suspense fallback={<p className="text-sm">Confirming…</p>}>
        <NewsletterConfirmPanel />
      </React.Suspense>
    </AuthShell>
  );
}
