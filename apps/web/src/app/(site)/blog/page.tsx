import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";

import { PostCard, PostCardSkeleton } from "@/components/blog/post-card";
import { Badge } from "@/components/ui/badge";
import { siteConfig } from "@/config/site";
import { api } from "@/lib/api";
import { ApiRequestError } from "@/lib/api";
import { cn } from "@/lib/utils";

export const metadata: Metadata = {
  title: "Blog — Playbooks and analysis for creators",
  description:
    "Practical guides on growing, analysing and producing content across YouTube, Instagram, TikTok and X. Written by people who ship, not by an SEO farm.",
  alternates: { canonical: "/blog" },
  openGraph: {
    title: `Blog | ${siteConfig.name}`,
    description: "Playbooks and analysis for creators, across every major platform.",
    url: `${siteConfig.url}/blog`,
    type: "website",
  },
};

export default async function BlogPage({ searchParams }: PageProps<"/blog">) {
  const params = await searchParams;

  const query = single(params.q);
  const category = single(params.category);
  const tag = single(params.tag);
  const page = Number(single(params.page) ?? 1);

  // The blog can be switched off in admin settings, in which case the API 404s
  // every route in the group and these pages must disappear too.
  const categories = await api.blog.categories().catch((error: unknown) => {
    if (error instanceof ApiRequestError && error.status === 404) notFound();
    return [];
  });

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

      {categories.length > 0 && (
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
        <PostGrid query={query} category={category} tag={tag} page={page} />
      </Suspense>
    </div>
  );
}

async function PostGrid({
  query,
  category,
  tag,
  page,
}: {
  query?: string;
  category?: string;
  tag?: string;
  page: number;
}) {
  const response = await api.blog.list({ q: query, category, tag, page }).catch(() => null);

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

  // Only the first page of an unfiltered listing gets a lead post: on page three of
  // a tag archive there is no editorial reason for one post to dominate.
  const isLanding = pagination.current === 1 && !query && !category && !tag;
  const lead = isLanding ? response.data[0] : null;
  const rest = isLanding ? response.data.slice(1) : response.data;

  return (
    <>
      {lead ? (
        <div className="mt-10">
          <PostCard post={lead} featured className="lg:flex-row lg:[&>div:first-child]:w-1/2" />
        </div>
      ) : null}

      <div className={cn("grid gap-5 sm:grid-cols-2 lg:grid-cols-3", lead ? "mt-6" : "mt-10")}>
        {rest.map((post) => (
          <PostCard key={post.slug} post={post} />
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
    <div className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
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

function buildHref(params: Record<string, string | number | undefined>): string {
  const search = new URLSearchParams();

  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== "" && value !== 1) {
      search.set(key, String(value));
    }
  }

  const query = search.toString();

  return query ? `/blog?${query}` : "/blog";
}

function single(value: string | string[] | undefined): string | undefined {
  return Array.isArray(value) ? value[0] : value;
}
