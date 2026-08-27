"use client";

import { ArrowDownRight, ArrowUpRight, Minus } from "lucide-react";
import type * as React from "react";

import { Sparkline } from "@/components/admin/charts";
import type { Metric } from "@/lib/admin/types";
import { cn, formatMoney, formatNumber } from "@/lib/utils";

/**
 * One headline number, its movement, and the shape behind it.
 *
 * The colour of the delta comes from `higher_is_better`, not from the sign — a
 * falling failure rate is green and a falling MRR is red, and a card that got that
 * backwards would be actively misleading at a glance, which is the only way anyone
 * reads a dashboard.
 */
export function MetricCard({
  metric,
  compact = false,
  className,
}: {
  metric: Metric;
  compact?: boolean;
  className?: string;
}) {
  const change = metric.change_percent;
  const good = change === null ? null : metric.higher_is_better === change >= 0;

  const Icon =
    metric.trend === "up" ? ArrowUpRight : metric.trend === "down" ? ArrowDownRight : Minus;

  const deltaColor =
    good === null
      ? "var(--color-foreground-subtle)"
      : good
        ? "var(--color-success)"
        : "var(--color-danger)";

  return (
    <div className={cn("app-card flex flex-col p-4", className)}>
      <div className="flex items-start justify-between gap-2">
        <p className="font-mono text-[0.625rem] font-medium uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
          {metric.label}
        </p>

        {change !== null && (
          <span
            className="tabular inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-[0.6875rem] font-semibold"
            style={{
              color: deltaColor,
              backgroundColor: `color-mix(in oklab, ${deltaColor} 12%, transparent)`,
            }}
          >
            <Icon className="size-3" aria-hidden="true" />
            {Math.abs(change)}%
            <span className="sr-only">
              {good ? "better than" : "worse than"} the previous period
            </span>
          </span>
        )}
      </div>

      <p className="tabular mt-2 text-2xl font-semibold leading-none tracking-[-0.02em] text-[var(--color-foreground)]">
        {formatMetric(metric)}
      </p>

      {!compact && metric.series.length > 1 && (
        <Sparkline
          points={metric.series.map((point) => point.value)}
          className="mt-3"
          tone={good === false ? "var(--color-danger)" : "var(--color-primary)"}
        />
      )}

      {metric.hint && (
        <p className="mt-2 text-xs leading-snug text-[var(--color-foreground-subtle)]">
          {metric.hint}
        </p>
      )}

      {change === null && metric.previous === null && !metric.hint && (
        <p className="mt-2 text-xs text-[var(--color-foreground-subtle)]">Point-in-time figure.</p>
      )}
    </div>
  );
}

export function formatMetric(metric: Pick<Metric, "value" | "format">): string {
  switch (metric.format) {
    case "currency":
      return formatMoney(Math.round(metric.value));
    case "percent":
      return `${metric.value.toFixed(metric.value < 10 ? 2 : 1)}%`;
    default:
      return formatNumber(Math.round(metric.value));
  }
}

export function MetricCardSkeleton() {
  return <div className="app-card h-[9.5rem] animate-pulse" aria-hidden="true" />;
}
