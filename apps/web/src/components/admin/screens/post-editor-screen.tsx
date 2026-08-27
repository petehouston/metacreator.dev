"use client";

import {
  ArrowLeft,
  Clock,
  ExternalLink,
  History,
  PanelRightClose,
  PanelRightOpen,
  Save,
  Search,
  Settings2,
  Trash2,
} from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import * as React from "react";

import { StatusPill } from "@/components/admin/admin-page";
import { Can, useCan } from "@/components/admin/can";
import { BlockEditor } from "@/components/admin/editor/block-editor";
import { ConfirmDialog, useToast } from "@/components/admin/feedback";
import { LoadError } from "@/components/admin/load-error";
import { tone } from "@/components/admin/status-tone";
import { Button } from "@/components/ui/button";
import { Checkbox, Field, Input, Select, Textarea } from "@/components/ui/field";
import { adminApi } from "@/lib/admin/api";
import type { AdminPostDetail, PostSeo, Taxonomy } from "@/lib/admin/types";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import type { BlockDocument } from "@/lib/types";
import { cn, formatDate, relativeTime } from "@/lib/utils";

type PanelTab = "settings" | "seo" | "revisions";

interface Draft {
  title: string;
  slug: string;
  excerpt: string;
  blocks: BlockDocument;
  status: string;
  scheduled_for: string;
  category_id: number | null;
  tags: number[];
  is_featured: boolean;
  seo: PostSeo;
}

const EMPTY: Draft = {
  title: "",
  slug: "",
  excerpt: "",
  blocks: { version: 1, blocks: [{ id: "b_first", type: "paragraph", data: { html: "" } }] },
  status: "draft",
  scheduled_for: "",
  category_id: null,
  tags: [],
  is_featured: false,
  seo: {},
};

/**
 * The post editor.
 *
 * The brief was that the writing experience must be "in full", with everything else
 * — status, taxonomy, SEO — arranged somewhere with better UX than a stack of
 * fields under the article. So: a centred writing column at the article's own
 * measure, and a collapsible side panel holding the rest, remembered per editor.
 *
 * Two behaviours are load-bearing rather than decorative:
 *
 * - **The version travels with every save.** Two editors in one post is the normal
 *   case; the server rejects a stale version with a 409 and this screen explains it
 *   rather than silently discarding somebody's morning.
 * - **Unsaved work warns on navigation.** A block editor with no dirty tracking is
 *   a block editor that eats an article the first time someone clicks the sidebar.
 */
export function PostEditorScreen({ id }: { id?: string }) {
  const router = useRouter();
  const can = useCan();
  const { notify, reportError } = useToast();

  const isNew = id === undefined;

  const { data, error, loading, reload } = useAdminResource(
    () =>
      isNew
        ? Promise.resolve({ ok: true as const, data: null as AdminPostDetail | null })
        : adminApi.posts.get(id),
    [id],
  );

  const categories = useAdminResource(() => adminApi.taxonomy.categories(), []);
  const tags = useAdminResource(() => adminApi.taxonomy.tags({ per_page: 200 }), []);

  const [draft, setDraft] = React.useState<Draft>(EMPTY);
  const [version, setVersion] = React.useState(0);
  const [dirty, setDirty] = React.useState(false);
  const [saving, setSaving] = React.useState(false);
  const [panelOpen, setPanelOpen] = React.useState(true);
  const [panelTab, setPanelTab] = React.useState<PanelTab>("settings");
  const [deleting, setDeleting] = React.useState(false);

  const [hydratedFor, setHydratedFor] = React.useState<string | null>(null);

  /**
   * Seed the draft once the post arrives, and re-seed when a different post does.
   *
   * Adjusted during render rather than in an effect, which is the pattern React
   * documents for "reset state when a prop changes": the re-render happens before
   * anything is painted, where an effect would flash the previous post's body into
   * the editor first. Keyed on the post's id specifically — carrying one post's
   * content into another is the bug that turns an editor into a data-loss incident.
   */
  if (data && hydratedFor !== data.post.id) {
    setHydratedFor(data.post.id);

    setDraft({
      title: data.post.title,
      slug: data.post.slug,
      excerpt: data.post.excerpt ?? "",
      blocks: data.blocks?.blocks?.length ? data.blocks : EMPTY.blocks,
      status: data.post.status,
      scheduled_for: data.post.scheduled_for?.slice(0, 16) ?? "",
      category_id: data.post.category?.id ?? null,
      tags: data.post.tags.map((tag) => tag.id),
      is_featured: data.post.is_featured,
      seo: data.seo ?? {},
    });

    setVersion(data.post.version);
    setDirty(false);
  }

  React.useEffect(() => {
    if (!dirty) return;

    function onBeforeUnload(event: BeforeUnloadEvent) {
      event.preventDefault();
    }

    window.addEventListener("beforeunload", onBeforeUnload);
    return () => window.removeEventListener("beforeunload", onBeforeUnload);
  }, [dirty]);

  // ⌘S. Writers reach for it whether or not anyone implemented it.
  React.useEffect(() => {
    function onKeyDown(event: KeyboardEvent) {
      if (event.key.toLowerCase() === "s" && (event.metaKey || event.ctrlKey)) {
        event.preventDefault();
        void save();
      }
    }

    document.addEventListener("keydown", onKeyDown);
    return () => document.removeEventListener("keydown", onKeyDown);
  });

  if (error) return <LoadError error={error} onRetry={reload} />;

  if (loading && !isNew && !data) {
    return (
      <div className="mx-auto max-w-3xl">
        <div className="h-10 w-2/3 animate-pulse rounded bg-[var(--color-surface-sunken)]" />
        <div className="mt-6 h-96 animate-pulse rounded-[var(--radius-lg)] bg-[var(--color-surface-sunken)]" />
      </div>
    );
  }

  const readOnly = !isNew && !can(["posts.update", "posts.update.own"]);

  function patch(next: Partial<Draft>) {
    setDraft((current) => ({ ...current, ...next }));
    setDirty(true);
  }

  async function save(overrides: Partial<Draft> = {}) {
    if (saving) return;

    const merged = { ...draft, ...overrides };

    if (merged.title.trim() === "") {
      notify("Give the post a title before saving.", "error");
      return;
    }

    setSaving(true);

    const payload: Record<string, unknown> = {
      title: merged.title,
      slug: merged.slug || undefined,
      excerpt: merged.excerpt,
      blocks: merged.blocks,
      status: merged.status,
      category_id: merged.category_id,
      tags: merged.tags,
      is_featured: merged.is_featured,
      seo: merged.seo,
      ...(merged.status === "scheduled" && merged.scheduled_for !== ""
        ? { scheduled_for: new Date(merged.scheduled_for).toISOString() }
        : {}),
      ...(isNew ? {} : { version }),
    };

    const result = isNew
      ? await adminApi.posts.create(payload)
      : await adminApi.posts.update(id!, payload);

    setSaving(false);

    if (!result.ok) {
      if (result.error.status === 409) {
        notify(result.error.message, "error");
      } else {
        reportError(result.error);
      }

      return;
    }

    setVersion(result.data.post.version);
    setDraft((current) => ({ ...current, ...overrides, slug: result.data.post.slug }));
    setDirty(false);
    notify(isNew ? "Post created." : "Saved.");

    if (isNew) {
      router.replace(`/admin/posts/${result.data.post.id}`);
    }
  }

  async function remove() {
    if (isNew || !id) return;

    const result = await adminApi.posts.remove(id);

    if (result.ok) {
      notify("Moved to the trash. Recoverable for thirty days.");
      router.push("/admin/posts");
    } else {
      reportError(result.error);
    }
  }

  const post = data?.post;

  return (
    <>
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <Button asChild variant="ghost" size="sm" className="-ml-2">
          <Link href="/admin/posts">
            <ArrowLeft className="size-4" aria-hidden="true" />
            All posts
          </Link>
        </Button>

        <div className="flex flex-wrap items-center gap-2">
          {post && (
            <StatusPill label={post.status_label} tone={tone.post(post.status)} />
          )}

          {dirty && (
            <span className="tabular text-xs text-[var(--color-warning)]" role="status">
              Unsaved changes
            </span>
          )}

          {!dirty && post?.updated_at && (
            <span className="text-xs text-[var(--color-foreground-subtle)]">
              Saved {relativeTime(post.updated_at)}
            </span>
          )}

          {post?.status === "published" && (
            <Button asChild variant="ghost" size="sm">
              <Link href={`/blog/${draft.slug}`} target="_blank" rel="noreferrer">
                <ExternalLink className="size-4" aria-hidden="true" />
                View
              </Link>
            </Button>
          )}

          <Button
            variant="ghost"
            size="sm"
            onClick={() => setPanelOpen((open) => !open)}
            aria-label={panelOpen ? "Hide the settings panel" : "Show the settings panel"}
            aria-expanded={panelOpen}
          >
            {panelOpen ? (
              <PanelRightClose className="size-4" aria-hidden="true" />
            ) : (
              <PanelRightOpen className="size-4" aria-hidden="true" />
            )}
            <span className="hidden sm:inline">Settings</span>
          </Button>

          {!readOnly && (
            <>
              <Button
                variant="secondary"
                size="sm"
                onClick={() => void save()}
                loading={saving}
                disabled={!dirty && !isNew}
              >
                <Save className="size-4" aria-hidden="true" />
                Save
              </Button>

              {draft.status !== "published" && (
                <Can permission="posts.publish">
                  <Button
                    size="sm"
                    onClick={() => {
                      patch({ status: "published" });
                      void save({ status: "published" });
                    }}
                    disabled={saving}
                  >
                    Publish
                  </Button>
                </Can>
              )}
            </>
          )}
        </div>
      </div>

      <div className={cn("flex gap-6", panelOpen && "xl:pr-0")}>
        {/* The writing column, at the article's own measure. Nothing that belongs
            to editing sits inside it — the width you type at is the width that
            ships. */}
        <div className="min-w-0 flex-1">
          <div className="mx-auto max-w-[44rem] pl-0 lg:pl-16">
            <textarea
              value={draft.title}
              onChange={(event) => patch({ title: event.target.value })}
              placeholder="Title"
              aria-label="Post title"
              rows={1}
              readOnly={readOnly}
              // Grows with the title rather than scrolling inside a fixed box: a
              // headline you cannot see all of is a headline you cannot judge.
              onInput={(event) => {
                const target = event.currentTarget;
                target.style.height = "auto";
                target.style.height = `${target.scrollHeight}px`;
              }}
              className="w-full resize-none overflow-hidden bg-transparent text-[2.25rem] font-bold leading-[1.12] tracking-[-0.025em] text-[var(--color-foreground)] outline-none placeholder:text-[var(--color-foreground-subtle)]"
            />

            <div className="mt-6">
              <BlockEditor
                document={draft.blocks}
                onChange={(blocks) => patch({ blocks })}
              />
            </div>
          </div>
        </div>

        {panelOpen && (
          <aside className="hidden w-[20rem] shrink-0 xl:block">
            <div className="app-card sticky top-20 overflow-hidden">
              <div
                role="tablist"
                aria-label="Post settings"
                className="flex border-b border-[var(--color-border-subtle)]"
              >
                {(
                  [
                    ["settings", "Post", Settings2],
                    ["seo", "SEO", Search],
                    ["revisions", "History", History],
                  ] as const
                ).map(([value, label, Icon]) => (
                  <button
                    key={value}
                    type="button"
                    role="tab"
                    aria-selected={panelTab === value}
                    onClick={() => setPanelTab(value)}
                    className={cn(
                      "flex flex-1 items-center justify-center gap-1.5 px-3 py-2.5 text-xs font-medium transition-colors",
                      panelTab === value
                        ? "border-b-2 border-[var(--color-primary)] text-[var(--color-foreground)]"
                        : "text-[var(--color-foreground-muted)] hover:text-[var(--color-foreground)]",
                    )}
                  >
                    <Icon className="size-3.5" aria-hidden="true" />
                    {label}
                  </button>
                ))}
              </div>

              <div className="scrollbar-slim max-h-[calc(100dvh-12rem)] overflow-y-auto p-4">
                {panelTab === "settings" && (
                  <SettingsTab
                    draft={draft}
                    patch={patch}
                    readOnly={readOnly}
                    categories={categories.data?.data ?? []}
                    tags={tags.data?.data ?? []}
                    allowedTransitions={post?.allowed_transitions}
                    onDelete={() => setDeleting(true)}
                  />
                )}

                {panelTab === "seo" && (
                  <SeoTab draft={draft} patch={patch} readOnly={readOnly} />
                )}

                {panelTab === "revisions" && <RevisionsTab revisions={data?.revisions ?? []} />}
              </div>
            </div>
          </aside>
        )}
      </div>

      <ConfirmDialog
        open={deleting}
        title="Move this post to the trash?"
        description="It is recoverable for thirty days. While it is trashed, its URL returns 410 so search engines drop it promptly rather than re-crawling for months."
        confirmLabel="Move to trash"
        destructive
        onConfirm={() => void remove()}
        onCancel={() => setDeleting(false)}
      />
    </>
  );
}

function SettingsTab({
  draft,
  patch,
  readOnly,
  categories,
  tags,
  allowedTransitions,
  onDelete,
}: {
  draft: Draft;
  patch: (next: Partial<Draft>) => void;
  readOnly: boolean;
  categories: Taxonomy[];
  tags: Taxonomy[];
  allowedTransitions?: string[];
  onDelete: () => void;
}) {
  const canPublish = useCan()("posts.publish");

  // Only the moves the lifecycle allows are offered. A dropdown that lets someone
  // pick a transition the server rejects is a dropdown that teaches them the tool
  // is unreliable.
  const statuses = [
    { value: "draft", label: "Draft" },
    { value: "scheduled", label: "Scheduled" },
    { value: "published", label: "Published" },
    { value: "unpublished", label: "Unpublished" },
    { value: "archived", label: "Archived" },
  ].filter(
    (option) =>
      option.value === draft.status ||
      allowedTransitions === undefined ||
      allowedTransitions.includes(option.value),
  );

  return (
    <div className="flex flex-col gap-4">
      <Field id="post-status" label="Status">
        {(props) => (
          <Select
            {...props}
            value={draft.status}
            disabled={readOnly}
            onChange={(event) => patch({ status: event.target.value })}
          >
            {statuses.map((option) => (
              <option
                key={option.value}
                value={option.value}
                disabled={option.value === "published" && !canPublish}
              >
                {option.label}
              </option>
            ))}
          </Select>
        )}
      </Field>

      {draft.status === "scheduled" && (
        <Field
          id="post-schedule"
          label="Publish at"
          hint="The scheduler runs every minute, so 09:00 means 09:00."
          required
        >
          {(props) => (
            <Input
              {...props}
              type="datetime-local"
              value={draft.scheduled_for}
              disabled={readOnly}
              onChange={(event) => patch({ scheduled_for: event.target.value })}
            />
          )}
        </Field>
      )}

      <Field
        id="post-slug"
        label="Slug"
        hint={
          draft.status === "published"
            ? "This post is live — changing its slug breaks every link pointing at it."
            : "Left blank, it is generated from the title."
        }
      >
        {(props) => (
          <Input
            {...props}
            value={draft.slug}
            disabled={readOnly}
            onChange={(event) => patch({ slug: event.target.value })}
            placeholder="generated-from-the-title"
            className="font-mono text-xs"
          />
        )}
      </Field>

      <Field
        id="post-excerpt"
        label="Excerpt"
        hint="Used on cards and as the meta description fallback. Generated from the first paragraph if you leave it blank."
        counter={`${draft.excerpt.length}/500`}
      >
        {(props) => (
          <Textarea
            {...props}
            value={draft.excerpt}
            disabled={readOnly}
            maxLength={500}
            onChange={(event) => patch({ excerpt: event.target.value })}
            className="min-h-20 text-sm"
          />
        )}
      </Field>

      <Field id="post-category" label="Category">
        {(props) => (
          <Select
            {...props}
            value={draft.category_id === null ? "" : String(draft.category_id)}
            disabled={readOnly}
            onChange={(event) =>
              patch({ category_id: event.target.value === "" ? null : Number(event.target.value) })
            }
          >
            {/* Keyed like its mapped siblings: once a mapped list sits in the
                children array React validates the whole array, and an unkeyed
                static element beside one is exactly what it warns about. */}
            <option key="none" value="">
              Uncategorised
            </option>
            {categories.map((category) => (
              <option key={category.id} value={category.id}>
                {category.name}
              </option>
            ))}
          </Select>
        )}
      </Field>

      <fieldset>
        <legend className="mb-1.5 text-sm font-medium text-[var(--color-foreground)]">Tags</legend>

        <div className="scrollbar-slim flex max-h-40 flex-wrap gap-1.5 overflow-y-auto">
          {tags.length === 0 && (
            <p key="empty" className="text-xs text-[var(--color-foreground-subtle)]">
              No tags yet.{" "}
              <Link href="/admin/taxonomy" className="text-[var(--color-primary)] hover:underline">
                Create some
              </Link>
              .
            </p>
          )}

          {tags.map((tag) => {
            const selected = draft.tags.includes(tag.id);

            return (
              <label
                key={tag.id}
                className={cn(
                  "cursor-pointer rounded-full border px-2.5 py-1 text-xs transition-colors",
                  selected
                    ? "border-[var(--color-primary)] bg-[var(--color-primary)] text-[var(--color-primary-foreground)]"
                    : "border-[var(--color-border)] text-[var(--color-foreground-muted)] hover:border-[var(--color-border-strong)]",
                )}
              >
                <input
                  type="checkbox"
                  checked={selected}
                  disabled={readOnly}
                  onChange={() =>
                    patch({
                      tags: selected
                        ? draft.tags.filter((entry) => entry !== tag.id)
                        : [...draft.tags, tag.id],
                    })
                  }
                  className="sr-only"
                />
                {tag.name}
              </label>
            );
          })}
        </div>
      </fieldset>

      <Checkbox
        label="Feature this post"
        hint="Featured posts lead the blog grid on page one."
        checked={draft.is_featured}
        disabled={readOnly}
        onChange={(event) => patch({ is_featured: event.target.checked })}
      />

      <Can permission="posts.delete">
        <button
          type="button"
          onClick={onDelete}
          className="mt-2 inline-flex items-center gap-1.5 self-start text-xs text-[var(--color-danger)] hover:underline"
        >
          <Trash2 className="size-3.5" aria-hidden="true" />
          Move to trash
        </button>
      </Can>
    </div>
  );
}

function SeoTab({
  draft,
  patch,
  readOnly,
}: {
  draft: Draft;
  patch: (next: Partial<Draft>) => void;
  readOnly: boolean;
}) {
  const seo = draft.seo;

  function setSeo(next: Partial<PostSeo>) {
    patch({ seo: { ...seo, ...next } });
  }

  const title = seo.title || draft.title;
  const description = seo.description || draft.excerpt;

  return (
    <div className="flex flex-col gap-4">
      {/* A live search preview. Character counters tell you a number; this tells
          you what will actually get truncated, which is the thing being decided. */}
      <div className="rounded-[var(--radius-md)] border border-[var(--color-border-subtle)] bg-[var(--color-surface-sunken)] p-3">
        <p className="mb-2 font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
          Search preview
        </p>
        <p className="truncate text-xs text-[var(--color-foreground-subtle)]">
          metacreator.dev › blog › {draft.slug || "your-post"}
        </p>
        <p className="mt-0.5 line-clamp-1 text-sm font-medium text-[var(--color-primary)]">
          {title || "Untitled post"}
        </p>
        <p className="mt-0.5 line-clamp-2 text-xs leading-snug text-[var(--color-foreground-muted)]">
          {description || "No description — search engines will pick a snippet themselves."}
        </p>
      </div>

      <Field
        id="seo-title"
        label="Meta title"
        hint="Left blank, the post title is used with the site template."
        counter={`${(seo.title ?? "").length}/60`}
      >
        {(props) => (
          <Input
            {...props}
            value={seo.title ?? ""}
            disabled={readOnly}
            maxLength={255}
            onChange={(event) => setSeo({ title: event.target.value })}
            placeholder={draft.title}
          />
        )}
      </Field>

      <Field
        id="seo-description"
        label="Meta description"
        hint="Aim for 140–160 characters. Longer is truncated; shorter wastes the slot."
        counter={`${(seo.description ?? "").length}/160`}
      >
        {(props) => (
          <Textarea
            {...props}
            value={seo.description ?? ""}
            disabled={readOnly}
            maxLength={500}
            onChange={(event) => setSeo({ description: event.target.value })}
            className="min-h-20 text-sm"
          />
        )}
      </Field>

      <Field
        id="seo-keyword"
        label="Focus keyword"
        hint="What this post is meant to rank for. Recorded, not enforced."
      >
        {(props) => (
          <Input
            {...props}
            value={seo.focus_keyword ?? ""}
            disabled={readOnly}
            onChange={(event) => setSeo({ focus_keyword: event.target.value })}
          />
        )}
      </Field>

      <Field
        id="seo-canonical"
        label="Canonical URL"
        hint="Only when this content is published elsewhere first."
      >
        {(props) => (
          <Input
            {...props}
            type="url"
            value={seo.canonical_url ?? ""}
            disabled={readOnly}
            onChange={(event) => setSeo({ canonical_url: event.target.value })}
            placeholder="https://…"
            className="font-mono text-xs"
          />
        )}
      </Field>

      <Field id="seo-robots" label="Robots">
        {(props) => (
          <Select
            {...props}
            value={seo.robots ?? "index,follow"}
            disabled={readOnly}
            onChange={(event) => setSeo({ robots: event.target.value })}
          >
            <option value="index,follow">Index and follow</option>
            <option value="noindex,follow">Do not index, follow links</option>
            <option value="index,nofollow">Index, do not follow links</option>
            <option value="noindex,nofollow">Do not index or follow</option>
          </Select>
        )}
      </Field>

      <Field
        id="seo-og-title"
        label="Social title"
        hint="Shown when the post is shared. Falls back to the meta title."
      >
        {(props) => (
          <Input
            {...props}
            value={seo.og_title ?? ""}
            disabled={readOnly}
            onChange={(event) => setSeo({ og_title: event.target.value })}
            placeholder={title}
          />
        )}
      </Field>

      <Field id="seo-twitter" label="Card type">
        {(props) => (
          <Select
            {...props}
            value={seo.twitter_card ?? "summary_large_image"}
            disabled={readOnly}
            onChange={(event) => setSeo({ twitter_card: event.target.value })}
          >
            <option value="summary_large_image">Large image</option>
            <option value="summary">Summary</option>
          </Select>
        )}
      </Field>
    </div>
  );
}

function RevisionsTab({
  revisions,
}: {
  revisions: { id: number; title: string; is_autosave: boolean; created_at: string | null }[];
}) {
  if (revisions.length === 0) {
    return (
      <p className="py-6 text-center text-sm text-[var(--color-foreground-subtle)]">
        No revisions yet. One is taken every time the content is saved over.
      </p>
    );
  }

  return (
    <ol className="flex flex-col gap-2.5">
      {revisions.map((revision) => (
        <li key={revision.id} className="flex items-start gap-2.5">
          <Clock
            className="mt-0.5 size-3.5 shrink-0 text-[var(--color-foreground-subtle)]"
            aria-hidden="true"
          />
          <span className="min-w-0 flex-1">
            <span className="block truncate text-sm text-[var(--color-foreground)]">
              {revision.title}
            </span>
            <span className="block text-xs text-[var(--color-foreground-subtle)]">
              {revision.created_at ? formatDate(revision.created_at) : "—"}
              {revision.is_autosave && " · autosave"}
            </span>
          </span>
        </li>
      ))}
    </ol>
  );
}
