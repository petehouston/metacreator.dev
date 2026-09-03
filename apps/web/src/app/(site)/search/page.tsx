import { ArrowUp, SearchX } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";

import { SearchResultCard } from "@/components/search/result-card";
import { Badge } from "@/components/ui/badge";
import { siteConfig } from "@/config/site";
import { api, ApiRequestError } from "@/lib/api";
import { pageWindow } from "@/lib/pagination";
import type { SearchResultType } from "@/lib/types";
import { siteFeatures } from "@/lib/site-settings";
import { cn } from "@/lib/utils";

/** The requirement, and the number a list of this row height fits above two folds. */
const PER_PAGE = 10;

const TYPE_FILTERS: { value: SearchResultType | "all"; label: string }[] = [
  { value: "all", label: "Everything" },
  { value: "tool", label: "Tools" },
  { value: "post", label: "Posts" },
  { value: "top_ranking", label: "Top Rankings" },
  { value: "page", label: "Pages" },
];

/**
 * `noindex`, always.
 *
 * A search results page is generated from whatever a visitor typed, so letting it
 * be indexed hands a crawler an unbounded set of thin, near-duplicate URLs — and
 * hands anyone who wants it a way to put arbitrary text on an indexed page of this
 * domain. Every search engine's own guidance says to keep these out of the index.
 */
export async function generateMetadata({ searchParams }: PageProps<"/search">): Promise<Metadata> {
  const query = single((await searchParams).q)?.trim();

  return {
    title: query ? `Search results for “${query}”` : "Search",
    description: `Search tools, posts and rankings across ${siteConfig.name}.`,
    robots: { index: false, follow: true },
    alternates: { canonical: "/search" },
  };
}

export default async function SearchPage({ searchParams }: PageProps<"/search">) {
  const params = await searchParams;

  const query = (single(params.q) ?? "").trim();
  const type = parseType(single(params.type));
  const page = pageNumber(single(params.page));

  // The API 404s every search route while the feature is off, and this page has to
  // disappear with it rather than render an empty shell around a dead endpoint.
  const { searchEnabled } = await siteFeatures();

  if (!searchEnabled) notFound();

  return (
    <div id="top" className="mx-auto w-full max-w-[60rem] px-4 py-12 sm:px-6 lg:py-16">
      <header className="flex flex-col gap-3">
        <p className="eyebrow">Search</p>
        <h1 className="text-heading-1 text-balance sm:text-display-lg">
          {query ? <>Results for “{query}”</> : "Search"}
        </h1>
        <p className="max-w-2xl text-body-lg text-[var(--color-foreground-muted)]">
          Tools, articles, rankings and pages — everything on {siteConfig.name}, in one
          list.
        </p>
      </header>

      {query !== "" && (
        <nav aria-label="Filter by type" className="mt-8 flex flex-wrap items-center gap-2">
          {TYPE_FILTERS.map((filter) => (
            <Link
              key={filter.value}
              href={buildHref({ q: query, type: filter.value === "all" ? undefined : filter.value })}
              scroll={false}
            >
              <Badge
                variant={
                  (type ?? "all") === filter.value ? "solid" : "neutral"
                }
                size="md"
                className="cursor-pointer transition-colors hover:border-[var(--color-border-strong)]"
              >
                {filter.label}
              </Badge>
            </Link>
          ))}
        </nav>
      )}

      {query === "" ? (
        <EmptyQuery />
      ) : (
        <Suspense key={`${query}-${type ?? "all"}-${page}`} fallback={<ListFallback />}>
          <Results query={query} type={type} page={page} />
        </Suspense>
      )}

      {/* A bare `<a>`, not a scroll handler: `html` already carries smooth
          scrolling and the reduced-motion block in globals.css turns it off for
          anyone who asked. It also works before hydration, which is the point of a
          control that sits under a full page of results. */}
      <div className="mt-12 flex justify-center">
        <a
          href="#top"
          className="inline-flex items-center gap-1.5 text-sm text-[var(--color-foreground-muted)] underline-offset-4 transition-colors hover:text-[var(--color-foreground)] hover:underline"
        >
          <ArrowUp className="size-4" aria-hidden="true" />
          Back to top
        </a>
      </div>
    </div>
  );
}

async function Results({
  query,
  type,
  page,
}: {
  query: string;
  type?: SearchResultType;
  page: number;
}) {
  const response = await api.search({ q: query, type, page, per_page: PER_PAGE }).catch(
    (error: unknown) => {
      // The feature being switched off between the layout's read and this one is a
      // 404, and it should behave like the page never existed.
      if (error instanceof ApiRequestError && error.status === 404) notFound();
      return null;
    },
  );

  if (!response) {
    return (
      <p className="mt-10 rounded-[var(--radius-lg)] border border-[var(--color-danger)]/30 bg-[var(--color-danger)]/8 p-6 text-sm">
        We couldn&apos;t run that search just now. Please try again in a moment.
      </p>
    );
  }

  if (response.data.length === 0) {
    return (
      <div className="mt-16 flex flex-col items-center gap-3 text-center">
        <SearchX className="size-8 text-[var(--color-foreground-subtle)]" aria-hidden="true" />
        <p className="text-heading-3">Nothing matched “{query}”</p>
        <p className="max-w-md text-sm text-[var(--color-foreground-muted)]">
          Try fewer words, or a different spelling.{" "}
          <Link href="/tools" className="text-[var(--color-primary)] hover:underline">
            Browse the tool catalog
          </Link>{" "}
          instead.
        </p>
      </div>
    );
  }

  const { page: pagination } = response.meta;

  return (
    <>
      <p className="mt-8 text-sm text-[var(--color-foreground-subtle)]">
        <span className="tabular">{pagination.total}</span>{" "}
        {pagination.total === 1 ? "result" : "results"}
      </p>

      <ul className="mt-4 flex flex-col gap-3">
        {response.data.map((result) => (
          <SearchResultCard key={result.id} result={result} />
        ))}
      </ul>

      {pagination.last_page > 1 && (
        <nav aria-label="Pagination" className="mt-12 flex flex-wrap items-center justify-center gap-2">
          {pageWindow(pagination.current, pagination.last_page).map((entry, index) =>
            entry === null ? (
              <span
                // Index-keyed on purpose: a gap has no identity of its own, and
                // there are at most two of them.
                key={`gap-${index}`}
                aria-hidden="true"
                className="px-1 text-sm text-[var(--color-foreground-subtle)]"
              >
                …
              </span>
            ) : (
              <Link
                key={entry}
                href={buildHref({ q: query, type, page: entry })}
                aria-label={`Page ${entry}`}
                aria-current={entry === pagination.current ? "page" : undefined}
                className={cn(
                  "tabular flex size-9 items-center justify-center rounded-[var(--radius-md)] text-sm transition-colors",
                  entry === pagination.current
                    ? "bg-[var(--color-primary)] text-[var(--color-primary-foreground)]"
                    : "border border-[var(--color-border)] text-[var(--color-foreground-muted)] hover:border-[var(--color-border-strong)]",
                )}
              >
                {entry}
              </Link>
            ),
          )}
        </nav>
      )}
    </>
  );
}

function EmptyQuery() {
  return (
    <div className="mt-16 flex flex-col items-center gap-3 text-center">
      <SearchX className="size-8 text-[var(--color-foreground-subtle)]" aria-hidden="true" />
      <p className="text-heading-3">What are you looking for?</p>
      <p className="max-w-md text-sm text-[var(--color-foreground-muted)]">
        Use the search box in the header to look across every tool, article and ranking
        on the site.
      </p>
    </div>
  );
}

function ListFallback() {
  return (
    <ul className="mt-12 flex flex-col gap-3">
      {Array.from({ length: 5 }, (_, index) => (
        <li key={index} className="panel flex gap-4 p-3 sm:gap-5 sm:p-4">
          <div className="aspect-[4/3] w-24 shrink-0 animate-pulse rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)] sm:w-36" />
          <div className="flex flex-1 flex-col gap-2 py-1">
            <div className="h-4 w-20 animate-pulse rounded-full bg-[var(--color-surface-sunken)]" />
            <div className="h-5 w-3/4 animate-pulse rounded bg-[var(--color-surface-sunken)]" />
            <div className="h-4 w-full animate-pulse rounded bg-[var(--color-surface-sunken)]" />
          </div>
        </li>
      ))}
    </ul>
  );
}

/** Query-string pagination, unlike the blog: these URLs are `noindex` anyway, so
 *  there is nothing to gain from giving each page a path of its own. */
function buildHref(params: { q: string; type?: SearchResultType; page?: number }): string {
  const search = new URLSearchParams({ q: params.q });

  if (params.type) search.set("type", params.type);
  if (params.page !== undefined && params.page > 1) search.set("page", String(params.page));

  return `/search?${search.toString()}`;
}

/** `all` is the absence of a filter, not a filter — it must not reach the API. */
function parseType(value: string | undefined): SearchResultType | undefined {
  return TYPE_FILTERS.some((filter) => filter.value !== "all" && filter.value === value)
    ? (value as SearchResultType)
    : undefined;
}

function pageNumber(value: string | undefined): number {
  const parsed = Number(value);

  return Number.isInteger(parsed) && parsed >= 1 ? parsed : 1;
}

function single(value: string | string[] | undefined): string | undefined {
  return Array.isArray(value) ? value[0] : value;
}
