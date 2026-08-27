import type { Metadata } from "next";
import Link from "next/link";
import { Suspense } from "react";

import { ToolCard, ToolCardSkeleton } from "@/components/tools/tool-card";
import { Badge } from "@/components/ui/badge";
import { platforms, siteConfig } from "@/config/site";
import { api } from "@/lib/api";
import { cn } from "@/lib/utils";

export const metadata: Metadata = {
  title: "All Creator Tools — YouTube, Instagram, TikTok, X & More",
  description:
    "Browse every MetaCreator tool: engagement calculators, hashtag generators, thumbnail downloaders, headline analyzers and more. Most are free with no account.",
  alternates: { canonical: "/tools" },
  openGraph: {
    title: `Creator Tools | ${siteConfig.name}`,
    description:
      "60+ tools for creators across YouTube, Instagram, TikTok, X, Facebook and LinkedIn.",
    url: `${siteConfig.url}/tools`,
  },
};

const TIERS = [
  { value: "", label: "All tools" },
  { value: "free", label: "Free" },
  { value: "account", label: "Free account" },
  { value: "premium", label: "Pro" },
] as const;

export default async function ToolsPage({ searchParams }: PageProps<"/tools">) {
  const params = await searchParams;

  const query = single(params.q);
  const category = single(params.category);
  const platform = single(params.platform);
  const tier = single(params.tier);
  const page = Number(single(params.page) ?? 1);

  const categories = await api.tools.categories().catch(() => []);

  return (
    <div className="mx-auto w-full max-w-[80rem] px-4 py-12 sm:px-6 lg:py-16">
      <header className="flex flex-col gap-3">
        <p className="eyebrow">The catalog</p>
        <h1 className="text-heading-1 text-balance sm:text-display-lg">Creator tools</h1>
        <p className="max-w-2xl text-body-lg text-[var(--color-foreground-muted)]">
          Every tool does one job properly. Free tools run instantly with no account —
          filter below to find what you need.
        </p>
      </header>

      {/* Filters are plain links, not client-side state: each combination is a real,
          crawlable, shareable URL, which is the whole point for a catalog that has to
          rank (see docs/16). */}
      <div className="panel mt-9 flex flex-col gap-4 p-5">
        <FilterRow label="Access">
          {TIERS.map((option) => (
            <FilterChip
              key={option.value}
              href={buildHref({ q: query, category, platform, tier: option.value || undefined })}
              active={(tier ?? "") === option.value}
            >
              {option.label}
            </FilterChip>
          ))}
        </FilterRow>

        <FilterRow label="Platform">
          <FilterChip
            href={buildHref({ q: query, category, tier })}
            active={!platform}
          >
            Any
          </FilterChip>
          {platforms.map((option) => (
            <FilterChip
              key={option.key}
              href={buildHref({ q: query, category, tier, platform: option.key })}
              active={platform === option.key}
            >
              {option.label}
            </FilterChip>
          ))}
        </FilterRow>

        {categories.length > 0 && (
          <FilterRow label="Category">
            <FilterChip href={buildHref({ q: query, platform, tier })} active={!category}>
              All
            </FilterChip>
            {categories.map((option) => (
              <FilterChip
                key={option.slug}
                href={buildHref({ q: query, platform, tier, category: option.slug })}
                active={category === option.slug}
              >
                {option.name}
                {option.tool_count !== undefined && (
                  <span className="ml-1 text-[var(--color-foreground-subtle)]">
                    {option.tool_count}
                  </span>
                )}
              </FilterChip>
            ))}
          </FilterRow>
        )}
      </div>

      <Suspense key={`${query}-${category}-${platform}-${tier}-${page}`} fallback={<GridFallback />}>
        <ToolGrid query={query} category={category} platform={platform} tier={tier} page={page} />
      </Suspense>
    </div>
  );
}

async function ToolGrid({
  query,
  category,
  platform,
  tier,
  page,
}: {
  query?: string;
  category?: string;
  platform?: string;
  tier?: string;
  page: number;
}) {
  const response = await api.tools
    .list({ q: query, category, platform, tier, page, per_page: 24 })
    .catch(() => null);

  if (!response) {
    return (
      <p className="mt-12 rounded-[var(--radius-lg)] border border-[var(--color-danger)]/30 bg-[var(--color-danger)]/8 p-6 text-sm">
        We couldn&apos;t load the catalog just now. Please refresh in a moment.
      </p>
    );
  }

  if (response.data.length === 0) {
    return (
      <div className="mt-16 flex flex-col items-center gap-3 text-center">
        <p className="text-heading-3">No tools match those filters</p>
        <p className="max-w-md text-sm text-[var(--color-foreground-muted)]">
          Try removing a filter, or{" "}
          <Link href="/contact" className="text-[var(--color-primary)] hover:underline">
            tell us what you were looking for
          </Link>{" "}
          — it is genuinely how we pick what to build next.
        </p>
      </div>
    );
  }

  const { page: pagination } = response.meta;

  return (
    <>
      <p
        className="tabular mt-8 font-mono text-[0.6875rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]"
        aria-live="polite"
      >
        {pagination.total} tool{pagination.total === 1 ? "" : "s"}
      </p>

      <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        {response.data.map((tool) => (
          <ToolCard key={tool.slug} tool={tool} />
        ))}
      </div>

      {pagination.last_page > 1 && (
        <nav aria-label="Pagination" className="mt-10 flex items-center justify-center gap-2">
          {Array.from({ length: pagination.last_page }, (_, index) => index + 1).map((number) => (
            <Link
              key={number}
              href={buildHref({ q: query, category, platform, tier, page: number })}
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
    <div className="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
      {Array.from({ length: 12 }, (_, index) => (
        <ToolCardSkeleton key={index} />
      ))}
    </div>
  );
}

function FilterRow({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex flex-wrap items-center gap-2">
      <span className="eyebrow w-28 shrink-0">{label}</span>
      {children}
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

  return query ? `/tools?${query}` : "/tools";
}

function single(value: string | string[] | undefined): string | undefined {
  return Array.isArray(value) ? value[0] : value;
}
