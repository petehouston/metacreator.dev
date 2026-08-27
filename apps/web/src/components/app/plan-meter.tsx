"use client";

import { ArrowUpRight, Infinity as InfinityIcon, Zap } from "lucide-react";
import Link from "next/link";
import * as React from "react";

import { planLabel, useEntitlements } from "@/components/app/entitlements-provider";
import { cn } from "@/lib/utils";

/**
 * The plan card that sits at the foot of the sidebar.
 *
 * It is the only permanently-visible upsell in the app, and it earns that spot by
 * being useful first: the number people actually want ("how many runs do I have
 * left today") is the headline, and the upgrade is the footnote.
 */
export function PlanMeter({ compact = false }: { compact?: boolean }) {
  const { entitlements, loading } = useEntitlements();

  if (loading || !entitlements) {
    return (
      <div
        className={cn(
          "animate-pulse rounded-[var(--radius-lg)] bg-[var(--color-surface-sunken)]",
          compact ? "h-10" : "h-[6.5rem]",
        )}
        aria-hidden="true"
      />
    );
  }

  const { usage, is_paid: isPaid } = entitlements;
  const ratio = usage.limit > 0 ? Math.min(1, usage.used / usage.limit) : 0;
  const percent = Math.round(ratio * 100);
  // Amber from three quarters, red once it is gone: the colour has to change before
  // the wall is hit, or the warning arrives too late to act on.
  const tone =
    ratio >= 1
      ? "var(--color-danger)"
      : ratio >= 0.75
        ? "var(--color-warning)"
        : "var(--color-primary)";

  if (compact) {
    return (
      <Link
        href="/dashboard/billing"
        title={`${usage.used} of ${usage.limit} runs used today`}
        className="flex size-10 items-center justify-center rounded-[var(--radius-md)] border border-[var(--color-border)] text-[var(--color-foreground-muted)] transition-colors hover:text-[var(--color-foreground)]"
      >
        <Zap className="size-4" style={{ color: tone }} aria-hidden="true" />
        <span className="sr-only">
          Plan and usage: {usage.used} of {usage.limit} runs used today
        </span>
      </Link>
    );
  }

  return (
    <div className="rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-[var(--color-surface-sunken)] p-3.5">
      <div className="flex items-center justify-between gap-2">
        <span className="inline-flex items-center gap-1.5 text-xs font-semibold text-[var(--color-foreground)]">
          <Zap className="size-3.5 text-[var(--color-accent)]" aria-hidden="true" />
          {planLabel(entitlements.plan)}
        </span>

        <span className="tabular font-mono text-[0.6875rem] text-[var(--color-foreground-subtle)]">
          {usage.used}/{usage.limit}
        </span>
      </div>

      <div
        className="mt-2.5 h-1.5 overflow-hidden rounded-full bg-[var(--color-border)]"
        role="progressbar"
        aria-valuenow={percent}
        aria-valuemin={0}
        aria-valuemax={100}
        aria-label="Daily runs used"
      >
        <div
          className="h-full rounded-full transition-[width] duration-500"
          style={{ width: `${percent}%`, backgroundColor: tone }}
        />
      </div>

      <p className="mt-2 text-[0.6875rem] leading-snug text-[var(--color-foreground-subtle)]">
        {usage.remaining > 0
          ? `${usage.remaining} runs left today`
          : "Daily limit reached"}
        {" · resets "}
        {new Date(usage.resets_at).toLocaleTimeString(undefined, {
          hour: "numeric",
          minute: "2-digit",
        })}
      </p>

      {isPaid ? (
        <p className="mt-2 inline-flex items-center gap-1 text-[0.6875rem] font-medium text-[var(--color-accent)]">
          <InfinityIcon className="size-3" aria-hidden="true" />
          All premium tools unlocked
        </p>
      ) : (
        <Link
          href="/pricing"
          className="mt-2.5 inline-flex items-center gap-1 text-[0.6875rem] font-semibold text-[var(--color-primary)] hover:underline"
        >
          Upgrade for more
          <ArrowUpRight className="size-3" aria-hidden="true" />
        </Link>
      )}
    </div>
  );
}
