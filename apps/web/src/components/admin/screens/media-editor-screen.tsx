"use client";

import { ArrowLeft, Save, Trash2 } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import * as React from "react";

import { AdminPageHeader, AdminPanel } from "@/components/admin/admin-page";
import { Can } from "@/components/admin/can";
import { ConfirmDialog, useToast } from "@/components/admin/feedback";
import { LoadError } from "@/components/admin/load-error";
import { Button } from "@/components/ui/button";
import { Checkbox, Field, Input, Textarea } from "@/components/ui/field";
import { adminApi } from "@/lib/admin/api";
import type { AdminMedia } from "@/lib/admin/types";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import { formatBytes, formatDate } from "@/lib/utils";

/**
 * One file and its description, on its own page.
 *
 * Alt text is the reason this screen exists, and it is the field most likely to be
 * abandoned half-written. A page keeps it addressable: `/admin/media/med_01J…` can
 * be pasted into a ticket that says "this image needs a description", which a panel
 * floating over a grid never could.
 */
export function MediaEditorScreen({ id }: { id: string }) {
  const { data, error, reload } = useAdminResource(() => adminApi.media.get(id), [id]);

  if (error) return <LoadError error={error} onRetry={reload} />;

  if (!data) {
    return (
      <div
        className="h-96 animate-pulse rounded-[var(--radius-lg)] bg-[var(--color-surface-sunken)]"
        aria-hidden="true"
      />
    );
  }

  // Keyed on the loaded file so the form state is built once the values exist.
  return <MediaForm key={data.id} media={data} />;
}

function MediaForm({ media }: { media: AdminMedia }) {
  const router = useRouter();
  const { notify, reportError } = useToast();

  const [form, setForm] = React.useState({
    title: media.title ?? "",
    alt_text: media.alt_text ?? "",
    caption: media.caption ?? "",
    credit: media.credit ?? "",
    is_decorative: media.is_decorative,
  });

  const [saving, setSaving] = React.useState(false);
  const [deleting, setDeleting] = React.useState(false);
  const [pending, setPending] = React.useState(false);

  async function save() {
    setSaving(true);
    const result = await adminApi.media.update(media.id, form);
    setSaving(false);

    if (result.ok) {
      notify("Saved.");
      router.push("/admin/media");
    } else {
      reportError(result.error);
    }
  }

  async function remove() {
    setPending(true);
    const result = await adminApi.media.remove(media.id);
    setPending(false);

    if (result.ok) {
      notify(`${media.filename} removed from the library.`);
      router.push("/admin/media");
    } else {
      reportError(result.error);
    }
  }

  return (
    <>
      <AdminPageHeader
        eyebrow="Content · Media"
        title={media.title || media.filename}
        description={`${formatBytes(media.size)} · ${media.mime_type}`}
        actions={
          <>
            <Button variant="secondary" size="sm" asChild>
              <Link href="/admin/media">
                <ArrowLeft className="size-4" aria-hidden="true" />
                Back to library
              </Link>
            </Button>

            <Can permission={["media.delete", "media.delete.own"]}>
              <Button variant="ghost" size="sm" onClick={() => setDeleting(true)}>
                <Trash2 className="size-4" aria-hidden="true" />
                Remove
              </Button>
            </Can>

            <Can permission={["media.update", "media.update.own"]}>
              <Button size="sm" onClick={() => void save()} loading={saving}>
                <Save className="size-4" aria-hidden="true" />
                Save
              </Button>
            </Can>
          </>
        }
      />

      <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
        <AdminPanel title="Description" description="What this file is, for people who cannot see it">
          <div className="flex max-w-xl flex-col gap-4">
            <Field id="media-title" label="Title" hint="What editors see when picking this file.">
              {(props) => (
                <Input
                  {...props}
                  value={form.title}
                  onChange={(event) => setForm({ ...form, title: event.target.value })}
                />
              )}
            </Field>

            <Field
              id="media-alt"
              label="Alt text"
              hint="Describe what the image shows, for someone who cannot see it. Not the filename, and not “image of”."
              required={media.kind === "image" && !form.is_decorative}
            >
              {(props) => (
                <Textarea
                  {...props}
                  value={form.alt_text}
                  disabled={form.is_decorative}
                  onChange={(event) => setForm({ ...form, alt_text: event.target.value })}
                  className="min-h-20 text-sm"
                />
              )}
            </Field>

            <Checkbox
              label="Decorative"
              hint="A divider, a texture, a flourish. Screen readers skip it — which is right when the image carries no information, and wrong otherwise."
              checked={form.is_decorative}
              onChange={(event) => setForm({ ...form, is_decorative: event.target.checked })}
            />

            <Field
              id="media-caption"
              label="Caption"
              hint="Shown under the image, when used in an article."
            >
              {(props) => (
                <Textarea
                  {...props}
                  value={form.caption}
                  onChange={(event) => setForm({ ...form, caption: event.target.value })}
                  className="min-h-16 text-sm"
                />
              )}
            </Field>

            <Field id="media-credit" label="Credit" hint="Photographer, source, licence.">
              {(props) => (
                <Input
                  {...props}
                  value={form.credit}
                  onChange={(event) => setForm({ ...form, credit: event.target.value })}
                />
              )}
            </Field>
          </div>
        </AdminPanel>

        <div className="flex flex-col gap-5">
          <AdminPanel title="File" bodyClassName="p-4">
            {media.kind === "image" && media.url ? (
              // A plain <img>: sources come from whatever disk is configured, and
              // enumerating every possible host in next/image's remote patterns for
              // an internal screen is not worth the coupling.
              // eslint-disable-next-line @next/next/no-img-element
              <img
                src={media.url}
                alt={form.alt_text || ""}
                className="mb-4 max-h-72 w-full rounded-[var(--radius-md)] border border-[var(--color-border-subtle)] object-contain"
              />
            ) : null}

            <dl className="grid grid-cols-2 gap-3 rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)] p-3 text-sm">
              <div>
                <dt className="font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
                  Used in
                </dt>
                <dd className="tabular mt-0.5 font-medium text-[var(--color-foreground)]">
                  {media.usage_count} {media.usage_count === 1 ? "place" : "places"}
                </dd>
              </div>
              <div>
                <dt className="font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
                  Uploaded
                </dt>
                <dd className="mt-0.5 truncate font-medium text-[var(--color-foreground)]">
                  {media.created_at ? formatDate(media.created_at) : "—"}
                </dd>
              </div>
              <div>
                <dt className="font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
                  Dimensions
                </dt>
                <dd className="tabular mt-0.5 font-medium text-[var(--color-foreground)]">
                  {media.width && media.height ? `${media.width}×${media.height}` : "—"}
                </dd>
              </div>
              <div>
                <dt className="font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
                  Filename
                </dt>
                <dd className="mt-0.5 truncate font-medium text-[var(--color-foreground)]">
                  {media.filename}
                </dd>
              </div>
            </dl>

            {media.url && (
              <div className="mt-4">
                <Field id="media-url" label="URL">
                  {(props) => (
                    <Input {...props} readOnly value={media.url ?? ""} className="font-mono text-xs" />
                  )}
                </Field>
              </div>
            )}
          </AdminPanel>
        </div>
      </div>

      <ConfirmDialog
        open={deleting}
        title={`Remove ${media.filename}?`}
        description={
          <>
            <p>
              It disappears from the library and from any picker. The file itself stays
              on disk, because a post published last year may still reference it — a
              broken image in an article is worse than a byte of storage.
            </p>
            {media.usage_count > 0 && (
              <p className="mt-2 font-medium text-[var(--color-warning)]">
                This file is used in {media.usage_count} places.
              </p>
            )}
          </>
        }
        confirmLabel="Remove from library"
        destructive
        pending={pending}
        onConfirm={() => void remove()}
        onCancel={() => setDeleting(false)}
      />
    </>
  );
}
