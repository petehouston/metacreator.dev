import type { Metadata } from "next";
import Link from "next/link";
import * as React from "react";

import { AuthShell } from "@/components/auth/auth-shell";
import { LoginForm } from "@/components/auth/login-form";

export const metadata: Metadata = {
  title: "Sign in",
  description: "Sign in to MetaCreator.dev to use premium tools and see your run history.",
  // Auth screens carry no unique content worth ranking, and indexing them puts a
  // sign-in page in results for brand searches.
  robots: { index: false, follow: true },
  alternates: { canonical: "/login" },
};

export default function LoginPage() {
  return (
    <AuthShell
      title="Welcome back"
      subtitle="Sign in to pick up where you left off."
      footer={
        <p>
          New here?{" "}
          <Link href="/register" className="font-medium text-[var(--color-primary)] hover:underline">
            Create a free account
          </Link>
        </p>
      }
    >
      {/* useSearchParams needs a Suspense boundary to keep the route statically
          renderable up to the form. */}
      <React.Suspense fallback={<FormSkeleton />}>
        <LoginForm />
      </React.Suspense>
    </AuthShell>
  );
}

function FormSkeleton() {
  return (
    <div className="flex flex-col gap-5" aria-hidden="true">
      <div className="h-16 animate-pulse rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)]" />
      <div className="h-16 animate-pulse rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)]" />
      <div className="h-12 animate-pulse rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)]" />
    </div>
  );
}
