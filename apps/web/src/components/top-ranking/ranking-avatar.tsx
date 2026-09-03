"use client";

import * as React from "react";

import { cn } from "@/lib/utils";

/**
 * An account's picture, with a monogram behind it.
 *
 * **A plain `<img>`, not `next/image`, and deliberately.** These URLs point at
 * seven different platform CDNs whose hostnames change between requests — Meta
 * answers from `scontent-dfw6-2` one minute and `scontent-lhr8-1` the next — so
 * allow-listing them in `remotePatterns` would be a list that is wrong by the time
 * it ships, and every unlisted host renders as nothing at all. The optimiser would
 * also be proxying a few hundred third-party images through our own server for a
 * saving of a couple of kilobytes at 40px. The account menu reaches the same
 * conclusion for the same reason.
 *
 * **The monogram is not a placeholder, it is the design.** Roughly one row in
 * fourteen has no resolvable picture — some platforms will not tell an anonymous
 * request, and Facebook publishes no handle for half its Pages — so a monogram in
 * the platform's own colour has to look like a decision rather than a gap. A `?`
 * in a grey circle would read as broken; two initials read as a person.
 *
 * `onError` matters as much as the null check: a signed CDN link can die between
 * the weekly refresh and this render, and swapping to the monogram on the failed
 * load is what stops a reader ever seeing a torn-image icon.
 *
 * Decorative to assistive technology — `alt=""`, and the monogram is `aria-hidden`.
 * The account's name is already on the row beside it, and labelling the picture too
 * makes a screen reader read every entry as a stutter.
 */
export function RankingAvatar({
  src,
  initials,
  accent,
  size = "md",
}: {
  src: string | null;
  initials: string;
  /** An oklch triple (`L C H`) from the page's platform. */
  accent: string;
  size?: "sm" | "md" | "lg";
}) {
  const [failed, setFailed] = React.useState(false);

  // Reset when the row changes: without this, a re-sorted list would carry one
  // row's failure onto whichever row reuses the component.
  const [lastSrc, setLastSrc] = React.useState(src);

  if (src !== lastSrc) {
    setLastSrc(src);
    setFailed(false);
  }

  const dimensions = {
    sm: "size-8 text-[0.625rem]",
    md: "size-10 text-xs",
    lg: "size-16 text-lg sm:size-20 sm:text-xl",
  }[size];

  const showImage = src !== null && !failed;

  return (
    <span
      className={cn(
        "relative flex shrink-0 items-center justify-center overflow-hidden rounded-full font-semibold",
        "ring-1 ring-inset ring-[var(--color-border)]",
        dimensions,
      )}
      style={
        showImage
          ? undefined
          : {
              // A tint of the platform's own hue rather than a neutral grey, so a
              // column of monograms still reads as belonging to this page.
              backgroundColor: `oklch(${accent} / 0.14)`,
              color: `oklch(${accent})`,
            }
      }
    >
      {showImage ? (
        /* eslint-disable-next-line @next/next/no-img-element */
        <img
          src={src}
          alt=""
          loading="lazy"
          decoding="async"
          // The platforms have no reason to receive our page addresses in a
          // Referer header, and some of them use it to gate hotlinking.
          referrerPolicy="no-referrer"
          onError={() => setFailed(true)}
          className="size-full object-cover"
        />
      ) : (
        <span aria-hidden="true">{initials}</span>
      )}
    </span>
  );
}
