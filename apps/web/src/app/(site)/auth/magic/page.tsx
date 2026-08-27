import type { Metadata } from "next";
import * as React from "react";

import { AuthShell } from "@/components/auth/auth-shell";
import { MagicLinkConsumer } from "@/components/auth/magic-link-consumer";

export const metadata: Metadata = {
  title: "Signing you in",
  robots: { index: false, follow: false },
};

export default function MagicLinkPage() {
  return (
    <AuthShell title="Signing you in" subtitle="One moment while we check your link.">
      <React.Suspense fallback={<p className="text-sm">Signing you in…</p>}>
        <MagicLinkConsumer />
      </React.Suspense>
    </AuthShell>
  );
}
