"use client";

import dynamic from "next/dynamic";
import type * as React from "react";

import type { ToolDetail } from "@/lib/types";

/**
 * Custom tool UIs, keyed on the tool's registry key.
 *
 * Almost every tool is a generated form plus one of the shared result renderers,
 * which is the whole point of the engine (docs/08). A handful are not: tools that
 * are genuinely interactive — you drop a file, you drag a slider, you watch the
 * thing change — cannot be expressed as "submit, wait, render", and pretending
 * otherwise makes them worse to use than the extension the visitor would install
 * instead.
 *
 * A key in this map takes over the workspace entirely: `ToolRunner` renders the
 * component in place of the form and result, and everything around it on the tool
 * page — masthead, access gate, instructions, FAQ, related tools, SEO — is
 * unchanged, because none of that is the workspace's business.
 *
 * Loaded with `next/dynamic` so a canvas renderer stays out of the bundle of every
 * tool page that does not use it. `ssr: false` because these components are built
 * on browser APIs — canvas, `FileReader`, object URLs — with no server equivalent
 * worth shimming.
 */
export interface CustomToolProps {
  tool: ToolDetail;
}

const YouTubeCommentGenerator = dynamic(
  () => import("@/tools/custom/youtube-comment-generator"),
  { ssr: false, loading: () => <CustomToolSkeleton /> },
);

/**
 * One workspace for every mock-up card generator.
 *
 * The five platforms differ only in which fields they collect and how the card is
 * painted, and both are described elsewhere — the fields by the tool's own input
 * schema, the painting by `social-card.ts`. So they share a component rather than
 * having five near-identical ones, and a sixth platform is a layout plus a key.
 */
const SocialCardGenerator = dynamic(() => import("@/tools/custom/social-card-generator"), {
  ssr: false,
  loading: () => <CustomToolSkeleton />,
});

/**
 * This tool's workspace, or null when the generated form should render instead.
 *
 * Returns the element rather than the component type on purpose: handing a
 * component back to the caller reads to React's lint rules — reasonably — as a
 * component being made up during render, and the state-resetting bug that rule
 * exists to catch is one this registry would be a plausible source of.
 */
export function renderCustomTool(tool: ToolDetail): React.ReactNode | null {
  switch (tool.key) {
    case "youtube.comment-generator":
      return <YouTubeCommentGenerator tool={tool} />;
    case "facebook.post-generator":
    case "instagram.post-generator":
    case "x.reply-generator":
    case "pinterest.pin-generator":
    case "tiktok.comment-generator":
      return <SocialCardGenerator tool={tool} />;
    default:
      return null;
  }
}

/**
 * Held while the chunk loads. Sized like the workspace it is standing in for, so
 * the page does not jump when the real thing arrives.
 */
function CustomToolSkeleton() {
  return (
    <div className="panel p-5 sm:p-7" aria-busy="true">
      <div className="flex flex-col gap-4">
        <div className="h-5 w-1/3 animate-pulse rounded bg-[var(--color-surface-sunken)]" />
        <div className="h-11 animate-pulse rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)]" />
        <div className="h-32 animate-pulse rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)]" />
      </div>
      <span className="sr-only">Loading the tool…</span>
    </div>
  );
}
