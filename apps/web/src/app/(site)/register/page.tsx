import type { Metadata } from "next";
import Link from "next/link";
import * as React from "react";

import { AuthShell } from "@/components/auth/auth-shell";
import { RegisterForm } from "@/components/auth/register-form";

export const metadata: Metadata = {
  title: "Create your free account",
  description:
    "Create a free MetaCreator.dev account for higher daily limits, saved run history and premium tool trials.",
  robots: { index: false, follow: true },
  alternates: { canonical: "/register" },
};

export default function RegisterPage() {
  return (
    <AuthShell
      title="Create your free account"
      subtitle="Higher daily limits, saved run history, and no credit card."
      footer={
        <p>
          Already have an account?{" "}
          <Link href="/login" className="font-medium text-[var(--color-primary)] hover:underline">
            Sign in
          </Link>
        </p>
      }
    >
      <React.Suspense fallback={<div className="h-96" aria-hidden="true" />}>
        <RegisterForm />
      </React.Suspense>
    </AuthShell>
  );
}
