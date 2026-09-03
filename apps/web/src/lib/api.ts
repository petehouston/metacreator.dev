import "server-only";

import type {
  ChangelogMeta,
  ChangelogRelease,
  Entitlements,
  Paginated,
  PostCategory,
  PostDetail,
  PostSummary,
  PostTag,
  ToolCategory,
  ToolDetail,
  ToolSummary,
  TopRankingPage,
} from "./types";

/**
 * Server-side API client.
 *
 * Server Components must call the API over the internal Docker/host network, while
 * the browser must use the public URL — getting this backwards is the single most
 * common way to break the local stack, so it is resolved in one place.
 */
const INTERNAL_URL = process.env.API_INTERNAL_URL ?? "http://localhost:8080";

export class ApiRequestError extends Error {
  constructor(
    readonly status: number,
    readonly code: string,
    message: string,
  ) {
    super(message);
    this.name = "ApiRequestError";
  }
}

interface RequestOptions {
  /** Cache tags, so a CMS publish can revalidate exactly the affected pages. */
  tags?: string[];
  /** Seconds. Omit for the route's default; 0 disables caching for this request. */
  revalidate?: number | false;
  searchParams?: Record<string, string | number | boolean | undefined>;
  headers?: HeadersInit;
}

async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const url = new URL(`/api/v1${path}`, INTERNAL_URL);

  for (const [key, value] of Object.entries(options.searchParams ?? {})) {
    if (value !== undefined && value !== "") {
      url.searchParams.set(key, String(value));
    }
  }

  const response = await fetch(url, {
    headers: { Accept: "application/json", ...options.headers },
    next: {
      tags: options.tags,
      ...(options.revalidate !== undefined ? { revalidate: options.revalidate } : {}),
    },
  });

  if (!response.ok) {
    // The API always returns the same error envelope; fall back only if the
    // response is not JSON at all (a proxy error page, for instance).
    const payload = await response.json().catch(() => null);

    throw new ApiRequestError(
      response.status,
      payload?.error?.code ?? "http.error",
      payload?.error?.message ?? `Request to ${path} failed (${response.status}).`,
    );
  }

  return response.json() as Promise<T>;
}

export const api = {
  tools: {
    list: (params: {
      q?: string;
      category?: string;
      platform?: string;
      tier?: string;
      page?: number;
      per_page?: number;
    } = {}) =>
      request<Paginated<ToolSummary>>("/catalog/tools", {
        searchParams: {
          q: params.q,
          "filter[category]": params.category,
          "filter[platform]": params.platform,
          "filter[tier]": params.tier,
          page: params.page,
          per_page: params.per_page,
        },
        tags: ["tools"],
        revalidate: 300,
      }),

    get: (slug: string) =>
      request<{ data: ToolDetail }>(`/catalog/tools/${slug}`, {
        tags: ["tools", `tool:${slug}`],
        revalidate: 300,
      }).then((r) => r.data),

    categories: () =>
      request<{ data: ToolCategory[] }>("/catalog/categories", {
        tags: ["tools", "tool-categories"],
        revalidate: 3600,
      }).then((r) => r.data),
  },

  /**
   * Public site settings, as a flat map.
   *
   * Only the rows an admin marked public and that are not encrypted — the filter
   * lives on the API so "which settings are safe to publish" is answered once.
   * Cached for five minutes, and tagged so a settings save can revalidate it.
   */
  settings: () =>
    request<{ data: Record<string, unknown> }>("/settings", {
      tags: ["settings"],
      revalidate: 300,
    }).then((r) => r.data),

  changelog: {
    list: (params: {
      q?: string;
      type?: string;
      year?: number;
      page?: number;
      per_page?: number;
    } = {}) =>
      request<Paginated<ChangelogRelease>>("/changelog", {
        searchParams: {
          q: params.q,
          "filter[type]": params.type,
          "filter[year]": params.year,
          page: params.page,
          per_page: params.per_page,
        },
        tags: ["changelog"],
        revalidate: 300,
      }),

    get: (slug: string) =>
      request<{ data: ChangelogRelease }>(`/changelog/${slug}`, {
        tags: ["changelog", `release:${slug}`],
        revalidate: 300,
      }).then((r) => r.data),

    /** The filter chips the page can offer — change types, and the years with releases. */
    meta: () =>
      request<{ data: ChangelogMeta }>("/changelog/meta", {
        tags: ["changelog"],
        revalidate: 3600,
      }).then((r) => r.data),
  },

  topRanking: {
    /**
     * Every published ranking, without its rows.
     *
     * Cached for an hour and tagged, because this is what draws the header menu on
     * every page of the site — a per-request fetch would put one API call in front
     * of every navigation for a list that changes when an admin adds a page.
     */
    list: () =>
      request<{ data: TopRankingPage[] }>("/top-ranking", {
        tags: ["top-ranking"],
        revalidate: 3600,
      }).then((r) => r.data),

    get: (slug: string) =>
      request<{ data: TopRankingPage }>(`/top-ranking/${slug}`, {
        // Tagged per page, so a single admin sync revalidates that one table
        // rather than every ranking on the site.
        tags: ["top-ranking", `ranking:${slug}`],
        // Six hours. The underlying data is refreshed weekly by a scheduled job,
        // so anything shorter is a cache that expires far more often than the
        // thing it caches changes.
        revalidate: 21_600,
      }).then((r) => r.data),
  },

  blog: {
    list: (params: {
      q?: string;
      category?: string;
      tag?: string;
      featured?: boolean;
      page?: number;
      per_page?: number;
    } = {}) =>
      request<Paginated<PostSummary>>("/blog/posts", {
        searchParams: {
          q: params.q,
          "filter[category]": params.category,
          "filter[tag]": params.tag,
          "filter[featured]": params.featured ? 1 : undefined,
          page: params.page,
          per_page: params.per_page,
        },
        tags: ["posts"],
        revalidate: 300,
      }),

    get: (slug: string) =>
      request<{ data: PostDetail }>(`/blog/posts/${slug}`, {
        // Tagged per-post so publishing one article revalidates only its own page
        // plus the listings, rather than the whole blog.
        tags: ["posts", `post:${slug}`],
        revalidate: 300,
      }).then((r) => r.data),

    categories: () =>
      request<{ data: PostCategory[] }>("/blog/categories", {
        tags: ["posts", "post-categories"],
        revalidate: 3600,
      }).then((r) => r.data),

    tags: () =>
      request<{ data: PostTag[] }>("/blog/tags", {
        tags: ["posts", "post-tags"],
        revalidate: 3600,
      }).then((r) => r.data),
  },

  account: {
    entitlements: (cookie: string) =>
      request<{ data: Entitlements }>("/account/entitlements", {
        headers: { Cookie: cookie },
        revalidate: 0,
      }).then((r) => r.data),
  },
};
