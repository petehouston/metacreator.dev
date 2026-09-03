"use client";

import { Loader2, Search, X } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import * as React from "react";

import { ResultTypeIcon } from "@/components/search/result-type";
import { useSearchEnabled } from "@/components/site/features-provider";
import { searchSite } from "@/lib/client-api";
import type { SearchResult } from "@/lib/types";
import { cn } from "@/lib/utils";

/** How many suggestions the dropdown shows before deferring to the results page. */
const SUGGESTIONS = 5;

/**
 * Long enough that a fast typist produces one request per word rather than one per
 * letter, short enough that the list feels like it is keeping up. Below ~150ms the
 * saving disappears; above ~300ms the dropdown feels like it is thinking.
 */
const DEBOUNCE_MS = 200;

/** Matches the API's own floor — one character matches most of the catalog. */
const MIN_QUERY = 2;

/**
 * Global search: an input, a dropdown of the best five matches, and a link to the
 * full results.
 *
 * **Why a field and not a modal palette.** The palette is the fashionable answer
 * and the wrong one for a site whose visitors mostly arrive from a search engine:
 * it is invisible until you already know the shortcut. A field that is simply
 * *there* needs no discovery.
 *
 * **Why two placements rather than one that collapses.** The header row on a phone
 * is already full — logo, call to action, menu button — and an extra 36px icon
 * pushed it into horizontal overflow. So below `md` search lives inside the mobile
 * menu as a full-width field (`variant="block"`), which is both roomier to type
 * into and where a phone user already goes to navigate. The two are never on
 * screen at once, so having independent state is not a way for them to disagree.
 *
 * **Why the dropdown never becomes the whole answer.** Five results and a way to
 * see the rest. A dropdown that tried to page through everything would be the
 * results page, rendered in a box a third of its width.
 */
export function SiteSearch({
  variant = "header",
  className,
}: {
  variant?: "header" | "block";
  className?: string;
}) {
  const enabled = useSearchEnabled();

  // Rendered conditionally rather than returning null from inside, so the hooks
  // below always run in the same order for a given mount.
  if (!enabled) return null;

  return <SearchBox variant={variant} className={className} />;
}

interface Answer {
  results: SearchResult[];
  total: number;
}

/** How many answers one mount keeps. Backspacing a long query is the worst case. */
const ANSWER_CACHE = 60;

function SearchBox({ variant, className }: { variant: "header" | "block"; className?: string }) {
  const router = useRouter();

  const [query, setQuery] = React.useState("");
  const [open, setOpen] = React.useState(false);

  /**
   * Every answer this mount has seen, keyed by the query that produced it.
   *
   * Held as one map rather than as `results`/`total`/`pending` triples, because
   * that makes all three *derived* from the query being shown rather than
   * synchronised to it by an effect. A list and a spinner that are synchronised
   * will, at some frame, disagree — the classic symptom being the previous query's
   * results flashing under the new query's text.
   *
   * It is also the cache: backspacing through a word re-asks every prefix of it,
   * and those are the queries answered a keystroke ago.
   */
  const [answers, setAnswers] = React.useState<Record<string, Answer>>({});

  /**
   * The highlighted row, stamped with the query it belongs to.
   *
   * -1 is "nothing highlighted", which is what Enter should treat as "search for
   * exactly what I typed" rather than "open whatever happened to sort first". The
   * stamp is what resets it when the query changes: an index left over from the
   * previous list is a keyboard user opening the wrong page.
   */
  const [highlight, setHighlight] = React.useState<{
    query: string;
    index: number;
  }>({
    query: "",
    index: -1,
  });

  const containerRef = React.useRef<HTMLDivElement>(null);
  const inputRef = React.useRef<HTMLInputElement>(null);

  const trimmed = query.trim();
  const searchable = trimmed.length >= MIN_QUERY;

  const answer = searchable ? answers[trimmed] : undefined;
  const results = answer?.results ?? [];
  const total = answer?.total ?? 0;
  // No answer yet for a query worth asking about — which covers the debounce, the
  // request itself, and nothing else.
  const pending = searchable && answer === undefined;
  const active = highlight.query === trimmed ? highlight.index : -1;

  const setActive = React.useCallback(
    (index: number) => setHighlight({ query: trimmed, index }),
    [trimmed],
  );

  React.useEffect(() => {
    // Already answered, or not worth asking. Both are handled by returning rather
    // than by writing state, so this effect only ever *starts* work.
    if (!pending) return;

    const controller = new AbortController();

    const record = (response: Answer) =>
      setAnswers((previous) => {
        const keys = Object.keys(previous);
        // Bounded: drop the oldest insertions once the map is full. `Object.keys`
        // preserves insertion order for string keys, which is what makes "oldest"
        // meaningful here without a second structure tracking it.
        const kept =
          keys.length < ANSWER_CACHE
            ? previous
            : Object.fromEntries(
                keys
                  .slice(keys.length - ANSWER_CACHE + 1)
                  .map((key) => [key, previous[key]]),
              );

        return { ...kept, [trimmed]: response };
      });

    const timer = setTimeout(() => {
      searchSite(trimmed, { signal: controller.signal, limit: SUGGESTIONS })
        .then(record)
        .catch(() => {
          // An abort is the normal path — the next keystroke cancelled this
          // request — and must leave no answer behind, so the newer request's
          // spinner keeps running instead of a stale empty list appearing.
          if (!controller.signal.aborted) record({ results: [], total: 0 });
        });
    }, DEBOUNCE_MS);

    return () => {
      clearTimeout(timer);
      controller.abort();
    };
  }, [trimmed, pending]);

  const close = React.useCallback(() => {
    setOpen(false);
    setHighlight({ query: "", index: -1 });
  }, []);

  React.useEffect(() => {
    if (!open) return;

    function onPointerDown(event: MouseEvent) {
      if (!containerRef.current?.contains(event.target as Node)) close();
    }

    document.addEventListener("mousedown", onPointerDown);
    return () => document.removeEventListener("mousedown", onPointerDown);
  }, [open, close]);

  /** `/` focuses the box, the way it does on every site that has one. */
  React.useEffect(() => {
    // Header variant only: the block variant sits inside a menu that is shut most
    // of the time, and focusing an element nobody can see is worse than no
    // shortcut at all.
    if (variant !== "header") return;

    function onKeyDown(event: KeyboardEvent) {
      if (event.key !== "/" || event.metaKey || event.ctrlKey || event.altKey)
        return;

      const target = event.target as HTMLElement | null;
      const tag = target?.tagName;

      // Not while the visitor is typing into something — including this box.
      if (tag === "INPUT" || tag === "TEXTAREA" || target?.isContentEditable)
        return;

      event.preventDefault();
      inputRef.current?.focus();
    }

    document.addEventListener("keydown", onKeyDown);
    return () => document.removeEventListener("keydown", onKeyDown);
  }, [variant]);

  const goToResults = React.useCallback(() => {
    if (!searchable) return;

    close();
    setQuery("");
    router.push(`/search?q=${encodeURIComponent(trimmed)}`);
  }, [close, router, searchable, trimmed]);

  function onKeyDown(event: React.KeyboardEvent<HTMLInputElement>) {
    if (event.key === "Escape") {
      event.preventDefault();
      close();
      inputRef.current?.blur();
      return;
    }

    if (event.key === "ArrowDown" || event.key === "ArrowUp") {
      if (results.length === 0) return;

      event.preventDefault();
      setOpen(true);

      const next = event.key === "ArrowDown" ? active + 1 : active - 1;

      // Wraps through -1, so arrowing past either end returns to the input's own
      // text rather than sticking to the first or last row.
      setActive(
        next >= results.length ? -1 : next < -1 ? results.length - 1 : next,
      );
      return;
    }

    if (event.key === "Enter") {
      event.preventDefault();

      const chosen = results[active];

      if (chosen) {
        close();
        setQuery("");
        router.push(chosen.url);
        return;
      }

      goToResults();
    }
  }

  const showPanel = open && searchable;

  return (
    <div
      ref={containerRef}
      className={cn(
        "relative",
        // The header field is desktop-only; below `md` the block variant inside the
        // mobile menu is the one that renders.
        variant === "header" ? "hidden md:block" : "block",
        className,
      )}
    >
      <SearchField
        ref={inputRef}
        query={query}
        pending={pending}
        onChange={(value) => {
          setQuery(value);
          setOpen(true);
        }}
        onFocus={() => setOpen(true)}
        onKeyDown={onKeyDown}
        onClear={() => {
          setQuery("");
          inputRef.current?.focus();
        }}
        expanded={showPanel}
        fullWidth={variant === "block"}
      />

      {showPanel && (
        <div
          className={cn(
            "z-50 mt-2",
            // Overlaid in the header, where it must not push the page down; in flow
            // inside the mobile menu, where an overlay would cover the nav links the
            // visitor opened the menu to reach.
            variant === "header"
              ? "absolute left-0 w-[min(28rem,calc(100vw-2rem))]"
              : "w-full",
          )}
        >
          {/* `app-card`, not `panel`: this is drawn over live text, and the
              frosted content surface would let the paragraph beneath read
              straight through it. */}
          <div className="app-card overflow-hidden shadow-[var(--shadow-raised)]">
            <SuggestionList
              results={results}
              pending={pending}
              total={total}
              query={trimmed}
              active={active}
              onHover={setActive}
              onNavigate={() => {
                close();
                setQuery("");
              }}
              onSeeAll={goToResults}
            />
          </div>
        </div>
      )}
    </div>
  );
}

function SearchField({
  ref,
  query,
  pending,
  expanded,
  fullWidth,
  onChange,
  onFocus,
  onKeyDown,
  onClear,
}: {
  /** A plain prop, not `forwardRef` — React 19 passes refs straight through. */
  ref: React.RefObject<HTMLInputElement | null>;
  query: string;
  pending: boolean;
  expanded: boolean;
  /** Fills its container instead of sitting at the header's fixed width. */
  fullWidth: boolean;
  onChange: (value: string) => void;
  onFocus: () => void;
  onKeyDown: (event: React.KeyboardEvent<HTMLInputElement>) => void;
  onClear: () => void;
}) {
  return (
    <div
      className={cn(
        "flex h-9 items-center gap-2 rounded-[var(--radius-md)] border bg-[var(--color-surface-sunken)] px-2.5 transition-colors",
        expanded
          ? "border-[var(--color-border-strong)]"
          : "border-[var(--color-border)] hover:border-[var(--color-border-strong)]",
      )}
    >
      {pending ? (
        <Loader2
          className="size-4 shrink-0 animate-spin text-[var(--color-foreground-subtle)]"
          aria-hidden="true"
        />
      ) : (
        <Search
          className="size-4 shrink-0 text-[var(--color-foreground-subtle)]"
          aria-hidden="true"
        />
      )}

      <input
        ref={ref}
        type="search"
        value={query}
        onChange={(event) => onChange(event.target.value)}
        onFocus={onFocus}
        onKeyDown={onKeyDown}
        placeholder="Search tools, posts, rankings…"
        aria-label="Search the site"
        // `role="combobox"` is deliberately not claimed. Doing it properly needs
        // `aria-activedescendant` wired to real option ids in a listbox; claiming
        // the role without that machinery tells a screen reader to expect
        // behaviour that is not there, which is worse than a plain search field
        // that also happens to show suggestions.
        autoComplete="off"
        spellCheck={false}
        // Safari draws its own clear button on `type="search"`, which sits on top
        // of ours and is not keyboard reachable.
        className={cn(
          "w-full min-w-0 bg-transparent text-sm text-[var(--color-foreground)] outline-none placeholder:text-[var(--color-foreground-subtle)] [&::-webkit-search-cancel-button]:appearance-none",
          !fullWidth && "md:w-52 lg:w-64",
        )}
      />

      {query !== "" && (
        <button
          type="button"
          onClick={onClear}
          aria-label="Clear search"
          className="flex size-5 shrink-0 items-center justify-center rounded-full text-[var(--color-foreground-subtle)] transition-colors hover:text-[var(--color-foreground)]"
        >
          <X className="size-3.5" aria-hidden="true" />
        </button>
      )}
    </div>
  );
}

function SuggestionList({
  results,
  pending,
  total,
  query,
  active,
  onHover,
  onNavigate,
  onSeeAll,
}: {
  results: SearchResult[];
  pending: boolean;
  total: number;
  query: string;
  active: number;
  onHover: (index: number) => void;
  onNavigate: () => void;
  onSeeAll: () => void;
}) {
  if (results.length === 0) {
    return (
      <p className="px-4 py-6 text-center text-sm text-[var(--color-foreground-muted)]">
        {/* While a request is in flight the honest answer is "still looking",
            not "nothing found" — which is what a reader would otherwise see
            flash on every keystroke of a query that does have results. */}
        {pending ? "Searching…" : <>No results for &ldquo;{query}&rdquo;.</>}
      </p>
    );
  }

  return (
    <>
      <ul className="max-h-[60vh] overflow-y-auto p-1.5">
        {results.map((result, index) => (
          <li key={result.id}>
            <Link
              href={result.url}
              onClick={onNavigate}
              onMouseEnter={() => onHover(index)}
              aria-current={index === active ? "true" : undefined}
              className={cn(
                "flex items-start gap-3 rounded-[var(--radius-md)] px-2.5 py-2 transition-colors",
                // The brand tint, not `surface-sunken`. Sunken is a *recess* on the
                // page background, and inside this opaque panel it lands darker
                // than the panel itself in dark mode — a highlight the eye cannot
                // find. The primary tint reads as a highlight in both themes.
                index === active && "bg-[var(--color-primary-subtle)]",
              )}
            >
              <ResultTypeIcon type={result.type} className="mt-0.5" />

              <span className="min-w-0 flex-1">
                {/* Wraps rather than truncates: a tool called "YouTube
                    Advertiser-Friendly Content Checker" is unrecognisable cut at
                    the width of a dropdown, and two lines cost less than a wrong
                    click. Capped at three so one long title cannot push the rest
                    of the list out of view. */}
                <span className="line-clamp-3 text-sm font-medium text-[var(--color-foreground)]">
                  {result.title}
                </span>
                <span className="mt-0.5 block text-xs text-[var(--color-foreground-subtle)]">
                  {result.type_label}
                  {result.context ? ` · ${result.context}` : ""}
                </span>
              </span>
            </Link>
          </li>
        ))}
      </ul>

      <button
        type="button"
        onClick={onSeeAll}
        className="flex w-full items-center justify-center gap-1.5 border-t border-[var(--color-border-subtle)] px-4 py-2.5 text-sm font-medium text-[var(--color-primary)] transition-colors hover:bg-[var(--color-surface-sunken)]"
      >
        {total === 1 ? "View the 1 result" : `View all ${total} results`}
      </button>
    </>
  );
}
