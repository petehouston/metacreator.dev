"use client";

import { AlertTriangle, FileAudio, FileText, Film, ImageIcon, Upload } from "lucide-react";
import Link from "next/link";
import * as React from "react";

import { AdminPageHeader, AdminPanel, StatusPill } from "@/components/admin/admin-page";
import { Can } from "@/components/admin/can";
import { useToast } from "@/components/admin/feedback";
import { LoadError } from "@/components/admin/load-error";
import { Pagination } from "@/components/admin/data-table";
import { FilterSelect, SearchInput } from "@/components/admin/toolbar";
import { Button } from "@/components/ui/button";
import { adminApi } from "@/lib/admin/api";
import { usePagedFilters } from "@/lib/admin/use-paged-filters";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import { formatBytes } from "@/lib/utils";

const KIND_ICONS: Record<string, React.ComponentType<{ className?: string }>> = {
  image: ImageIcon,
  video: Film,
  audio: FileAudio,
  application: FileText,
};

/**
 * The media library.
 *
 * A grid, not a table: the thing being chosen is what a file *looks like*, and a
 * filename column makes that a memory exercise. The one piece of metadata promoted
 * onto the tile is whether the image has alt text — an image library without it
 * produces an inaccessible site one upload at a time, and it is invisible unless
 * something makes it visible.
 *
 * A tile links to `/c0ns0le/media/<id>`, where the file is described and, if it comes
 * to it, removed.
 */
export function MediaScreen() {
  const { notify, reportError } = useToast();

  const [{ query, kind }, setFilters, page, setPage] = usePagedFilters({ query: "", kind: "" });

  const [uploading, setUploading] = React.useState(false);

  const fileInput = React.useRef<HTMLInputElement>(null);

  const { data, error, loading, reload } = useAdminResource(
    () =>
      adminApi.media.list({
        q: query || undefined,
        "filter[kind]": kind || undefined,
        page,
        per_page: 40,
      }),
    [query, kind, page],
  );

  if (error) return <LoadError error={error} onRetry={reload} />;

  async function upload(files: FileList | null) {
    if (!files || files.length === 0) return;

    setUploading(true);

    // Sequential rather than parallel: ten images at once will happily saturate an
    // upstream link and time out, and a half-finished batch is worse than a slow one.
    let uploaded = 0;

    for (const file of Array.from(files)) {
      const form = new FormData();
      form.append("file", file);

      const result = await adminApi.media.upload(form);

      if (result.ok) {
        uploaded += 1;
      } else {
        reportError(result.error);
        break;
      }
    }

    setUploading(false);

    if (uploaded > 0) {
      notify(
        `${uploaded} ${uploaded === 1 ? "file" : "files"} uploaded. Add alt text before using them.`,
      );
      reload();
    }
  }

  const items = data?.data ?? [];
  const missingAlt = items.filter((item) => item.kind === "image" && !item.alt_text && !item.is_decorative);

  return (
    <>
      <AdminPageHeader
        eyebrow="Content"
        title="Media"
        description="Every file, with the alt text it needs. Deleting a file here removes it from the library but leaves the bytes in place — an article published last year may still point at them."
        actions={
          <Can permission="media.create">
            <>
              <input
                ref={fileInput}
                type="file"
                multiple
                accept="image/*,video/*,audio/*,.pdf"
                onChange={(event) => {
                  void upload(event.target.files);
                  event.target.value = "";
                }}
                className="sr-only"
              />
              <Button size="sm" onClick={() => fileInput.current?.click()} loading={uploading}>
                <Upload className="size-4" aria-hidden="true" />
                Upload
              </Button>
            </>
          </Can>
        }
      />

      {missingAlt.length > 0 && (
        <div
          role="status"
          className="mb-4 flex items-start gap-2.5 rounded-[var(--radius-md)] border border-[var(--color-warning)]/30 bg-[var(--color-warning)]/8 px-3 py-2.5"
        >
          <AlertTriangle
            className="mt-0.5 size-4 shrink-0 text-[var(--color-warning)]"
            aria-hidden="true"
          />
          <p className="text-sm leading-relaxed text-[var(--color-foreground-muted)]">
            {missingAlt.length} {missingAlt.length === 1 ? "image on this page has" : "images on this page have"}{" "}
            no alt text. Add a description, or mark it decorative — &ldquo;no alt
            text&rdquo; should be a decision, not an omission.
          </p>
        </div>
      )}

      <AdminPanel
        title="Library"
        description={data ? `${data.meta.page.total} files` : "Loading…"}
        bodyClassName="p-0"
        action={
          <div className="flex flex-wrap items-center gap-2">
            <SearchInput
              value={query}
              onChange={(next) => setFilters({ query: next })}
              placeholder="Filename or alt text…"
              className="w-48"
            />
            <FilterSelect
              label="Kind"
              value={kind}
              onChange={(next) => setFilters({ kind: next })}
              options={[
                { value: "", label: "All" },
                { value: "image", label: "Images" },
                { value: "video", label: "Video" },
                { value: "audio", label: "Audio" },
                { value: "application", label: "Documents" },
              ]}
            />
          </div>
        }
      >
        {loading && items.length === 0 ? (
          <div className="grid grid-cols-2 gap-3 p-4 sm:grid-cols-3 lg:grid-cols-5">
            {Array.from({ length: 10 }, (_, index) => (
              <div
                key={index}
                className="aspect-square animate-pulse rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)]"
                aria-hidden="true"
              />
            ))}
          </div>
        ) : items.length === 0 ? (
          <div className="px-4 py-16 text-center">
            <span className="mx-auto mb-3 flex size-11 items-center justify-center rounded-full bg-[var(--color-surface-sunken)] text-[var(--color-foreground-subtle)]">
              <ImageIcon className="size-5" aria-hidden="true" />
            </span>
            <p className="text-sm font-semibold text-[var(--color-foreground)]">
              {query === "" ? "The library is empty" : `Nothing matches “${query}”`}
            </p>
            <p className="mx-auto mt-1 max-w-sm text-sm text-[var(--color-foreground-muted)]">
              {query === ""
                ? "Upload an image and it will be available to every post and tool page."
                : "Try a partial filename, or clear the search."}
            </p>
          </div>
        ) : (
          <ul className="grid grid-cols-2 gap-3 p-4 sm:grid-cols-3 lg:grid-cols-5">
            {items.map((item) => {
              const Icon = KIND_ICONS[item.kind] ?? FileText;
              const needsAlt = item.kind === "image" && !item.alt_text && !item.is_decorative;

              return (
                <li key={item.id}>
                  <Link
                    href={`/c0ns0le/media/${item.id}`}
                    className="app-card app-card-interactive group block w-full overflow-hidden text-left"
                  >
                    <span className="relative flex aspect-square items-center justify-center overflow-hidden bg-[var(--color-surface-sunken)]">
                      {item.kind === "image" && item.url ? (
                        // A plain <img>: sources come from whatever disk is configured,
                        // and enumerating every possible host in next/image's remote
                        // patterns for an internal grid is not worth the coupling.
                        // eslint-disable-next-line @next/next/no-img-element
                        <img
                          src={item.url}
                          alt=""
                          loading="lazy"
                          className="size-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
                        />
                      ) : (
                        <Icon className="size-8 text-[var(--color-foreground-subtle)]" aria-hidden="true" />
                      )}

                      {needsAlt && (
                        <span className="absolute left-1.5 top-1.5">
                          <StatusPill label="No alt text" tone="warning" />
                        </span>
                      )}
                    </span>

                    <span className="block p-2">
                      <span className="block truncate text-xs font-medium text-[var(--color-foreground)]">
                        {item.title || item.filename}
                      </span>
                      <span className="tabular block text-[0.625rem] text-[var(--color-foreground-subtle)]">
                        {formatBytes(item.size)}
                        {item.width && item.height ? ` · ${item.width}×${item.height}` : ""}
                      </span>
                    </span>
                  </Link>
                </li>
              );
            })}
          </ul>
        )}

        {data && (
          <Pagination
            page={data.meta.page.current}
            lastPage={data.meta.page.last_page}
            total={data.meta.page.total}
            perPage={data.meta.page.per_page}
            onChange={setPage}
          />
        )}
      </AdminPanel>
    </>
  );
}
