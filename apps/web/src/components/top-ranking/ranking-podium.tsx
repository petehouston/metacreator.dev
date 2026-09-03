import Link from "next/link";

import { RankingAvatar } from "@/components/top-ranking/ranking-avatar";
import { formatMetric, metricTitle } from "@/lib/ranking-format";
import type { TopRankingEntry, TopRankingPage } from "@/lib/types";
import { cn } from "@/lib/utils";

/**
 * The top three, lifted out of the table.
 *
 * A ranking's whole point is the shape of its head, and a reader who has to scan
 * three rows of a fifty-row table to find it has been given a spreadsheet rather
 * than an answer. The three cards are the answer; the table below is the evidence.
 *
 * Laid out 2–1–3 at desktop width, so the leader is centred and tallest and the
 * silhouette reads as a podium before a single word is. That arrangement is CSS
 * `order`, not markup order — below `sm` the cards stack in the sequence they are
 * written, 1, 2, 3, because a "podium" one card wide is just three cards, and
 * showing the runner-up above the winner is the ranking told wrong.
 */
export function RankingPodium({
  page,
  entries,
}: {
  page: TopRankingPage;
  entries: TopRankingEntry[];
}) {
  if (entries.length < 3) return null;

  const [first, second, third] = entries;

  // Document order is 1, 2, 3 — always. The podium arrangement is produced by
  // `order` at `sm` and above, which is where it exists at all. Reversing the
  // markup instead would put the runner-up first on a phone and first for a screen
  // reader, which is the ranking told wrong in the two places it is read linearly.
  const podium: { entry: TopRankingEntry; order: string; lift: string }[] = [
    { entry: first, order: "sm:order-2", lift: "" },
    { entry: second, order: "sm:order-1", lift: "sm:mt-8" },
    { entry: third, order: "sm:order-3", lift: "sm:mt-12" },
  ];

  return (
    <div className="mt-10 grid grid-cols-1 gap-4 sm:grid-cols-3 sm:items-end">
      {podium.map(({ entry, order, lift }) => (
        <PodiumCard
          key={entry.rank}
          page={page}
          entry={entry}
          className={cn(order, lift)}
        />
      ))}
    </div>
  );
}

function PodiumCard({
  page,
  entry,
  className,
}: {
  page: TopRankingPage;
  entry: TopRankingEntry;
  className?: string;
}) {
  const leader = entry.rank === 1;

  const card = (
    <article
      className="panel panel-lift relative flex h-full flex-col items-center gap-3 overflow-hidden px-5 pb-6 pt-8 text-center"
      style={{
        // A wash of the platform's colour rather than a border in it: at this size
        // a coloured outline reads as an alert, and a wash reads as a brand.
        backgroundImage: `radial-gradient(20rem 10rem at 50% -10%, oklch(${page.platform_accent} / ${leader ? 0.2 : 0.11}), transparent 70%)`,
      }}
    >
      {/* The rank, set large and faint behind the card's own content — legible as
          a number, quiet enough not to compete with the name. */}
      <span
        aria-hidden="true"
        className="tabular pointer-events-none absolute -right-1 -top-3 text-[4.5rem] font-bold leading-none opacity-[0.07]"
        style={{ color: `oklch(${page.platform_accent})` }}
      >
        {entry.rank}
      </span>

      <RankingAvatar
        src={entry.avatar_url}
        initials={entry.initials}
        accent={page.platform_accent}
        size="lg"
      />

      <div className="min-w-0 max-w-full">
        <h3 className="truncate text-heading-3 leading-tight">
          {entry.owner ?? entry.name}
        </h3>

        {entry.handle && (
          <p className="truncate font-mono text-xs text-[var(--color-foreground-subtle)]">
            @{entry.handle}
          </p>
        )}
      </div>

      <p className="mt-auto flex flex-col items-center gap-0.5">
        <span
          className={cn(
            "tabular font-bold leading-none",
            leader ? "text-[3rem] sm:text-display-lg" : "text-heading-1",
          )}
          title={metricTitle(entry.metric, page.metric_unit, page.metric_label)}
        >
          {formatMetric(entry.metric, page.metric_unit)}
        </span>

        <span className="eyebrow">{page.metric_label}</span>
      </p>
    </article>
  );

  // Linked only where the source published a profile address. Half of the Facebook
  // Pages list has none, and a card that looks clickable and is not is worse than
  // one that plainly is not.
  if (entry.profile_url === null) {
    return <div className={className}>{card}</div>;
  }

  return (
    <Link
      href={entry.profile_url}
      target="_blank"
      rel="noopener noreferrer nofollow"
      className={cn(
        "block rounded-[var(--radius-lg)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-ring)]",
        className,
      )}
    >
      {card}
    </Link>
  );
}
