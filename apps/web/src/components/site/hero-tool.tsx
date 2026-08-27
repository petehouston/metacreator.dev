"use client";

import Link from "next/link";
import * as React from "react";

import { cn } from "@/lib/utils";

/**
 * A real, working tool in the hero.
 *
 * The landing page's whole strategy is delivering value before asking for anything
 * (see docs/01), and this is where that happens: a visitor gets a useful answer in
 * under five seconds, before any signup prompt exists.
 *
 * It counts locally rather than calling the API. Character counting is trivial
 * arithmetic, and keeping it local means the hero is interactive on first paint with
 * no request in the critical path — which is also what protects LCP.
 */
const SURFACES = [
  { key: "x", label: "X post", limit: 280, fold: null, weighted: true },
  { key: "instagram", label: "Instagram caption", limit: 2200, fold: 125, weighted: false },
  { key: "tiktok", label: "TikTok caption", limit: 2200, fold: 90, weighted: false },
  { key: "youtube", label: "YouTube title", limit: 100, fold: 60, weighted: false },
  { key: "linkedin", label: "LinkedIn post", limit: 3000, fold: 210, weighted: false },
] as const;

const SAMPLE =
  "I spent 90 days posting daily on every platform. Here's what actually moved the needle 👇";

export function HeroTool() {
  const [text, setText] = React.useState(SAMPLE);

  const counts = React.useMemo(() => measure(text), [text]);

  return (
    <div className="panel flex flex-col gap-4 rounded-[var(--radius-xl)] p-5 shadow-[var(--shadow-raised)]">
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-sm font-semibold text-[var(--color-foreground)]">
          Character counter
        </h2>
        <span className="flex items-center gap-1.5 font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
          <span className="size-1.5 animate-pulse rounded-full bg-[var(--color-accent)]" />
          Live
        </span>
      </div>

      <label htmlFor="hero-tool-input" className="sr-only">
        Paste your caption or post
      </label>
      <textarea
        id="hero-tool-input"
        value={text}
        onChange={(event) => setText(event.target.value)}
        rows={3}
        maxLength={5000}
        placeholder="Paste a caption, title or post…"
        className="w-full resize-none rounded-[var(--radius-md)] border border-[var(--color-border)] bg-[var(--color-surface-sunken)] p-3 text-sm leading-relaxed text-[var(--color-foreground)] placeholder:text-[var(--color-foreground-subtle)] focus:border-[var(--color-ring)] focus:outline-none focus:ring-2 focus:ring-[var(--color-ring)]/25"
      />

      <ul className="flex flex-col gap-1.5" aria-live="polite">
        {SURFACES.map((surface) => {
          const used = surface.weighted ? counts.weighted : counts.graphemes;
          const over = used > surface.limit;
          const cut = surface.fold !== null && used > surface.fold;
          const pct = Math.min(100, (used / surface.limit) * 100);

          return (
            <li key={surface.key} className="flex items-center gap-3">
              <span className="w-36 shrink-0 truncate text-xs text-[var(--color-foreground-muted)]">
                {surface.label}
              </span>

              <span className="h-1 flex-1 overflow-hidden rounded-full bg-[var(--color-surface-sunken)]">
                <span
                  className={cn(
                    "block h-full rounded-full transition-[width] duration-200",
                    over
                      ? "bg-[var(--color-danger)]"
                      : cut
                        ? "bg-[var(--color-warning)]"
                        : "bg-[var(--color-accent)]",
                  )}
                  style={{ width: `${Math.max(2, pct)}%` }}
                />
              </span>

              <span
                className={cn(
                  "tabular w-24 shrink-0 text-right text-xs",
                  over ? "font-semibold text-[var(--color-danger)]" : "text-[var(--color-foreground-subtle)]",
                )}
              >
                {used} / {surface.limit.toLocaleString()}
              </span>
            </li>
          );
        })}
      </ul>

      <p className="text-xs text-[var(--color-foreground-subtle)]">
        Emoji count as one character. Links count as 23 on X.{" "}
        <Link
          href="/tools/social-media-character-counter"
          className="text-[var(--color-primary)] hover:underline"
        >
          Open the full tool →
        </Link>
      </p>
    </div>
  );
}

/**
 * Mirrors the server's counting rules (see PostLength on the API side): graphemes,
 * not codepoints; CJK weighted double on X; links a flat 23.
 */
function measure(text: string): { graphemes: number; weighted: number } {
  const withoutUrls = text.replace(/\bhttps?:\/\/\S+/giu, "");
  const urlCount = (text.match(/\bhttps?:\/\/\S+/giu) ?? []).length;

  const segmenter =
    typeof Intl !== "undefined" && "Segmenter" in Intl
      ? new Intl.Segmenter("en", { granularity: "grapheme" })
      : null;

  const clusters = segmenter
    ? [...segmenter.segment(withoutUrls)].map((s) => s.segment)
    : [...withoutUrls];

  let weighted = 0;

  for (const cluster of clusters) {
    const code = cluster.codePointAt(0) ?? 0;
    const doubleWidth =
      (code >= 0x1100 && code <= 0x115f) ||
      (code >= 0x2e80 && code <= 0xa4cf) ||
      (code >= 0xac00 && code <= 0xd7a3) ||
      (code >= 0xf900 && code <= 0xfaff) ||
      (code >= 0xff00 && code <= 0xff60);

    weighted += doubleWidth ? 2 : 1;
  }

  const graphemeTotal = segmenter
    ? [...segmenter.segment(text)].length
    : [...text].length;

  return { graphemes: graphemeTotal, weighted: weighted + urlCount * 23 };
}
