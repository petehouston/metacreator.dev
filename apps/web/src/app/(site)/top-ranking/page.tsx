import type { Metadata } from "next";
import Link from "next/link";

import { siteConfig } from "@/config/site";
import { api } from "@/lib/api";
import { freshness } from "@/lib/ranking-format";
import type { TopRankingPage } from "@/lib/types";

export const metadata: Metadata = {
  title: "Top Rankings — The biggest accounts on every network",
  description:
    "The most-followed accounts, channels and pages across YouTube, Instagram, TikTok, X, Facebook, Twitch and Bluesky. Sourced from Wikipedia and refreshed every week.",
  alternates: { canonical: "/top-ranking" },
  openGraph: {
    title: `Top Rankings | ${siteConfig.name}`,
    description:
      "The biggest accounts on every major social network, ranked and refreshed weekly.",
    url: `${siteConfig.url}/top-ranking`,
    type: "website",
  },
};

export default async function TopRankingIndexPage() {
  const pages = await api.topRanking.list().catch(() => []);

  // Grouped by network so the index reads as "what we cover" rather than as one
  // undifferentiated list — the two YouTube rankings and the two Twitch ones
  // belong beside each other, and sorting alone would not put them there.
  const groups = new Map<string, TopRankingPage[]>();

  for (const page of pages) {
    groups.set(page.platform_label, [
      ...(groups.get(page.platform_label) ?? []),
      page,
    ]);
  }

  return (
    <div className="relative">
      <div
        aria-hidden="true"
        className="bg-aurora pointer-events-none absolute inset-x-0 top-0 h-[28rem]"
      />

      <div className="relative mx-auto w-full max-w-[75rem] px-4 py-12 sm:px-6 lg:py-16">
        <header className="flex max-w-3xl flex-col gap-3">
          <p className="eyebrow">Reference</p>

          <h1 className="text-heading-1 text-balance sm:text-display-lg">
            Top <span className="text-gradient">rankings</span>
          </h1>

          <p className="text-body-lg text-[var(--color-foreground-muted)]">
            Who is actually the biggest, on each network, right now. Every list
            below is transcribed from Wikipedia&apos;s community-maintained
            rankings and refreshed automatically each week — with the account
            pictures pulled live from the platforms themselves.
          </p>
        </header>

        {pages.length === 0 ? (
          <p className="mt-16 rounded-[var(--radius-lg)] border border-[var(--color-border)] p-6 text-sm text-[var(--color-foreground-muted)]">
            The rankings are being prepared. Check back shortly.
          </p>
        ) : (
          <div className="mt-12 flex flex-col gap-10">
            {[...groups.entries()].map(([platform, group]) => (
              <section key={platform}>
                <h2 className="eyebrow mb-4">{platform}</h2>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                  {group.map((page) => (
                    <RankingCard key={page.slug} page={page} />
                  ))}
                </div>
              </section>
            ))}
          </div>
        )}

        <p className="mt-14 max-w-3xl text-xs leading-relaxed text-[var(--color-foreground-subtle)]">
          Rankings are derived from Wikipedia and available under{" "}
          <a
            href="https://creativecommons.org/licenses/by-sa/4.0/"
            target="_blank"
            rel="noopener noreferrer"
            className="underline hover:text-[var(--color-foreground-muted)]"
          >
            CC BY-SA 4.0
          </a>
          . Follower counts are approximate and change constantly; each page
          links to the article it was built from.
        </p>
      </div>
    </div>
  );
}

function RankingCard({ page }: { page: TopRankingPage }) {
  const updated = freshness(page.synced_at);

  return (
    <Link
      href={`/top-ranking/${page.slug}`}
      className="panel panel-lift group relative flex flex-col gap-3 overflow-hidden p-5"
      style={{
        backgroundImage: `radial-gradient(18rem 9rem at 100% 0%, oklch(${page.platform_accent} / 0.13), transparent 70%)`,
      }}
    >
      <div className="flex items-center gap-2">
        <span
          aria-hidden="true"
          className="size-2 rounded-full"
          style={{ backgroundColor: `oklch(${page.platform_accent})` }}
        />
        <span className="font-mono text-[0.625rem] font-medium uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
          {page.platform_label}
        </span>
      </div>

      <h3 className="text-heading-3 leading-snug text-balance transition-colors group-hover:text-[var(--color-primary)]">
        {page.title}
      </h3>

      {page.intro && (
        <p className="line-clamp-3 text-sm leading-relaxed text-[var(--color-foreground-muted)]">
          {page.intro}
        </p>
      )}

      <p className="mt-auto flex flex-wrap items-center gap-x-2 gap-y-1 pt-2 text-xs text-[var(--color-foreground-subtle)]">
        <span className="tabular font-medium text-[var(--color-foreground-muted)]">
          {page.entries_count} {page.noun}
          {page.entries_count === 1 ? "" : "s"}
        </span>

        {updated && (
          <>
            <span aria-hidden="true">·</span>
            <span>{updated}</span>
          </>
        )}
      </p>
    </Link>
  );
}
