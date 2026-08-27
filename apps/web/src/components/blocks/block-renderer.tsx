import {
  AlertTriangle,
  ArrowUpRight,
  Check,
  ChevronDown,
  Info,
  Lightbulb,
  OctagonAlert,
} from "lucide-react";
import Image from "next/image";
import Link from "next/link";
import type * as React from "react";

import { Button } from "@/components/ui/button";
import { TierBadge } from "@/components/ui/badge";
import { CopyButton } from "@/components/ui/copy-button";
import { cn } from "@/lib/utils";
import type { Block, BlockDocument, ToolSummary } from "@/lib/types";

/** A list item after normalising the two stored shapes. */
interface ListItem {
  html: string;
  checked?: boolean;
}

/** FAQ items exist under two key pairs; see the `faq` renderer. */
interface FaqItem {
  question?: string;
  answer?: string;
  q?: string;
  a?: string;
}

/**
 * Renders portable block JSON (ADR 0003).
 *
 * This is the *single* renderer: the editor mounts these same components behind its
 * selection chrome, which is what makes WYSIWYG structural rather than a promise
 * somebody has to keep in sync.
 */
export function BlockRenderer({
  document,
  className,
  tools,
}: {
  document: BlockDocument | null | undefined;
  className?: string;
  /**
   * Tools referenced by `toolCard` blocks, keyed by slug.
   *
   * Resolved by the page and passed in rather than fetched here: this component is
   * also what the editor mounts, so it has to stay free of server-only imports —
   * and one lookup per page beats one request per block.
   */
  tools?: Record<string, ToolSummary>;
}) {
  if (!document?.blocks?.length) return null;

  return (
    <div className={cn("flex flex-col gap-5", className)}>
      {document.blocks.map((block) => (
        <BlockNode key={block.id} block={block} tools={tools} />
      ))}
    </div>
  );
}

/** Every `toolCard` slug in a document, for the page to resolve up front. */
export function toolSlugsIn(document: BlockDocument | null | undefined): string[] {
  const slugs = (document?.blocks ?? [])
    .filter((block) => block.type === "toolCard")
    .map((block) => String(block.data?.toolSlug ?? ""))
    .filter(Boolean);

  return [...new Set(slugs)];
}

function BlockNode({ block, tools }: { block: Block; tools?: Record<string, ToolSummary> }) {
  const Renderer = RENDERERS[block.type];

  // An unknown type means content written by a newer deploy. Render a labelled
  // placeholder rather than throwing — a rollback must never destroy or hide content.
  if (!Renderer) return <UnknownBlock type={block.type} />;

  return <Renderer data={block.data} tools={tools} />;
}

type BlockProps = {
  data: Record<string, unknown>;
  tools?: Record<string, ToolSummary>;
};

const RENDERERS: Record<string, React.ComponentType<BlockProps>> = {
  paragraph: ({ data }: BlockProps) => (
    <div
      className="text-[var(--color-foreground-muted)] leading-relaxed [&_a]:text-[var(--color-primary)] [&_a]:underline [&_a]:underline-offset-2 [&_code]:rounded [&_code]:bg-[var(--color-surface-sunken)] [&_code]:px-1 [&_code]:py-0.5 [&_code]:font-mono [&_code]:text-[0.9em] [&_strong]:font-semibold [&_strong]:text-[var(--color-foreground)]"
      // Sanitised on save AND on render (see docs/21). This is the one place in the
      // app where raw HTML is injected, and an ESLint rule forbids it elsewhere.
      dangerouslySetInnerHTML={{ __html: String(data.html ?? "") }}
    />
  ),

  heading: ({ data }: BlockProps) => {
    const level = Math.min(Math.max(Number(data.level ?? 2), 2), 4);
    const Tag = `h${level}` as "h2" | "h3" | "h4";

    return (
      <Tag
        id={slugify(String(data.text ?? ""))}
        className={cn(
          "scroll-mt-24 font-semibold text-[var(--color-foreground)]",
          level === 2 && "text-heading-2 mt-2",
          level === 3 && "text-heading-3 mt-1",
          level === 4 && "text-base",
        )}
      >
        {String(data.text ?? "")}
      </Tag>
    );
  },

  list: ({ data }: BlockProps) => {
    // Two shapes in the wild: tool instructions store plain HTML strings, blog
    // posts store { html, checked }. Normalise rather than migrating either.
    const items = ((data.items ?? []) as (string | ListItem)[]).map((item) =>
      typeof item === "string" ? { html: item, checked: false } : item,
    );
    const style = String(data.style ?? "unordered");
    const ordered = style === "ordered";
    const checklist = style === "checklist";
    const Tag = ordered ? "ol" : "ul";

    return (
      <Tag
        className={cn(
          "flex flex-col gap-2 text-[var(--color-foreground-muted)]",
          checklist
            ? "list-none pl-0"
            : cn("pl-5 marker:text-[var(--color-foreground-subtle)]", ordered ? "list-decimal" : "list-disc"),
        )}
      >
        {items.map((item, index) => (
          <li
            key={index}
            className={cn(
              "leading-relaxed [&_strong]:font-semibold [&_strong]:text-[var(--color-foreground)]",
              checklist && "flex items-start gap-2.5",
            )}
          >
            {checklist ? (
              <>
                {/* Decorative: the checked state is conveyed by the text itself,
                    so a screen reader should not announce a stray checkbox. */}
                <span
                  aria-hidden="true"
                  className={cn(
                    "mt-0.5 grid size-4 shrink-0 place-items-center rounded border",
                    item.checked
                      ? "border-[var(--color-success)] bg-[var(--color-success)] text-white"
                      : "border-[var(--color-border-strong)]",
                  )}
                >
                  {item.checked ? <Check className="size-3" strokeWidth={3} /> : null}
                </span>
                <span dangerouslySetInnerHTML={{ __html: item.html }} />
              </>
            ) : (
              <span dangerouslySetInnerHTML={{ __html: item.html }} />
            )}
          </li>
        ))}
      </Tag>
    );
  },

  quote: ({ data }: BlockProps) => {
    const pull = data.variant === "pull";

    return (
      <blockquote
        className={cn(
          pull
            ? "my-4 border-y border-[var(--color-border)] py-6 text-center"
            : "border-l-2 border-[var(--color-primary)] pl-5",
        )}
      >
        <p
          className={cn(
            "text-[var(--color-foreground)] [&_a]:underline [&_em]:italic [&_strong]:font-semibold",
            pull ? "text-heading-3 text-balance" : "text-body-lg italic",
          )}
          dangerouslySetInnerHTML={{ __html: String(data.text ?? "") }}
        />
        {data.cite ? (
          <cite className="mt-2 block text-sm not-italic text-[var(--color-foreground-subtle)]">
            — {String(data.cite)}
          </cite>
        ) : null}
      </blockquote>
    );
  },

  callout: ({ data }: BlockProps) => {
    const tone = String(data.tone ?? "info") as keyof typeof CALLOUTS;
    const config = CALLOUTS[tone] ?? CALLOUTS.info;
    const Icon = config.icon;

    return (
      <aside
        className={cn(
          "flex items-start gap-3 rounded-[var(--radius-md)] border p-4",
          config.className,
        )}
      >
        <Icon className="mt-0.5 size-4 shrink-0" />
        <div className="flex flex-col gap-1">
          {data.title ? (
            <strong className="text-sm font-semibold text-[var(--color-foreground)]">
              {String(data.title)}
            </strong>
          ) : null}
          <div
            className="text-sm leading-relaxed [&_a]:underline [&_code]:font-mono [&_p]:m-0"
            dangerouslySetInnerHTML={{ __html: String(data.html ?? "") }}
          />
        </div>
      </aside>
    );
  },

  code: ({ data }: BlockProps) => {
    const code = String(data.code ?? "");
    const label = String(data.filename ?? "") || String(data.language ?? "");

    return (
      <figure className="overflow-hidden rounded-[var(--radius-md)] border border-[var(--color-border)] bg-[var(--color-surface-sunken)]">
        {label ? (
          <figcaption className="flex items-center justify-between gap-3 border-b border-[var(--color-border-subtle)] py-1 pl-4 pr-1 font-mono text-xs text-[var(--color-foreground-subtle)]">
            {label}
            <CopyButton value={code} />
          </figcaption>
        ) : null}
        <pre className="overflow-x-auto p-4 font-mono text-xs leading-relaxed">
          <code>{code}</code>
        </pre>
      </figure>
    );
  },

  divider: () => <hr className="border-[var(--color-border-subtle)]" />,

  toolCard: ({ data, tools }: BlockProps) => {
    const slug = String(data.toolSlug ?? "");
    if (!slug) return null;

    const tool = tools?.[slug];

    // A renamed or withdrawn tool must not break the article around it: fall back
    // to a plain link rather than rendering nothing or throwing.
    if (!tool) {
      return (
        <Link
          href={`/tools/${slug}`}
          className="group flex items-center justify-between gap-4 rounded-[var(--radius-md)] border border-[var(--color-border)] bg-[var(--color-surface-sunken)] p-4 transition-colors hover:border-[var(--color-primary)]/40"
        >
          <span className="text-sm font-medium text-[var(--color-foreground)]">
            Try the {humanise(slug)}
          </span>
          <span
            aria-hidden="true"
            className="text-[var(--color-primary)] transition-transform group-hover:translate-x-0.5"
          >
            →
          </span>
        </Link>
      );
    }

    return (
      <Link
        href={`/tools/${tool.slug}`}
        className="group flex items-center gap-4 rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-[var(--color-surface-sunken)] p-5 transition-all hover:-translate-y-0.5 hover:border-[var(--color-brand-500)]/40 hover:shadow-[var(--shadow-card)]"
      >
        <div className="flex flex-1 flex-col gap-1.5">
          <div className="flex flex-wrap items-center gap-2">
            <TierBadge tier={tool.tier} />
            <span className="font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
              Mentioned in this article
            </span>
          </div>
          <span className="text-base font-semibold text-[var(--color-foreground)]">
            {tool.name}
          </span>
          {tool.tagline ? (
            <span className="text-sm leading-relaxed text-[var(--color-foreground-muted)]">
              {tool.tagline}
            </span>
          ) : null}
        </div>
        <ArrowUpRight
          aria-hidden="true"
          className="size-5 shrink-0 text-[var(--color-foreground-subtle)] transition-all group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-[var(--color-primary)]"
        />
      </Link>
    );
  },

  embed: ({ data }: BlockProps) => (
    <div className="aspect-video overflow-hidden rounded-[var(--radius-md)] border border-[var(--color-border)]">
      <iframe
        src={embedUrl(String(data.provider ?? ""), String(data.url ?? ""))}
        title="Embedded media"
        className="size-full"
        loading="lazy"
        // youtube-nocookie / player.vimeo only — enforced by the CSP frame-src list.
        allow="accelerometer; clipboard-write; encrypted-media; picture-in-picture"
        referrerPolicy="strict-origin-when-cross-origin"
        allowFullScreen
      />
    </div>
  ),

  faq: ({ data }: BlockProps) => {
    // Tools store { q, a }; posts store { question, answer }. Both are in the
    // database already, so the renderer reads either rather than forcing a migration.
    const items = ((data.items ?? []) as FaqItem[]).map((item) => ({
      question: item.question ?? item.q ?? "",
      answer: item.answer ?? item.a ?? "",
    }));

    return (
      <div className="flex flex-col gap-2">
        {items.map((item) => (
          <details
            key={item.question}
            className="panel group p-4"
          >
            <summary className="flex cursor-pointer list-none items-center justify-between gap-3 font-medium text-[var(--color-foreground)] marker:hidden">
              {item.question}
              <ChevronDown
                aria-hidden="true"
                className="size-4 shrink-0 text-[var(--color-foreground-subtle)] transition-transform group-open:rotate-180"
              />
            </summary>
            <div
              className="mt-2 text-sm leading-relaxed text-[var(--color-foreground-muted)] [&_a]:text-[var(--color-primary)] [&_a]:underline"
              dangerouslySetInnerHTML={{ __html: item.answer }}
            />
          </details>
        ))}
      </div>
    );
  },

  image: ({ data }: BlockProps) => {
    const url = String(data.url ?? "");
    if (!url) return null;

    const size = String(data.size ?? "inline");
    const caption = String(data.caption ?? "");
    const width = Number(data.width ?? 0) || 1200;
    const height = Number(data.height ?? 0) || 675;

    return (
      <figure
        className={cn(
          "flex flex-col gap-2",
          // `wide` and `full` break out of the article measure. The negative margin
          // is capped by the container, so it never causes a horizontal scrollbar.
          size === "wide" && "lg:-mx-16",
          size === "full" && "lg:-mx-24",
        )}
      >
        <Image
          src={url}
          alt={String(data.alt ?? "")}
          width={width}
          height={height}
          className="w-full rounded-[var(--radius-md)] border border-[var(--color-border-subtle)]"
          sizes="(max-width: 768px) 100vw, 768px"
        />
        {caption ? (
          <figcaption
            className="text-center text-sm text-[var(--color-foreground-subtle)] [&_a]:underline"
            dangerouslySetInnerHTML={{ __html: caption }}
          />
        ) : null}
      </figure>
    );
  },

  html: ({ data }: BlockProps) => (
    <div
      className="[&_a]:text-[var(--color-primary)] [&_a]:underline [&_p]:leading-relaxed [&_p]:text-[var(--color-foreground-muted)]"
      // Sanitised on save under the `embed` profile and again here by the same
      // profile on the server. See docs/21-security.md.
      dangerouslySetInnerHTML={{ __html: String(data.html ?? "") }}
    />
  ),

  table: ({ data }: BlockProps) => {
    const rows = (data.rows ?? []) as string[][];
    if (rows.length === 0) return null;

    const hasHeader = data.has_header !== false;
    const head = hasHeader ? rows[0] : null;
    const body = hasHeader ? rows.slice(1) : rows;

    return (
      // A wide table scrolls inside its own box rather than widening the article.
      <div className="overflow-x-auto rounded-[var(--radius-md)] border border-[var(--color-border)]">
        <table className="w-full border-collapse text-sm">
          {head ? (
            <thead>
              <tr className="bg-[var(--color-surface-sunken)]">
                {head.map((cell, index) => (
                  <th
                    key={index}
                    scope="col"
                    className="px-4 py-2.5 text-left font-semibold text-[var(--color-foreground)]"
                    dangerouslySetInnerHTML={{ __html: cell }}
                  />
                ))}
              </tr>
            </thead>
          ) : null}
          <tbody>
            {body.map((row, rowIndex) => (
              <tr
                key={rowIndex}
                className="border-t border-[var(--color-border-subtle)] text-[var(--color-foreground-muted)]"
              >
                {row.map((cell, cellIndex) => (
                  <td
                    key={cellIndex}
                    className="px-4 py-2.5 align-top"
                    dangerouslySetInnerHTML={{ __html: cell }}
                  />
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    );
  },

  button: ({ data }: BlockProps) => {
    const href = String(data.href ?? "");
    const label = String(data.label ?? "");
    if (!href || !label) return null;

    const variant = String(data.variant ?? "primary") as "primary" | "secondary" | "outline";

    return (
      <div className="flex">
        <Button asChild variant={variant} size="lg">
          <Link href={href}>{label}</Link>
        </Button>
      </div>
    );
  },
};

const CALLOUTS = {
  info: {
    icon: Info,
    className:
      "border-[var(--color-info)]/25 bg-[var(--color-info)]/8 text-[var(--color-foreground-muted)]",
  },
  tip: {
    icon: Lightbulb,
    className:
      "border-[var(--color-success)]/25 bg-[var(--color-success)]/8 text-[var(--color-foreground-muted)]",
  },
  warning: {
    icon: AlertTriangle,
    className:
      "border-[var(--color-warning)]/25 bg-[var(--color-warning)]/8 text-[var(--color-foreground-muted)]",
  },
  danger: {
    icon: OctagonAlert,
    className:
      "border-[var(--color-danger)]/25 bg-[var(--color-danger)]/8 text-[var(--color-foreground-muted)]",
  },
} as const;

function UnknownBlock({ type }: { type: string }) {
  return (
    <div className="rounded-[var(--radius-md)] border border-dashed border-[var(--color-border-strong)] p-4 text-sm text-[var(--color-foreground-subtle)]">
      This content block ({type}) is not supported by the current version of the site.
    </div>
  );
}

/** Privacy-preserving hosts only; these match the CSP `frame-src` allow-list. */
function embedUrl(provider: string, url: string): string {
  if (provider === "youtube") {
    const id = url.match(/(?:v=|youtu\.be\/|embed\/|shorts\/)([A-Za-z0-9_-]{11})/)?.[1];
    return id ? `https://www.youtube-nocookie.com/embed/${id}` : url;
  }

  if (provider === "vimeo") {
    const id = url.match(/vimeo\.com\/(\d+)/)?.[1];
    return id ? `https://player.vimeo.com/video/${id}` : url;
  }

  return url;
}

function slugify(value: string): string {
  return value
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-|-$/g, "");
}

function humanise(slug: string): string {
  return slug.replace(/-/g, " ");
}
