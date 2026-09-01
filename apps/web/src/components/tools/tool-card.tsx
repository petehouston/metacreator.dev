import { ArrowUpRight } from "lucide-react";
import Link from "next/link";

import { FavoriteButton } from "@/components/tools/favorite-button";
import { TierBadge } from "@/components/ui/badge";
import { cn, formatNumber } from "@/lib/utils";
import { actionVerb } from "@/lib/tool-action";
import type { ToolSummary } from "@/lib/types";

/**
 * The catalog card.
 *
 * The signature here is the spine: a hairline down the left edge that fills with
 * the brand gradient on hover. It gives a grid of cards a direction to read in,
 * and it costs one pseudo-element rather than a colour change on the whole card.
 */
export function ToolCard({
  tool,
  className,
}: {
  tool: ToolSummary;
  className?: string;
}) {
  return (
    <Link
      href={`/tools/${tool.slug}`}
      className={cn(
        "panel panel-lift group relative isolate flex flex-col gap-3 overflow-hidden p-5 pl-6",
        className,
      )}
    >
      {/* The spine. */}
      <span
        aria-hidden="true"
        className="absolute inset-y-0 left-0 w-[3px] bg-gradient-to-b from-[var(--color-primary)] to-[var(--color-accent)] opacity-0 transition-opacity duration-200 group-hover:opacity-100"
      />

      <div className="flex items-start justify-between gap-3">
        <div className="flex flex-wrap items-center gap-1.5">
          <TierBadge tier={tool.tier} />
          {tool.category && (
            <span className="font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
              {tool.category.name}
            </span>
          )}
        </div>

        <div className="flex shrink-0 items-center gap-0.5">
          {/* Renders nothing for a guest — see FavoriteButton. */}
          <FavoriteButton slug={tool.slug} />

          <ArrowUpRight
            aria-hidden="true"
            className="size-4 shrink-0 text-[var(--color-foreground-subtle)] transition-all group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-[var(--color-primary)]"
          />
        </div>
      </div>

      <div className="flex flex-1 flex-col gap-1.5">
        <h3 className="text-base font-semibold leading-snug text-[var(--color-foreground)]">
          {tool.name}
        </h3>
        <p className="text-sm leading-relaxed text-[var(--color-foreground-muted)]">
          {tool.tagline}
        </p>
      </div>

      <div className="mt-auto flex items-center justify-between gap-2 border-t border-[var(--color-border-subtle)] pt-3">
        <span className="font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-primary)]">
          {actionVerb(tool.name, tool.slug)} →
        </span>

        {tool.stats.runs > 0 && (
          <span className="tabular text-[0.625rem] text-[var(--color-foreground-subtle)]">
            {formatNumber(tool.stats.runs)} runs
          </span>
        )}
      </div>
    </Link>
  );
}

export function ToolCardSkeleton() {
  return (
    <div className="panel flex flex-col gap-3 p-5 pl-6">
      <div className="h-5 w-20 animate-pulse rounded-full bg-[var(--color-surface-sunken)]" />
      <div className="h-5 w-3/4 animate-pulse rounded bg-[var(--color-surface-sunken)]" />
      <div className="h-4 w-full animate-pulse rounded bg-[var(--color-surface-sunken)]" />
      <div className="h-4 w-2/3 animate-pulse rounded bg-[var(--color-surface-sunken)]" />
    </div>
  );
}
