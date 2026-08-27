"use client";

import { Pencil, Plus, Trash2 } from "lucide-react";
import * as React from "react";

import { AdminPageHeader, AdminPanel } from "@/components/admin/admin-page";
import { Can } from "@/components/admin/can";
import { ConfirmDialog, Drawer, useToast } from "@/components/admin/feedback";
import { LoadError } from "@/components/admin/load-error";
import { SearchInput } from "@/components/admin/toolbar";
import { Button } from "@/components/ui/button";
import { Field, Input, Textarea } from "@/components/ui/field";
import { adminApi } from "@/lib/admin/api";
import type { Taxonomy } from "@/lib/admin/types";
import { useAdminResource } from "@/lib/admin/use-admin-resource";

type Kind = "category" | "tag";

/**
 * How the blog is organised.
 *
 * Both taxonomies on one screen because they are edited in the same sitting — an
 * editor tidying up tags almost always notices a category that needs renaming, and
 * two routes make that two navigations.
 */
export function TaxonomyScreen() {
  const { notify, reportError } = useToast();

  const [tagQuery, setTagQuery] = React.useState("");
  const [editing, setEditing] = React.useState<{ kind: Kind; item?: Taxonomy } | null>(null);
  const [deleting, setDeleting] = React.useState<{ kind: Kind; item: Taxonomy } | null>(null);
  const [pending, setPending] = React.useState(false);

  const categories = useAdminResource(() => adminApi.taxonomy.categories(), []);
  const tags = useAdminResource(
    () => adminApi.taxonomy.tags({ q: tagQuery || undefined }),
    [tagQuery],
  );

  if (categories.error) {
    return <LoadError error={categories.error} onRetry={categories.reload} />;
  }

  async function remove() {
    if (!deleting) return;

    setPending(true);

    const result =
      deleting.kind === "category"
        ? await adminApi.taxonomy.removeCategory(deleting.item.slug)
        : await adminApi.taxonomy.removeTag(deleting.item.slug);

    setPending(false);

    if (result.ok) {
      notify(`${deleting.item.name} was deleted. Its posts are untouched.`);
      setDeleting(null);

      if (deleting.kind === "category") {
        categories.reload();
      } else {
        tags.reload();
      }
    } else {
      reportError(result.error);
    }
  }

  return (
    <>
      <AdminPageHeader
        eyebrow="Content"
        title="Categories & tags"
        description="A category is where a post lives; tags are what it is about. Deleting either never deletes the writing — the label is simply removed."
      />

      <div className="grid gap-4 lg:grid-cols-2">
        <AdminPanel
          title="Categories"
          description="One per post, ordered by hand"
          action={
            <Can permission="post_categories.create">
              <Button size="sm" variant="secondary" onClick={() => setEditing({ kind: "category" })}>
                <Plus className="size-4" aria-hidden="true" />
                New
              </Button>
            </Can>
          }
          bodyClassName="p-0"
        >
          <TaxonomyList
            items={categories.data?.data ?? []}
            loading={categories.loading}
            emptyLabel="No categories yet. Posts can live without one, but the blog reads better with three or four."
            onEdit={(item) => setEditing({ kind: "category", item })}
            onDelete={(item) => setDeleting({ kind: "category", item })}
            editPermission="post_categories.update"
            deletePermission="post_categories.delete"
          />
        </AdminPanel>

        <AdminPanel
          title="Tags"
          description="Many per post, ordered by how much they are used"
          action={
            <div className="flex items-center gap-2">
              <SearchInput
                value={tagQuery}
                onChange={setTagQuery}
                placeholder="Find a tag…"
                className="w-36"
              />
              <Can permission="tags.create">
                <Button size="sm" variant="secondary" onClick={() => setEditing({ kind: "tag" })}>
                  <Plus className="size-4" aria-hidden="true" />
                  New
                </Button>
              </Can>
            </div>
          }
          bodyClassName="p-0"
        >
          <TaxonomyList
            items={tags.data?.data ?? []}
            loading={tags.loading}
            emptyLabel={
              tagQuery === ""
                ? "No tags yet. They drive related posts, so a handful goes a long way."
                : `No tag matches “${tagQuery}”.`
            }
            onEdit={(item) => setEditing({ kind: "tag", item })}
            onDelete={(item) => setDeleting({ kind: "tag", item })}
            editPermission="tags.update"
            deletePermission="tags.delete"
          />
        </AdminPanel>
      </div>

      {editing && (
        <TaxonomyEditor
          kind={editing.kind}
          item={editing.item}
          onClose={() => setEditing(null)}
          onSaved={() => {
            if (editing.kind === "category") {
              categories.reload();
            } else {
              tags.reload();
            }

            setEditing(null);
          }}
        />
      )}

      <ConfirmDialog
        open={deleting !== null}
        title={`Delete “${deleting?.item.name}”?`}
        description={
          <>
            <p>
              {deleting?.item.posts_count ?? 0} posts carry
              this label. They are kept — only the label is removed.
            </p>
            <p className="mt-2">
              {deleting?.kind === "category"
                ? "Those posts become uncategorised."
                : "Related-post suggestions that relied on this tag will change."}
            </p>
          </>
        }
        confirmLabel="Delete"
        destructive
        pending={pending}
        onConfirm={() => void remove()}
        onCancel={() => setDeleting(null)}
      />
    </>
  );
}

function TaxonomyList({
  items,
  loading,
  emptyLabel,
  onEdit,
  onDelete,
  editPermission,
  deletePermission,
}: {
  items: Taxonomy[];
  loading: boolean;
  emptyLabel: string;
  onEdit: (item: Taxonomy) => void;
  onDelete: (item: Taxonomy) => void;
  editPermission: string;
  deletePermission: string;
}) {
  if (loading && items.length === 0) {
    return (
      <div className="flex flex-col gap-2 p-4">
        {[0, 1, 2, 3].map((row) => (
          <div
            key={row}
            className="h-8 animate-pulse rounded bg-[var(--color-surface-sunken)]"
            aria-hidden="true"
          />
        ))}
      </div>
    );
  }

  if (items.length === 0) {
    return (
      <p className="px-4 py-10 text-center text-sm text-[var(--color-foreground-muted)]">
        {emptyLabel}
      </p>
    );
  }

  return (
    <ul className="scrollbar-slim max-h-[28rem] overflow-y-auto">
      {items.map((item) => (
        <li
          key={item.id}
          className="group flex items-center gap-3 border-b border-[var(--color-border-subtle)] px-4 py-2.5 last:border-b-0"
        >
          <span className="min-w-0 flex-1">
            <span className="block truncate text-sm font-medium text-[var(--color-foreground)]">
              {item.name}
            </span>
            <span className="block truncate font-mono text-[0.6875rem] text-[var(--color-foreground-subtle)]">
              /{item.slug}
            </span>
          </span>

          <span className="tabular shrink-0 text-xs text-[var(--color-foreground-subtle)]">
            {item.posts_count}
          </span>

          <span className="flex shrink-0 gap-0.5 opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
            <Can permission={editPermission}>
              <button
                type="button"
                onClick={() => onEdit(item)}
                aria-label={`Edit ${item.name}`}
                className="flex size-7 items-center justify-center rounded-[var(--radius-sm)] text-[var(--color-foreground-subtle)] transition-colors hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-foreground)]"
              >
                <Pencil className="size-3.5" aria-hidden="true" />
              </button>
            </Can>

            <Can permission={deletePermission}>
              <button
                type="button"
                onClick={() => onDelete(item)}
                aria-label={`Delete ${item.name}`}
                className="flex size-7 items-center justify-center rounded-[var(--radius-sm)] text-[var(--color-foreground-subtle)] transition-colors hover:bg-[var(--color-danger)]/10 hover:text-[var(--color-danger)]"
              >
                <Trash2 className="size-3.5" aria-hidden="true" />
              </button>
            </Can>
          </span>
        </li>
      ))}
    </ul>
  );
}

function TaxonomyEditor({
  kind,
  item,
  onClose,
  onSaved,
}: {
  kind: Kind;
  item?: Taxonomy;
  onClose: () => void;
  onSaved: () => void;
}) {
  const { notify, reportError } = useToast();

  const [name, setName] = React.useState(item?.name ?? "");
  const [slug, setSlug] = React.useState(item?.slug ?? "");
  const [description, setDescription] = React.useState(item?.description ?? "");
  const [saving, setSaving] = React.useState(false);
  const [errors, setErrors] = React.useState<Record<string, string[]>>({});

  async function save() {
    setSaving(true);
    setErrors({});

    const body = { name, slug: slug || undefined, description };

    const result =
      kind === "category"
        ? await adminApi.taxonomy.saveCategory(body, item?.slug)
        : await adminApi.taxonomy.saveTag(body, item?.slug);

    setSaving(false);

    if (result.ok) {
      notify(item ? `${name} updated.` : `${name} created.`);
      onSaved();
    } else {
      setErrors(result.error.fieldErrors ?? {});
      reportError(result.error);
    }
  }

  const label = kind === "category" ? "category" : "tag";

  return (
    <Drawer
      open
      title={item ? `Edit ${item.name}` : `New ${label}`}
      description={kind === "category" ? "One per post" : "Many per post"}
      onClose={onClose}
      footer={
        <>
          <Button variant="secondary" size="sm" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button size="sm" onClick={() => void save()} loading={saving} disabled={name.trim() === ""}>
            {item ? "Save" : `Create ${label}`}
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-4">
        <Field id="tax-name" label="Name" error={errors.name?.[0]} required>
          {(props) => (
            <Input
              {...props}
              value={name}
              onChange={(event) => setName(event.target.value)}
              autoFocus
            />
          )}
        </Field>

        <Field
          id="tax-slug"
          label="Slug"
          hint={
            item
              ? "Changing this breaks any link pointing at the old one."
              : "Generated from the name if you leave it blank."
          }
          error={errors.slug?.[0]}
        >
          {(props) => (
            <Input
              {...props}
              value={slug}
              onChange={(event) => setSlug(event.target.value)}
              placeholder="generated-from-the-name"
              className="font-mono text-xs"
            />
          )}
        </Field>

        <Field
          id="tax-description"
          label="Description"
          hint="Shown on the archive page and used as its meta description."
        >
          {(props) => (
            <Textarea
              {...props}
              value={description ?? ""}
              onChange={(event) => setDescription(event.target.value)}
              className="min-h-24 text-sm"
            />
          )}
        </Field>
      </div>
    </Drawer>
  );
}
