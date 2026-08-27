"use client";

import * as React from "react";

import { cn, formatNumber } from "@/lib/utils";

/**
 * Charts, drawn as inline SVG.
 *
 * No charting library: everything the admin needs is a line, a stacked bar and a
 * proportion strip, and each of those is a `<path>`. A dependency would cost more
 * kilobytes than every chart on this screen combined, and would still have to be
 * fought into the design tokens.
 *
 * Two rules hold throughout. Every chart is `aria-hidden` and paired with a real
 * number in text — a screen reader must never be handed a shape. And every chart
 * degrades to something honest when it has no data, rather than drawing a flat
 * line that looks like a measurement of zero.
 */

/** A bare trend line, sized to its container. */
export function Sparkline({
  points,
  className,
  tone = "var(--color-primary)",
}: {
  points: number[];
  className?: string;
  tone?: string;
}) {
  // Before the early return: a hook after a conditional changes the hook order
  // between renders, which is the one thing React cannot recover from.
  const gradientId = React.useId();

  if (points.length < 2) {
    return <div className={cn("h-8", className)} aria-hidden="true" />;
  }

  const max = Math.max(...points);
  const min = Math.min(...points);
  // A flat series has zero range; dividing by it would produce NaN and an empty
  // path. Draw it down the middle instead — flat *is* the finding.
  const range = max - min || 1;

  const width = 100;
  const height = 32;

  const coordinates = points.map((value, index) => {
    const x = (index / (points.length - 1)) * width;
    const y = height - ((value - min) / range) * (height - 4) - 2;

    return [x, y] as const;
  });

  const line = coordinates.map(([x, y], i) => `${i === 0 ? "M" : "L"}${x.toFixed(2)},${y.toFixed(2)}`).join(" ");
  const area = `${line} L${width},${height} L0,${height} Z`;

  return (
    <svg
      viewBox={`0 0 ${width} ${height}`}
      preserveAspectRatio="none"
      className={cn("h-8 w-full", className)}
      aria-hidden="true"
    >
      <defs>
        <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor={tone} stopOpacity="0.22" />
          <stop offset="100%" stopColor={tone} stopOpacity="0" />
        </linearGradient>
      </defs>

      <path d={area} fill={`url(#${gradientId})`} />
      <path
        d={line}
        fill="none"
        stroke={tone}
        strokeWidth="1.5"
        strokeLinecap="round"
        strokeLinejoin="round"
        vectorEffect="non-scaling-stroke"
      />
    </svg>
  );
}

export interface ColumnSeries {
  date: string;
  values: { key: string; value: number; label: string; color: string }[];
}

/**
 * A stacked column chart with a hover readout.
 *
 * Stacked rather than grouped because the question is "how much of this volume
 * failed?", and grouped bars make a reader do the division themselves.
 */
export function StackedColumns({
  data,
  height = 200,
  className,
}: {
  data: ColumnSeries[];
  height?: number;
  className?: string;
}) {
  const [hover, setHover] = React.useState<number | null>(null);

  const totals = data.map((column) => column.values.reduce((sum, slice) => sum + slice.value, 0));
  const max = Math.max(1, ...totals);
  const active = hover !== null ? data[hover] : null;

  if (data.length === 0) {
    return (
      <div
        className={cn(
          "flex items-center justify-center rounded-[var(--radius-md)] border border-dashed border-[var(--color-border)] text-sm text-[var(--color-foreground-subtle)]",
          className,
        )}
        style={{ height }}
      >
        Nothing recorded in this period yet.
      </div>
    );
  }

  return (
    <div className={cn("relative", className)}>
      {/* The readout sits above the plot rather than following the cursor: a
          tooltip that moves is a tooltip you chase, and this one has a fixed place
          to look. */}
      <div className="mb-2 flex h-8 items-center justify-between text-xs">
        {active ? (
          <>
            <span className="font-medium text-[var(--color-foreground)]">
              {new Date(active.date).toLocaleDateString(undefined, {
                month: "short",
                day: "numeric",
              })}
            </span>
            <span className="flex flex-wrap items-center gap-3">
              {active.values.map((slice) => (
                <span key={slice.key} className="flex items-center gap-1.5">
                  <span
                    className="size-2 rounded-[2px]"
                    style={{ backgroundColor: slice.color }}
                    aria-hidden="true"
                  />
                  <span className="text-[var(--color-foreground-muted)]">{slice.label}</span>
                  <span className="tabular font-medium text-[var(--color-foreground)]">
                    {formatNumber(slice.value)}
                  </span>
                </span>
              ))}
            </span>
          </>
        ) : (
          <span className="text-[var(--color-foreground-subtle)]">
            Hover a column for that day&rsquo;s breakdown
          </span>
        )}
      </div>

      <div
        className="flex items-end gap-[2px]"
        style={{ height }}
        onMouseLeave={() => setHover(null)}
      >
        {data.map((column, index) => {
          const total = totals[index];

          return (
            <button
              key={column.date}
              type="button"
              onMouseEnter={() => setHover(index)}
              onFocus={() => setHover(index)}
              // Focusable so the readout is reachable without a mouse; the label
              // carries the numbers the shape encodes.
              aria-label={`${column.date}: ${column.values
                .map((slice) => `${slice.label} ${slice.value}`)
                .join(", ")}`}
              className={cn(
                "group flex h-full min-w-0 flex-1 flex-col justify-end rounded-t-[3px] transition-opacity",
                hover !== null && hover !== index && "opacity-45",
              )}
            >
              {total === 0 ? (
                <span
                  className="h-[2px] w-full rounded-full bg-[var(--color-border)]"
                  aria-hidden="true"
                />
              ) : (
                column.values
                  .filter((slice) => slice.value > 0)
                  .map((slice) => (
                    <span
                      key={slice.key}
                      className="w-full first:rounded-t-[3px]"
                      style={{
                        height: `${(slice.value / max) * 100}%`,
                        backgroundColor: slice.color,
                      }}
                      aria-hidden="true"
                    />
                  ))
              )}
            </button>
          );
        })}
      </div>
    </div>
  );
}

/** A proportion strip: the shares of a whole, in one row. */
export function ShareBar({
  slices,
  className,
}: {
  slices: { key: string; label: string; value: number; share: number; color: string }[];
  className?: string;
}) {
  if (slices.length === 0) {
    return (
      <p className={cn("text-sm text-[var(--color-foreground-subtle)]", className)}>
        No runs to break down yet.
      </p>
    );
  }

  return (
    <div className={className}>
      <div
        className="flex h-2.5 overflow-hidden rounded-full bg-[var(--color-surface-sunken)]"
        aria-hidden="true"
      >
        {slices.map((slice) => (
          <span
            key={slice.key}
            style={{ width: `${slice.share}%`, backgroundColor: slice.color }}
            className="h-full"
          />
        ))}
      </div>

      <ul className="mt-3 flex flex-col gap-1.5">
        {slices.map((slice) => (
          <li key={slice.key} className="flex items-center gap-2 text-sm">
            <span
              className="size-2.5 shrink-0 rounded-[3px]"
              style={{ backgroundColor: slice.color }}
              aria-hidden="true"
            />
            <span className="flex-1 truncate text-[var(--color-foreground-muted)]">
              {slice.label}
            </span>
            <span className="tabular text-xs text-[var(--color-foreground-subtle)]">
              {slice.share}%
            </span>
            <span className="tabular w-16 text-right font-medium text-[var(--color-foreground)]">
              {formatNumber(slice.value)}
            </span>
          </li>
        ))}
      </ul>
    </div>
  );
}

/**
 * The funnel, as nested bars.
 *
 * Every step is measured against the *top* of the funnel rather than the step
 * before it, so the widths are directly comparable — a classic funnel chart where
 * each bar is 100% of its own parent hides exactly the drop-off it claims to show.
 */
export function FunnelBars({
  steps,
  className,
}: {
  steps: { step: string; label: string; count: number; retention: number | null }[];
  className?: string;
}) {
  const top = Math.max(1, steps[0]?.count ?? 1);

  return (
    <ol className={cn("flex flex-col gap-3", className)}>
      {steps.map((step, index) => {
        const width = Math.max(1.5, (step.count / top) * 100);
        const previous = index > 0 ? steps[index - 1].count : null;
        const dropOff =
          previous !== null && previous > 0
            ? Math.round(((previous - step.count) / previous) * 100)
            : null;

        return (
          <li key={step.step}>
            <div className="mb-1 flex items-baseline justify-between gap-3 text-sm">
              <span className="font-medium text-[var(--color-foreground)]">{step.label}</span>
              <span className="flex items-baseline gap-2">
                <span className="tabular font-semibold text-[var(--color-foreground)]">
                  {formatNumber(step.count)}
                </span>
                {step.retention !== null && (
                  <span className="tabular text-xs text-[var(--color-foreground-subtle)]">
                    {step.retention}% of visitors
                  </span>
                )}
              </span>
            </div>

            <div className="h-7 overflow-hidden rounded-[var(--radius-sm)] bg-[var(--color-surface-sunken)]">
              <div
                className="h-full rounded-[var(--radius-sm)] transition-[width] duration-500"
                style={{
                  width: `${width}%`,
                  background: `linear-gradient(90deg, var(--color-brand-500), var(--color-signal-500))`,
                  opacity: 1 - index * 0.14,
                }}
                aria-hidden="true"
              />
            </div>

            {dropOff !== null && dropOff > 0 && (
              <p className="mt-1 text-xs text-[var(--color-foreground-subtle)]">
                {dropOff}% did not continue from {steps[index - 1].label.toLowerCase()}
              </p>
            )}
          </li>
        );
      })}
    </ol>
  );
}
