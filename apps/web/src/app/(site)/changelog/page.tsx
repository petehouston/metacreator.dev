import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";

import { ReleaseEntry, ReleaseEntrySkeleton } from "@/components/changelog/release-entry";
import { Badge } from "@/components/ui/badge";
import { siteConfig } from "@/config/site";
import { api, ApiRequestError } from "@/lib/api";
import { cn } from "@/lib/utils";

export const metadata: Metadata = {
  title: "Changelog — Every release, dated",
  description:
    "What we shipped and when: new tools, improvements, fixes and security updates to MetaCreator, in reverse order.",
  alternates: { canonical: "/changelog" },
  openGraph: {
    title: `Changelog | ${siteConfig.name}`,
    description: "Every release, dated — new tools, improvements, fixes and security updates.",
    url: `${siteConfig.url}/changelog`,
    type: "website",
  },
};

export default async function ChangelogPage({ searchParams }: PageProps<"/changelog">) {
  const params = await searchParams;

  const type = single(params.type);
  const year = single(params.year);
  const page = Number(single(params.page) ?? 1);

  // The filter catalog is cheap and cached for an hour; the timeline below streams
  // in its own boundary so a slow list never delays the chips.
  //
  // The changelog can be switched off in admin settings, in which case the API 404s
  // every route in the group and this page has to disappear with them — an empty
  // timeline would keep the URL indexed.
  const meta = await api.changelog.meta().catch((error: unknown) => {
    if (error instanceof ApiRequestError && error.status === 404) notFound();
    return { types: [], years: [] };
  });

  return (
    <div className="mx-auto w-full max-w-[68rem] px-4 py-12 sm:px-6 lg:py-16">
      <header className="flex flex-col gap-3">
        <p className="eyebrow">Product updates</p>
        <h1 className="text-heading-1 text-balance sm:text-display-lg">Changelog</h1>
        <p className="max-w-2xl text-body-lg text-[var(--color-foreground-muted)]">
          Everything we ship, dated and in plain language — new tools, the
          improvements that made existing ones better, and the fixes that should
          never have been needed.
        </p>
      </header>

      {(meta.types.length > 0 || meta.years.length > 0) && (
        <div className="mt-8 flex flex-col gap-3 border-y border-[var(--color-border-subtle)] py-4">
          <nav aria-label="Filter by change type" className="flex flex-wrap items-center gap-2">
            <FilterChip href={buildHref({ year })} active={!type}>
              Everything
            </FilterChip>

            {meta.types.map((option) => (
              <FilterChip
                key={option.value}
                href={buildHref({ year, type: option.value })}
                active={type === option.value}
                title={option.hint}
              >
                {option.label}
              </FilterChip>
            ))}
          </nav>

          {meta.years.length > 1 && (
            <nav aria-label="Filter by year" className="flex flex-wrap items-center gap-2">
              <FilterChip href={buildHref({ type })} active={!year}>
                All time
              </FilterChip>

              {meta.years.map((option) => (
                <FilterChip
                  key={option.year}
                  href={buildHref({ type, year: String(option.year) })}
                  active={year === String(option.year)}
                >
                  {option.year}
                  <span className="tabular ml-1 text-[var(--color-foreground-subtle)]">
                    {option.total}
                  </span>
                </FilterChip>
              ))}
            </nav>
          )}
        </div>
      )}

      <Suspense key={`${type}-${year}-${page}`} fallback={<TimelineFallback />}>
        <Timeline type={type} year={year} page={page} />
      </Suspense>
    </div>
  );
}

async function Timeline({
  type,
  year,
  page,
}: {
  type?: string;
  year?: string;
  page: number;
}) {
  const response = await api.changelog
    .list({ type, year: year ? Number(year) : undefined, page })
    .catch(() => null);

  if (!response) {
    return (
      <p className="mt-12 rounded-[var(--radius-lg)] border border-[var(--color-danger)]/30 bg-[var(--color-danger)]/8 p-6 text-sm">
        We couldn&apos;t load the changelog just now. Please refresh in a moment.
      </p>
    );
  }

  if (response.data.length === 0) {
    return (
      <div className="mt-16 flex flex-col items-center gap-3 text-center">
        <p className="text-heading-3">Nothing matches that filter</p>
        <p className="max-w-md text-sm text-[var(--color-foreground-muted)]">
          No release here fits those filters.{" "}
          <Link href="/changelog" className="text-[var(--color-primary)] hover:underline">
            See everything
          </Link>{" "}
          instead.
        </p>
      </div>
    );
  }

  const { page: pagination } = response.meta;

  return (
    <>
      <div className="relative mt-10 flex flex-col gap-10 lg:gap-14">
        {/* The timeline's spine: one rule spanning the whole list rather than a
            segment per entry, which never quite meet. It sits in the middle of the
            grid gutter each entry defines — 11rem rail plus half of the 2.5rem gap
            — and only exists at `lg`, where that two-column layout does. */}
        <span
          aria-hidden="true"
          className="absolute inset-y-0 left-[12.25rem] hidden w-px bg-[var(--color-border)] lg:block"
        />

        {response.data.map((release) => (
          <ReleaseEntry key={release.slug} release={release} />
        ))}
      </div>

      {pagination.last_page > 1 && (
        <nav aria-label="Pagination" className="mt-14 flex items-center justify-center gap-2">
          {Array.from({ length: pagination.last_page }, (_, index) => index + 1).map((number) => (
            <Link
              key={number}
              href={buildHref({ type, year, page: number })}
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

function TimelineFallback() {
  return (
    <div className="mt-10 flex flex-col gap-10">
      {[0, 1, 2].map((entry) => (
        <ReleaseEntrySkeleton key={entry} />
      ))}
    </div>
  );
}

function FilterChip({
  href,
  active,
  title,
  children,
}: {
  href: string;
  active: boolean;
  title?: string;
  children: React.ReactNode;
}) {
  return (
    <Link href={href} scroll={false} title={title}>
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

  return query ? `/changelog?${query}` : "/changelog";
}

function single(value: string | string[] | undefined): string | undefined {
  return Array.isArray(value) ? value[0] : value;
}
