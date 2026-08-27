"use client";

import { Plus, Trash2 } from "lucide-react";
import * as React from "react";

import { RichText } from "@/components/admin/editor/rich-text";
import { Input, Select, Textarea } from "@/components/ui/field";
import type { Block } from "@/lib/types";
import { cn } from "@/lib/utils";

/**
 * How each block type is edited.
 *
 * The rule throughout: a block that *is* text is typed directly, with the published
 * article's own typography, and nothing else around it. A block that is a
 * configuration — an embed URL, a table, a tool slug — gets labelled fields, because
 * pretending a YouTube URL is prose does not make it one.
 *
 * Adding a type is one `case` here plus one entry in the registry, matching the
 * backend where it is one enum case plus one sanitiser branch.
 */
export function BlockFields({
  block,
  onChange,
  onEnterAtEnd,
  onBackspaceWhenEmpty,
}: {
  block: Block;
  onChange: (data: Record<string, unknown>) => void;
  onEnterAtEnd?: () => void;
  onBackspaceWhenEmpty?: () => void;
}) {
  const data = block.data ?? {};

  function set(patch: Record<string, unknown>) {
    onChange({ ...data, ...patch });
  }

  switch (block.type) {
    case "paragraph":
      return (
        <RichText
          value={String(data.html ?? "")}
          onChange={(html) => set({ html })}
          placeholder="Write, or press the + between blocks to add something else…"
          className="text-[var(--color-foreground-muted)] leading-relaxed"
          onEnterAtEnd={onEnterAtEnd}
          onBackspaceWhenEmpty={onBackspaceWhenEmpty}
        />
      );

    case "heading": {
      const level = Math.min(Math.max(Number(data.level ?? 2), 2), 4);

      return (
        <div className="flex items-start gap-2">
          {/* The level selector sits inline, tiny, and only on hover — a heading
              whose size is chosen from a dropdown above it is a heading you cannot
              see the shape of while writing. */}
          <select
            value={level}
            onChange={(event) => set({ level: Number(event.target.value) })}
            aria-label="Heading level"
            className="mt-1.5 shrink-0 rounded-[var(--radius-sm)] border border-transparent bg-transparent font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)] outline-none hover:border-[var(--color-border)] focus:border-[var(--color-ring)]"
          >
            <option value={2}>H2</option>
            <option value={3}>H3</option>
            <option value={4}>H4</option>
          </select>

          <input
            value={String(data.text ?? "")}
            onChange={(event) => set({ text: event.target.value })}
            onKeyDown={(event) => {
              if (event.key === "Enter") {
                event.preventDefault();
                onEnterAtEnd?.();
              }

              if (event.key === "Backspace" && String(data.text ?? "") === "") {
                event.preventDefault();
                onBackspaceWhenEmpty?.();
              }
            }}
            placeholder="Section heading"
            aria-label={`Heading level ${level}`}
            className={cn(
              "w-full bg-transparent font-semibold tracking-[-0.02em] text-[var(--color-foreground)] outline-none placeholder:text-[var(--color-foreground-subtle)]",
              level === 2 && "text-[1.875rem] leading-[1.22]",
              level === 3 && "text-[1.375rem] leading-[1.3]",
              level === 4 && "text-base",
            )}
          />
        </div>
      );
    }

    case "list": {
      const style = String(data.style ?? "unordered");
      const items = normaliseItems(data.items);

      function setItem(index: number, patch: Partial<{ html: string; checked: boolean }>) {
        set({
          items: items.map((item, position) =>
            position === index ? { ...item, ...patch } : item,
          ),
        });
      }

      return (
        <div>
          <div className="mb-1.5 flex items-center gap-2">
            <select
              value={style}
              onChange={(event) => set({ style: event.target.value })}
              aria-label="List style"
              className="rounded-[var(--radius-sm)] border border-transparent bg-transparent font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)] outline-none hover:border-[var(--color-border)] focus:border-[var(--color-ring)]"
            >
              <option value="unordered">Bulleted</option>
              <option value="ordered">Numbered</option>
              <option value="checklist">Checklist</option>
            </select>
          </div>

          <ul className="flex flex-col gap-1">
            {items.map((item, index) => (
              <li key={index} className="group/item flex items-start gap-2">
                {style === "checklist" ? (
                  <input
                    type="checkbox"
                    checked={item.checked === true}
                    onChange={(event) => setItem(index, { checked: event.target.checked })}
                    aria-label={`Item ${index + 1} done`}
                    className="mt-1.5 size-4 shrink-0 rounded border-[var(--color-border-strong)] accent-[var(--color-primary)]"
                  />
                ) : (
                  <span
                    aria-hidden="true"
                    className="tabular mt-0.5 w-5 shrink-0 text-right text-sm text-[var(--color-foreground-subtle)]"
                  >
                    {style === "ordered" ? `${index + 1}.` : "•"}
                  </span>
                )}

                <RichText
                  value={item.html}
                  onChange={(html) => setItem(index, { html })}
                  placeholder="List item"
                  multiline={false}
                  className="flex-1 text-[var(--color-foreground-muted)]"
                  onEnterAtEnd={() =>
                    set({
                      items: [
                        ...items.slice(0, index + 1),
                        { html: "", checked: false },
                        ...items.slice(index + 1),
                      ],
                    })
                  }
                  onBackspaceWhenEmpty={() => {
                    if (items.length > 1) {
                      set({ items: items.filter((_, position) => position !== index) });
                    } else {
                      onBackspaceWhenEmpty?.();
                    }
                  }}
                />

                <button
                  type="button"
                  onClick={() =>
                    set({ items: items.filter((_, position) => position !== index) })
                  }
                  disabled={items.length === 1}
                  aria-label={`Remove item ${index + 1}`}
                  className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded text-[var(--color-foreground-subtle)] opacity-0 transition-opacity hover:text-[var(--color-danger)] group-hover/item:opacity-100 disabled:hidden"
                >
                  <Trash2 className="size-3" aria-hidden="true" />
                </button>
              </li>
            ))}
          </ul>

          <button
            type="button"
            onClick={() => set({ items: [...items, { html: "", checked: false }] })}
            className="mt-1.5 inline-flex items-center gap-1 text-xs text-[var(--color-primary)] hover:underline"
          >
            <Plus className="size-3" aria-hidden="true" />
            Add item
          </button>
        </div>
      );
    }

    case "quote":
      return (
        <figure className="border-l-2 border-[var(--color-primary)] pl-4">
          <RichText
            value={String(data.text ?? "")}
            onChange={(text) => set({ text })}
            placeholder="The quotation"
            className="text-[1.0625rem] italic leading-relaxed text-[var(--color-foreground)]"
            onBackspaceWhenEmpty={onBackspaceWhenEmpty}
          />

          <figcaption className="mt-2 flex items-center gap-2">
            <span aria-hidden="true" className="text-[var(--color-foreground-subtle)]">
              —
            </span>
            <input
              value={String(data.cite ?? "")}
              onChange={(event) => set({ cite: event.target.value })}
              placeholder="Who said it"
              aria-label="Attribution"
              className="flex-1 bg-transparent text-sm text-[var(--color-foreground-subtle)] outline-none placeholder:text-[var(--color-foreground-subtle)]"
            />
            <select
              value={String(data.variant ?? "default")}
              onChange={(event) => set({ variant: event.target.value })}
              aria-label="Quote style"
              className="rounded-[var(--radius-sm)] border border-transparent bg-transparent font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)] outline-none hover:border-[var(--color-border)]"
            >
              <option value="default">Inline</option>
              <option value="pull">Pull quote</option>
            </select>
          </figcaption>
        </figure>
      );

    case "callout": {
      const tone = String(data.tone ?? "info");
      const accent = {
        info: "var(--color-primary)",
        tip: "var(--color-accent)",
        warning: "var(--color-warning)",
        danger: "var(--color-danger)",
      }[tone] ?? "var(--color-primary)";

      return (
        <div
          className="rounded-[var(--radius-md)] border-l-2 p-3"
          style={{
            borderLeftColor: accent,
            backgroundColor: `color-mix(in oklab, ${accent} 8%, transparent)`,
          }}
        >
          <div className="mb-1 flex items-center gap-2">
            <select
              value={tone}
              onChange={(event) => set({ tone: event.target.value })}
              aria-label="Callout tone"
              className="rounded-[var(--radius-sm)] border border-transparent bg-transparent font-mono text-[0.625rem] uppercase tracking-[0.12em] outline-none hover:border-[var(--color-border)]"
              style={{ color: accent }}
            >
              <option value="info">Note</option>
              <option value="tip">Tip</option>
              <option value="warning">Warning</option>
              <option value="danger">Danger</option>
            </select>

            <input
              value={String(data.title ?? "")}
              onChange={(event) => set({ title: event.target.value })}
              placeholder="Optional heading"
              aria-label="Callout heading"
              className="flex-1 bg-transparent text-sm font-semibold text-[var(--color-foreground)] outline-none placeholder:font-normal placeholder:text-[var(--color-foreground-subtle)]"
            />
          </div>

          <RichText
            value={String(data.html ?? "")}
            onChange={(html) => set({ html })}
            placeholder="What the reader must not miss"
            className="text-sm leading-relaxed text-[var(--color-foreground-muted)]"
          />
        </div>
      );
    }

    case "image":
      return (
        <figure className="flex flex-col gap-2">
          {String(data.url ?? "") !== "" ? (
            // A plain <img>: the source is arbitrary and next/image would need every
            // possible host in its remote patterns. The published article uses the
            // optimised component; this is a preview.
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={String(data.url)}
              alt={String(data.alt ?? "")}
              className="max-h-80 w-full rounded-[var(--radius-md)] border border-[var(--color-border-subtle)] object-contain"
            />
          ) : (
            <div className="flex h-32 items-center justify-center rounded-[var(--radius-md)] border border-dashed border-[var(--color-border)] text-sm text-[var(--color-foreground-subtle)]">
              Paste an image URL below, or pick one from the media library
            </div>
          )}

          <FieldRow>
            <Input
              value={String(data.url ?? "")}
              onChange={(event) => set({ url: event.target.value })}
              placeholder="https://…"
              aria-label="Image URL"
            />
            <Select
              value={String(data.size ?? "inline")}
              onChange={(event) => set({ size: event.target.value })}
              aria-label="Image width"
              className="sm:w-32"
            >
              <option value="inline">Inline</option>
              <option value="wide">Wide</option>
              <option value="full">Full bleed</option>
            </Select>
          </FieldRow>

          <Input
            value={String(data.alt ?? "")}
            onChange={(event) => set({ alt: event.target.value })}
            placeholder="Alt text — describe the image for someone who cannot see it"
            aria-label="Alt text"
          />

          <RichText
            value={String(data.caption ?? "")}
            onChange={(caption) => set({ caption })}
            placeholder="Caption (optional)"
            className="text-xs text-[var(--color-foreground-subtle)]"
          />
        </figure>
      );

    case "embed":
      return (
        <div className="flex flex-col gap-2">
          <div className="flex items-center justify-center rounded-[var(--radius-md)] border border-dashed border-[var(--color-border)] px-4 py-8 text-center text-sm text-[var(--color-foreground-subtle)]">
            {String(data.url ?? "") === ""
              ? "Paste the URL of the video, tweet or pen"
              : `${String(data.provider ?? "generic")} · ${String(data.url)}`}
          </div>

          <FieldRow>
            <Select
              value={String(data.provider ?? "youtube")}
              onChange={(event) => set({ provider: event.target.value })}
              aria-label="Embed provider"
              className="sm:w-40"
            >
              <option value="youtube">YouTube</option>
              <option value="vimeo">Vimeo</option>
              <option value="twitter">X / Twitter</option>
              <option value="codepen">CodePen</option>
              <option value="generic">Other</option>
            </Select>

            <Input
              value={String(data.url ?? "")}
              onChange={(event) => set({ url: event.target.value })}
              placeholder="https://…"
              aria-label="Embed URL"
            />

            <Select
              value={String(data.aspect ?? "16:9")}
              onChange={(event) => set({ aspect: event.target.value })}
              aria-label="Aspect ratio"
              className="sm:w-28"
            >
              <option value="16:9">16:9</option>
              <option value="4:3">4:3</option>
              <option value="1:1">1:1</option>
              <option value="9:16">9:16</option>
            </Select>
          </FieldRow>

          <Input
            value={String(data.caption ?? "")}
            onChange={(event) => set({ caption: event.target.value })}
            placeholder="Caption (optional)"
            aria-label="Embed caption"
          />
        </div>
      );

    case "code":
      return (
        <div className="overflow-hidden rounded-[var(--radius-md)] border border-[var(--color-border)]">
          <div className="flex items-center gap-2 border-b border-[var(--color-border-subtle)] bg-[var(--color-surface-sunken)] px-2 py-1.5">
            <input
              value={String(data.language ?? "text")}
              onChange={(event) => set({ language: event.target.value })}
              placeholder="language"
              aria-label="Language"
              className="w-24 bg-transparent font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)] outline-none"
            />
            <input
              value={String(data.filename ?? "")}
              onChange={(event) => set({ filename: event.target.value })}
              placeholder="filename (optional)"
              aria-label="Filename"
              className="flex-1 bg-transparent font-mono text-xs text-[var(--color-foreground-muted)] outline-none"
            />
          </div>

          <textarea
            value={String(data.code ?? "")}
            onChange={(event) => set({ code: event.target.value })}
            // Tab must indent, not leave the field. A code editor where Tab moves
            // focus is a code editor nobody can use.
            onKeyDown={(event) => {
              if (event.key !== "Tab") return;

              event.preventDefault();
              const target = event.currentTarget;
              const { selectionStart, selectionEnd, value } = target;
              const next = `${value.slice(0, selectionStart)}  ${value.slice(selectionEnd)}`;

              set({ code: next });
              requestAnimationFrame(() => {
                target.selectionStart = target.selectionEnd = selectionStart + 2;
              });
            }}
            placeholder="Paste your code"
            aria-label="Code"
            spellCheck={false}
            rows={Math.min(20, Math.max(4, String(data.code ?? "").split("\n").length + 1))}
            className="w-full resize-y bg-[var(--app-surface)] p-3 font-mono text-[0.8125rem] leading-relaxed text-[var(--color-foreground)] outline-none"
          />
        </div>
      );

    case "html":
      return (
        <div className="flex flex-col gap-1.5">
          <p className="flex items-center gap-1.5 font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-warning)]">
            Custom HTML — sanitised on save
          </p>
          <Textarea
            value={String(data.html ?? "")}
            onChange={(event) => set({ html: event.target.value })}
            placeholder="<div>…</div>"
            aria-label="Custom HTML"
            spellCheck={false}
            className="min-h-24 font-mono text-xs"
          />
          <p className="text-xs leading-relaxed text-[var(--color-foreground-subtle)]">
            Scripts, event handlers and inline styles are stripped by the server. If
            what you paste does not survive a save, that is why — put it in Settings
            → Tracking instead, which is permissioned for exactly that.
          </p>
        </div>
      );

    case "table": {
      const rows = normaliseRows(data.rows);

      function setCell(row: number, column: number, value: string) {
        set({
          rows: rows.map((cells, r) =>
            r === row ? cells.map((cell, c) => (c === column ? value : cell)) : cells,
          ),
        });
      }

      return (
        <div>
          <div className="scrollbar-slim overflow-x-auto rounded-[var(--radius-md)] border border-[var(--color-border)]">
            <table className="w-full border-collapse text-sm">
              <tbody>
                {rows.map((cells, rowIndex) => (
                  <tr
                    key={rowIndex}
                    className={cn(
                      "border-b border-[var(--color-border-subtle)] last:border-b-0",
                      rowIndex === 0 && "bg-[var(--color-surface-sunken)]",
                    )}
                  >
                    {cells.map((cell, columnIndex) => (
                      <td key={columnIndex} className="border-r border-[var(--color-border-subtle)] last:border-r-0">
                        <input
                          value={cell}
                          onChange={(event) => setCell(rowIndex, columnIndex, event.target.value)}
                          placeholder={rowIndex === 0 ? "Header" : ""}
                          aria-label={`Row ${rowIndex + 1}, column ${columnIndex + 1}`}
                          className={cn(
                            "w-full min-w-24 bg-transparent px-2.5 py-1.5 outline-none focus:bg-[var(--color-primary-subtle)]/40",
                            rowIndex === 0
                              ? "font-semibold text-[var(--color-foreground)]"
                              : "text-[var(--color-foreground-muted)]",
                          )}
                        />
                      </td>
                    ))}

                    <td className="w-8 px-1">
                      <button
                        type="button"
                        onClick={() => set({ rows: rows.filter((_, r) => r !== rowIndex) })}
                        disabled={rows.length <= 1}
                        aria-label={`Remove row ${rowIndex + 1}`}
                        className="flex size-6 items-center justify-center rounded text-[var(--color-foreground-subtle)] hover:text-[var(--color-danger)] disabled:opacity-30"
                      >
                        <Trash2 className="size-3" aria-hidden="true" />
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="mt-1.5 flex gap-3">
            <button
              type="button"
              onClick={() => set({ rows: [...rows, rows[0].map(() => "")] })}
              className="inline-flex items-center gap-1 text-xs text-[var(--color-primary)] hover:underline"
            >
              <Plus className="size-3" aria-hidden="true" />
              Row
            </button>

            <button
              type="button"
              onClick={() => set({ rows: rows.map((cells) => [...cells, ""]) })}
              className="inline-flex items-center gap-1 text-xs text-[var(--color-primary)] hover:underline"
            >
              <Plus className="size-3" aria-hidden="true" />
              Column
            </button>

            <button
              type="button"
              onClick={() =>
                set({ rows: rows.map((cells) => cells.slice(0, Math.max(1, cells.length - 1))) })
              }
              disabled={rows[0].length <= 1}
              className="text-xs text-[var(--color-foreground-subtle)] hover:text-[var(--color-danger)] disabled:opacity-40"
            >
              Remove last column
            </button>
          </div>
        </div>
      );
    }

    case "divider":
      return (
        <div className="flex items-center gap-3">
          <hr className="flex-1 border-t border-[var(--color-border)]" />
          <select
            value={String(data.style ?? "plain")}
            onChange={(event) => set({ style: event.target.value })}
            aria-label="Divider style"
            className="rounded-[var(--radius-sm)] border border-transparent bg-transparent font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)] outline-none hover:border-[var(--color-border)]"
          >
            <option value="plain">Rule</option>
            <option value="dots">Dots</option>
            <option value="asterism">Asterism</option>
          </select>
          <hr className="flex-1 border-t border-[var(--color-border)]" />
        </div>
      );

    case "faq": {
      const items = normaliseFaq(data.items);

      return (
        <div className="flex flex-col gap-2">
          <p className="font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
            FAQ — emits FAQPage structured data
          </p>

          {items.map((item, index) => (
            <div
              key={index}
              className="group/faq rounded-[var(--radius-md)] border border-[var(--color-border-subtle)] p-2.5"
            >
              <div className="flex items-start gap-2">
                <input
                  value={item.question}
                  onChange={(event) =>
                    set({
                      items: items.map((entry, position) =>
                        position === index ? { ...entry, question: event.target.value } : entry,
                      ),
                    })
                  }
                  placeholder="A question a reader would actually type"
                  aria-label={`Question ${index + 1}`}
                  className="flex-1 bg-transparent text-sm font-semibold text-[var(--color-foreground)] outline-none placeholder:font-normal placeholder:text-[var(--color-foreground-subtle)]"
                />

                <button
                  type="button"
                  onClick={() => set({ items: items.filter((_, p) => p !== index) })}
                  disabled={items.length === 1}
                  aria-label={`Remove question ${index + 1}`}
                  className="flex size-5 shrink-0 items-center justify-center rounded text-[var(--color-foreground-subtle)] opacity-0 transition-opacity hover:text-[var(--color-danger)] group-hover/faq:opacity-100 disabled:hidden"
                >
                  <Trash2 className="size-3" aria-hidden="true" />
                </button>
              </div>

              <textarea
                value={item.answer}
                onChange={(event) =>
                  set({
                    items: items.map((entry, position) =>
                      position === index ? { ...entry, answer: event.target.value } : entry,
                    ),
                  })
                }
                placeholder="The answer, in one or two sentences"
                aria-label={`Answer ${index + 1}`}
                rows={2}
                className="mt-1 w-full resize-y bg-transparent text-sm leading-relaxed text-[var(--color-foreground-muted)] outline-none placeholder:text-[var(--color-foreground-subtle)]"
              />
            </div>
          ))}

          <button
            type="button"
            onClick={() => set({ items: [...items, { question: "", answer: "" }] })}
            className="inline-flex items-center gap-1 self-start text-xs text-[var(--color-primary)] hover:underline"
          >
            <Plus className="size-3" aria-hidden="true" />
            Add question
          </button>
        </div>
      );
    }

    case "button":
      return (
        <FieldRow>
          <Input
            value={String(data.label ?? "")}
            onChange={(event) => set({ label: event.target.value })}
            placeholder="Button label"
            aria-label="Button label"
          />
          <Input
            value={String(data.href ?? "")}
            onChange={(event) => set({ href: event.target.value })}
            placeholder="/tools/…  or  https://…"
            aria-label="Button link"
          />
          <Select
            value={String(data.variant ?? "primary")}
            onChange={(event) => set({ variant: event.target.value })}
            aria-label="Button style"
            className="sm:w-36"
          >
            <option value="primary">Primary</option>
            <option value="secondary">Secondary</option>
            <option value="outline">Outline</option>
          </Select>
        </FieldRow>
      );

    case "toolCard":
      return (
        <div className="flex flex-col gap-1.5">
          <Input
            value={String(data.toolSlug ?? "")}
            onChange={(event) => set({ toolSlug: event.target.value })}
            placeholder="tool-slug"
            aria-label="Tool slug"
          />
          <p className="text-xs text-[var(--color-foreground-subtle)]">
            The card renders live from the catalog — its name, tier and run count stay
            current without anyone editing this post again.
          </p>
        </div>
      );

    default:
      // Content written by a newer deploy. Show the JSON rather than swallowing it:
      // an older build must never be able to destroy a block it does not understand.
      return (
        <div className="rounded-[var(--radius-md)] border border-dashed border-[var(--color-warning)]/50 bg-[var(--color-warning)]/5 p-3">
          <p className="font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-warning)]">
            Unknown block: {block.type}
          </p>
          <p className="mt-1 text-xs leading-relaxed text-[var(--color-foreground-muted)]">
            This editor does not know how to edit this type — probably content from a
            newer deploy. It is preserved exactly as it is when you save.
          </p>
          <pre className="scrollbar-slim mt-2 max-h-32 overflow-auto font-mono text-[0.6875rem] text-[var(--color-foreground-subtle)]">
            {JSON.stringify(data, null, 2)}
          </pre>
        </div>
      );
  }
}

function FieldRow({ children }: { children: React.ReactNode }) {
  return <div className="flex flex-col gap-2 sm:flex-row">{children}</div>;
}

/** Two shapes exist in stored content; normalise rather than migrating either. */
function normaliseItems(value: unknown): { html: string; checked?: boolean }[] {
  const items = Array.isArray(value) ? value : [];

  const mapped = items.map((item) =>
    typeof item === "string"
      ? { html: item, checked: false }
      : { html: String((item as { html?: unknown })?.html ?? ""), checked: (item as { checked?: boolean })?.checked === true },
  );

  return mapped.length > 0 ? mapped : [{ html: "", checked: false }];
}

function normaliseRows(value: unknown): string[][] {
  const rows = Array.isArray(value) ? value : [];

  const mapped = rows
    .filter(Array.isArray)
    .map((row) => (row as unknown[]).map((cell) => String(cell ?? "")));

  return mapped.length > 0 ? mapped : [["", ""], ["", ""]];
}

function normaliseFaq(value: unknown): { question: string; answer: string }[] {
  const items = Array.isArray(value) ? value : [];

  const mapped = items.map((item) => {
    const entry = item as { question?: unknown; answer?: unknown; q?: unknown; a?: unknown };

    return {
      question: String(entry?.question ?? entry?.q ?? ""),
      answer: String(entry?.answer ?? entry?.a ?? ""),
    };
  });

  return mapped.length > 0 ? mapped : [{ question: "", answer: "" }];
}
