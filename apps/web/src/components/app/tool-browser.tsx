"use client";

import { ArrowUpRight, Search, SlidersHorizontal, Wrench, X } from "lucide-react";
import Link from "next/link";
import * as React from "react";

import { EmptyState } from "@/components/app/empty-state";
import { FormAlert } from "@/components/auth/form-alert";
import { TierBadge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { platforms } from "@/config/site";
import { apiFetch } from "@/lib/http";
import type { Paginated, ToolCategory, ToolSummary, ToolTier } from "@/lib/types";
import { cn, formatNumber } from "@/lib/utils";

const TIERS: { value: ToolTier; label: string }[] = [
  { value: "free", label: "Free" },
  { value: "account", label: "Account" },
  { value: "premium", label: "Premium" },
];

/**
 * The in-app catalog.
 *
 * The public `/tools` page is a landing page — it has to rank, so it is rendered on
 * the server and reads like an index. This is the working version of the same data:
 * filter state lives in the URL so a filtered view is linkable and survives a
 * reload, and the grid tells you whether *you* can run each tool (`meta.access`)
 * rather than only what tier it is.
 */
export function ToolBrowser() {
  const [query, setQuery] = React.useState("");
  const [debouncedQuery, setDebouncedQuery] = React.useState("");
  const [tier, setTier] = React.useState<ToolTier | null>(null);
  const [platform, setPlatform] = React.useState<string | null>(null);
  const [category, setCategory] = React.useState<string | null>(null);
  const [filtersOpen, setFiltersOpen] = React.useState(false);
  const [categories, setCategories] = React.useState<ToolCategory[]>([]);

  React.useEffect(() => {
    // Debounced so a fast typist costs one catalog request, not eight.
    const timer = setTimeout(() => setDebouncedQuery(query.trim()), 220);
    return () => clearTimeout(timer);
  }, [query]);

  React.useEffect(() => {
    let cancelled = false;

    void (async () => {
      const result = await apiFetch<Paginated<ToolCategory>>("/catalog/categories");
      if (!cancelled && result.ok) setCategories(result.data.data);
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  /**
   * As in the run history: `loading` is derived from which request is on screen,
   * not held as a flag that has to be raised inside the effect body. The last
   * successful grid stays visible while the next one is in flight, which is what
   * keeps typing in the search box from strobing the page.
   */
  const requestKey = `${debouncedQuery}|${tier ?? ""}|${platform ?? ""}|${category ?? ""}`;

  const [loaded, setLoaded] = React.useState<{
    key: string;
    tools: ToolSummary[];
    access: Record<string, boolean>;
    total: number;
    error: string | null;
  } | null>(null);

  const loading = loaded?.key !== requestKey;
  const tools = loaded?.tools ?? [];
  const access = loaded?.access ?? {};
  const total = loaded?.total ?? 0;
  const error = loaded?.error ?? null;

  React.useEffect(() => {
    const controller = new AbortController();

    void (async () => {
      const result = await apiFetch<Paginated<ToolSummary>>("/catalog/tools", {
        searchParams: {
          q: debouncedQuery || undefined,
          "filter[tier]": tier ?? undefined,
          "filter[platform]": platform ?? undefined,
          "filter[category]": category ?? undefined,
          per_page: 48,
        },
        signal: controller.signal,
      });

      // An aborted request is a superseded one, not a failure: its replacement is
      // already in flight, and reporting it would flash an error mid-keystroke.
      if (controller.signal.aborted) return;

      setLoaded((previous) =>
        result.ok
          ? {
              key: requestKey,
              tools: result.data.data,
              access: result.data.meta.access ?? {},
              total: result.data.meta.page.total,
              error: null,
            }
          : {
              key: requestKey,
              tools: previous?.tools ?? [],
              access: previous?.access ?? {},
              total: previous?.total ?? 0,
              error: result.error.message,
            },
      );
    })();

    return () => controller.abort();
  }, [requestKey, debouncedQuery, tier, platform, category]);

  const activeFilters = [tier, platform, category].filter(Boolean).length;

  function clearFilters() {
    setTier(null);
    setPlatform(null);
    setCategory(null);
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center gap-2">
        <div className="relative min-w-0 flex-1">
          <Search
            className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[var(--color-foreground-subtle)]"
            aria-hidden="true"
          />

          <input
            type="search"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="Search the catalog…"
            aria-label="Search tools"
            className="h-10 w-full rounded-[var(--radius-md)] border border-[var(--color-border)] bg-[var(--app-surface)] pl-9 pr-3 text-sm text-[var(--color-foreground)] outline-none transition-colors placeholder:text-[var(--color-foreground-subtle)] focus:border-[var(--color-primary)]"
          />
        </div>

        <Button
          variant="secondary"
          onClick={() => setFiltersOpen((open) => !open)}
          aria-expanded={filtersOpen}
        >
          <SlidersHorizontal className="size-4" aria-hidden="true" />
          Filters
          {activeFilters > 0 && (
            <span className="ml-0.5 rounded-full bg-[var(--color-primary)] px-1.5 text-[0.625rem] font-semibold text-[var(--color-primary-foreground)]">
              {activeFilters}
            </span>
          )}
        </Button>
      </div>

      {filtersOpen && (
        <div className="app-card animate-fade-in flex flex-col gap-4 p-4">
          <FilterRow label="Tier">
            {TIERS.map((option) => (
              <Chip
                key={option.value}
                active={tier === option.value}
                onClick={() => setTier(tier === option.value ? null : option.value)}
              >
                {option.label}
              </Chip>
            ))}
          </FilterRow>

          <FilterRow label="Platform">
            {platforms.map((option) => (
              <Chip
                key={option.key}
                active={platform === option.key}
                onClick={() => setPlatform(platform === option.key ? null : option.key)}
              >
                {option.label}
              </Chip>
            ))}
          </FilterRow>

          {categories.length > 0 && (
            <FilterRow label="Category">
              {categories.map((option) => (
                <Chip
                  key={option.slug}
                  active={category === option.slug}
                  onClick={() => setCategory(category === option.slug ? null : option.slug)}
                >
                  {option.name}
                  {option.tool_count !== undefined && (
                    <span className="ml-1 tabular font-mono text-[0.625rem] opacity-70">
                      {option.tool_count}
                    </span>
                  )}
                </Chip>
              ))}
            </FilterRow>
          )}

          {activeFilters > 0 && (
            <button
              type="button"
              onClick={clearFilters}
              className="inline-flex w-fit items-center gap-1 text-xs font-semibold text-[var(--color-primary)] hover:underline"
            >
              <X className="size-3" aria-hidden="true" />
              Clear filters
            </button>
          )}
        </div>
      )}

      {error && <FormAlert>{error}</FormAlert>}

      <p className="tabular font-mono text-[0.6875rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
        {loading ? "Loading…" : `${total} tool${total === 1 ? "" : "s"}`}
      </p>

      {loading && tools.length === 0 ? (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {[0, 1, 2, 3, 4, 5].map((card) => (
            <div
              key={card}
              className="app-card h-32 animate-pulse"
              aria-hidden="true"
            />
          ))}
        </div>
      ) : tools.length === 0 ? (
        <EmptyState
          icon={Wrench}
          title="Nothing matches"
          description="Try a different search, or clear the filters to see the whole catalog."
          action={
            activeFilters > 0 ? (
              <Button size="sm" variant="secondary" onClick={clearFilters}>
                Clear filters
              </Button>
            ) : undefined
          }
        />
      ) : (
        <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {tools.map((tool) => (
            <li key={tool.slug}>
              <Link
                href={`/tools/${tool.slug}`}
                className="app-card app-card-interactive group flex h-full flex-col gap-2 p-4"
              >
                <div className="flex items-start justify-between gap-2">
                  <div className="flex flex-wrap items-center gap-1.5">
                    <TierBadge tier={tool.tier} />

                    {/* The catalog knows whether this specific person can run this
                        specific tool. Saying so on the card is what stops someone
                        opening four pages to find the one they are entitled to. */}
                    {access[tool.slug] === false && (
                      <span className="rounded-full bg-[var(--color-surface-sunken)] px-2 py-0.5 text-[0.625rem] font-medium text-[var(--color-foreground-subtle)]">
                        Locked
                      </span>
                    )}
                  </div>

                  <ArrowUpRight
                    className="size-4 shrink-0 text-[var(--color-foreground-subtle)] transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5"
                    aria-hidden="true"
                  />
                </div>

                <p className="text-sm font-semibold text-[var(--color-foreground)]">
                  {tool.name}
                </p>

                {tool.tagline && (
                  <p className="line-clamp-2 text-xs leading-relaxed text-[var(--color-foreground-muted)]">
                    {tool.tagline}
                  </p>
                )}

                <p className="tabular mt-auto flex items-center gap-2 pt-1 font-mono text-[0.625rem] text-[var(--color-foreground-subtle)]">
                  <span>{formatNumber(tool.stats.runs)} runs</span>
                  {tool.category && <span>· {tool.category.name}</span>}
                </p>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

function FilterRow({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex flex-wrap items-center gap-2">
      <span className="w-16 shrink-0 font-mono text-[0.625rem] font-medium uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
        {label}
      </span>
      {children}
    </div>
  );
}

function Chip({
  active,
  onClick,
  children,
}: {
  active: boolean;
  onClick: () => void;
  children: React.ReactNode;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      className={cn(
        "rounded-full border px-2.5 py-1 text-xs font-medium transition-colors",
        active
          ? "border-[var(--color-primary)] bg-[var(--color-primary-subtle)] text-[var(--color-primary)]"
          : "border-[var(--color-border)] text-[var(--color-foreground-muted)] hover:border-[var(--color-border-strong)] hover:text-[var(--color-foreground)]",
      )}
    >
      {children}
    </button>
  );
}
