import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";

import { PostCard, PostCardSkeleton } from "@/components/blog/post-card";
import { Badge } from "@/components/ui/badge";
import { siteConfig } from "@/config/site";
import { api } from "@/lib/api";
import { ApiRequestError } from "@/lib/api";
import { blogDisplay, type BlogDisplay } from "@/lib/site-settings";
import { cn } from "@/lib/utils";

const DESCRIPTION =
  "Practical guides on growing, analysing and producing content across YouTube, Instagram, TikTok and X. Written by people who ship, not by an SEO farm.";

/**
 * Pagination is a path segment — `/blog`, `/blog/2`, `/blog/3` — rewritten onto
 * this route's `?page=` in next.config.ts. The canonical must therefore point at
 * the path form, or every page but the first would self-canonicalise to `/blog`
 * and drop its posts out of the index.
 */
export async function generateMetadata({ searchParams }: PageProps<"/blog">): Promise<Metadata> {
  const page = pageNumber(single((await searchParams).paged));
  const path = page > 1 ? `/blog/${page}` : "/blog";
  const title =
    page > 1
      ? `Blog — Page ${page}`
      : "Blog — Playbooks and analysis for creators";

  return {
    title,
    description: DESCRIPTION,
    alternates: { canonical: path },
    openGraph: {
      title: `${title} | ${siteConfig.name}`,
      description: "Playbooks and analysis for creators, across every major platform.",
      url: `${siteConfig.url}${path}`,
      type: "website",
    },
  };
}

export default async function BlogPage({ searchParams }: PageProps<"/blog">) {
  const params = await searchParams;

  const query = single(params.q);
  const category = single(params.category);
  const tag = single(params.tag);

  // Set by the `/blog/{n}` rewrite. A leftover `?page=` from the query-string
  // pagination this route used to have is deliberately not read: next.config.ts
  // has already redirected those to the path form, and the redirect carries the
  // stale param along to the destination.
  const page = pageNumber(single(params.paged));

  // The blog can be switched off in admin settings, in which case the API 404s
  // every route in the group and these pages must disappear too.
  const categories = await api.blog.categories().catch((error: unknown) => {
    if (error instanceof ApiRequestError && error.status === 404) notFound();
    return [];
  });

  const display = await blogDisplay();

  return (
    <div className="mx-auto w-full max-w-[80rem] px-4 py-12 sm:px-6 lg:py-16">
      <header className="flex flex-col gap-3">
        <p className="eyebrow">Field notes</p>
        <h1 className="text-heading-1 text-balance sm:text-display-lg">Blog</h1>
        <p className="max-w-2xl text-body-lg text-[var(--color-foreground-muted)]">
          Playbooks, teardowns and the occasional argument — everything we have
          learned helping creators grow across every major platform.
        </p>
      </header>

      {display.showCategories && categories.length > 0 && (
        <nav aria-label="Categories" className="mt-8 flex flex-wrap items-center gap-2">
          <FilterChip href={buildHref({ q: query, tag })} active={!category}>
            All posts
          </FilterChip>
          {categories.map((option) => (
            <FilterChip
              key={option.slug}
              href={buildHref({ q: query, tag, category: option.slug })}
              active={category === option.slug}
            >
              {option.name}
              {option.post_count !== undefined && (
                <span className="ml-1 text-[var(--color-foreground-subtle)]">
                  {option.post_count}
                </span>
              )}
            </FilterChip>
          ))}
        </nav>
      )}

      <Suspense key={`${query}-${category}-${tag}-${page}`} fallback={<GridFallback />}>
        <PostGrid query={query} category={category} tag={tag} page={page} display={display} />
      </Suspense>
    </div>
  );
}

async function PostGrid({
  query,
  category,
  tag,
  page,
  display,
}: {
  query?: string;
  category?: string;
  tag?: string;
  page: number;
  display: BlogDisplay;
}) {
  const response = await api.blog
    .list({ q: query, category, tag, page, per_page: display.postsPerPage })
    .catch(() => null);

  if (!response) {
    return (
      <p className="mt-12 rounded-[var(--radius-lg)] border border-[var(--color-danger)]/30 bg-[var(--color-danger)]/8 p-6 text-sm">
        We couldn&apos;t load the blog just now. Please refresh in a moment.
      </p>
    );
  }

  if (response.data.length === 0) {
    return (
      <div className="mt-16 flex flex-col items-center gap-3 text-center">
        <p className="text-heading-3">Nothing here yet</p>
        <p className="max-w-md text-sm text-[var(--color-foreground-muted)]">
          No posts match that filter.{" "}
          <Link href="/blog" className="text-[var(--color-primary)] hover:underline">
            Browse everything
          </Link>{" "}
          instead.
        </p>
      </div>
    );
  }

  const { page: pagination } = response.meta;

  // Every page reads the same. Page one used to pull its first post out into a
  // full-width lead, which made the landing page a different shape from every
  // other page of the same listing - and, because the lead was simply whatever
  // sorted first, gave one post that prominence without anyone choosing it.
  const posts = response.data;

  return (
    <>
      {/* A fixed 3-up grid: every card keeps the same width, including on a
          final page whose count is not a multiple of three. A short last row
          leaves its trailing columns empty rather than stretching the cards. */}
      <div className="mt-10 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
        {posts.map((post) => (
          <PostCard key={post.slug} post={post} display={display} />
        ))}
      </div>

      {pagination.last_page > 1 && (
        <nav aria-label="Pagination" className="mt-12 flex items-center justify-center gap-2">
          {Array.from({ length: pagination.last_page }, (_, index) => index + 1).map((number) => (
            <Link
              key={number}
              href={buildHref({ q: query, category, tag, page: number })}
              aria-current={number === pagination.current ? "page" : undefined}
              className={cn(
                "tabular flex size-9 items-center justify-center rounded-[var(--radius-md)] text-sm transition-colors",
                number === pagination.current
                  ? "bg-[var(--color-primary)] text-[var(--color-primary-foreground)]"
                  : "border border-[var(--color-border)] text-[var(--color-foreground-muted)] hover:border-[var(--color-border-strong)]",
              )}
            >
              {number}
            </Link>
          ))}
        </nav>
      )}
    </>
  );
}

function GridFallback() {
  return (
    <div className="mt-10 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
      {Array.from({ length: 6 }, (_, index) => (
        <PostCardSkeleton key={index} />
      ))}
    </div>
  );
}

function FilterChip({
  href,
  active,
  children,
}: {
  href: string;
  active: boolean;
  children: React.ReactNode;
}) {
  return (
    <Link href={href} scroll={false}>
      <Badge
        variant={active ? "solid" : "neutral"}
        size="md"
        className="cursor-pointer transition-colors hover:border-[var(--color-border-strong)]"
      >
        {children}
      </Badge>
    </Link>
  );
}

/**
 * Page one is `/blog`; every other page is `/blog/{n}`. Filters stay in the query
 * string - they combine freely, and only one of them can own the path.
 */
function buildHref(params: {
  q?: string;
  category?: string;
  tag?: string;
  page?: number;
}): string {
  const { page, ...filters } = params;
  const search = new URLSearchParams();

  for (const [key, value] of Object.entries(filters)) {
    if (value !== undefined && value !== "") {
      search.set(key, value);
    }
  }

  const path = page !== undefined && page > 1 ? `/blog/${page}` : "/blog";
  const query = search.toString();

  return query ? `${path}?${query}` : path;
}

function pageNumber(value: string | undefined): number {
  const parsed = Number(value);

  return Number.isInteger(parsed) && parsed >= 1 ? parsed : 1;
}

function single(value: string | string[] | undefined): string | undefined {
  return Array.isArray(value) ? value[0] : value;
}
