import Link from "next/link";

import { MARK_ROD, MARK_SPARKS } from "@/lib/brand-mark.generated";
import { cn } from "@/lib/utils";

/**
 * The bare mark: a tapered wand throwing four sparks — the tool, and what it makes.
 *
 * The geometry comes from `lib/brand-mark.generated.ts` so this component and the
 * checked-in favicons can never drift; the colour does not, because the generated
 * assets bake in fixed hex and this one paints in theme tokens instead. That is
 * the whole reason the paths and the finished SVG are exported separately.
 *
 * Exported on its own for the collapsed sidebar rails, which want the mark without
 * the wordmark. The gradient ids are fixed rather than generated: two of these can
 * share a page, and since both definitions are identical it does not matter which
 * one a reference resolves to.
 */
export function LogoMark({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 32 32" className={className} aria-hidden="true">
      <defs>
        {/* The rod travels cobalt at the handle to emerald at the tip, so the
            sparks read as the colour the wand arrives at rather than a second
            unrelated hue. */}
        <linearGradient id="mc-rod" x1="3.75" y1="27.6" x2="17.15" y2="14.2" gradientUnits="userSpaceOnUse">
          <stop offset="0" stopColor="var(--color-brand-500)" />
          <stop offset="1" stopColor="var(--color-signal-400)" />
        </linearGradient>
        <linearGradient id="mc-spark" x1="13" y1="4" x2="30" y2="22" gradientUnits="userSpaceOnUse">
          <stop offset="0" stopColor="var(--color-signal-300)" />
          <stop offset="1" stopColor="var(--color-signal-500)" />
        </linearGradient>
      </defs>
      <path d={MARK_ROD} fill="url(#mc-rod)" />
      {MARK_SPARKS.map((d) => (
        <path key={d} d={d} fill="url(#mc-spark)" />
      ))}
    </svg>
  );
}

/** The mark plus the wordmark, linked home. The default lockup everywhere. */
export function Logo({ className, href = "/" }: { className?: string; href?: string }) {
  return (
    <Link
      href={href}
      className={cn("group inline-flex items-center gap-2.5", className)}
      aria-label="MetaCreator.Dev — home"
    >
      <span className="relative flex size-8 items-center justify-center">
        <LogoMark className="size-8" />
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
