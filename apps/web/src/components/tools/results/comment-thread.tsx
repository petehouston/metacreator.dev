"use client";

import { MessageSquare, ThumbsUp } from "lucide-react";
import * as React from "react";

import { cn, formatNumber } from "@/lib/utils";
import type { ToolResult } from "@/lib/types";

interface Comment {
  author: string;
  body: string;
  avatar?: string;
  published_at?: string;
  published?: string;
  likes?: number;
  replies?: number;
  link?: string;
}

/**
 * Comments as the platform draws them: avatar, author, relative time, body, then
 * the like and reply counts underneath. A table of comments reads like a
 * spreadsheet — the shape people recognise is the card, and the whole card is the
 * link to the comment in place on the video.
 */
export function CommentThreadResult({ result }: { result: ToolResult }) {
  const comments = (result.data.comments ?? []) as Comment[];

  if (comments.length === 0) return null;

  return (
    <ul className="flex flex-col gap-1">
      {comments.map((comment, index) => (
        <CommentCard key={comment.link || index} comment={comment} />
      ))}
    </ul>
  );
}

function CommentCard({ comment }: { comment: Comment }) {
  const linked = Boolean(comment.link);

  const card = (
    <>
      <Avatar src={comment.avatar} name={comment.author} />

      <div className="flex min-w-0 flex-col gap-1">
        <p className="flex flex-wrap items-baseline gap-x-2">
          <span className="text-sm font-semibold text-[var(--color-foreground)]">
            {comment.author || "Unknown"}
          </span>
          <span className="text-xs text-[var(--color-foreground-subtle)]">
            {relativeTime(comment.published_at) ?? comment.published ?? ""}
          </span>
        </p>

        {/* Comments are the author's words, newlines and all — rendered as text,
            never as markup. */}
        <p className="whitespace-pre-wrap break-words text-sm text-[var(--color-foreground)]">
          {comment.body}
        </p>

        <p className="mt-1 flex items-center gap-4 text-xs text-[var(--color-foreground-subtle)]">
          <span className="tabular flex items-center gap-1.5">
            <ThumbsUp className="size-3.5" aria-hidden />
            {formatNumber(comment.likes ?? 0)}
            <span className="sr-only">likes</span>
          </span>
          {(comment.replies ?? 0) > 0 && (
            <span className="tabular flex items-center gap-1.5">
              <MessageSquare className="size-3.5" aria-hidden />
              {formatNumber(comment.replies ?? 0)}
              <span className="sr-only">replies</span>
            </span>
          )}
        </p>
      </div>
    </>
  );

  const shape =
    "flex gap-3 rounded-[var(--radius-md)] p-3 transition-colors";

  if (!linked) {
    return (
      <li className={cn(shape, "bg-transparent")}>{card}</li>
    );
  }

  return (
    <li>
      <a
        href={comment.link}
        target="_blank"
        rel="noopener noreferrer"
        // The card is the hit target, so the hover tint has to come from the card
        // and not from an underlined run of text inside it.
        className={cn(
          shape,
          "hover:bg-[var(--color-surface-sunken)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primary)]",
        )}
      >
        {card}
      </a>
    </li>
  );
}

function Avatar({ src, name }: { src?: string; name: string }) {
  const [failed, setFailed] = React.useState(false);
  const usable = src && src.startsWith("https://") && !failed;

  if (usable) {
    return (
      // Avatars are hotlinked from Google's CDN, which next/image would need
      // configured per host.
      // eslint-disable-next-line @next/next/no-img-element
      <img
        src={src}
        alt=""
        width={40}
        height={40}
        loading="lazy"
        referrerPolicy="no-referrer"
        onError={() => setFailed(true)}
        className="size-10 shrink-0 rounded-full bg-[var(--color-surface-sunken)] object-cover"
      />
    );
  }

  return (
    <span
      aria-hidden
      className="flex size-10 shrink-0 items-center justify-center rounded-full bg-[var(--color-surface-sunken)] text-sm font-semibold text-[var(--color-foreground-subtle)]"
    >
      {name.replace(/^@/, "").charAt(0).toUpperCase() || "?"}
    </span>
  );
}

const UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
  ["year", 31_536_000],
  ["month", 2_592_000],
  ["week", 604_800],
  ["day", 86_400],
  ["hour", 3_600],
  ["minute", 60],
];

/** "2 years ago", the way the platform stamps a comment. */
function relativeTime(iso?: string): string | null {
  if (!iso) return null;

  const then = Date.parse(iso);

  if (Number.isNaN(then)) return null;

  const seconds = Math.max(0, (Date.now() - then) / 1000);
  const format = new Intl.RelativeTimeFormat("en", { numeric: "auto" });

  for (const [unit, size] of UNITS) {
    if (seconds >= size) {
      return format.format(-Math.floor(seconds / size), unit);
    }
  }

  return format.format(-Math.floor(seconds), "second");
}
