import Link from "next/link";

import { cn } from "@/lib/utils";

/**
 * The mark is two offset rounded squares — a layered "meta" glyph that reads at
 * 16px in a browser tab, which is the only size that really matters for a favicon.
 */
export function Logo({ className, href = "/" }: { className?: string; href?: string }) {
  return (
    <Link
      href={href}
      className={cn("group inline-flex items-center gap-2.5", className)}
      aria-label="MetaCreator.Dev — home"
    >
      <span className="relative flex size-8 items-center justify-center">
        <svg viewBox="0 0 32 32" className="size-8" aria-hidden="true">
          <rect
            x="3"
            y="3"
            width="19"
            height="19"
            rx="6"
            className="fill-[var(--color-brand-500)]"
            opacity="0.85"
          />
          <rect
            x="10"
            y="10"
            width="19"
            height="19"
            rx="6"
            className="fill-[var(--color-signal-400)]"
            opacity="0.9"
            style={{ mixBlendMode: "screen" }}
          />
        </svg>
      </span>

      <span className="text-[0.9375rem] font-semibold tracking-tight text-[var(--color-foreground)]">
        MetaCreator
        {/* Mono for the TLD: it is the half of the name that says "this is a tool,
            not a magazine", and the type change is what makes people read it. */}
        <span className="font-mono text-[0.8125rem] font-normal text-[var(--color-accent)]">
          .dev
        </span>
      </span>
    </Link>
  );
}
