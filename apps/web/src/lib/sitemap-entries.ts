import "server-only";

import type { MetadataRoute } from "next";

import { siteConfig } from "@/config/site";
import { api } from "@/lib/api";
import { siteFeatures } from "@/lib/site-settings";

/**
 * The one list of URLs this site claims to have.
 *
 * It lives here rather than inside `app/sitemap.ts` so that the admin's sitemap
 * screen can ask "what should be in the file right now?" by running the same code
 * that writes it. A second implementation of that question would be a second
 * opinion, and the whole value of the screen is that its answer is authoritative.
 *
 * Only indexable, 200-returning URLs belong here. A sitemap that lists a redirect
 * or a 404 actively costs crawl budget, so the tool list comes from the same
 * `public` scope the catalog uses — hidden and draft tools are never included.
 */

/** Nothing here should ever run away; a sitemap is bounded content, not a crawl. */
const MAX_PAGES = 20;

/**
 * Every page of a paginated endpoint, not just the first.
 *
 * Asking for `per_page: 100` is a *request*, not a guarantee: each controller caps
 * it, and the blog's cap is 24. Passing a large number and trusting it is how a
 * sitemap silently stops listing most of the site the moment the catalogue outgrows
 * one page — which is exactly what happened here, with the posts past the first
 * twenty-four absent and nothing failing to say so.
 *
 * The first response is what tells us how many pages exist, so the rest are fetched
 * together rather than in a chain of awaits.
 */
async function allPages<T>(
  fetchPage: (page: number) => Promise<{ data: T[]; meta: { page: { last_page: number } } }>,
): Promise<T[]> {
  try {
    const first = await fetchPage(1);
    const pages = Math.min(first.meta?.page?.last_page ?? 1, MAX_PAGES);

    if (pages <= 1) return first.data;

    const rest = await Promise.all(
      Array.from({ length: pages - 1 }, (_, index) => fetchPage(index + 2).then((r) => r.data)),
    );

    return [...first.data, ...rest.flat()];
  } catch {
    // Same reasoning as the callers below: a failing API must cost this one list,
    // never the whole sitemap.
    return [];
  }
}

export async function sitemapEntries(): Promise<MetadataRoute.Sitemap> {
  const base = siteConfig.url;

  const now = new Date();

  // `/pricing` 404s while billing is off, and a sitemap that lists a 404 costs
  // crawl budget — the same reason the blog URLs are dropped when the blog is.
  const { billingEnabled } = await siteFeatures();

  const staticRoutes: MetadataRoute.Sitemap = [
    { url: base, changeFrequency: "weekly", priority: 1, lastModified: now },
    { url: `${base}/tools`, changeFrequency: "daily", priority: 0.9, lastModified: now },
    ...(billingEnabled
      ? [
          {
            url: `${base}/pricing`,
            changeFrequency: "monthly" as const,
            priority: 0.8,
            lastModified: now,
          },
        ]
      : []),
    { url: `${base}/about`, changeFrequency: "monthly", priority: 0.5, lastModified: now },
    { url: `${base}/contact`, changeFrequency: "yearly", priority: 0.4, lastModified: now },
    { url: `${base}/terms`, changeFrequency: "yearly", priority: 0.2, lastModified: now },
    { url: `${base}/privacy`, changeFrequency: "yearly", priority: 0.2, lastModified: now },
  ];

  // A failing API must not produce an empty sitemap — serving the static routes is
  // far better than telling a crawler the site has no pages. Each call fails
  // independently for the same reason: when an admin switches the blog off the API
  // 404s, and that should cost the blog URLs, not the tool catalog with them.
  const [tools, categories, posts, postCategories, releases, rankings] = await Promise.all([
    allPages((page) => api.tools.list({ per_page: 100, page })),
    api.tools.categories().catch(() => []),
    allPages((page) => api.blog.list({ per_page: 100, page })),
    api.blog.categories().catch(() => []),
    allPages((page) => api.changelog.list({ per_page: 100, page })),
    // Not paginated by the API — the index is what draws the site's header menu, so
    // it is always the whole list.
    api.topRanking.list().catch(() => []),
  ]);

  const blogRoutes: MetadataRoute.Sitemap = posts.length > 0
    ? [{ url: `${base}/blog`, changeFrequency: "daily", priority: 0.8, lastModified: now }]
    : [];

  // An empty changelog is a thin page, so the index only lists it once something
  // has shipped — and its `lastModified` is the newest release rather than `now`,
  // which is the honest signal a crawler can act on.
  const changelogRoutes: MetadataRoute.Sitemap = releases.length > 0
    ? [
        {
          url: `${base}/changelog`,
          changeFrequency: "weekly" as const,
          priority: 0.6,
          lastModified: releases[0].released_at ? new Date(releases[0].released_at) : now,
        },
        ...releases.map((release) => ({
          url: `${base}/changelog/${release.slug}`,
          changeFrequency: "yearly" as const,
          // A shipped release note does not change again; it is worth indexing, but
          // it is not competing with the tool pages for crawl budget.
          priority: 0.4,
          lastModified: release.released_at ? new Date(release.released_at) : now,
        })),
      ]
    : [];

  // The rankings are among the most indexable pages on the site: stable URLs,
  // substantial content, and a genuine weekly change. `lastModified` is the sync
  // date rather than `now`, which is the only signal here a crawler can act on.
  const rankingRoutes: MetadataRoute.Sitemap = rankings.length > 0
    ? [
        {
          url: `${base}/top-ranking`,
          changeFrequency: "weekly" as const,
          priority: 0.8,
          lastModified: now,
        },
        // A no-index ranking in the sitemap is a contradictory signal: it asks a
        // crawler to spend budget on a URL the page then tells it not to index.
        // The tool list is filtered on the same principle.
        ...rankings
          .filter((ranking) => !(ranking.seo?.robots ?? "").includes("noindex"))
          .map((ranking) => ({
            url: `${base}/top-ranking/${ranking.slug}`,
            changeFrequency: "weekly" as const,
            priority: 0.7,
            lastModified: ranking.synced_at ? new Date(ranking.synced_at) : now,
          })),
      ]
    : [];

  return [
    ...staticRoutes,
    ...blogRoutes,
    ...changelogRoutes,
    ...rankingRoutes,
    ...categories.map((category) => ({
      url: `${base}/tools?category=${category.slug}`,
      changeFrequency: "weekly" as const,
      priority: 0.7,
      lastModified: new Date(),
    })),
    // A no-index tool in the sitemap is a contradictory signal: it asks a crawler
    // to spend budget on a URL the page itself then tells it not to index.
    ...tools
      .filter((tool) => tool.is_indexable !== false)
      .map((tool) => ({
        url: `${base}/tools/${tool.slug}`,
        changeFrequency: "weekly" as const,
        priority: tool.is_featured ? 0.9 : 0.8,
        lastModified: new Date(),
      })),
    ...postCategories.map((category) => ({
      url: `${base}/blog?category=${category.slug}`,
      changeFrequency: "weekly" as const,
      priority: 0.6,
      lastModified: new Date(),
    })),
    ...posts.map((post) => ({
      url: `${base}/blog/${post.slug}`,
      changeFrequency: "monthly" as const,
      priority: post.is_featured ? 0.8 : 0.7,
      // The publish date is the honest signal here; a crawler that sees `now` on
      // every URL learns nothing about what actually changed.
      lastModified: post.published_at ? new Date(post.published_at) : new Date(),
    })),
  ];
}
