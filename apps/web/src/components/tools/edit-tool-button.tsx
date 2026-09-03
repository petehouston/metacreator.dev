"use client";

import { PencilLine } from "lucide-react";
import Link from "next/link";
import * as React from "react";

import { useSession } from "@/components/auth/session-provider";
import { cn } from "@/lib/utils";

/**
 * The staff shortcut from a tool page to its editor.
 *
 * Everybody who maintains the catalog reads it the way a visitor does — on the
 * public page, where a stale example or a wrong tagline is actually visible. This
 * closes the loop that used to run through the admin listing and a search box: see
 * the problem, click, fix it.
 *
 * Rendered client-side and only for staff who hold `tools.update`. Hiding it is a
 * convenience, never the thing that makes the editor safe: `/c0ns0le` re-checks the
 * permission on the route and the API re-checks it on the request.
 */
export function EditToolButton({ slug, className }: { slug: string; className?: string }) {
  const { user, loading, can } = useSession();

  // `loading` matters here: the session arrives after the first paint, and a button
  // that flickers in on a page a visitor is already reading is worse than one that
  // appears a moment late.
  if (loading || user === null || !user.is_staff || !can("tools.update")) return null;

  return (
    <Link
      href={`/c0ns0le/tools/${slug}`}
      className={cn(
        "inline-flex cursor-pointer items-center gap-1.5 rounded-[var(--radius-md)] border border-[var(--color-border)] px-3 py-1.5 text-sm font-medium text-[var(--color-foreground-muted)] transition-colors hover:border-[var(--color-border-strong)] hover:text-[var(--color-foreground)]",
        className,
      )}
    >
      <PencilLine aria-hidden="true" className="size-4" />
      Edit tool
    </Link>
  );
}
