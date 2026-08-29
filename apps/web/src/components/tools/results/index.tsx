"use client";

import { AlertTriangle, Download, TriangleAlert } from "lucide-react";
import * as React from "react";

import { CommentThreadResult } from "@/components/tools/results/comment-thread";
import { SocialPreviewResult } from "@/components/tools/results/social-preview";
import { CopyButton } from "@/components/ui/copy-button";
import { cn, formatNumber } from "@/lib/utils";
import type { ResultView, ToolResult } from "@/lib/types";

/**
 * One renderer per result view type.
 *
 * A tool declares its view; the frontend already knows how to draw it. This map is
 * the reason 60 tools do not need 60 React components (see docs/08).
 */
const RENDERERS: Record<
  ResultView,
  React.ComponentType<{ result: ToolResult }>
> = {
  keyvalue: KeyValueResult,
  table: TableResult,
  "list.cards": CardsResult,
  "list.comments": CommentThreadResult,
  "text.blocks": TextBlocksResult,
  "score.report": ScoreResult,
  "media.gallery": MediaResult,
  "diff.compare": CompareResult,
  "chart.timeseries": FallbackResult,
  "download.bundle": MediaResult,
  "preview.social": SocialPreviewResult,
};

export function ResultRenderer({ result }: { result: ToolResult }) {
  const Renderer = RENDERERS[result.view] ?? FallbackResult;

  return (
    <div className="flex flex-col gap-5">
      {result.summary && (
        <p className="text-body-lg font-medium text-[var(--color-foreground)]">
          {result.summary}
        </p>
      )}

      <PaletteStrip palette={result.meta.palette} />

      <Renderer result={result} />

      {isCodeBlock(result.meta.code) && <CodeCard block={result.meta.code} />}

      {result.meta.json !== undefined && result.meta.json !== null && (
        <JsonCard payload={result.meta.json} />
      )}

      {result.warnings.length > 0 && (
        <ul className="flex flex-col gap-2">
          {result.warnings.map((warning) => (
            <li
              key={warning}
              className="flex items-start gap-2 rounded-[var(--radius-md)] border border-[var(--color-warning)]/25 bg-[var(--color-warning)]/8 p-3 text-sm text-[var(--color-foreground-muted)]"
            >
              <TriangleAlert className="mt-0.5 size-4 shrink-0 text-[var(--color-warning)]" />
              <span>{warning}</span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

/* ── keyvalue ─────────────────────────────────────────────────────────────── */

interface Pair {
  label: string;
  value: string | number;
  hint?: string;
  tone?: "positive" | "neutral" | "warning" | "negative";
}

function KeyValueResult({ result }: { result: ToolResult }) {
  const pairs = (result.data.pairs ?? []) as Pair[];

  return (
    <dl className="grid gap-3 sm:grid-cols-2">
      {pairs.map((pair) => (
        <div
          key={pair.label}
          className="flex flex-col gap-1 rounded-[var(--radius-md)] border border-[var(--color-border)] bg-[var(--color-surface-sunken)] p-4"
        >
          <div className="flex items-start justify-between gap-3">
            <dt className="font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
              {pair.label}
            </dt>
            {/* Hard right, so the icons line up down the grid however long the
                labels beside them run. */}
            <CopyButton
              value={String(pair.value)}
              label=""
              copiedLabel=""
              size="icon"
              className="-mt-1 size-7 shrink-0"
              aria-label={`Copy ${pair.label}`}
            />
          </div>
          <dd
            className={cn(
              "tabular text-heading-3 break-words",
              pair.tone === "positive" && "text-[var(--color-success)]",
              pair.tone === "warning" && "text-[var(--color-warning)]",
              pair.tone === "negative" && "text-[var(--color-danger)]",
            )}
          >
            {pair.value}
          </dd>
          {pair.hint && (
            <p className="text-xs text-[var(--color-foreground-subtle)]">
              {pair.hint}
            </p>
          )}
        </div>
      ))}
    </dl>
  );
}

/* ── table ────────────────────────────────────────────────────────────────── */

function TableResult({ result }: { result: ToolResult }) {
  const columns = (result.data.columns ?? []) as {
    key: string;
    label: string;
    align?: string;
    type?: string;
    copyable?: boolean;
    copy_all?: boolean;
  }[];
  const rows = (result.data.rows ?? []) as Record<string, unknown>[];

  return (
    // Wide tables scroll inside their own container; the page body never scrolls
    // horizontally.
    <div className="overflow-x-auto rounded-[var(--radius-md)] border border-[var(--color-border)]">
      <table className="w-full min-w-[36rem] text-sm">
        <thead className="bg-[var(--color-surface-sunken)]">
          <tr>
            {columns.map((column) => (
              <th
                key={column.key}
                scope="col"
                className={cn(
                  "px-4 py-3 font-mono text-[0.625rem] font-semibold uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]",
                  column.align === "right" ? "text-right" : "text-left",
                )}
              >
                <span className="inline-flex items-center gap-2">
                  {column.label}
                  {/* One button for the whole column, so a long tag list does not
                      have to be copied a row at a time. */}
                  {column.copy_all && rows.length > 0 && (
                    <CopyButton
                      value={rows
                        .map((row) => String(row[column.key] ?? ""))
                        .filter((value) => value !== "")
                        .join(", ")}
                      label="Copy all"
                      copiedLabel="Copied all"
                      size="sm"
                      className="h-6 px-1.5 text-[0.625rem] tracking-normal normal-case"
                      aria-label={`Copy all ${column.label} values`}
                    />
                  )}
                </span>
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row, index) => (
            <tr
              key={index}
              className="border-t border-[var(--color-border-subtle)] hover:bg-[var(--color-surface-sunken)]/60"
            >
              {columns.map((column) => (
                <td
                  key={column.key}
                  className={cn(
                    "tabular px-4 py-3 align-top text-[var(--color-foreground)]",
                    column.align === "right" ? "text-right" : "text-left",
                    // Labels stay on one line; only the value column wraps.
                    column.key === "value" ? "w-full" : "whitespace-nowrap",
                  )}
                >
                  {column.type === "color" ? (
                    <Swatch value={row[column.key]} />
                  ) : column.type === "download" ? (
                    <DownloadCell value={row[column.key]} />
                  ) : (
                    <Cell
                      value={row[column.key]}
                      copyable={column.copyable ?? column.key === "value"}
                    />
                  )}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

/**
 * A download column holds a file URL, not something anyone reads: a CDN path with
 * a crop spec in it tells the reader nothing and wraps the table. Show the verb.
 */
function DownloadCell({ value }: { value: unknown }) {
  const url = typeof value === "string" ? value : "";
  if (!/^https?:\/\//.test(url)) return <>—</>;

  return (
    <a
      href={url}
      download
      target="_blank"
      rel="noopener noreferrer"
      className="inline-flex items-center gap-1 whitespace-nowrap text-[var(--color-primary)] hover:underline"
    >
      <Download className="size-3.5" aria-hidden="true" />
      Download
    </a>
  );
}

function Cell({
  value,
  copyable = false,
}: {
  value: unknown;
  copyable?: boolean;
}) {
  if (value === null || value === undefined || value === "") return <>—</>;

  const isUrl = typeof value === "string" && /^https?:\/\//.test(value);
  const body = isUrl ? (
    <a
      href={value as string}
      target="_blank"
      rel="noopener noreferrer"
      className="break-all text-[var(--color-primary)] hover:underline"
    >
      {value as string}
    </a>
  ) : (
    // Wrapping is the cell's call, not this component's: label columns are
    // whitespace-nowrap on the <td>, so inherit rather than override them.
    <span className={cn(copyable && "whitespace-pre-wrap break-words")}>
      {typeof value === "number" ? formatNumber(value) : String(value)}
    </span>
  );

  if (!copyable) return body;

  // The copy button sits hard right so the icons line up down the column, however
  // ragged the values beside them are.
  return (
    <span className="flex items-start justify-between gap-3">
      {body}
      <CopyButton
        value={String(value)}
        label=""
        copiedLabel=""
        size="icon"
        className="size-7 shrink-0"
        aria-label="Copy value"
      />
    </span>
  );
}

/**
 * Only hex and rgb()/rgba() literals are honoured, so a colour coming back from a
 * tool can never turn into arbitrary CSS.
 */
function asColor(value: unknown): string | null {
  const color = typeof value === "string" ? value.trim() : "";

  return /^#(?:[0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i.test(color) ||
    /^rgba?\(\s*[\d.\s,%/]+\)$/i.test(color)
    ? color
    : null;
}

/**
 * The palette itself, before any of the numbers about it: one full-width bar split
 * into equal bands, one per extracted colour. Tools opt in by putting a list of
 * colours in `meta.palette`.
 */
function PaletteStrip({ palette }: { palette: unknown }) {
  if (!Array.isArray(palette)) return null;

  const colors = palette
    .map(asColor)
    .filter((color): color is string => color !== null);

  if (colors.length === 0) return null;

  return (
    <div
      className="flex h-24 w-full overflow-hidden rounded-[var(--radius-md)] border border-[var(--color-border)]"
      role="img"
      aria-label={`Extracted palette: ${colors.join(", ")}`}
    >
      {colors.map((color, index) => (
        <div
          key={`${color}-${index}`}
          className="flex-1"
          style={{ backgroundColor: color }}
          title={color}
        />
      ))}
    </div>
  );
}

/**
 * A column declared `type: "color"` holds a colour, not a string to read: draw it.
 */
function Swatch({ value }: { value: unknown }) {
  const color = asColor(value);

  if (!color) return <>—</>;

  return (
    <span
      className="block size-6 rounded-full border border-[var(--color-border)] shadow-[inset_0_0_0_1px_rgba(0,0,0,0.04)]"
      style={{ backgroundColor: color }}
      role="img"
      aria-label={`Colour ${color}`}
      title={color}
    />
  );
}

/* ── raw JSON card ────────────────────────────────────────────────────────── */

/**
 * Tools that fetch a structured payload carry it verbatim in `meta.json`. The table
 * above is the readable view; this is the one people copy into their own code.
 */
interface CodeBlock {
  label: string;
  text: string;
}

function isCodeBlock(value: unknown): value is CodeBlock {
  return (
    typeof value === "object" &&
    value !== null &&
    typeof (value as CodeBlock).label === "string" &&
    typeof (value as CodeBlock).text === "string" &&
    (value as CodeBlock).text !== ""
  );
}

/**
 * Raw source a tool wants to hand over whole — a feed document, a manifest, a
 * generated snippet.
 *
 * It sits outside the table on purpose: this is the payload, not a field of it,
 * and squeezing kilobytes of markup into a table cell makes both unreadable. The
 * copy button takes the entire block, since a partial copy of a document is
 * useless.
 */
function CodeCard({ block }: { block: CodeBlock }) {
  return (
    <div className="flex flex-col gap-2 rounded-[var(--radius-md)] border border-[var(--color-border)] bg-[var(--color-surface-sunken)] p-4">
      <div className="flex items-center justify-between gap-3">
        <span className="font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
          {block.label}
        </span>
        <CopyButton value={block.text} label="Copy" />
      </div>

      <pre className="max-h-[32rem] overflow-auto whitespace-pre-wrap break-all font-mono text-xs leading-relaxed text-[var(--color-foreground)]">
        {block.text}
      </pre>
    </div>
  );
}

function JsonCard({ payload }: { payload: unknown }) {
  const json = React.useMemo(() => JSON.stringify(payload, null, 2), [payload]);

  return (
    <div className="flex flex-col gap-2 rounded-[var(--radius-md)] border border-[var(--color-border)] bg-[var(--color-surface-sunken)] p-4">
      <div className="flex items-center justify-between gap-3">
        <span className="font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
          Raw JSON
        </span>
        <CopyButton value={json} label="Copy JSON" />
      </div>

      <pre className="max-h-[32rem] overflow-auto font-mono text-sm leading-relaxed text-[var(--color-foreground)]">
        {json}
      </pre>
    </div>
  );
}

/* ── list.cards ───────────────────────────────────────────────────────────── */

function CardsResult({ result }: { result: ToolResult }) {
  const items = (result.data.items ?? []) as {
    title?: string;
    body: string;
    meta?: Record<string, unknown>;
  }[];

  return (
    <div className="grid gap-3">
      {items.map((item, index) => {
        const emphasised = Boolean(item.meta?.emphasis);
        const muted = Boolean(item.meta?.muted);

        return (
          <div
            key={index}
            className={cn(
              "flex flex-col gap-2 rounded-[var(--radius-md)] border p-4",
              emphasised
                ? "border-[var(--color-primary)]/40 bg-[var(--color-primary-subtle)]"
                : "border-[var(--color-border)] bg-[var(--color-surface-sunken)]",
              muted && "opacity-70",
            )}
          >
            <div className="flex items-start justify-between gap-3">
              {item.title && (
                <h4 className="text-sm font-semibold text-[var(--color-foreground)]">
                  {item.title}
                </h4>
              )}
              <CopyButton value={item.body} />
            </div>

            <p className="text-sm leading-relaxed break-words text-[var(--color-foreground)]">
              {item.body}
            </p>

            {typeof item.meta?.note === "string" && (
              <p className="text-xs text-[var(--color-foreground-subtle)]">
                {item.meta.note}
              </p>
            )}
          </div>
        );
      })}
    </div>
  );
}

/* ── text.blocks ──────────────────────────────────────────────────────────── */

function TextBlocksResult({ result }: { result: ToolResult }) {
  const blocks = (result.data.blocks ?? []) as {
    label: string;
    text: string;
    meta?: { characters?: number; limit?: number; over_limit?: boolean };
  }[];

  const all = blocks.map((block) => block.text).join("\n\n");

  return (
    <div className="flex flex-col gap-3">
      <div className="flex justify-end">
        <CopyButton value={all} label="Copy all" variant="secondary" />
      </div>

      {blocks.map((block, index) => (
        <div
          key={index}
          className="flex flex-col gap-2 rounded-[var(--radius-md)] border border-[var(--color-border)] bg-[var(--color-surface-sunken)] p-4"
        >
          <div className="flex items-center justify-between gap-3">
            <span className="font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
              {block.label}
            </span>

            <div className="flex items-center gap-2">
              {block.meta?.characters !== undefined && (
                <span
                  className={cn(
                    "tabular text-xs",
                    block.meta.over_limit
                      ? "font-semibold text-[var(--color-danger)]"
                      : "text-[var(--color-foreground-subtle)]",
                  )}
                >
                  {block.meta.characters}
                  {block.meta.limit ? ` / ${block.meta.limit}` : ""}
                </span>
              )}
              <CopyButton value={block.text} />
            </div>
          </div>

          <p className="text-sm leading-relaxed whitespace-pre-wrap text-[var(--color-foreground)]">
            {block.text}
          </p>
        </div>
      ))}
    </div>
  );
}

/* ── score.report ─────────────────────────────────────────────────────────── */

function ScoreResult({ result }: { result: ToolResult }) {
  const overall = Number(result.data.overall ?? 0);
  const sections = (result.data.sections ?? []) as {
    key: string;
    label: string;
    score: number;
    notes?: string[];
  }[];
  const fixes = (result.data.fixes ?? []) as {
    severity: string;
    title: string;
    detail: string;
  }[];

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col items-center gap-4 sm:flex-row sm:items-center sm:gap-8">
        <ScoreDial value={overall} />

        <div className="flex flex-1 flex-col gap-3">
          {sections.map((section) => (
            <div key={section.key} className="flex flex-col gap-1">
              <div className="flex items-baseline justify-between text-sm">
                <span className="font-medium text-[var(--color-foreground)]">
                  {section.label}
                </span>
                <span className="tabular text-[var(--color-foreground-muted)]">
                  {section.score}
                </span>
              </div>

              <div
                className="h-1.5 overflow-hidden rounded-full bg-[var(--color-surface-sunken)]"
                role="meter"
                aria-valuenow={section.score}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-label={section.label}
              >
                <div
                  className="h-full rounded-full transition-[width] duration-500 ease-[var(--ease-out-quint)]"
                  style={{
                    width: `${section.score}%`,
                    backgroundColor: scoreColor(section.score),
                  }}
                />
              </div>

              {section.notes?.map((note) => (
                <p
                  key={note}
                  className="text-xs text-[var(--color-foreground-subtle)]"
                >
                  {note}
                </p>
              ))}
            </div>
          ))}
        </div>
      </div>

      {fixes.length > 0 && (
        <div className="flex flex-col gap-2">
          <h4 className="font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
            Do these first
          </h4>

          {fixes.map((fix) => (
            <div
              key={fix.title}
              className="flex items-start gap-3 rounded-[var(--radius-md)] border border-[var(--color-border)] bg-[var(--color-surface-sunken)] p-4"
            >
              <AlertTriangle
                className="mt-0.5 size-4 shrink-0"
                style={{ color: severityColor(fix.severity) }}
              />
              <div className="flex flex-col gap-1">
                <p className="text-sm font-semibold text-[var(--color-foreground)]">
                  {fix.title}
                </p>
                <p className="text-sm text-[var(--color-foreground-muted)]">
                  {fix.detail}
                </p>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

function ScoreDial({ value }: { value: number }) {
  const radius = 52;
  const circumference = 2 * Math.PI * radius;

  return (
    <div className="relative size-32 shrink-0">
      <svg
        viewBox="0 0 120 120"
        className="size-full -rotate-90"
        aria-hidden="true"
      >
        <circle
          cx="60"
          cy="60"
          r={radius}
          fill="none"
          strokeWidth="10"
          className="stroke-[var(--color-surface-sunken)]"
        />
        <circle
          cx="60"
          cy="60"
          r={radius}
          fill="none"
          strokeWidth="10"
          strokeLinecap="round"
          stroke={scoreColor(value)}
          strokeDasharray={circumference}
          strokeDashoffset={circumference * (1 - value / 100)}
          className="transition-[stroke-dashoffset] duration-700 ease-[var(--ease-out-quint)]"
        />
      </svg>

      <div className="absolute inset-0 flex flex-col items-center justify-center">
        <span className="tabular text-heading-1">{value}</span>
        <span className="font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
          / 100
        </span>
      </div>
    </div>
  );
}

/* ── media.gallery / download.bundle ──────────────────────────────────────── */

function MediaResult({ result }: { result: ToolResult }) {
  if (result.artifacts.length === 0) return <FallbackResult result={result} />;

  return (
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      {result.artifacts.map((artifact) => (
        <figure
          key={artifact.filename}
          className="flex flex-col overflow-hidden rounded-[var(--radius-md)] border border-[var(--color-border)] bg-[var(--color-surface-sunken)]"
        >
          {artifact.preview_url && (
            // Contained, not cropped: these tools exist to show you a 9:16 story
            // export or a 2:3 Pin at its real shape, and a thumbnail that
            // centre-crops every output to 16:9 would hide the thing being checked.
            <div className="flex h-48 items-center justify-center bg-[var(--color-surface-sunken)] p-2">
              {/* eslint-disable-next-line @next/next/no-img-element -- signed, expiring URL on an unknown host */}
              <img
                src={artifact.preview_url}
                alt={artifact.label ?? artifact.filename}
                className="max-h-full max-w-full object-contain"
                loading="lazy"
              />
            </div>
          )}

          <figcaption className="flex items-center justify-between gap-2 p-3">
            <span className="truncate text-xs text-[var(--color-foreground-muted)]">
              {artifact.label ?? artifact.filename}
              {artifact.width && artifact.height
                ? ` · ${artifact.width} × ${artifact.height}`
                : ""}
            </span>

            {artifact.url && (
              <a
                href={artifact.url}
                download={artifact.filename}
                className="inline-flex items-center gap-1 text-xs font-medium text-[var(--color-primary)] hover:underline"
              >
                <Download className="size-3.5" />
                Download
              </a>
            )}
          </figcaption>
        </figure>
      ))}
    </div>
  );
}

/* ── diff.compare ─────────────────────────────────────────────────────────── */

function CompareResult({ result }: { result: ToolResult }) {
  const variants = (result.data.variants ?? []) as string[];
  const rows = (result.data.rows ?? []) as {
    label: string;
    variants?: Record<string, unknown>;
  }[];

  return (
    <div className="overflow-x-auto rounded-[var(--radius-md)] border border-[var(--color-border)]">
      <table className="w-full min-w-[32rem] text-sm">
        <thead className="bg-[var(--color-surface-sunken)]">
          <tr>
            <th
              scope="col"
              className="px-4 py-3 text-left font-mono text-[0.625rem] uppercase tracking-[0.12em]"
            >
              Metric
            </th>
            {variants.map((variant) => (
              <th
                key={variant}
                scope="col"
                className="px-4 py-3 text-left font-mono text-[0.625rem] uppercase tracking-[0.12em]"
              >
                {variant}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr
              key={row.label}
              className="border-t border-[var(--color-border-subtle)]"
            >
              <th scope="row" className="px-4 py-3 text-left font-medium">
                {row.label}
              </th>
              {variants.map((variant) => (
                <td key={variant} className="tabular px-4 py-3">
                  {String(row.variants?.[variant] ?? "—")}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

/* ── fallback ─────────────────────────────────────────────────────────────── */

/**
 * A tool shipped a view this build does not know how to draw — usually a frontend
 * deploy lagging behind the API. Show the data rather than an error: a raw result is
 * still useful, an empty box is not.
 */
function FallbackResult({ result }: { result: ToolResult }) {
  return (
    <pre className="overflow-x-auto rounded-[var(--radius-md)] border border-[var(--color-border)] bg-[var(--color-surface-sunken)] p-4 font-mono text-xs text-[var(--color-foreground-muted)]">
      {JSON.stringify(result.data, null, 2)}
    </pre>
  );
}

function scoreColor(value: number): string {
  if (value >= 80) return "var(--color-success)";
  if (value >= 55) return "var(--color-warning)";
  return "var(--color-danger)";
}

function severityColor(severity: string): string {
  return (
    { high: "var(--color-danger)", medium: "var(--color-warning)" }[severity] ??
    "var(--color-info)"
  );
}
