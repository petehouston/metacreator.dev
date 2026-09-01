"use client";

import { Pencil, Plus, Trash2 } from "lucide-react";
import Link from "next/link";
import * as React from "react";

import { AdminPageHeader, AdminPanel } from "@/components/admin/admin-page";
import { Can } from "@/components/admin/can";
import { ConfirmDialog, useToast } from "@/components/admin/feedback";
import { LoadError } from "@/components/admin/load-error";
import { SearchInput } from "@/components/admin/toolbar";
import { Button } from "@/components/ui/button";
import { adminApi } from "@/lib/admin/api";
import type { Taxonomy } from "@/lib/admin/types";
import { useAdminResource } from "@/lib/admin/use-admin-resource";

type Kind = "category" | "tag";

/**
 * How the blog is organised.
 *
 * Both taxonomies on one screen because they are reviewed in the same sitting — an
 * editor tidying up tags almost always notices a category that needs renaming, and
 * two routes make that two navigations. Editing one is a page of its own, so a
 * half-written description survives a refresh and can be linked to.
 */
export function TaxonomyScreen() {
  const { notify, reportError } = useToast();

  const [tagQuery, setTagQuery] = React.useState("");
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
              <Button size="sm" variant="secondary" asChild>
                <Link href="/c0ns0le/taxonomy/categories/new">
                  <Plus className="size-4" aria-hidden="true" />
                  New
                </Link>
              </Button>
            </Can>
          }
          bodyClassName="p-0"
        >
          <TaxonomyList
            items={categories.data?.data ?? []}
            loading={categories.loading}
            emptyLabel="No categories yet. Posts can live without one, but the blog reads better with three or four."
            editHref={(item) => `/c0ns0le/taxonomy/categories/${item.slug}`}
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
                <Button size="sm" variant="secondary" asChild>
                  <Link href="/c0ns0le/taxonomy/tags/new">
                    <Plus className="size-4" aria-hidden="true" />
                    New
                  </Link>
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
            editHref={(item) => `/c0ns0le/taxonomy/tags/${item.slug}`}
            onDelete={(item) => setDeleting({ kind: "tag", item })}
            editPermission="tags.update"
            deletePermission="tags.delete"
          />
        </AdminPanel>
      </div>

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
  editHref,
  onDelete,
  editPermission,
  deletePermission,
}: {
  items: Taxonomy[];
  loading: boolean;
  emptyLabel: string;
  editHref: (item: Taxonomy) => string;
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
              <Link
                href={editHref(item)}
                aria-label={`Edit ${item.name}`}
                className="flex size-7 items-center justify-center rounded-[var(--radius-sm)] text-[var(--color-foreground-subtle)] transition-colors hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-foreground)]"
              >
                <Pencil className="size-3.5" aria-hidden="true" />
              </Link>
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
