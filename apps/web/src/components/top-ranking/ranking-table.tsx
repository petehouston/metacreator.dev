import { ExternalLink } from "lucide-react";
import Link from "next/link";

import { RankingAvatar } from "@/components/top-ranking/ranking-avatar";
import { formatMetric, metricTitle } from "@/lib/ranking-format";
import type { TopRankingEntry, TopRankingPage } from "@/lib/types";
import { cn } from "@/lib/utils";

/**
 * The ranking itself.
 *
 * **The column set is decided per page, not per row.** These nine lists do not
 * carry the same facts — TikTok publishes likes as well as followers, Twitch
 * publishes categories, X publishes neither a country nor a category — and a fixed
 * set of columns would leave a third of this table permanently full of dashes. So
 * each optional column is included only when at least one row has something to put
 * in it, which is what lets one component render all nine without any of them
 * looking like a form with fields left blank.
 *
 * **One table, scrolling inside itself.** Everything past the name is hidden below
 * `md` rather than squeezed, and the wrapper — not the page — is what scrolls. A
 * ranking that makes the whole document scroll sideways on a phone is unreadable
 * exactly where most of its readers are.
 */
export function RankingTable({
  page,
  entries,
  /** The rows already shown on the podium, so they are not repeated at the top. */
  offset = 0,
}: {
  page: TopRankingPage;
  entries: TopRankingEntry[];
  offset?: number;
}) {
  const rows = entries.slice(offset);

  if (rows.length === 0) return null;

  // Which of the optional columns this page actually has anything to say in.
  const has = {
    // The name cell already carries the owner as its headline and the handle
    // beneath, so a second "Owner" column would repeat itself. What earns a column
    // is the one-line description the articles publish — "Footballer", "Social
    // media personality" — which is the fact a reader scanning the table wants.
    description: rows.some((row) => row.description !== null),
    secondary:
      page.secondary_metric_label !== null &&
      rows.some((row) => row.secondary_metric !== null),
    category: rows.some((row) => row.category !== null),
    country: rows.some((row) => row.country !== null),
  };

  return (
    <div className="app-card mt-10 overflow-hidden">
      <div className="scrollbar-slim overflow-x-auto">
        <table className="w-full border-collapse text-sm">
          <caption className="sr-only">
            {page.title}, ranked by {page.metric_label.toLowerCase()}.
          </caption>

          <thead>
            <tr className="border-b border-[var(--color-border)]">
              <Th className="w-14 pl-4 text-center">#</Th>
              <Th>{titleCase(page.noun)}</Th>
              {has.description && <Th hideBelow="lg">Known for</Th>}
              <Th numeric>{page.metric_label}</Th>
              {has.secondary && (
                <Th numeric hideBelow="sm">
                  {page.secondary_metric_label}
                </Th>
              )}
              {has.category && <Th hideBelow="xl">Category</Th>}
              {has.country && <Th hideBelow="md">Country</Th>}
              <Th className="w-12 pr-4">
                <span className="sr-only">Open profile</span>
              </Th>
            </tr>
          </thead>

          <tbody>
            {rows.map((entry) => (
              <tr
                key={`${entry.rank}-${entry.name}`}
                className="border-b border-[var(--color-border-subtle)] transition-colors last:border-0 hover:bg-[var(--color-surface-sunken)]"
              >
                <td className="tabular py-2.5 pl-4 text-center text-sm font-medium text-[var(--color-foreground-subtle)]">
                  {entry.rank}
                </td>

                <td className="py-2.5 pr-3">
                  <div className="flex min-w-0 items-center gap-3">
                    <RankingAvatar
                      src={entry.avatar_url}
                      initials={entry.initials}
                      accent={page.platform_accent}
                    />

                    <div className="min-w-0">
                      <span className="block truncate font-medium text-[var(--color-foreground)]">
                        {entry.owner ?? entry.name}
                      </span>

                      {/* Rendered only when it has something new to say. Several of
                          these articles publish neither a handle nor a description —
                          the YouTube list names a channel and stops — and a fallback
                          chain ending at the name printed "SET India" twice, once in
                          each line, which reads as a rendering fault. */}
                      {subtitle(entry) && (
                        <span className="block truncate font-mono text-xs text-[var(--color-foreground-subtle)]">
                          {subtitle(entry)}
                        </span>
                      )}
                    </div>
                  </div>
                </td>

                {has.description && (
                  <Td
                    hideBelow="lg"
                    className="text-[var(--color-foreground-muted)]"
                  >
                    {entry.description ?? "—"}
                  </Td>
                )}

                <td
                  className="tabular whitespace-nowrap px-3 py-2.5 text-right font-semibold"
                  title={metricTitle(
                    entry.metric,
                    page.metric_unit,
                    page.metric_label,
                  )}
                >
                  {formatMetric(entry.metric, page.metric_unit)}
                </td>

                {has.secondary && (
                  <Td
                    numeric
                    hideBelow="sm"
                    className="tabular text-[var(--color-foreground-muted)]"
                  >
                    {formatMetric(
                      entry.secondary_metric,
                      page.secondary_metric_unit ?? "millions",
                    )}
                  </Td>
                )}

                {has.category && (
                  <Td
                    hideBelow="xl"
                    className="text-[var(--color-foreground-muted)]"
                  >
                    {entry.category ?? "—"}
                  </Td>
                )}

                {has.country && (
                  <Td
                    hideBelow="md"
                    className="text-[var(--color-foreground-muted)]"
                  >
                    {entry.country ?? "—"}
                  </Td>
                )}

                <td className="py-2.5 pr-4 text-right">
                  {entry.profile_url && (
                    <Link
                      href={entry.profile_url}
                      target="_blank"
                      // `nofollow` on every one of these: a page of fifty outbound
                      // links to the largest accounts on the internet is exactly the
                      // shape a search engine reads as a link farm.
                      rel="noopener noreferrer nofollow"
                      aria-label={`Open ${entry.name} on ${page.platform_label}`}
                      className="inline-flex size-7 items-center justify-center rounded-[var(--radius-sm)] text-[var(--color-foreground-subtle)] transition-colors hover:bg-[var(--color-surface-raised)] hover:text-[var(--color-primary)]"
                    >
                      <ExternalLink className="size-3.5" aria-hidden="true" />
                    </Link>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

const HIDE = {
  sm: "hidden sm:table-cell",
  md: "hidden md:table-cell",
  lg: "hidden lg:table-cell",
  xl: "hidden xl:table-cell",
} as const;

function Th({
  children,
  numeric,
  hideBelow,
  className,
}: {
  children: React.ReactNode;
  numeric?: boolean;
  hideBelow?: keyof typeof HIDE;
  className?: string;
}) {
  return (
    <th
      scope="col"
      className={cn(
        "whitespace-nowrap px-3 py-2.5 font-mono text-[0.625rem] font-medium uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]",
        numeric ? "text-right" : "text-left",
        hideBelow && HIDE[hideBelow],
        className,
      )}
    >
      {children}
    </th>
  );
}

function Td({
  children,
  numeric,
  hideBelow,
  className,
}: {
  children: React.ReactNode;
  numeric?: boolean;
  hideBelow?: keyof typeof HIDE;
  className?: string;
}) {
  return (
    <td
      className={cn(
        "max-w-[16rem] truncate px-3 py-2.5",
        numeric ? "whitespace-nowrap text-right" : "",
        hideBelow && HIDE[hideBelow],
        className,
      )}
    >
      {children}
    </td>
  );
}

/**
 * The quiet second line under a row's name, or nothing at all.
 *
 * The handle where there is one; failing that, the description — but only when it
 * says something the line above did not.
 */
function subtitle(entry: TopRankingEntry): string | null {
  if (entry.handle) return `@${entry.handle}`;

  const headline = entry.owner ?? entry.name;

  return entry.description && entry.description !== headline
    ? entry.description
    : null;
}

function titleCase(value: string): string {
  return value.charAt(0).toUpperCase() + value.slice(1);
}
