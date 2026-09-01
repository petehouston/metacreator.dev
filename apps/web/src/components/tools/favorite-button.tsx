"use client";

import { Heart } from "lucide-react";
import Link from "next/link";
import * as React from "react";

import { useFavorites } from "@/components/tools/favorites-provider";
import { cn } from "@/lib/utils";

/**
 * The save control.
 *
 * Two sizes because it lives in two places with different jobs: an icon on a
 * catalog card, where it must not compete with the tool's name, and a labelled
 * button on the tool page, where saving is a deliberate act worth naming.
 *
 * For a guest it renders as a link to sign-in rather than disappearing. A control
 * that is simply absent teaches nobody that the feature exists; one that explains
 * what it needs is the cheapest reason to create an account on the page.
 */
export function FavoriteButton({
  slug,
  variant = "icon",
  className,
}: {
  slug: string;
  variant?: "icon" | "labelled";
  className?: string;
}) {
  const { enabled, isFavorite, toggle, loading } = useFavorites();
  const saved = isFavorite(slug);

  const heart = (
    <Heart
      aria-hidden="true"
      className={cn(
        "size-4 transition-[fill,color] duration-150",
        saved ? "fill-[var(--color-danger)] text-[var(--color-danger)]" : "text-current",
      )}
    />
  );

  if (!enabled) {
    if (variant === "icon") {
      // Nothing on a card for a guest: sixty hearts that all mean "sign in" is
      // sixty pieces of noise. The tool page makes the offer instead.
      return null;
    }

    return (
      <Link
        href={`/login?redirect=/tools/${slug}`}
        className={cn(
          "inline-flex items-center gap-1.5 rounded-[var(--radius-md)] border border-[var(--color-border)] px-3 py-1.5 text-sm font-medium text-[var(--color-foreground-muted)] transition-colors hover:border-[var(--color-border-strong)] hover:text-[var(--color-foreground)]",
          className,
        )}
      >
        {heart}
        Save this tool
      </Link>
    );
  }

  const label = saved ? `Remove ${slug} from favourites` : `Save ${slug} to favourites`;

  return (
    <button
      type="button"
      // The card is a link; a button inside it must not navigate as well.
      onClick={(event) => {
        event.preventDefault();
        event.stopPropagation();
        void toggle(slug);
      }}
      // aria-pressed rather than a changing label: a screen reader announces the
      // state change on the same control instead of re-reading a new one.
      aria-pressed={saved}
      aria-label={variant === "icon" ? label : undefined}
      title={variant === "icon" ? (saved ? "Saved" : "Save") : undefined}
      disabled={loading}
      className={cn(
        variant === "icon"
          ? "inline-flex size-7 shrink-0 items-center justify-center rounded-full text-[var(--color-foreground-subtle)] transition-colors hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-foreground)] disabled:opacity-50"
          : "inline-flex items-center gap-1.5 rounded-[var(--radius-md)] border border-[var(--color-border)] px-3 py-1.5 text-sm font-medium text-[var(--color-foreground-muted)] transition-colors hover:border-[var(--color-border-strong)] hover:text-[var(--color-foreground)] disabled:opacity-50",
        className,
      )}
    >
      {heart}
      {variant === "labelled" && (saved ? "Saved" : "Save this tool")}
    </button>
  );
}
