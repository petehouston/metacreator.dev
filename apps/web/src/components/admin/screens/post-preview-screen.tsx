"use client";

import { Eye } from "lucide-react";
import * as React from "react";

import { BlockRenderer } from "@/components/blocks/block-renderer";
import { Badge } from "@/components/ui/badge";
import { PREVIEW_STORAGE_KEY } from "@/lib/admin/post-preview";
import type { BlockDocument } from "@/lib/types";
import { formatDate } from "@/lib/utils";

/** The draft never changes while the preview tab is open; nothing to subscribe to. */
function subscribeToNothing(): () => void {
  return () => {};
}

function readDraft(): string | null {
  try {
    return window.sessionStorage.getItem(PREVIEW_STORAGE_KEY);
  } catch {
    // A browser configured to block site data has no draft to show.
    return null;
  }
}

interface PreviewPayload {
  title: string;
  slug: string;
  excerpt: string;
  blocks: BlockDocument;
  status: string;
  category: string | null;
  tags: string[];
  featured_image: string | null;
  featured_alt: string;
  author: string | null;
  published_at: string | null;
  reading_minutes: number | null;
}

/**
 * Preview a post without publishing it.
 *
 * The draft is read from session storage, which is where the editor put it a
 * moment ago — so this shows the *unsaved* article, which is the only version
 * anyone previews for. It is the public article page's own layout and the public
 * block renderer; a preview drawn from a second set of components would be a
 * preview of nothing in particular.
 *
 * Session storage rather than a query string because a block document does not fit
 * in a URL, and rather than the server because there is nothing on the server yet.
 */
export function PostPreviewScreen() {
  // Session storage is an external store, so it is read as one: `useSyncExternalStore`
  // gives the server `null` and the browser the real value on the first client render,
  // with no effect, no cascading setState and no hydration mismatch. The snapshot is
  // the raw string — returning a fresh parsed object every call would never compare
  // equal and would re-render forever.
  const raw = React.useSyncExternalStore(subscribeToNothing, readDraft, () => null);

  const payload = React.useMemo<PreviewPayload | null>(() => {
    if (raw === null) return null;

    try {
      return JSON.parse(raw) as PreviewPayload;
    } catch {
      // A malformed store is the empty state, not a crash.
      return null;
    }
  }, [raw]);

  if (!payload) {
    return (
      <div className="mx-auto max-w-lg py-24 text-center">
        <h1 className="text-lg font-semibold text-[var(--color-foreground)]">
          Nothing to preview
        </h1>
        <p className="mt-2 text-sm leading-relaxed text-[var(--color-foreground-muted)]">
          A preview is handed over by the editor when you press Preview, and it lives
          only in this tab. Open it from the post you are working on.
        </p>
      </div>
    );
  }

  return (
    <>
      <div className="mb-6 flex flex-wrap items-center gap-2 rounded-[var(--radius-md)] border border-[var(--color-primary)]/30 bg-[var(--color-primary-subtle)]/50 px-3 py-2 text-xs text-[var(--color-foreground-muted)]">
        <Eye className="size-3.5 text-[var(--color-primary)]" aria-hidden="true" />
        <span className="font-medium text-[var(--color-foreground)]">Preview</span>
        <span>
          This is the draft in your editor, not what is live. Nothing here has been
          published, and nobody else can see this page.
        </span>
      </div>

      <article className="mx-auto w-full max-w-[46rem] pb-16">
        <header className="flex flex-col gap-5">
          <div className="flex flex-wrap items-center gap-2">
            {payload.category ? (
              <Badge variant="neutral" size="md">
                {payload.category}
              </Badge>
            ) : null}
            <span className="tabular font-mono text-[0.6875rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
              {payload.reading_minutes ?? 1} min read
            </span>
          </div>

          <h1 className="text-heading-1 text-balance sm:text-display-lg">
            {payload.title || "Untitled post"}
          </h1>

          {payload.excerpt ? (
            <p className="text-body-lg text-[var(--color-foreground-muted)]">{payload.excerpt}</p>
          ) : null}

          <div className="flex items-center gap-3 border-t border-[var(--color-border-subtle)] pt-5 text-sm">
            <div className="flex flex-col">
              {payload.author ? (
                <span className="font-medium text-[var(--color-foreground)]">{payload.author}</span>
              ) : null}
              <span className="text-[var(--color-foreground-subtle)]">
                {payload.published_at ? formatDate(payload.published_at) : "Not published yet"}
              </span>
            </div>
          </div>
        </header>

        {payload.featured_image ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={payload.featured_image}
            alt={payload.featured_alt}
            className="mt-8 w-full rounded-[var(--radius-xl)] border border-[var(--color-border-subtle)] object-cover"
          />
        ) : null}

        <div className="mt-10">
          <BlockRenderer document={payload.blocks} className="gap-6" />
        </div>

        {payload.tags.length > 0 ? (
          <div className="mt-10 flex flex-wrap items-center gap-2 border-t border-[var(--color-border-subtle)] pt-6">
            <span className="eyebrow">Tagged</span>
            {payload.tags.map((tag) => (
              <Badge key={tag} variant="neutral">
                {tag}
              </Badge>
            ))}
          </div>
        ) : null}
      </article>
    </>
  );
}
