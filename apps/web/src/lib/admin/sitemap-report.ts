import "server-only";

import type { MetadataRoute } from "next";

import { sitemapEntries } from "@/lib/sitemap-entries";
import { isHome, parseSitemap, pathOf, sectionOf } from "./sitemap-parse";
import type { SitemapEntry, SitemapIssue, SitemapReport, SitemapSection } from "./types";

/**
 * What `/sitemap.xml` is serving, next to what it should be serving.
 *
 * The interesting question about a sitemap is never "what does it contain" — it is
 * "how far behind is it". `/sitemap.xml` is a cached route: it is re-rendered on a
 * timer, so between one render and the next it describes a version of the site that
 * no longer exists. So the report fetches the file crawlers actually get and diffs
 * it against a fresh run of the same generator, and the difference is the answer.
 */

/** Matches `app/sitemap.ts`. Read by the screen to explain the automatic cadence. */
export const SITEMAP_REVALIDATE_SECONDS = 3600;

/** The sitemap protocol's own ceilings. Past either, crawlers stop reading. */
const MAX_URLS = 50_000;
const MAX_BYTES = 50 * 1024 * 1024;

/** Beyond this the file is old enough that the hourly render is clearly not running. */
const STALE_AFTER_SECONDS = SITEMAP_REVALIDATE_SECONDS * 2;

/** How many differing URLs are worth listing before the point is made. */
const MAX_DIFF_SHOWN = 50;

const SECTIONS: { key: string; label: string }[] = [
  { key: "home", label: "Home" },
  { key: "static", label: "Static pages" },
  { key: "tools", label: "Tool pages" },
  { key: "tool-filters", label: "Tool category filters" },
  { key: "blog", label: "Blog posts" },
  { key: "blog-filters", label: "Blog category filters" },
  { key: "changelog", label: "Changelog" },
];

/**
 * @param from    Origin to fetch the file from — this renderer, on loopback, so the
 *                report describes the cache the screen can actually refresh rather
 *                than whatever a CDN in front of it is holding.
 * @param publicOrigin  How that same file is addressed from outside. Only the
 *                report's `url` uses it: it is what the screen shows and links to,
 *                and a loopback address there would be neither.
 */
export async function buildSitemapReport(
  from: string,
  publicOrigin: string,
): Promise<SitemapReport> {
  const url = new URL("/sitemap.xml", publicOrigin).toString();

  // `no-store` is the whole point: this must read what a crawler would be handed,
  // not a copy this request happens to have cached.
  const response = await fetch(new URL("/sitemap.xml", from), {
    headers: { Accept: "application/xml" },
    cache: "no-store",
  });

  const xml = await response.text();
  const served = response.ok ? parseSitemap(xml) : [];

  // The same function `app/sitemap.ts` calls. Its own fetches are cached for five
  // minutes, so this is cheap; the refresh action clears those first.
  const expected = await sitemapEntries()
    .then(toEntries)
    .catch(() => null);

  const generatedAt = served.find((entry) => isHome(entry.path))?.lastmod ?? null;
  const ageSeconds =
    generatedAt === null
      ? null
      : Math.max(0, Math.round((Date.now() - Date.parse(generatedAt)) / 1000));

  const servedLocs = new Set(served.map((entry) => entry.loc));
  const expectedLocs = new Set((expected ?? []).map((entry) => entry.loc));

  const stale = expected === null ? [] : served.filter((e) => !expectedLocs.has(e.loc));
  const missing = expected === null ? [] : expected.filter((e) => !servedLocs.has(e.loc));

  const bytes = new TextEncoder().encode(xml).length;

  return {
    url,
    fetched_at: new Date().toISOString(),
    status: response.status,
    bytes,
    served_total: served.length,
    expected_total: expected?.length ?? 0,
    generated_at: generatedAt,
    age_seconds: ageSeconds,
    revalidate_seconds: SITEMAP_REVALIDATE_SECONDS,
    sections: countSections(served, expected ?? []),
    stale: stale.slice(0, MAX_DIFF_SHOWN).map((entry) => entry.path),
    missing: missing.slice(0, MAX_DIFF_SHOWN).map((entry) => entry.path),
    issues: findIssues({
      status: response.status,
      bytes,
      served: served.length,
      expected,
      ageSeconds,
      staleCount: stale.length,
      missingCount: missing.length,
    }),
    entries: served,
  };
}

function findIssues(input: {
  status: number;
  bytes: number;
  served: number;
  expected: SitemapEntry[] | null;
  ageSeconds: number | null;
  staleCount: number;
  missingCount: number;
}): SitemapIssue[] {
  const issues: SitemapIssue[] = [];

  if (input.status !== 200) {
    issues.push({
      level: "danger",
      message: `The site is answering ${input.status} at /sitemap.xml. Search engines have nothing to read.`,
    });

    // Everything below reasons about a file we did not get. Say the one true thing.
    return issues;
  }

  if (input.served === 0) {
    issues.push({
      level: "danger",
      message: "The sitemap is empty. That tells a crawler this site has no pages at all.",
    });
  }

  if (input.expected === null) {
    issues.push({
      level: "warning",
      message:
        "We could not reach the API to work out what the sitemap should contain, so the comparison below is unavailable. The served file is still shown.",
    });
  }

  if (input.missingCount > 0) {
    issues.push({
      level: "warning",
      message: `${plural(input.missingCount, "published URL is", "published URLs are")} missing from the served file. Refresh to publish ${input.missingCount === 1 ? "it" : "them"} now instead of waiting for the next hourly render.`,
    });
  }

  if (input.staleCount > 0) {
    issues.push({
      level: "warning",
      message: `${plural(input.staleCount, "URL is", "URLs are")} still listed but no longer generated — unpublished, hidden or renamed. Crawl budget is being spent on ${input.staleCount === 1 ? "it" : "them"}.`,
    });
  }

  if (input.ageSeconds !== null && input.ageSeconds > STALE_AFTER_SECONDS) {
    issues.push({
      level: "warning",
      message: `The served file was rendered ${formatAge(input.ageSeconds)} ago, well past its one-hour window. Nothing has requested it since, which is normal for a quiet site — the render happens on the first request after it expires, not on a timer of its own.`,
    });
  }

  if (input.served > MAX_URLS) {
    issues.push({
      level: "danger",
      message: `${input.served.toLocaleString()} URLs exceeds the 50,000 the sitemap format allows. It needs splitting into a sitemap index.`,
    });
  }

  if (input.bytes > MAX_BYTES) {
    issues.push({
      level: "danger",
      message: "The file is over the 50 MB limit and needs splitting into a sitemap index.",
    });
  }

  return issues;
}

/** `MetadataRoute.Sitemap` is the generator's shape; this is the report's. */
function toEntries(entries: MetadataRoute.Sitemap): SitemapEntry[] {
  return entries.map((entry) => ({
    loc: entry.url,
    path: pathOf(entry.url),
    lastmod:
      entry.lastModified === undefined
        ? null
        : new Date(entry.lastModified).toISOString(),
    changefreq: entry.changeFrequency ?? null,
    priority: entry.priority ?? null,
  }));
}

function countSections(served: SitemapEntry[], expected: SitemapEntry[]): SitemapSection[] {
  const tally = (entries: SitemapEntry[]): Map<string, number> => {
    const counts = new Map<string, number>();

    for (const entry of entries) {
      const key = sectionOf(entry.path);
      counts.set(key, (counts.get(key) ?? 0) + 1);
    }

    return counts;
  };

  const servedCounts = tally(served);
  const expectedCounts = tally(expected);

  return SECTIONS.map((section) => ({
    ...section,
    served: servedCounts.get(section.key) ?? 0,
    expected: expectedCounts.get(section.key) ?? 0,
    // A section that is empty in both is noise on the screen, not information.
  })).filter((section) => section.served > 0 || section.expected > 0);
}

function formatAge(seconds: number): string {
  if (seconds < 90) return `${seconds} seconds`;
  if (seconds < 5400) return `${Math.round(seconds / 60)} minutes`;
  if (seconds < 172_800) return `${Math.round(seconds / 3600)} hours`;

  return `${Math.round(seconds / 86_400)} days`;
}

function plural(count: number, one: string, many: string): string {
  return `${count} ${count === 1 ? one : many}`;
}
