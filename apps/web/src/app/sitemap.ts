import type { MetadataRoute } from "next";

import { siteConfig } from "@/config/site";
import { api } from "@/lib/api";
import { siteFeatures } from "@/lib/site-settings";

/**
 * Only indexable, 200-returning URLs belong here.
 *
 * A sitemap that lists a redirect or a 404 actively costs crawl budget, so the tool
 * list comes from the same `public` scope the catalog uses — hidden and draft tools
 * are never included.
 */
export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
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
  const [tools, categories, posts, postCategories, releases] = await Promise.all([
    api.tools.list({ per_page: 100 }).then((r) => r.data).catch(() => []),
    api.tools.categories().catch(() => []),
    api.blog.list({ per_page: 100 }).then((r) => r.data).catch(() => []),
    api.blog.categories().catch(() => []),
    api.changelog.list({ per_page: 100 }).then((r) => r.data).catch(() => []),
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

  return [
    ...staticRoutes,
    ...blogRoutes,
    ...changelogRoutes,
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
