import type { MetricUnit } from "@/lib/types";

const MULTIPLIER: Record<MetricUnit, number> = {
  exact: 1,
  thousands: 1_000,
  millions: 1_000_000,
  billions: 1_000_000_000,
};

/**
 * A ranking metric, written the way the source meant it.
 *
 * The API stores what the article published — "515" beneath a header reading
 * "(millions)" — rather than a normalised count, because multiplying on write would
 * invent six digits of precision nobody measured. So the unit travels with the page
 * and the arithmetic happens here, once, at render.
 *
 * The output keeps the source's own precision: 515 stays "515M" and does not become
 * "515.0M", while 162.7 keeps its decimal. Rounding those to the same shape would
 * make a rounded figure and a measured one look equally exact.
 */
export function formatMetric(value: number | null, unit: MetricUnit): string {
  if (value === null || !Number.isFinite(value)) return "—";

  if (unit === "exact") {
    // A page that publishes 1,112,947 is publishing the exact number on purpose;
    // abbreviating it to "1.1M" would throw away the reason it was written that way.
    return new Intl.NumberFormat("en-US").format(Math.round(value));
  }

  const suffix = { thousands: "K", millions: "M", billions: "B" }[unit];

  return `${trim(value)}${suffix}`;
}

/**
 * The same figure as a full count, for the `title` attribute and for structured
 * data — where a machine reading "515M" learns much less than one reading
 * 515,000,000.
 */
export function metricAsCount(
  value: number | null,
  unit: MetricUnit,
): number | null {
  if (value === null || !Number.isFinite(value)) return null;

  return Math.round(value * MULTIPLIER[unit]);
}

/** The long form, for a tooltip: "approximately 515,000,000 subscribers". */
export function metricTitle(
  value: number | null,
  unit: MetricUnit,
  label: string,
): string | undefined {
  const count = metricAsCount(value, unit);

  if (count === null) return undefined;

  const formatted = new Intl.NumberFormat("en-US").format(count);

  // "Approximately" is not hedging. Every one of these figures except the exact
  // ones is a rounded number a Wikipedia editor transcribed from a profile page on
  // some particular day, and presenting it as a precise count would be a claim the
  // source never made.
  return unit === "exact"
    ? `${formatted} ${label.toLowerCase()}`
    : `approximately ${formatted} ${label.toLowerCase()}`;
}

/** `515.0` → `515`, `162.70` → `162.7`. */
function trim(value: number): string {
  return String(Number(value.toFixed(2)));
}

/** "Updated 3 days ago", or null when a page has never synced. */
export function freshness(iso: string | null): string | null {
  if (iso === null) return null;

  const days = Math.floor((Date.now() - new Date(iso).getTime()) / 86_400_000);

  if (!Number.isFinite(days) || days < 0) return null;
  if (days === 0) return "Updated today";
  if (days === 1) return "Updated yesterday";
  if (days < 14) return `Updated ${days} days ago`;

  return `Updated ${new Intl.DateTimeFormat("en-US", { month: "short", day: "numeric", year: "numeric" }).format(new Date(iso))}`;
}
