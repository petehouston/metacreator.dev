import "server-only";

import { api } from "@/lib/api";
import type { RankingPlatformValue } from "@/lib/types";

/**
 * The header menu's ranking links.
 *
 * A trimmed shape rather than the full page objects: this crosses the server →
 * client boundary on every page of the site, and shipping nine intros and nine meta
 * descriptions into the RSC payload to render nine `<a>` elements is bytes spent on
 * nothing.
 */
export interface RankingNavItem {
  href: string;
  label: string;
  platform: RankingPlatformValue;
  platformLabel: string;
  accent: string;
  count: number;
}

/**
 * An empty list is the fallback, and it is the right one.
 *
 * Unlike the blog and billing switches — where the safe default is "on", because a
 * momentary settings failure must not give the paid catalog away — there is nothing
 * to protect here. If the API cannot be reached the menu simply does not appear,
 * which is better than a dropdown of links to pages we could not confirm exist.
 */
export async function rankingNav(): Promise<RankingNavItem[]> {
  const pages = await api.topRanking.list().catch(() => []);

  return pages.map((page) => ({
    href: `/top-ranking/${page.slug}`,
    label: page.title,
    platform: page.platform,
    platformLabel: page.platform_label,
    accent: page.platform_accent,
    count: page.entries_count,
  }));
}
