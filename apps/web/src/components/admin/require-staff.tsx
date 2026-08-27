"use client";

import { ShieldAlert } from "lucide-react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import * as React from "react";

import { useSession } from "@/components/auth/session-provider";
import { Button } from "@/components/ui/button";
import { firstReachable } from "@/components/admin/admin-nav";

/**
 * Routing, not security.
 *
 * Every admin endpoint re-checks the actor server-side; this only decides what to
 * put on the screen while that is true. Three outcomes, and the third is the one
 * that usually gets forgotten: signed out → sign in, no staff role at all → say so
 * plainly, and *has* a staff role but not the one this screen needs → send them to
 * the first screen they can actually use, rather than an empty page.
 */
export function RequireStaff({
  children,
  permissions,
}: {
  children: React.ReactNode;
  /** Any one of these grants the screen. Omit for "any staff role at all". */
  permissions?: string[];
}) {
  const { user, loading, can } = useSession();
  const router = useRouter();
  const pathname = usePathname();

  const allowed = permissions === undefined
    ? user?.is_staff === true
    : permissions.some(can);

  React.useEffect(() => {
    if (!loading && !user) {
      router.replace(`/login?next=${encodeURIComponent(pathname)}`);
    }
  }, [loading, user, router, pathname]);

  if (loading) return <AdminSkeleton />;
  if (!user) return null;

  if (!allowed) {
    const elsewhere = firstReachable(can);

    return (
      <div className="mx-auto flex min-h-[60vh] max-w-lg flex-col items-center justify-center gap-4 px-6 text-center">
        <span className="flex size-12 items-center justify-center rounded-full bg-[var(--color-danger)]/10 text-[var(--color-danger)]">
          <ShieldAlert className="size-6" aria-hidden="true" />
        </span>

        <h1 className="text-lg font-semibold text-[var(--color-foreground)]">
          You do not have access to this screen
        </h1>

        <p className="text-sm leading-relaxed text-[var(--color-foreground-muted)]">
          {user.is_staff
            ? "Your role does not include this area. If you need it, an administrator can add the permission to your role."
            : "This is the staff area. Your account is a normal customer account."}
        </p>

        <div className="mt-1 flex gap-2">
          {elsewhere && (
            <Button asChild size="sm">
              <Link href={elsewhere.href}>Go to {elsewhere.label}</Link>
            </Button>
          )}
          <Button asChild variant="secondary" size="sm">
            <Link href="/dashboard">Back to your dashboard</Link>
          </Button>
        </div>
      </div>
    );
  }

  return <>{children}</>;
}

function AdminSkeleton() {
  return (
    <div className="flex min-h-dvh bg-[var(--app-ground)]">
      <div className="hidden w-[17rem] shrink-0 border-r border-[var(--color-border-subtle)] p-4 lg:block">
        <div className="h-8 w-40 animate-pulse rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)]" />
        <div className="mt-6 flex flex-col gap-2">
          {[0, 1, 2, 3, 4, 5, 6].map((row) => (
            <div
              key={row}
              className="h-9 animate-pulse rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)]"
            />
          ))}
        </div>
      </div>

      <div className="min-w-0 flex-1 p-6 lg:p-8">
        <div className="h-8 w-56 animate-pulse rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)]" />
        <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          {[0, 1, 2, 3].map((tile) => (
            <div
              key={tile}
              className="h-32 animate-pulse rounded-[var(--radius-lg)] bg-[var(--color-surface-sunken)]"
            />
          ))}
        </div>
        <div className="mt-4 h-72 animate-pulse rounded-[var(--radius-lg)] bg-[var(--color-surface-sunken)]" />
      </div>
    </div>
  );
}
