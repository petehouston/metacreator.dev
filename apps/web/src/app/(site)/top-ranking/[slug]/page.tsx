import { ArrowLeft, RefreshCw } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";

import { RankingPodium } from "@/components/top-ranking/ranking-podium";
import { RankingTable } from "@/components/top-ranking/ranking-table";
import { siteConfig } from "@/config/site";
import { api } from "@/lib/api";
import { freshness, metricAsCount } from "@/lib/ranking-format";
import type { TopRankingPage } from "@/lib/types";

/**
 * One ranking, in full.
 *
 * Every page in this route shares a single structure by design — hero, podium,
 * table, provenance — so that a reader who has understood one has understood all
 * nine, and so that the nine differ only in the ways the data actually differs
 * (which columns exist, what the metric is called). The variation lives in
 * {@see RankingTable}, not here.
 */

async function loadPage(slug: string): Promise<TopRankingPage | null> {
  return api.topRanking.get(slug).catch(() => null);
}

/**
 * Pre-rendered at build time, one path per published ranking.
 *
 * These are the site's most cacheable pages: the content changes once a week, on a
 * schedule we own, and the set of them changes only when an admin adds a page.
 */
export async function generateStaticParams() {
  const pages = await api.topRanking.list().catch(() => []);

  return pages.map((page) => ({ slug: page.slug }));
}

export async function generateMetadata({
  params,
}: PageProps<"/top-ranking/[slug]">): Promise<Metadata> {
  const { slug } = await params;
  const page = await loadPage(slug);

  if (page === null) return { title: "Ranking not found" };

  const seo = page.seo;

  const title = seo?.title || page.title;
  const description =
    seo?.description || page.intro || `The ${page.title.toLowerCase()}, refreshed weekly.`;

  const socialTitle = seo?.og_title || title;
  const socialDescription = seo?.og_description || description;
  const image = seo?.og_image_url || `${siteConfig.url}/opengraph-image`;

  const robots = seo?.robots ?? "index,follow";
  const canonical = seo?.canonical_url || `/top-ranking/${page.slug}`;

  return {
    title,
    description,
    keywords: seo?.focus_keyword ? [seo.focus_keyword] : undefined,
    alternates: { canonical },
    // A page set to no-index is still reachable; it just stops competing in
    // search. The sitemap reads the same flag, so the two never disagree.
    robots: {
      index: !robots.includes("noindex"),
      follow: !robots.includes("nofollow"),
    },
    openGraph: {
      title: socialTitle,
      description: socialDescription,
      url: `${siteConfig.url}/top-ranking/${page.slug}`,
      type: "website",
      images: [{ url: image, width: 1200, height: 630 }],
    },
    twitter: {
      card: seo?.twitter_card === "summary" ? "summary" : "summary_large_image",
      title: socialTitle,
      description: socialDescription,
      images: [image],
    },
  };
}

export default async function TopRankingDetailPage({
  params,
}: PageProps<"/top-ranking/[slug]">) {
  const { slug } = await params;
  const page = await loadPage(slug);

  if (page === null) notFound();

  const entries = page.entries ?? [];
  const updated = freshness(page.synced_at);

  return (
    <div className="relative">
      <div
        aria-hidden="true"
        className="bg-aurora pointer-events-none absolute inset-x-0 top-0 h-[30rem]"
      />

      <div className="relative mx-auto w-full max-w-[75rem] px-4 py-10 sm:px-6 lg:py-14">
        <Link
          href="/top-ranking"
          className="inline-flex items-center gap-1.5 text-sm text-[var(--color-foreground-muted)] transition-colors hover:text-[var(--color-foreground)]"
        >
          <ArrowLeft className="size-3.5" aria-hidden="true" />
          All rankings
        </Link>

        <header className="mt-6 flex max-w-3xl flex-col gap-3">
          <p className="eyebrow">
            <span
              aria-hidden="true"
              className="size-2 rounded-full"
              style={{ backgroundColor: `oklch(${page.platform_accent})` }}
            />
            {page.platform_label}
          </p>

          <h1 className="text-heading-1 text-balance sm:text-display-lg">
            {page.title}
          </h1>

          {page.intro && (
            <p className="text-body-lg text-[var(--color-foreground-muted)]">
              {page.intro}
            </p>
          )}

          <dl className="mt-2 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-[var(--color-foreground-subtle)]">
            <div className="flex items-center gap-1.5">
              <dt className="sr-only">Entries</dt>
              <dd className="tabular font-medium text-[var(--color-foreground-muted)]">
                {entries.length} {page.noun}
                {entries.length === 1 ? "" : "s"}
              </dd>
            </div>

            {updated && (
              <div className="flex items-center gap-1.5">
                <RefreshCw className="size-3.5" aria-hidden="true" />
                <dt className="sr-only">Last refreshed</dt>
                <dd>{updated}</dd>
              </div>
            )}

            <div className="flex items-center gap-1.5">
              <dt className="sr-only">Source</dt>
              <dd>
                Source:{" "}
                <a
                  href={page.source_url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="underline decoration-dotted underline-offset-2 hover:text-[var(--color-foreground)]"
                >
                  Wikipedia
                </a>
              </dd>
            </div>
          </dl>
        </header>

        <RankingPodium page={page} entries={entries} />

        {/* The podium already showed the first three; repeating them at the top of
            the table makes the reader check whether they are the same rows. */}
        <RankingTable
          page={page}
          entries={entries}
          offset={entries.length >= 3 ? 3 : 0}
        />

        <footer className="mt-8 flex flex-col gap-2 border-t border-[var(--color-border-subtle)] pt-6 text-xs leading-relaxed text-[var(--color-foreground-subtle)]">
          <p>
            Figures transcribed from{" "}
            <a
              href={page.source_url}
              target="_blank"
              rel="noopener noreferrer"
              className="underline hover:text-[var(--color-foreground-muted)]"
            >
              “{page.source_page}”
            </a>{" "}
            on Wikipedia, available under{" "}
            <a
              href="https://creativecommons.org/licenses/by-sa/4.0/"
              target="_blank"
              rel="noopener noreferrer"
              className="underline hover:text-[var(--color-foreground-muted)]"
            >
              CC BY-SA 4.0
            </a>
            . Counts are approximate and were current when the article was last
            edited. Profile pictures are served by {page.platform_label} and
            belong to their respective owners.
          </p>
        </footer>
      </div>

      <RankingJsonLd page={page} />
    </div>
  );
}

/**
 * `ItemList` structured data for the ranking.
 *
 * A ranked list is one of the few things schema.org models exactly, and this page
 * is the archetype: numbered positions, a stable name per item, an external URL.
 * The counts are emitted as full integers rather than as the "515M" on screen,
 * because the abbreviation is a reading convenience and a crawler gains nothing
 * from parsing it back.
 *
 * Capped at 50 items. Beyond that the payload starts to rival the page itself, and
 * the tail of a hundred-row list is not what any result would surface.
 */
function RankingJsonLd({ page }: { page: TopRankingPage }) {
  const entries = (page.entries ?? []).slice(0, 50);

  const data = {
    "@context": "https://schema.org",
    "@type": "ItemList",
    name: page.seo?.title ?? page.title,
    description: page.seo?.description ?? page.intro ?? undefined,
    url: `${siteConfig.url}/top-ranking/${page.slug}`,
    numberOfItems: page.entries?.length ?? 0,
    itemListOrder: "https://schema.org/ItemListOrderDescending",
    itemListElement: entries.map((entry) => ({
      "@type": "ListItem",
      position: entry.rank,
      name: entry.owner ?? entry.name,
      url: entry.profile_url ?? undefined,
      description: entry.description ?? undefined,
      ...(metricAsCount(entry.metric, page.metric_unit) !== null
        ? {
            item: {
              "@type": "Organization",
              name: entry.owner ?? entry.name,
              url: entry.profile_url ?? undefined,
              interactionStatistic: {
                "@type": "InteractionCounter",
                interactionType: "https://schema.org/FollowAction",
                userInteractionCount: metricAsCount(
                  entry.metric,
                  page.metric_unit,
                ),
              },
            },
          }
        : {}),
    })),
  };

  return (
    <script
      type="application/ld+json"
      // The payload is built here from typed API data, never from user input.
      dangerouslySetInnerHTML={{ __html: JSON.stringify(data) }}
    />
  );
}
