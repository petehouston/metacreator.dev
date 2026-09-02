import type { MetadataRoute } from "next";

import { sitemapEntries } from "@/lib/sitemap-entries";

/**
 * Regenerate hourly, rather than once per deploy.
 *
 * Without this the route is prerendered at build time and never rendered again:
 * the `revalidate` on each fetch in {@link sitemapEntries} governs the *data*
 * cache, and a static route that is never re-rendered never reads it. That is not
 * a theoretical staleness — posts are published from `marketing/blog` between
 * deploys, and tools are seeded during one *after* the web bundle has already been
 * built, so both arrive on a site whose sitemap still describes the previous
 * release.
 *
 * An hour is the right granularity for a file crawlers fetch daily at best. Admin
 * → Sitemap shows how old the served file actually is and can force it, and the
 * machine-to-machine route at `/api/revalidate` remains the way for a publish to
 * make itself visible immediately.
 */
export const revalidate = 3600;

/**
 * The URL list itself lives in `@/lib/sitemap-entries`, so the admin screen can
 * run the same generator to see what the file *should* contain right now.
 */
export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  return sitemapEntries();
}
