"use client";

import { Search, X } from "lucide-react";
import * as React from "react";

import { ToolCard } from "@/components/tools/tool-card";
import { Badge } from "@/components/ui/badge";
import { keyed } from "@/components/ui/keyed";
import { platforms } from "@/config/site";
import type { ToolCategory, ToolSummary } from "@/lib/types";

const TIERS = [
  { value: "", label: "All tools" },
  { value: "free", label: "Free" },
  { value: "account", label: "Account Required" },
  { value: "premium", label: "Pro" },
] as const;

/**
 * The whole catalog, filtered in the browser.
 *
 * Every tool is delivered in the first response and narrowed locally, so a filter
 * click or a keystroke is instant and no request is made. That is only affordable
 * because a card is a handful of fields — if the catalog ever outgrows that, this
 * goes back to server-side paging.
 */
export function ToolBrowser({
  tools,
  categories,
  initial,
}: {
  tools: ToolSummary[];
  categories: ToolCategory[];
  /** Seeded from the URL so links like /tools?category=youtube still land filtered. */
  initial?: { q?: string; tier?: string; platform?: string; category?: string };
}) {
  const [query, setQuery] = React.useState(initial?.q ?? "");
  const [tier, setTier] = React.useState<string>(initial?.tier ?? "");
  const [platform, setPlatform] = React.useState<string>(initial?.platform ?? "");
  const [category, setCategory] = React.useState<string>(initial?.category ?? "");

  const results = React.useMemo(() => {
    const needle = query.trim().toLowerCase();

    return tools.filter((tool) => {
      if (tier && tool.tier.value !== tier) return false;
      if (platform && !tool.platforms.includes(platform)) return false;
      if (category && tool.category?.slug !== category) return false;
      if (!needle) return true;

      // Name, tagline and category all match: people search for "counter" as
      // readily as they search for "instagram".
      return [tool.name, tool.tagline ?? "", tool.category?.name ?? ""]
        .join(" ")
        .toLowerCase()
        .includes(needle);
    });
  }, [tools, query, tier, platform, category]);

  const filtered = Boolean(query || tier || platform || category);

  function clearAll() {
    setQuery("");
    setTier("");
    setPlatform("");
    setCategory("");
  }

  return (
    <>
      <div className="panel mt-9 flex flex-col gap-4 p-5">
        <div className="relative">
          <Search
            aria-hidden="true"
            className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[var(--color-foreground-subtle)]"
          />
          <input
            type="search"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="Search tools — “thumbnail”, “character”, “engagement”…"
            aria-label="Search tools by name or description"
            className="h-11 w-full rounded-[var(--radius-md)] border border-[var(--color-border)] bg-[var(--color-surface-sunken)] pl-9 pr-9 text-sm text-[var(--color-foreground)] outline-none transition-colors placeholder:text-[var(--color-foreground-subtle)] focus:border-[var(--color-primary)] [&::-webkit-search-cancel-button]:hidden"
          />
          {query && (
            <button
              type="button"
              onClick={() => setQuery("")}
              aria-label="Clear search"
              className="absolute right-3 top-1/2 -translate-y-1/2 text-[var(--color-foreground-subtle)] hover:text-[var(--color-foreground)]"
            >
              <X className="size-4" />
            </button>
          )}
        </div>

        <FilterRow label="Access">
          {TIERS.map((option) => (
            <FilterChip
              key={option.value}
              active={tier === option.value}
              onClick={() => setTier(option.value)}
            >
              {option.label}
            </FilterChip>
          ))}
        </FilterRow>

        <FilterRow label="Platform">
          <FilterChip active={!platform} onClick={() => setPlatform("")}>
            Any
          </FilterChip>
          {platforms.map((option) => (
            <FilterChip
              key={option.key}
              active={platform === option.key}
              onClick={() => setPlatform(platform === option.key ? "" : option.key)}
            >
              {option.label}
            </FilterChip>
          ))}
        </FilterRow>

        {categories.length > 0 && (
          <FilterRow label="Category">
            <FilterChip active={!category} onClick={() => setCategory("")}>
              All
            </FilterChip>
            {categories.map((option) => (
              <FilterChip
                key={option.slug}
                active={category === option.slug}
                onClick={() => setCategory(category === option.slug ? "" : option.slug)}
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

      <div className="mt-8 flex items-center gap-3">
        <p
          className="tabular font-mono text-[0.6875rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]"
          aria-live="polite"
        >
          {results.length} of {tools.length} tool{tools.length === 1 ? "" : "s"}
        </p>

        {filtered && (
          <button
            type="button"
            onClick={clearAll}
            className="font-mono text-[0.6875rem] uppercase tracking-[0.12em] text-[var(--color-primary)] hover:underline"
          >
            Clear filters
          </button>
        )}
      </div>

      {results.length === 0 ? (
        <div className="mt-16 flex flex-col items-center gap-3 text-center">
          <p className="text-heading-3">No tools match that</p>
          <p className="max-w-md text-sm text-[var(--color-foreground-muted)]">
            Try a broader word, or clear the filters to see the whole catalog.
          </p>
        </div>
      ) : (
        <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {results.map((tool) => (
            <ToolCard key={tool.slug} tool={tool} />
          ))}
        </div>
      )}
    </>
  );
}

function FilterRow({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex flex-wrap items-center gap-2">
      <span className="eyebrow w-28 shrink-0">{label}</span>
      {keyed(children)}
    </div>
  );
}

function FilterChip({
  active,
  onClick,
  children,
}: {
  active: boolean;
  onClick: () => void;
  children: React.ReactNode;
}) {
  return (
    <button type="button" onClick={onClick} aria-pressed={active}>
      <Badge
        variant={active ? "solid" : "neutral"}
        size="md"
        className="cursor-pointer transition-colors hover:border-[var(--color-border-strong)]"
      >
        {children}
      </Badge>
    </button>
  );
}
