import { Link2 } from "lucide-react";
import Link from "next/link";

import { ChangeBadge } from "@/components/changelog/change-badge";
import { Badge } from "@/components/ui/badge";
import type { ChangelogRelease } from "@/lib/types";
import { cn } from "@/lib/utils";

/**
 * One release on the timeline.
 *
 * The date lives in a rail of its own rather than inside the card, because the
 * question a changelog is opened to answer is "what changed since I last looked" —
 * and that is answered by scanning dates, not titles. On a phone the rail folds
 * above the card, which keeps the same reading order.
 *
 * Entries render in the order the editor arranged them, not grouped by type. The
 * admin editor shows the same list in the same order, so what an editor arranges is
 * what ships — and the badges carry the scannability that grouping would.
 */
export function ReleaseEntry({
  release,
  standalone = false,
}: {
  release: ChangelogRelease;
  /**
   * Rendered on its own page rather than on the timeline: no dated rail beside the
   * card, no node, and the date sits above the title instead. Same component
   * because a release rendered by two components is a release that drifts.
   */
  standalone?: boolean;
}) {
  const date = release.released_at ? new Date(release.released_at) : null;

  return (
    <article
      id={release.slug}
      // `scroll-mt` keeps the heading clear of the sticky site header when someone
      // arrives on a permalink.
      className={cn(
        "group/entry relative scroll-mt-24",
        !standalone && "lg:grid lg:grid-cols-[11rem_1fr] lg:gap-10",
      )}
    >
      {/* The node on the timeline's spine, aligned with the rule the list draws at
          12.25rem less half the node's width. It hangs off the article rather than
          off the rail below, because the rail is `sticky` — a positioned element,
          and so would become this node's containing block and carry it away on
          scroll. Decorative, and only drawn at `lg` where the spine exists. */}
      {!standalone && (
        <span
          aria-hidden="true"
          className={cn(
            "absolute left-[11.875rem] top-1.5 hidden size-3 rounded-full ring-4 ring-[var(--color-background)] lg:block",
            release.is_major ? "bg-[var(--color-primary)]" : "bg-[var(--color-border-strong)]",
          )}
        />
      )}

      <ReleaseRail
        date={date}
        version={release.version}
        isMajor={release.is_major}
        standalone={standalone}
      />

      <div
        className={cn(
          "app-card relative mt-4 p-5 sm:p-6",
          !standalone && "lg:mt-0",
          // A major release is wider-feeling rather than louder: a tinted edge and
          // a little more air, instead of a second accent colour competing with the
          // six the badges already use.
          release.is_major &&
            "border-[var(--color-brand-500)]/25 bg-[var(--color-primary-subtle)]/25 sm:p-7",
        )}
      >
        <header className="flex flex-col gap-2">
          <h2 className="text-balance text-xl font-semibold leading-snug tracking-[-0.02em] text-[var(--color-foreground)] sm:text-2xl">
            <Link
              href={`/changelog/${release.slug}`}
              className="transition-colors hover:text-[var(--color-primary)]"
            >
              {release.title}
            </Link>

            {/* Revealed on hover of the entry, and always reachable by keyboard —
                a permalink nobody can tab to is not a permalink. */}
            <Link
              href={`/changelog#${release.slug}`}
              aria-label={`Permalink to ${release.title}`}
              className="ml-2 inline-flex translate-y-[0.1em] text-[var(--color-foreground-subtle)] opacity-0 transition-opacity hover:text-[var(--color-primary)] focus-visible:opacity-100 group-hover/entry:opacity-100"
            >
              <Link2 className="size-4" aria-hidden="true" />
            </Link>
          </h2>

          {release.summary && (
            <p className="max-w-2xl text-[0.9375rem] leading-relaxed text-[var(--color-foreground-muted)]">
              {release.summary}
            </p>
          )}
        </header>

        <ul className="mt-5 flex flex-col gap-px">
          {release.items.map((item) => (
            <li
              key={item.id}
              className="flex flex-col gap-1.5 border-t border-[var(--color-border-subtle)] py-3 first:border-t-0 first:pt-0 sm:flex-row sm:gap-4"
            >
              <ChangeBadge label={item.type_label} tone={item.tone} className="mt-0.5 self-start" />

              <div className="min-w-0 flex-1">
                <p className="text-[0.9375rem] font-medium leading-snug text-[var(--color-foreground)]">
                  {item.title}
                </p>

                {item.description && (
                  <p className="mt-1 text-sm leading-relaxed text-[var(--color-foreground-muted)]">
                    {item.description}
                  </p>
                )}
              </div>
            </li>
          ))}
        </ul>
      </div>
    </article>
  );
}

/**
 * The dated rail: the timeline's spine, its node, and the version label.
 */
function ReleaseRail({
  date,
  version,
  isMajor,
  standalone,
}: {
  date: Date | null;
  version: string | null;
  isMajor: boolean;
  standalone: boolean;
}) {
  return (
    <div
      className={cn(
        !standalone && "lg:sticky lg:top-24 lg:h-fit lg:pb-8 lg:text-right",
      )}
    >
      <div
        className={cn(
          "flex flex-wrap items-center gap-2",
          !standalone && "lg:flex-col lg:items-end lg:gap-1.5",
        )}
      >
        {date && (
          <time
            dateTime={date.toISOString()}
            className="text-sm font-semibold text-[var(--color-foreground)]"
          >
            {date.toLocaleDateString("en-GB", {
              day: "numeric",
              month: "short",
              year: "numeric",
            })}
          </time>
        )}

        {version && (
          <Badge variant="neutral" className="font-mono text-[0.6875rem]">
            {version}
          </Badge>
        )}

        {isMajor && (
          <Badge variant="brand" className="text-[0.6875rem]">
            Major release
          </Badge>
        )}
      </div>
    </div>
  );
}

export function ReleaseEntrySkeleton() {
  return (
    <div className="lg:grid lg:grid-cols-[11rem_1fr] lg:gap-10" aria-hidden="true">
      <div className="hidden lg:flex lg:flex-col lg:items-end lg:gap-2">
        <div className="h-4 w-24 animate-pulse rounded bg-[var(--color-surface-sunken)]" />
        <div className="h-5 w-14 animate-pulse rounded-full bg-[var(--color-surface-sunken)]" />
      </div>

      <div className="app-card flex flex-col gap-4 p-6">
        <div className="h-6 w-2/3 animate-pulse rounded bg-[var(--color-surface-sunken)]" />
        <div className="h-4 w-full animate-pulse rounded bg-[var(--color-surface-sunken)]" />
        {[0, 1, 2].map((row) => (
          <div key={row} className="flex gap-4">
            <div className="h-5 w-[5.5rem] shrink-0 animate-pulse rounded-full bg-[var(--color-surface-sunken)]" />
            <div className="h-5 flex-1 animate-pulse rounded bg-[var(--color-surface-sunken)]" />
          </div>
        ))}
      </div>
    </div>
  );
}
