"use client";

import { AlertTriangle, RefreshCw } from "lucide-react";

import { Button } from "@/components/ui/button";
import type { ApiFailure } from "@/lib/http";

/**
 * What a screen shows when its data would not load.
 *
 * The server's own message, verbatim, plus the request id — that pairing is what
 * turns "the admin is broken" into a support conversation someone can act on.
 */
export function LoadError({ error, onRetry }: { error: ApiFailure; onRetry: () => void }) {
  return (
    <div
      role="alert"
      className="flex flex-col items-center gap-3 rounded-[var(--radius-lg)] border border-dashed border-[var(--color-danger)]/40 bg-[var(--color-danger)]/5 px-6 py-10 text-center"
    >
      <span className="flex size-10 items-center justify-center rounded-full bg-[var(--color-danger)]/12 text-[var(--color-danger)]">
        <AlertTriangle className="size-5" aria-hidden="true" />
      </span>

      <p className="text-sm font-semibold text-[var(--color-foreground)]">
        {error.status === 403
          ? "You do not have permission to read this"
          : "We could not load this screen"}
      </p>

      <p className="max-w-md text-sm leading-relaxed text-[var(--color-foreground-muted)]">
        {error.message}
      </p>

      {error.request_id && (
        <p className="font-mono text-[0.625rem] text-[var(--color-foreground-subtle)]">
          Request {error.request_id}
        </p>
      )}

      {error.status !== 403 && (
        <Button variant="secondary" size="sm" onClick={onRetry} className="mt-1">
          <RefreshCw className="size-4" aria-hidden="true" />
          Try again
        </Button>
      )}
    </div>
  );
}
