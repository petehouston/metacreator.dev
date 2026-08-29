"use client";

import { Search, X } from "lucide-react";
import { useSearchParams } from "next/navigation";
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

const SORTS = [
  { value: "popular", label: "Popular" },
  { value: "az", label: "A-Z" },
  { value: "za", label: "Z-A" },
] as const;

const DEFAULT_SORT = "popular";

type Filters = {
  q?: string;
  tier?: string;
  platform?: string;
  category?: string;
  sort?: string;
};

/**
 * The whole catalog, filtered in the browser.
 *
 * Every tool is delivered in the first response and narrowed locally, so a filter
 * click or a keystroke is instant and no request is made. That is only affordable
 * because a card is a handful of fields — if the catalog ever outgrows that, this
 * goes back to server-side paging.
 *
 * Every control is mirrored back into the query string so a filtered view can be
 * bookmarked and shared. Unrecognised values in that string are dropped rather than
 * matching nothing, so a stale or hand-edited link degrades to the full catalog.
 */
export function ToolBrowser({
  tools,
  categories,
  initial,
}: {
  tools: ToolSummary[];
  categories: ToolCategory[];
  /** Seeded from the URL so links like /tools?category=youtube still land filtered. */
  initial?: Filters;
}) {
  // The URL is the source of truth, not `initial`. The two agree on a fresh load,
  // but not when the browser restores this page with the Back button: the restored
  // entry replays the props the server rendered for whatever URL was first requested,
  // while the address bar still carries the filters mirrored into it below. Next
  // keeps useSearchParams in step with those replaceState writes, so reading it here
  // is what makes Back return to the view the user actually left.
  const searchParams = useSearchParams();
  const seed = React.useMemo(
    () => fromParams(searchParams, initial),
    // Only the first render seeds state; later edits come from the controls.
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [],
  );

  const [query, setQuery] = React.useState(seed.q ?? "");
  const [tier, setTier] = React.useState<string>(() =>
    oneOf(seed.tier, TIERS.map((option) => option.value)),
  );
  const [platform, setPlatform] = React.useState<string>(() =>
    oneOf(seed.platform, platforms.map((option) => option.key)),
  );
  const [category, setCategory] = React.useState<string>(() =>
    oneOf(seed.category, categories.map((option) => option.slug)),
  );
  const [sort, setSort] = React.useState<string>(
    () => oneOf(seed.sort, SORTS.map((option) => option.value)) || DEFAULT_SORT,
  );

  // Mirror the controls into the query string. This is history.replaceState rather
  // than router.replace precisely because it must not re-run the server component:
  // the whole point of the local filtering above is that nothing is refetched. The
  // write is debounced so typing a word rewrites the URL once, not once per letter.
  React.useEffect(() => {
    const params = new URLSearchParams();
    if (query.trim()) params.set("q", query.trim());
    if (tier) params.set("tier", tier);
    if (platform) params.set("platform", platform);
    if (category) params.set("category", category);
    if (sort !== DEFAULT_SORT) params.set("sort", sort);

    const next = params.toString();
    const timer = window.setTimeout(() => {
      window.history.replaceState(null, "", next ? `?${next}` : window.location.pathname);
    }, 250);

    return () => window.clearTimeout(timer);
  }, [query, tier, platform, category, sort]);

  const results = React.useMemo(() => {
    const needle = query.trim().toLowerCase();

    const matches = tools.filter((tool) => {
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

    // `matches` is already a fresh array from filter(), so sorting it in place
    // never touches the `tools` prop.
    if (sort === "az" || sort === "za") {
      const direction = sort === "az" ? 1 : -1;
      return matches.sort(
        (a, b) => direction * a.name.localeCompare(b.name, undefined, { sensitivity: "base" }),
      );
    }

    // Popular: most-run first, with the name as a stable tie-break so tools that
    // have never run keep a predictable order rather than the API's.
    return matches.sort(
      (a, b) => b.stats.runs - a.stats.runs || a.name.localeCompare(b.name),
    );
  }, [tools, query, tier, platform, category, sort]);

  const filtered = Boolean(query || tier || platform || category || sort !== DEFAULT_SORT);

  function resetAll() {
    setQuery("");
    setTier("");
    setPlatform("");
    setCategory("");
    setSort(DEFAULT_SORT);
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

        <FilterRow label="Sort">
          {SORTS.map((option) => (
            <FilterChip
              key={option.value}
              active={sort === option.value}
              onClick={() => setSort(option.value)}
            >
              {option.label}
            </FilterChip>
          ))}
        </FilterRow>
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
            onClick={resetAll}
            className="rounded-[var(--radius-sm)] border border-[var(--color-border)] px-2 py-1 font-mono text-[0.6875rem] uppercase tracking-[0.12em] text-[var(--color-primary)] transition-colors hover:border-[var(--color-border-strong)]"
          >
            Reset
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

/**
 * The filters carried by the current URL. Falls back to the server-supplied values
 * when the URL names nothing at all, so a first render is unaffected.
 */
function fromParams(params: URLSearchParams, initial: Filters | undefined): Filters {
  const keys: (keyof Filters)[] = ["q", "tier", "platform", "category", "sort"];
  if (!keys.some((key) => params.has(key))) return initial ?? {};

  return Object.fromEntries(
    keys.map((key) => [key, params.get(key) ?? undefined]),
  ) as Filters;
}

/** Keeps a URL-supplied value only when it names a filter that actually exists. */
function oneOf(value: string | undefined, allowed: readonly string[]): string {
  return value && allowed.includes(value) ? value : "";
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
