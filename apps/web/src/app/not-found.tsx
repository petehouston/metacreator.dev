import Link from "next/link";

import { Button } from "@/components/ui/button";

export default function NotFound() {
  return (
    <div className="mx-auto flex w-full max-w-2xl flex-col items-center gap-5 px-4 py-24 text-center sm:px-6">
      <p className="tabular text-display-lg text-[var(--color-foreground-subtle)]">404</p>

      <h1 className="text-heading-1 text-balance">We couldn&apos;t find that page</h1>

      <p className="text-[var(--color-foreground-muted)]">
        The link may be out of date, or the tool may have been renamed. The catalog is the
        fastest way to find what you were after.
      </p>

      <div className="flex flex-wrap justify-center gap-3">
        <Button asChild size="lg">
          <Link href="/tools">Browse the tools</Link>
        </Button>
        <Button asChild variant="ghost" size="lg">
          <Link href="/">Go home</Link>
        </Button>
      </div>
    </div>
  );
}
