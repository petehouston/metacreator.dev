import "server-only";

import { api } from "@/lib/api";
import type { ToolDetail, ToolSummary } from "@/lib/types";

/**
 * The "where do I go next" data for a tool page.
 *
 * Two different jobs, deliberately kept apart:
 *
 *   · `sameCategory` — *rotating*. Someone who came for a hashtag generator should
 *     meet a different neighbour on their second visit, otherwise the catalog looks
 *     four tools deep no matter how big it gets.
 *   · `elsewhere` — *popular*. The catalog's own best work, from categories this
 *     visitor has not seen, ordered by the API's featured/run-count ranking.
 *
 * Both are best-effort: a tool page must render even when the catalog request
 * fails, because the tool itself still works.
 */
export interface ToolDiscovery {
  sameCategory: ToolSummary[];
  elsewhere: ToolSummary[];
}

export async function discoverTools(
  tool: Pick<ToolDetail, "slug" | "category">,
  { perSection = 4 }: { perSection?: number } = {},
): Promise<ToolDiscovery> {
  const categorySlug = tool.category?.slug;

  const [inCategory, popular] = await Promise.all([
    categorySlug
      ? api.tools
          .list({ category: categorySlug, per_page: 24 })
          .then((response) => response.data)
          .catch(() => [])
      : Promise.resolve<ToolSummary[]>([]),
    api.tools
      .list({ per_page: 24 })
      .then((response) => response.data)
      .catch(() => []),
  ]);

  const sameCategory = rotate(
    inCategory.filter((candidate) => candidate.slug !== tool.slug),
    tool.slug,
  ).slice(0, perSection);

  const shown = new Set([tool.slug, ...sameCategory.map((item) => item.slug)]);

  const elsewhere = popular
    .filter((candidate) => !shown.has(candidate.slug))
    // "Other categories" is the point of the section — a second helping of the
    // category above would just be the same shelf twice.
    .filter((candidate) => !categorySlug || candidate.category?.slug !== categorySlug)
    .slice(0, perSection);

  // A catalog with only one populated category would otherwise leave this section
  // empty; falling back to anything unseen is better than a gap on the page.
  if (elsewhere.length === 0) {
    return {
      sameCategory,
      elsewhere: popular.filter((candidate) => !shown.has(candidate.slug)).slice(0, perSection),
    };
  }

  return { sameCategory, elsewhere };
}

/**
 * A deterministic rotation, seeded on the tool slug and the current hour.
 *
 * Not `Math.random()`: the page is cached for five minutes, and a random order
 * would mean the selection depends on which request happened to miss the cache.
 * An hour-bucketed seed rotates the shelf on a schedule instead — the visitor sees
 * something new tomorrow, and the cache still does its job today.
 */
function rotate<T>(items: T[], seed: string): T[] {
  if (items.length < 2) return items;

  const hourBucket = Math.floor(Date.now() / 3_600_000);
  let state = hash(`${seed}:${hourBucket}`);

  const shuffled = [...items];

  // Fisher–Yates driven by a small xorshift, so the order is a pure function of
  // the seed rather than of request timing.
  for (let index = shuffled.length - 1; index > 0; index -= 1) {
    state ^= state << 13;
    state ^= state >>> 17;
    state ^= state << 5;

    const pick = Math.abs(state) % (index + 1);
    [shuffled[index], shuffled[pick]] = [shuffled[pick], shuffled[index]];
  }

  return shuffled;
}

function hash(value: string): number {
  let result = 0x811c9dc5;

  for (let index = 0; index < value.length; index += 1) {
    result ^= value.charCodeAt(index);
    result = Math.imul(result, 0x01000193);
  }

  // Never zero: xorshift is a fixed point at zero and would return the input order.
  return result || 1;
}

/**
 * The tool shelf under an article.
 *
 * An article that mentions a tool has already earned the click for it, so those
 * come first and in the order the writer used them. The rest is topped up from the
 * catalog's own popularity ranking — the point of the shelf is that a reader who
 * just finished 1,200 words on hashtags leaves with something to *do*, and an empty
 * grid does that job worse than a merely-popular one.
 */
export async function toolsForReader(
  { prefer = [], limit = 6 }: { prefer?: ToolSummary[]; limit?: number } = {},
): Promise<ToolSummary[]> {
  const picks: ToolSummary[] = [];
  const seen = new Set<string>();

  for (const tool of prefer) {
    if (seen.has(tool.slug)) continue;
    seen.add(tool.slug);
    picks.push(tool);
  }

  if (picks.length >= limit) return picks.slice(0, limit);

  const popular = await api.tools
    .list({ per_page: 24 })
    .then((response) => response.data)
    .catch(() => []);

  for (const tool of popular) {
    if (picks.length >= limit) break;
    if (seen.has(tool.slug)) continue;
    seen.add(tool.slug);
    picks.push(tool);
  }

  return picks;
}
