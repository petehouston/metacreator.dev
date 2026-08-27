"use client";

import { RotateCcw } from "lucide-react";
import Link from "next/link";
import * as React from "react";

import { Button } from "@/components/ui/button";

export default function Error({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  React.useEffect(() => {
    // Report to the error tracker. The digest is what support needs to find the
    // matching server-side trace.
    console.error(error);
  }, [error]);

  return (
    <div className="mx-auto flex w-full max-w-2xl flex-col items-center gap-5 px-4 py-24 text-center sm:px-6">
      <h1 className="text-heading-1 text-balance">Something went wrong on our end</h1>

      <p className="text-[var(--color-foreground-muted)]">
        This has been reported automatically. Trying again often works — the underlying
        request may simply have timed out.
      </p>

      <div className="flex flex-wrap justify-center gap-3">
        <Button onClick={reset} size="lg">
          <RotateCcw />
          Try again
        </Button>
        <Button asChild variant="ghost" size="lg">
          <Link href="/tools">Back to the tools</Link>
        </Button>
      </div>

      {error.digest && (
        <p className="font-mono text-xs text-[var(--color-foreground-subtle)]">
          Reference: {error.digest}
        </p>
      )}
    </div>
  );
}
