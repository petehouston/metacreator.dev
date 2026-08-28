"use client";

import { Check, ImageIcon, Search, Upload, X } from "lucide-react";
import * as React from "react";

import { useToast } from "@/components/admin/feedback";
import { Button } from "@/components/ui/button";
import { Input, Textarea } from "@/components/ui/field";
import { adminApi } from "@/lib/admin/api";
import type { AdminMedia } from "@/lib/admin/types";
import { cn, formatDate } from "@/lib/utils";

/**
 * The media library, as a modal.
 *
 * The same library the Media screen shows, reachable from anywhere a file has to be
 * chosen — the image block, the featured image, the social share image. Browse,
 * search, upload and fix the alt text without leaving the post, because the moment
 * someone has to open another tab to attach a picture is the moment they paste a
 * URL instead and the library stops being the record of what the site uses.
 *
 * Alt text is editable *here* rather than only on the Media screen: the person
 * placing an image is the one who knows what it is for.
 */
export function MediaPicker({
  open,
  onClose,
  onSelect,
  accept = "image",
  title = "Media library",
}: {
  open: boolean;
  onClose: () => void;
  onSelect: (media: AdminMedia) => void;
  /** Restrict the grid to one kind. `""` shows everything. */
  accept?: string;
  title?: string;
}) {
  const { notify, reportError } = useToast();

  const [query, setQuery] = React.useState("");
  const [items, setItems] = React.useState<AdminMedia[]>([]);
  const [loading, setLoading] = React.useState(false);
  const [uploading, setUploading] = React.useState(false);
  const [selected, setSelected] = React.useState<AdminMedia | null>(null);
  const [savingMeta, setSavingMeta] = React.useState(false);

  const fileInput = React.useRef<HTMLInputElement>(null);

  const load = React.useCallback(async () => {
    setLoading(true);

    const result = await adminApi.media.list({
      q: query || undefined,
      "filter[kind]": accept || undefined,
      per_page: 60,
    });

    setLoading(false);

    if (result.ok) {
      setItems(result.data.data);
    } else {
      reportError(result.error);
    }
  }, [query, accept, reportError]);

  React.useEffect(() => {
    if (!open) return;

    // Debounced so typing a filename is one request rather than one per keystroke.
    const timer = setTimeout(() => void load(), query === "" ? 0 : 250);
    return () => clearTimeout(timer);
  }, [open, query, load]);

  React.useEffect(() => {
    if (!open) return;

    function onKeyDown(event: KeyboardEvent) {
      if (event.key === "Escape") onClose();
    }

    document.addEventListener("keydown", onKeyDown);
    return () => document.removeEventListener("keydown", onKeyDown);
  }, [open, onClose]);

  if (!open) return null;

  async function upload(files: FileList | null) {
    if (!files || files.length === 0) return;

    setUploading(true);

    let last: AdminMedia | null = null;

    for (const file of Array.from(files)) {
      const form = new FormData();
      form.append("file", file);

      const result = await adminApi.media.upload(form);

      if (!result.ok) {
        reportError(result.error);
        break;
      }

      last = result.data;
    }

    setUploading(false);

    if (last) {
      // Pre-select what was just uploaded and open its metadata: an image with no
      // alt text is the default outcome unless the flow asks for it immediately.
      setSelected(last);
      await load();
    }
  }

  async function saveMeta(patch: Partial<AdminMedia>) {
    if (!selected) return;

    setSavingMeta(true);
    const result = await adminApi.media.update(selected.id, patch);
    setSavingMeta(false);

    if (result.ok) {
      setSelected(result.data);
      setItems((current) =>
        current.map((item) => (item.id === result.data.id ? result.data : item)),
      );
      notify("Saved.");
    } else {
      reportError(result.error);
    }
  }

  return (
    <div className="fixed inset-0 z-[88] flex items-center justify-center p-4">
      <button
        type="button"
        aria-label="Close the media library"
        onClick={onClose}
        className="animate-fade-in absolute inset-0 bg-[oklch(0.15_0.02_258/0.55)]"
      />

      <div
        role="dialog"
        aria-modal="true"
        aria-label={title}
        className="relative flex h-[min(44rem,calc(100dvh-2rem))] w-full max-w-5xl flex-col overflow-hidden rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-[var(--app-surface)] shadow-[var(--shadow-popover)]"
      >
        <header className="flex shrink-0 flex-wrap items-center gap-3 border-b border-[var(--color-border-subtle)] px-4 py-3">
          <h2 className="text-sm font-semibold text-[var(--color-foreground)]">{title}</h2>

          <div className="relative ml-auto w-full max-w-64">
            <Search
              className="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-[var(--color-foreground-subtle)]"
              aria-hidden="true"
            />
            <Input
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Search filenames and alt text"
              aria-label="Search the media library"
              className="h-8 pl-8 text-xs"
            />
          </div>

          <input
            ref={fileInput}
            type="file"
            multiple
            accept={accept === "image" ? "image/*" : undefined}
            className="sr-only"
            onChange={(event) => {
              void upload(event.target.files);
              event.target.value = "";
            }}
          />

          <Button
            size="sm"
            variant="secondary"
            loading={uploading}
            onClick={() => fileInput.current?.click()}
          >
            <Upload className="size-4" aria-hidden="true" />
            Upload
          </Button>

          <button
            type="button"
            onClick={onClose}
            aria-label="Close"
            className="flex size-8 items-center justify-center rounded-[var(--radius-md)] text-[var(--color-foreground-muted)] transition-colors hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-foreground)]"
          >
            <X className="size-4" aria-hidden="true" />
          </button>
        </header>

        <div className="flex min-h-0 flex-1">
          <div className="scrollbar-slim min-w-0 flex-1 overflow-y-auto p-4">
            {loading && items.length === 0 && (
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                {[0, 1, 2, 3, 4, 5, 6, 7].map((tile) => (
                  <div
                    key={tile}
                    className="aspect-square animate-pulse rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)]"
                    aria-hidden="true"
                  />
                ))}
              </div>
            )}

            {!loading && items.length === 0 && (
              <div className="flex h-full flex-col items-center justify-center gap-3 py-16 text-center">
                <ImageIcon className="size-8 text-[var(--color-foreground-subtle)]" aria-hidden="true" />
                <p className="text-sm text-[var(--color-foreground-muted)]">
                  {query === ""
                    ? "The library is empty. Upload something to get started."
                    : `Nothing in the library matches “${query}”.`}
                </p>
              </div>
            )}

            {items.length > 0 && (
              <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                {items.map((item) => {
                  const active = selected?.id === item.id;
                  const needsAlt = item.kind === "image" && !item.alt_text && !item.is_decorative;

                  return (
                    <li key={item.id}>
                      <button
                        type="button"
                        onClick={() => setSelected(item)}
                        onDoubleClick={() => onSelect(item)}
                        aria-pressed={active}
                        className={cn(
                          "group relative block w-full overflow-hidden rounded-[var(--radius-md)] border text-left transition-colors",
                          active
                            ? "border-[var(--color-primary)] ring-2 ring-[var(--color-primary)]/30"
                            : "border-[var(--color-border-subtle)] hover:border-[var(--color-border-strong)]",
                        )}
                      >
                        <span className="flex aspect-square items-center justify-center bg-[var(--color-surface-sunken)]">
                          {item.kind === "image" && item.url ? (
                            // eslint-disable-next-line @next/next/no-img-element
                            <img
                              src={item.url}
                              alt=""
                              loading="lazy"
                              className="size-full object-cover"
                            />
                          ) : (
                            <ImageIcon
                              className="size-6 text-[var(--color-foreground-subtle)]"
                              aria-hidden="true"
                            />
                          )}
                        </span>

                        {active && (
                          <span className="absolute right-1.5 top-1.5 flex size-5 items-center justify-center rounded-full bg-[var(--color-primary)] text-[var(--color-primary-foreground)]">
                            <Check className="size-3" strokeWidth={3} aria-hidden="true" />
                          </span>
                        )}

                        <span className="block truncate px-2 py-1.5 text-[0.6875rem] text-[var(--color-foreground-muted)]">
                          {item.filename}
                        </span>

                        {needsAlt && (
                          <span className="absolute bottom-7 left-1.5 rounded-full bg-[var(--color-warning)] px-1.5 py-0.5 text-[0.5625rem] font-medium text-[oklch(0.2_0.02_258)]">
                            No alt text
                          </span>
                        )}
                      </button>
                    </li>
                  );
                })}
              </ul>
            )}
          </div>

          {selected && (
            <aside className="scrollbar-slim hidden w-72 shrink-0 overflow-y-auto border-l border-[var(--color-border-subtle)] p-4 md:block">
              <p className="truncate text-sm font-medium text-[var(--color-foreground)]">
                {selected.filename}
              </p>
              <p className="tabular mt-0.5 text-xs text-[var(--color-foreground-subtle)]">
                {selected.width && selected.height
                  ? `${selected.width}×${selected.height} · `
                  : ""}
                {Math.round(selected.size / 1024).toLocaleString()} KB
                {selected.created_at ? ` · ${formatDate(selected.created_at)}` : ""}
              </p>

              <label
                htmlFor="picker-alt"
                className="mt-4 block text-xs font-medium text-[var(--color-foreground)]"
              >
                Alt text
              </label>
              <Textarea
                id="picker-alt"
                defaultValue={selected.alt_text ?? ""}
                key={`alt-${selected.id}`}
                onBlur={(event) => {
                  if (event.target.value !== (selected.alt_text ?? "")) {
                    void saveMeta({ alt_text: event.target.value });
                  }
                }}
                placeholder="Describe the image for someone who cannot see it"
                className="mt-1.5 min-h-20 text-xs"
              />

              <label
                htmlFor="picker-caption"
                className="mt-3 block text-xs font-medium text-[var(--color-foreground)]"
              >
                Caption
              </label>
              <Input
                id="picker-caption"
                key={`caption-${selected.id}`}
                defaultValue={selected.caption ?? ""}
                onBlur={(event) => {
                  if (event.target.value !== (selected.caption ?? "")) {
                    void saveMeta({ caption: event.target.value });
                  }
                }}
                className="mt-1.5 text-xs"
              />

              {savingMeta && (
                <p className="mt-2 text-xs text-[var(--color-foreground-subtle)]" role="status">
                  Saving…
                </p>
              )}
            </aside>
          )}
        </div>

        <footer className="flex shrink-0 items-center justify-between gap-3 border-t border-[var(--color-border-subtle)] px-4 py-3">
          <p className="text-xs text-[var(--color-foreground-subtle)]">
            Double-click a tile to insert it straight away.
          </p>

          <div className="flex gap-2">
            <Button variant="secondary" size="sm" onClick={onClose}>
              Cancel
            </Button>
            <Button
              size="sm"
              disabled={selected === null}
              onClick={() => selected && onSelect(selected)}
            >
              Use this file
            </Button>
          </div>
        </footer>
      </div>
    </div>
  );
}
