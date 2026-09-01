"use client";

import { ArrowUpRight, Infinity as InfinityIcon, Zap } from "lucide-react";
import Link from "next/link";
import * as React from "react";

import { planLabel, useEntitlements } from "@/components/app/entitlements-provider";
import { useBillingEnabled } from "@/components/site/features-provider";
import { cn } from "@/lib/utils";

/** How each budget window reads in a sentence and as a label. */
const PERIOD: Record<string, { suffix: string; adjective: string }> = {
  daily: { suffix: "today", adjective: "Daily" },
  weekly: { suffix: "this week", adjective: "Weekly" },
  monthly: { suffix: "this month", adjective: "Monthly" },
};

/**
 * The plan card that sits at the foot of the sidebar.
 *
 * It is the only permanently-visible upsell in the app, and it earns that spot by
 * being useful first: the number people actually want ("how many runs do I have
 * left") is the headline, and the upgrade is the footnote.
 */
export function PlanMeter({ compact = false }: { compact?: boolean }) {
  const { entitlements, loading } = useEntitlements();
  const billingEnabled = useBillingEnabled();

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
  // The server sends the *binding* window — whichever of the day, week or month
  // has the least left — rather than always the day, so the meter measures what
  // will actually stop the next run. Naming the period matters: "3 runs left"
  // means something different when the reset is Tuesday than when it is midnight.
  const period = PERIOD[usage.window] ?? PERIOD.daily;
  // An unlimited allowance has no meter: a bar is a fraction of something, and
  // there is no denominator here. It shows a count and says so instead.
  const unlimited = usage.unlimited;
  const ratio = !unlimited && usage.limit > 0 ? Math.min(1, usage.used / usage.limit) : 0;
  const percent = Math.round(ratio * 100);
  // Amber from three quarters, red once it is gone: the colour has to change before
  // the wall is hit, or the warning arrives too late to act on.
  const tone =
    !unlimited && ratio >= 1
      ? "var(--color-danger)"
      : ratio >= 0.75
        ? "var(--color-warning)"
        : "var(--color-primary)";

  if (compact) {
    return (
      <Link
        href={billingEnabled ? "/dashboard/billing" : "/dashboard/runs"}
        title={
          unlimited
            ? `${usage.used} runs ${period.suffix} · unlimited`
            : `${usage.used} of ${usage.limit} runs used ${period.suffix}`
        }
        className="flex size-10 items-center justify-center rounded-[var(--radius-md)] border border-[var(--color-border)] text-[var(--color-foreground-muted)] transition-colors hover:text-[var(--color-foreground)]"
      >
        <Zap className="size-4" style={{ color: tone }} aria-hidden="true" />
        <span className="sr-only">
          Plan and usage:{" "}
          {unlimited
            ? `${usage.used} runs ${period.suffix}, no limit`
            : `${usage.used} of ${usage.limit} runs used ${period.suffix}`}
        </span>
      </Link>
    );
  }

  return (
    <div className="rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-[var(--color-surface-sunken)] p-3.5">
      <div className="flex items-center justify-between gap-2">
        <span className="inline-flex items-center gap-1.5 text-xs font-semibold text-[var(--color-foreground)]">
          <Zap className="size-3.5 text-[var(--color-accent)]" aria-hidden="true" />
          {billingEnabled ? planLabel(entitlements.plan) : "Usage"}
        </span>

        <span className="tabular font-mono text-[0.6875rem] text-[var(--color-foreground-subtle)]">
          {unlimited ? `${usage.used} ${period.suffix}` : `${usage.used}/${usage.limit}`}
        </span>
      </div>

      {!unlimited && (
        <div
          className="mt-2.5 h-1.5 overflow-hidden rounded-full bg-[var(--color-border)]"
          role="progressbar"
          aria-valuenow={percent}
          aria-valuemin={0}
          aria-valuemax={100}
          aria-label={`${period.adjective} runs used`}
        >
          <div
            className="h-full rounded-full transition-[width] duration-500"
            style={{ width: `${percent}%`, backgroundColor: tone }}
          />
        </div>
      )}

      <p className="mt-2 text-[0.6875rem] leading-snug text-[var(--color-foreground-subtle)]">
        {unlimited ? (
          "No run limit"
        ) : (
          <>
            {(usage.remaining ?? 0) > 0
              ? `${usage.remaining} runs left ${period.suffix}`
              : `${period.adjective} limit reached`}
            {" · resets "}
            {/* A reset hours away wants a clock; one days away wants a date. */}
            {usage.window === "daily"
              ? new Date(usage.resets_at).toLocaleTimeString(undefined, {
                  hour: "numeric",
                  minute: "2-digit",
                })
              : new Date(usage.resets_at).toLocaleDateString(undefined, {
                  month: "short",
                  day: "numeric",
                })}
          </>
        )}
      </p>

      {/* The card's whole footer is the upsell, so with billing off it is the meter
          alone — a permanent "Upgrade" pointing at a 404 is worse than no footer. */}
      {!billingEnabled ? (
        <p className="mt-2 inline-flex items-center gap-1 text-[0.6875rem] font-medium text-[var(--color-accent)]">
          <InfinityIcon className="size-3" aria-hidden="true" />
          Every tool unlocked
        </p>
      ) : isPaid ? (
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
