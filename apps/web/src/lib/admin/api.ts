"use client";

/**
 * The admin API, as one typed surface.
 *
 * Every call goes through `apiFetch`, so the session cookie, the CSRF priming and
 * the failure-as-a-value contract are the same here as everywhere else. The only
 * thing this file adds is the shape of each endpoint — which is worth having in
 * one place, because a mistyped query parameter on a management screen produces a
 * silently unfiltered list rather than an error.
 */

import {
  apiData,
  apiFetch,
  NETWORK_FAILURE,
  type ApiFailure,
  type ApiResult,
} from "@/lib/http";
import type { ChangelogMeta, ChangeTypeOption, Paginated } from "@/lib/types";
import type {
  ActivityEntry,
  AdminChangelogRelease,
  AdminInvoice,
  AdminInvoiceDetail,
  AdminMedia,
  AdminPlan,
  AdminPost,
  AdminPostDetail,
  AdminRole,
  AdminSubscription,
  AdminTicket,
  AdminTool,
  AdminTopRankingEntry,
  AdminTopRankingPage,
  AdminUser,
  BillingReport,
  ContactMessage,
  ContentAnalyticsRow,
  FunnelStep,
  NewsletterSubscriber,
  Overview,
  PeriodInfo,
  MailStatus,
  MailTestResult,
  PermissionCatalog,
  SettingsPayload,
  SitemapReport,
  RankingPlatformOption,
  Taxonomy,
  ToolAnalytics,
  ToolGrant,
} from "./types";

type Params = Record<string, string | number | boolean | undefined>;

/** A paginated list plus whatever extra `meta` the endpoint attaches. */
export type AdminList<T, M = unknown> = Paginated<T> & { meta: Paginated<T>["meta"] & M };

function list<T, M = unknown>(path: string, params: Params = {}): Promise<ApiResult<AdminList<T, M>>> {
  return apiFetch<AdminList<T, M>>(`/admin${path}`, { searchParams: params });
}

function one<T>(path: string): Promise<ApiResult<T>> {
  return apiData<T>(`/admin${path}`);
}

function write<T>(
  method: "POST" | "PATCH" | "PUT" | "DELETE",
  path: string,
  body?: unknown,
): Promise<ApiResult<T>> {
  return apiData<T>(`/admin${path}`, { method, body });
}

export const adminApi = {
  overview: (period: number) => one<Overview>(`/overview?period=${period}`),

  analytics: {
    tools: (params: Params) => one<ToolAnalytics>(`/analytics/tools?${query(params)}`),
    funnel: (period: number) =>
      one<{ period: PeriodInfo; periods: number[]; steps: FunnelStep[] }>(
        `/analytics/funnel?period=${period}`,
      ),
    content: (period: number) =>
      one<{ period: PeriodInfo; periods: number[]; rows: ContentAnalyticsRow[] }>(
        `/analytics/content?period=${period}`,
      ),
  },

  users: {
    list: (params: Params) => list<AdminUser>("/users", params),
    get: (id: string) => one<AdminUser>(`/users/${id}`),
    update: (id: string, body: Partial<AdminUser>) => write<AdminUser>("PATCH", `/users/${id}`, body),
    suspend: (id: string, reason?: string) =>
      write<AdminUser>("POST", `/users/${id}/suspend`, { reason }),
    remove: (id: string) => write<null>("DELETE", `/users/${id}`),
    setRoles: (id: string, roles: string[]) =>
      write<{ roles: string[] }>("PUT", `/users/${id}/roles`, { roles }),
  },

  roles: {
    list: () => list<AdminRole>("/roles"),
    permissions: () => one<PermissionCatalog>("/permissions"),
    create: (body: { name: string; permissions: string[] }) => write<AdminRole>("POST", "/roles", body),
    update: (id: number, permissions: string[]) =>
      write<AdminRole>("PATCH", `/roles/${id}`, { permissions }),
    remove: (id: number) => write<null>("DELETE", `/roles/${id}`),
  },

  tools: {
    list: (params: Params) => list<AdminTool>("/tools", params),
    get: (slug: string) => one<AdminTool>(`/tools/${slug}`),
    update: (slug: string, body: Partial<AdminTool>) =>
      write<AdminTool>("PATCH", `/tools/${slug}`, body),
    categories: () => list<Taxonomy>("/tool-categories"),
  },

  grants: {
    list: (params: Params) => list<ToolGrant>("/tool-grants", params),
    create: (body: { user: string; tool: string; reason: string; expires_at?: string | null }) =>
      write<ToolGrant>("POST", "/tool-grants", body),
    remove: (id: number) => write<null>("DELETE", `/tool-grants/${id}`),
  },

  changelog: {
    list: (params: Params) =>
      list<AdminChangelogRelease, { counts: Record<string, number>; types: ChangeTypeOption[] }>(
        "/changelog",
        params,
      ),
    get: (id: string) => one<AdminChangelogRelease>(`/changelog/${id}`),
    /**
     * The change-type catalog for the editor's picker.
     *
     * Deliberately the public `meta` endpoint rather than an admin one: the types
     * are not privileged, the site already fetches them to render its filter chips,
     * and a second admin-only copy would be a second thing to keep in step.
     */
    types: () => apiData<ChangelogMeta>("/changelog/meta"),
    create: (body: Record<string, unknown>) =>
      write<AdminChangelogRelease>("POST", "/changelog", body),
    update: (id: string, body: Record<string, unknown>) =>
      write<AdminChangelogRelease>("PATCH", `/changelog/${id}`, body),
    /** Live now, whatever date the release was carrying. Its own permission. */
    publish: (id: string) => write<AdminChangelogRelease>("POST", `/changelog/${id}/publish`),
    remove: (id: string) => write<null>("DELETE", `/changelog/${id}`),
  },

  topRankings: {
    list: (params: Params = {}) =>
      list<AdminTopRankingPage, { platforms: RankingPlatformOption[] }>("/top-rankings", params),
    get: (id: string) => one<AdminTopRankingPage>(`/top-rankings/${id}`),
    create: (body: Record<string, unknown>) =>
      write<AdminTopRankingPage>("POST", "/top-rankings", body),
    update: (id: string, body: Record<string, unknown>) =>
      write<AdminTopRankingPage>("PATCH", `/top-rankings/${id}`, body),
    remove: (id: string) => write<null>("DELETE", `/top-rankings/${id}`),

    /**
     * Re-read the Wikipedia article now.
     *
     * Runs inline server-side rather than queueing, so the response *is* the
     * answer: the returned page carries the new rows and the sync message. An
     * editor pressing this is asking whether the source still parses, and a 202
     * would leave them reloading the screen to find out.
     */
    sync: (id: string) => write<AdminTopRankingPage>("POST", `/top-rankings/${id}/sync`),

    /** Resolve every missing picture on the page. `force` re-checks the good ones too. */
    syncAvatars: (id: string, force = false) =>
      write<AdminTopRankingPage>("POST", `/top-rankings/${id}/avatars${force ? "?force=1" : ""}`),

    entries: {
      create: (pageId: string, body: Record<string, unknown>) =>
        write<AdminTopRankingEntry>("POST", `/top-rankings/${pageId}/entries`, body),
      update: (pageId: string, entryId: number, body: Record<string, unknown>) =>
        write<AdminTopRankingEntry>("PATCH", `/top-rankings/${pageId}/entries/${entryId}`, body),
      remove: (pageId: string, entryId: number) =>
        write<null>("DELETE", `/top-rankings/${pageId}/entries/${entryId}`),
      /** The whole arrangement, not a move — see ReorderRankingEntries server-side. */
      reorder: (pageId: string, ids: number[]) =>
        write<{ data: AdminTopRankingEntry[] }>("PUT", `/top-rankings/${pageId}/entries/order`, { ids }),
      /** One row's picture. The per-row retry. */
      syncAvatar: (pageId: string, entryId: number) =>
        write<AdminTopRankingEntry>("POST", `/top-rankings/${pageId}/entries/${entryId}/avatar`),
    },
  },

  posts: {
    list: (params: Params) => list<AdminPost, { counts: Record<string, number> }>("/posts", params),
    get: (id: string) => one<AdminPostDetail>(`/posts/${id}`),
    create: (body: Record<string, unknown>) => write<AdminPostDetail>("POST", "/posts", body),
    update: (id: string, body: Record<string, unknown>) =>
      write<AdminPostDetail>("PATCH", `/posts/${id}`, body),
    remove: (id: string) => write<null>("DELETE", `/posts/${id}`),
    restore: (id: string) => write<AdminPost>("POST", `/posts/${id}/restore`),
    bulk: (ids: string[], action: string) =>
      write<{ action: string; applied: string[]; skipped: string[]; counts: Record<string, number> }>(
        "POST",
        "/posts/bulk",
        { ids, action },
      ),
  },

  taxonomy: {
    categories: () => list<Taxonomy>("/post-categories"),
    tags: (params: Params = {}) => list<Taxonomy>("/tags", params),
    category: (slug: string) => one<Taxonomy>(`/post-categories/${slug}`),
    tag: (slug: string) => one<Taxonomy>(`/tags/${slug}`),
    saveCategory: (body: Partial<Taxonomy>, slug?: string) =>
      slug
        ? write<Taxonomy>("PATCH", `/post-categories/${slug}`, body)
        : write<Taxonomy>("POST", "/post-categories", body),
    saveTag: (body: Partial<Taxonomy>, slug?: string) =>
      slug ? write<Taxonomy>("PATCH", `/tags/${slug}`, body) : write<Taxonomy>("POST", "/tags", body),
    removeCategory: (slug: string) => write<null>("DELETE", `/post-categories/${slug}`),
    removeTag: (slug: string) => write<null>("DELETE", `/tags/${slug}`),
  },

  media: {
    list: (params: Params) => list<AdminMedia>("/media", params),
    get: (id: string) => one<AdminMedia>(`/media/${id}`),
    upload: (formData: FormData) => apiData<AdminMedia>("/admin/media", { method: "POST", formData }),
    update: (id: string, body: Partial<AdminMedia>) => write<AdminMedia>("PATCH", `/media/${id}`, body),
    remove: (id: string) => write<null>("DELETE", `/media/${id}`),
  },

  billing: {
    plans: () => list<AdminPlan>("/plans"),
    plan: (id: number) => one<AdminPlan>(`/plans/${id}`),
    createPlan: (body: Partial<AdminPlan>) => write<AdminPlan>("POST", "/plans", body),
    updatePlan: (id: number, body: Partial<AdminPlan>) => write<AdminPlan>("PATCH", `/plans/${id}`, body),
    removePlan: (id: number) => write<null>("DELETE", `/plans/${id}`),
    subscriptions: (params: Params) => list<AdminSubscription>("/subscriptions", params),
    invoices: (params: Params) =>
      list<AdminInvoice, { totals: Record<string, number | string> }>("/invoices", params),
    invoice: (id: number) => one<AdminInvoiceDetail>(`/invoices/${id}`),
    report: (period: number) => one<BillingReport>(`/invoices/report?period=${period}`),
  },

  tickets: {
    list: (params: Params) => list<AdminTicket, { counts: Record<string, number> }>("/tickets", params),
    get: (id: string) => one<AdminTicket>(`/tickets/${id}`),
    update: (id: string, body: Record<string, unknown>) =>
      write<AdminTicket>("PATCH", `/tickets/${id}`, body),
    reply: (id: string, body: { body: string; is_internal_note?: boolean; status?: string }) =>
      write<AdminTicket>("POST", `/tickets/${id}/messages`, body),
  },

  contact: {
    list: (params: Params) =>
      list<ContactMessage, { counts: { unhandled: number } }>("/contact-messages", params),
    toggleHandled: (id: number) => write<ContactMessage>("POST", `/contact-messages/${id}/handled`),
  },

  newsletter: {
    list: (params: Params) =>
      list<NewsletterSubscriber, { counts: Record<string, number> }>("/newsletter/subscribers", params),
    exportUrl: () =>
      `${process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8080"}/api/v1/admin/newsletter/export`,
  },

  settings: {
    get: () => one<SettingsPayload>("/settings"),
    update: (settings: { key: string; value: unknown }[]) =>
      write<{ updated: string[] }>("PUT", "/settings", { settings }),

    mail: {
      status: () => one<MailStatus>("/settings/mail"),
      // Omitting the address sends to the signed-in admin, which is the case that
      // wants no typing at all.
      test: (email?: string) =>
        write<MailTestResult>("POST", "/settings/mail/test", email ? { email } : {}),
    },
  },

  activity: (params: Params) => list<ActivityEntry>("/activity", params),

  /**
   * The one endpoint here that is not the Laravel API.
   *
   * `/sitemap.xml` is rendered by this app from its own cache, so only this app can
   * say what is currently in it. The route handler at `app/api/admin/sitemap`
   * re-checks the caller's permission against the API before answering.
   */
  sitemap: {
    get: () => local<SitemapReport>("/api/admin/sitemap"),
    refresh: () => local<SitemapReport>("/api/admin/sitemap", "POST"),
  },
};

/**
 * A same-origin call to one of this app's own route handlers.
 *
 * Deliberately not `apiFetch`: that one is aimed at the API's host and primes
 * Sanctum's CSRF cookie, neither of which applies here. The error envelope is kept
 * identical so `LoadError` and `useAdminResource` do not need to know the
 * difference.
 */
async function local<T>(path: string, method: "GET" | "POST" = "GET"): Promise<ApiResult<T>> {
  let response: Response;

  try {
    response = await fetch(path, {
      method,
      headers: { Accept: "application/json" },
      credentials: "include",
    });
  } catch {
    return { ok: false, error: NETWORK_FAILURE };
  }

  const payload = (await response.json().catch(() => null)) as
    | { data?: T; error?: ApiFailure }
    | null;

  if (!response.ok || !payload?.data) {
    return {
      ok: false,
      error: payload?.error ?? {
        code: "server.error",
        message: "Something went wrong. Please try again.",
        status: response.status,
      },
    };
  }

  return { ok: true, data: payload.data };
}

function query(params: Params): string {
  const search = new URLSearchParams();

  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== "") {
      search.set(key, String(value));
    }
  }

  return search.toString();
}
