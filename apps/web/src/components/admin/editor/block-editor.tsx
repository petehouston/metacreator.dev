"use client";

import {
  ChevronDown,
  ChevronUp,
  Copy,
  GripVertical,
  Plus,
  Trash2,
} from "lucide-react";
import * as React from "react";

import {
  BLOCK_KIND_BY_TYPE,
  makeBlock,
  searchKinds,
  type BlockKind,
} from "@/components/admin/editor/block-kinds";
import { BlockFields } from "@/components/admin/editor/block-fields";
import { Button } from "@/components/ui/button";
import type { Block, BlockDocument } from "@/lib/types";
import { cn } from "@/lib/utils";

/**
 * The post editor's canvas.
 *
 * WYSIWYG is structural here, not cosmetic: each block is rendered with the same
 * typography the published article uses, and the editing chrome — the handle, the
 * move buttons — lives in a gutter *outside* the content column. Nothing that
 * belongs to editing sits inside the text, so the column you type in is the column
 * that ships.
 *
 * Everything else about a post — status, category, tags, SEO, the featured image —
 * lives in a side panel, because the editor's own instruction was that the writing
 * experience must be "in full".
 */
export function BlockEditor({
  document: value,
  onChange,
  className,
  onPickMedia,
}: {
  document: BlockDocument;
  onChange: (next: BlockDocument) => void;
  className?: string;
  /** Opens the media library; the editor calls back with what was chosen. */
  onPickMedia?: (apply: (media: { url: string; alt: string; width: number | null; height: number | null }) => void) => void;
}) {
  const [selected, setSelected] = React.useState<string | null>(null);
  const [inserting, setInserting] = React.useState<{ afterIndex: number } | null>(null);

  const blocks = value.blocks ?? [];

  function commit(next: Block[]) {
    onChange({ version: value.version ?? 1, blocks: next });
  }

  function update(index: number, data: Record<string, unknown>) {
    commit(blocks.map((block, position) => (position === index ? { ...block, data } : block)));
  }

  function insert(type: string, afterIndex: number) {
    const block = makeBlock(type);
    const next = [...blocks];

    next.splice(afterIndex + 1, 0, block);
    commit(next);
    setSelected(block.id ?? null);
    setInserting(null);
  }

  function remove(index: number) {
    // Never leave the canvas with nothing to type into — an editor with zero blocks
    // has no caret and looks broken.
    const next = blocks.filter((_, position) => position !== index);

    commit(next.length > 0 ? next : [makeBlock("paragraph")]);
  }

  function duplicate(index: number) {
    const copy = { ...makeBlock(blocks[index].type), data: { ...blocks[index].data } };
    const next = [...blocks];

    next.splice(index + 1, 0, copy);
    commit(next);
  }

  function move(index: number, direction: -1 | 1) {
    const target = index + direction;

    if (target < 0 || target >= blocks.length) return;

    const next = [...blocks];
    [next[index], next[target]] = [next[target], next[index]];
    commit(next);
  }

  return (
    <div className={cn("flex flex-col", className)}>
      {blocks.map((block, index) => {
        const kind = BLOCK_KIND_BY_TYPE[block.type];
        const id = block.id ?? `index-${index}`;
        const active = selected === id;

        return (
          <div
            key={id}
            onFocusCapture={() => setSelected(id)}
            onMouseDown={() => setSelected(id)}
            className={cn(
              "group relative -mx-2 rounded-[var(--radius-md)] px-2 py-1.5 transition-colors",
              active && "bg-[var(--color-surface-sunken)]/50",
            )}
          >
            {/* The gutter. Absolutely positioned so it never affects the width of
                the writing column — the whole point of WYSIWYG. */}
            <div
              className={cn(
                "absolute -left-[4.5rem] top-1.5 hidden items-center gap-0.5 lg:flex",
                active ? "opacity-100" : "opacity-0 group-hover:opacity-100",
                "transition-opacity",
              )}
            >
              <GutterButton
                label="Move up"
                icon={ChevronUp}
                onClick={() => move(index, -1)}
                disabled={index === 0}
              />
              <GutterButton
                label="Move down"
                icon={ChevronDown}
                onClick={() => move(index, 1)}
                disabled={index === blocks.length - 1}
              />
              <span
                aria-hidden="true"
                title={kind?.label ?? block.type}
                className="flex size-6 items-center justify-center text-[var(--color-foreground-subtle)]"
              >
                <GripVertical className="size-3.5" />
              </span>
            </div>

            <BlockFields
              block={block}
              selected={active}
              onPickMedia={onPickMedia}
              onChange={(data) => update(index, data)}
              onEnterAtEnd={() => insert("paragraph", index)}
              onBackspaceWhenEmpty={() => {
                if (blocks.length > 1) remove(index);
              }}
            />

            <div
              className={cn(
                "absolute right-1 top-1 flex items-center gap-0.5",
                active ? "opacity-100" : "opacity-0 group-hover:opacity-100",
                "transition-opacity",
              )}
            >
              <span className="mr-1 hidden font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)] sm:inline">
                {kind?.label ?? block.type}
              </span>
              <GutterButton label="Duplicate" icon={Copy} onClick={() => duplicate(index)} />
              <GutterButton
                label="Delete block"
                icon={Trash2}
                destructive
                onClick={() => remove(index)}
              />
            </div>

            {/* The insert affordance sits between blocks, where a writer's cursor
                already is when they want one. */}
            <div className="relative h-3">
              <button
                type="button"
                onClick={() => setInserting({ afterIndex: index })}
                aria-label={`Insert a block after block ${index + 1}`}
                className="absolute inset-x-0 top-0 flex h-3 items-center justify-center opacity-0 transition-opacity hover:opacity-100 focus-visible:opacity-100"
              >
                <span className="h-px flex-1 bg-[var(--color-primary)]/40" aria-hidden="true" />
                <span className="mx-2 flex size-5 items-center justify-center rounded-full border border-[var(--color-primary)]/40 bg-[var(--app-surface)] text-[var(--color-primary)]">
                  <Plus className="size-3" aria-hidden="true" />
                </span>
                <span className="h-px flex-1 bg-[var(--color-primary)]/40" aria-hidden="true" />
              </button>
            </div>
          </div>
        );
      })}

      <Button
        variant="secondary"
        size="sm"
        className="mt-3 self-start"
        onClick={() => setInserting({ afterIndex: blocks.length - 1 })}
      >
        <Plus className="size-4" aria-hidden="true" />
        Add a block
      </Button>

      {inserting && (
        <InsertMenu
          onPick={(type) => insert(type, inserting.afterIndex)}
          onClose={() => setInserting(null)}
        />
      )}
    </div>
  );
}

function GutterButton({
  label,
  icon: Icon,
  onClick,
  disabled,
  destructive,
}: {
  label: string;
  icon: React.ComponentType<{ className?: string }>;
  onClick: () => void;
  disabled?: boolean;
  destructive?: boolean;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      aria-label={label}
      title={label}
      className={cn(
        "flex size-6 items-center justify-center rounded-[var(--radius-sm)] text-[var(--color-foreground-subtle)] transition-colors disabled:opacity-30",
        destructive
          ? "hover:bg-[var(--color-danger)]/10 hover:text-[var(--color-danger)]"
          : "hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-foreground)]",
      )}
    >
      <Icon className="size-3.5" aria-hidden="true" />
    </button>
  );
}

/** The block picker: search, grouped results, keyboard-first. */
function InsertMenu({
  onPick,
  onClose,
}: {
  onPick: (type: string) => void;
  onClose: () => void;
}) {
  const [query, setQuery] = React.useState("");
  const [index, setIndex] = React.useState(0);

  const results = searchKinds(query);

  React.useEffect(() => {
    function onKeyDown(event: KeyboardEvent) {
      if (event.key === "Escape") onClose();
    }

    document.addEventListener("keydown", onKeyDown);
    return () => document.removeEventListener("keydown", onKeyDown);
  }, [onClose]);

  const groups: BlockKind["group"][] = ["Text", "Media", "Structure", "Product"];
  let cursor = -1;

  return (
    <div className="fixed inset-0 z-[80] flex items-start justify-center px-4 pt-[15vh]">
      <button
        type="button"
        aria-label="Close"
        onClick={onClose}
        className="animate-fade-in absolute inset-0 bg-[oklch(0.15_0.02_258/0.5)]"
      />

      <div
        role="dialog"
        aria-modal="true"
        aria-label="Insert a block"
        className="relative w-full max-w-md overflow-hidden rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-[var(--app-surface)] shadow-[var(--shadow-popover)]"
      >
        <input
          autoFocus
          value={query}
          onChange={(event) => {
            setQuery(event.target.value);
            // Same transition as the query: an effect would leave one render where
            // the highlight points at a result that is no longer there.
            setIndex(0);
          }}
          onKeyDown={(event) => {
            if (event.key === "ArrowDown") {
              event.preventDefault();
              setIndex((current) => Math.min(current + 1, results.length - 1));
            } else if (event.key === "ArrowUp") {
              event.preventDefault();
              setIndex((current) => Math.max(current - 1, 0));
            } else if (event.key === "Enter") {
              event.preventDefault();
              const picked = results[index];
              if (picked) onPick(picked.type);
            }
          }}
          placeholder="Search block types…"
          aria-label="Search block types"
          className="h-12 w-full border-b border-[var(--color-border-subtle)] bg-transparent px-4 text-sm text-[var(--color-foreground)] outline-none placeholder:text-[var(--color-foreground-subtle)]"
        />

        <div className="scrollbar-slim max-h-[22rem] overflow-y-auto p-2">
          {results.length === 0 && (
            <p className="px-3 py-8 text-center text-sm text-[var(--color-foreground-subtle)]">
              No block type matches “{query}”.
            </p>
          )}

          {groups.map((group) => {
            const inGroup = results.filter((kind) => kind.group === group);

            if (inGroup.length === 0) return null;

            return (
              <div key={group} className="mb-1 last:mb-0">
                <p className="px-3 py-1.5 font-mono text-[0.625rem] font-medium uppercase tracking-[0.14em] text-[var(--color-foreground-subtle)]">
                  {group}
                </p>

                {inGroup.map((kind) => {
                  cursor += 1;
                  const position = cursor;
                  const Icon = kind.icon;

                  return (
                    <button
                      key={kind.type}
                      type="button"
                      onMouseEnter={() => setIndex(position)}
                      onClick={() => onPick(kind.type)}
                      className={cn(
                        "flex w-full items-start gap-3 rounded-[var(--radius-md)] px-3 py-2 text-left transition-colors",
                        position === index && "bg-[var(--color-surface-sunken)]",
                      )}
                    >
                      <span className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-[var(--radius-sm)] bg-[var(--color-primary-subtle)] text-[var(--color-primary)]">
                        <Icon className="size-3.5" aria-hidden="true" />
                      </span>

                      <span className="min-w-0 flex-1">
                        <span className="block text-sm font-medium text-[var(--color-foreground)]">
                          {kind.label}
                        </span>
                        <span className="block text-xs leading-snug text-[var(--color-foreground-subtle)]">
                          {kind.description}
                        </span>
                      </span>
                    </button>
                  );
                })}
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
