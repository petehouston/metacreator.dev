"use client";

import type * as React from "react";

import { useSession } from "@/components/auth/session-provider";

/**
 * Render children only if the actor holds one of these permissions.
 *
 * A convenience for hiding controls that would 403 — never the thing that makes an
 * action safe. `fallback` exists because a disabled-looking control with an
 * explanation is often kinder than a button that silently is not there.
 */
export function Can({
  permission,
  children,
  fallback = null,
}: {
  permission: string | string[];
  children: React.ReactNode;
  fallback?: React.ReactNode;
}) {
  const { can } = useSession();
  const needed = Array.isArray(permission) ? permission : [permission];

  return <>{needed.some(can) ? children : fallback}</>;
}

/** The hook form, for logic that has to branch rather than wrap. */
export function useCan(): (permission: string | string[]) => boolean {
  const { can } = useSession();

  return (permission) => (Array.isArray(permission) ? permission.some(can) : can(permission));
}
