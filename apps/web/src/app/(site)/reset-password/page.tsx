import type { Metadata } from "next";
import Link from "next/link";
import * as React from "react";

import { AuthShell } from "@/components/auth/auth-shell";
import { ResetPasswordForm } from "@/components/auth/reset-password-form";

export const metadata: Metadata = {
  title: "Choose a new password",
  robots: { index: false, follow: false },
};

export default function ResetPasswordPage() {
  return (
    <AuthShell
      title="Choose a new password"
      subtitle="Signing in everywhere else will need the new one."
      footer={
        <p>
          <Link href="/login" className="font-medium text-[var(--color-primary)] hover:underline">
            Back to sign in
          </Link>
        </p>
      }
    >
      <React.Suspense fallback={<div className="h-64" aria-hidden="true" />}>
        <ResetPasswordForm />
      </React.Suspense>
    </AuthShell>
  );
}
