"use client";

import {
  ArrowLeft,
  Check,
  Clock,
  Copy,
  Eye,
  ExternalLink,
  History,
  ImagePlus,
  PanelRightClose,
  PanelRightOpen,
  Plus,
  Save,
  Search,
  Settings2,
  Star,
  Trash2,
  X,
} from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import * as React from "react";

import { StatusPill } from "@/components/admin/admin-page";
import { Can, useCan } from "@/components/admin/can";
import { BlockEditor } from "@/components/admin/editor/block-editor";
import { ConfirmDialog, useToast } from "@/components/admin/feedback";
import { LoadError } from "@/components/admin/load-error";
import { MediaPicker } from "@/components/admin/media-picker";
import { tone } from "@/components/admin/status-tone";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox, Field, Input, Select, Textarea } from "@/components/ui/field";
import { siteConfig } from "@/config/site";
import { adminApi } from "@/lib/admin/api";
import { PREVIEW_STORAGE_KEY, PREVIEW_WINDOW } from "@/lib/admin/post-preview";
import type { AdminMedia, AdminPostDetail, PostSeo, Taxonomy } from "@/lib/admin/types";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import type { BlockDocument } from "@/lib/types";
import { cn, formatDate, relativeTime } from "@/lib/utils";

/** How long after the last keystroke a draft saves itself. */
const AUTOSAVE_MS = 7000;

type PanelTab = "settings" | "seo" | "revisions";

interface Draft {
  title: string;
  slug: string;
  excerpt: string;
  blocks: BlockDocument;
  status: string;
  scheduled_for: string;
  /** The primary category — it owns the URL, the breadcrumb and the archive. */
  category_id: number | null;
  /** The other categories the post also appears under. */
  category_ids: number[];
  tags: number[];
  featured_media_id: number | null;
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
  category_ids: [],
  tags: [],
  featured_media_id: null,
  is_featured: false,
  seo: {},
};

/**
 * The post editor.
 *
 * The writing column is the *article*, not a form that happens to hold prose: the
 * same header the public page renders — category, reading time, title, excerpt,
 * byline, featured image — laid out at the same measure, with the block canvas
 * beneath it. The only thing the editor adds is the tools: a gutter outside the
 * column, and a block's options revealed when the caret is in it.
 *
 * Four behaviours are load-bearing rather than decorative:
 *
 * - **The version travels with every save.** Two editors in one post is the normal
 *   case; the server rejects a stale version with a 409 and this screen explains it
 *   rather than silently discarding somebody's morning.
 * - **Autosave every few seconds**, flagged `is_autosave` so the revision it snapshots
 *   is distinguishable from a deliberate save. It never runs on a post that has not
 *   been created yet, and never changes status.
 * - **Preview without publishing** hands the *current, unsaved* draft to a second tab
 *   through session storage. Previewing what is on the server would defeat the point.
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
  const tags = useAdminResource(() => adminApi.taxonomy.tags({ per_page: 500 }), []);

  const [draft, setDraft] = React.useState<Draft>(EMPTY);
  const [featuredMedia, setFeaturedMedia] = React.useState<AdminMedia | null>(null);
  const [version, setVersion] = React.useState(0);
  const [dirty, setDirty] = React.useState(false);
  const [saving, setSaving] = React.useState(false);
  const [autosavedAt, setAutosavedAt] = React.useState<string | null>(null);
  const [panelOpen, setPanelOpen] = React.useState(true);
  const [panelTab, setPanelTab] = React.useState<PanelTab>("settings");
  const [deleting, setDeleting] = React.useState(false);

  /** Set while the media library is open, holding what to do with the choice. */
  const [picking, setPicking] = React.useState<((media: AdminMedia) => void) | null>(null);

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
      category_ids: (data.post.categories ?? []).map((category) => category.id),
      tags: data.post.tags.map((tag) => tag.id),
      featured_media_id: data.featured_media?.numeric_id ?? null,
      is_featured: data.post.is_featured,
      seo: data.seo ?? {},
    });

    setFeaturedMedia(data.featured_media);
    setVersion(data.post.version);
    setDirty(false);
  }

  const readOnly = !isNew && !can(["posts.update", "posts.update.own"]);

  // ── Saving ─────────────────────────────────────────────────────────────────
  // `save` is held in a ref so the autosave timer and the ⌘S handler always call
  // the latest closure without re-arming themselves on every keystroke.
  const saveRef = React.useRef<(overrides?: Partial<Draft>, autosave?: boolean) => Promise<void>>(
    async () => {},
  );

  async function save(overrides: Partial<Draft> = {}, autosave = false) {
    if (saving) return;

    const merged = { ...draft, ...overrides };

    if (merged.title.trim() === "") {
      if (!autosave) notify("Give the post a title before saving.", "error");
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
      category_ids: merged.category_ids,
      tags: merged.tags,
      featured_media_id: merged.featured_media_id,
      is_featured: merged.is_featured,
      seo: merged.seo,
      ...(autosave ? { is_autosave: true } : {}),
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
      // A failed autosave is reported once, quietly: an editor typing into a screen
      // that shouts every seven seconds will turn autosave off in their head first
      // and in the settings second.
      if (result.error.status === 409) {
        notify(result.error.message, "error");
      } else if (!autosave) {
        reportError(result.error);
      }

      return;
    }

    setVersion(result.data.post.version);
    setDraft((current) => ({ ...current, ...overrides, slug: result.data.post.slug }));
    setDirty(false);

    if (autosave) {
      setAutosavedAt(new Date().toISOString());
    } else {
      setAutosavedAt(null);
      notify(isNew ? "Post created." : "Saved.");
    }

    if (isNew) {
      router.replace(`/admin/posts/${result.data.post.id}`);
    }
  }

  // Kept current in an effect rather than during render: the timer below and the
  // ⌘S handler are armed once and must still reach the latest closure.
  React.useEffect(() => {
    saveRef.current = save;
  });

  /**
   * Autosave.
   *
   * Only for a post that already exists — the first save has to be deliberate, or
   * every abandoned "New post" click leaves an empty draft behind. The timer resets
   * on each change, so it fires once the writer pauses rather than mid-sentence.
   */
  React.useEffect(() => {
    if (!dirty || isNew || readOnly || saving) return;

    const timer = setTimeout(() => void saveRef.current({}, true), AUTOSAVE_MS);
    return () => clearTimeout(timer);
  }, [dirty, isNew, readOnly, saving, draft]);

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
        void saveRef.current();
      }
    }

    document.addEventListener("keydown", onKeyDown);
    return () => document.removeEventListener("keydown", onKeyDown);
  }, []);

  if (error) return <LoadError error={error} onRetry={reload} />;

  if (loading && !isNew && !data) {
    return (
      <div className="mx-auto max-w-3xl">
        <div className="h-10 w-2/3 animate-pulse rounded bg-[var(--color-surface-sunken)]" />
        <div className="mt-6 h-96 animate-pulse rounded-[var(--radius-lg)] bg-[var(--color-surface-sunken)]" />
      </div>
    );
  }

  function patch(next: Partial<Draft>) {
    setDraft((current) => ({ ...current, ...next }));
    setDirty(true);
  }

  /**
   * Preview.
   *
   * The draft in the browser, not the row on the server: previewing what was last
   * saved would answer a question nobody asked. Session storage rather than a query
   * string because a block document does not fit in a URL.
   */
  function preview() {
    const payload = {
      title: draft.title,
      slug: draft.slug,
      excerpt: draft.excerpt,
      blocks: draft.blocks,
      status: draft.status,
      category: allCategories.find((category) => category.id === draft.category_id)?.name ?? null,
      tags: tagObjects.map((tag) => tag.name),
      featured_image: featuredMedia?.url ?? null,
      featured_alt: featuredMedia?.alt_text ?? "",
      author: post?.author?.display_name ?? null,
      published_at: post?.published_at ?? null,
      reading_minutes: post?.reading_minutes ?? null,
    };

    try {
      window.sessionStorage.setItem(PREVIEW_STORAGE_KEY, JSON.stringify(payload));
      window.open("/admin/posts/preview", PREVIEW_WINDOW);
    } catch {
      notify("This browser blocked the preview window or its storage.", "error");
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
  const allCategories = categories.data?.data ?? [];
  const allTags = tags.data?.data ?? [];
  const tagObjects = allTags.filter((tag) => draft.tags.includes(tag.id));
  const primaryCategory = allCategories.find((category) => category.id === draft.category_id);

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
          {post && <StatusPill label={post.status_label} tone={tone.post(post.status)} />}

          {saving && (
            <span className="text-xs text-[var(--color-foreground-subtle)]" role="status">
              Saving…
            </span>
          )}

          {!saving && dirty && (
            <span className="tabular text-xs text-[var(--color-warning)]" role="status">
              Unsaved changes
            </span>
          )}

          {!saving && !dirty && autosavedAt && (
            <span className="text-xs text-[var(--color-foreground-subtle)]" role="status">
              Draft autosaved {relativeTime(autosavedAt)}
            </span>
          )}

          {!saving && !dirty && !autosavedAt && post?.updated_at && (
            <span className="text-xs text-[var(--color-foreground-subtle)]">
              Saved {relativeTime(post.updated_at)}
            </span>
          )}

          <Button variant="ghost" size="sm" onClick={preview}>
            <Eye className="size-4" aria-hidden="true" />
            Preview
          </Button>

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

      <div className="flex gap-6">
        {/* The writing column, at the article's own measure and with the article's
            own header. What you arrange here is what the reader sees. */}
        <div className="min-w-0 flex-1">
          <div className="mx-auto max-w-[46rem] pl-0 lg:pl-16">
            <PermalinkBar
              slug={draft.slug}
              readOnly={readOnly}
              onChange={(slug) => patch({ slug })}
              published={post?.status === "published"}
            />

            <div className="mt-6 flex flex-wrap items-center gap-2">
              {primaryCategory ? (
                <Badge variant="neutral" size="md">
                  {primaryCategory.name}
                </Badge>
              ) : (
                <span className="text-xs text-[var(--color-foreground-subtle)]">
                  No category yet
                </span>
              )}

              <span className="tabular font-mono text-[0.6875rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
                {post?.reading_minutes ?? estimateMinutes(draft.blocks)} min read
              </span>
            </div>

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
              className="mt-4 w-full resize-none overflow-hidden bg-transparent text-[2.25rem] font-bold leading-[1.12] tracking-[-0.025em] text-[var(--color-foreground)] outline-none placeholder:text-[var(--color-foreground-subtle)]"
            />

            <textarea
              value={draft.excerpt}
              onChange={(event) => patch({ excerpt: event.target.value })}
              placeholder="A standfirst — the sentence that makes someone read the rest. Left blank, it is taken from the first paragraph."
              aria-label="Excerpt"
              rows={2}
              maxLength={500}
              readOnly={readOnly}
              className="mt-5 w-full resize-none bg-transparent text-[1.0625rem] leading-relaxed text-[var(--color-foreground-muted)] outline-none placeholder:text-[var(--color-foreground-subtle)]"
            />

            <div className="mt-5 flex items-center gap-3 border-t border-[var(--color-border-subtle)] pt-5 text-sm">
              <span className="flex size-9 items-center justify-center rounded-full bg-[var(--color-surface-sunken)] text-xs font-medium text-[var(--color-foreground-muted)]">
                {post?.author?.initials ?? "—"}
              </span>
              <span className="flex flex-col">
                <span className="font-medium text-[var(--color-foreground)]">
                  {post?.author?.display_name ?? "You"}
                </span>
                <span className="text-[var(--color-foreground-subtle)]">
                  {post?.published_at ? formatDate(post.published_at) : "Not published yet"}
                </span>
              </span>
            </div>

            <FeaturedImage
              media={featuredMedia}
              readOnly={readOnly}
              onPick={() =>
                setPicking(() => (media: AdminMedia) => {
                  setFeaturedMedia(media);
                  patch({ featured_media_id: media.numeric_id });
                })
              }
              onClear={() => {
                setFeaturedMedia(null);
                patch({ featured_media_id: null });
              }}
            />

            <div className="mt-8">
              <BlockEditor
                document={draft.blocks}
                onChange={(blocks) => patch({ blocks })}
                onPickMedia={(apply) =>
                  setPicking(() => (media: AdminMedia) =>
                    apply({
                      url: media.url ?? "",
                      alt: media.alt_text ?? "",
                      width: media.width,
                      height: media.height,
                    }),
                  )
                }
              />
            </div>

            {tagObjects.length > 0 && (
              <div className="mt-10 flex flex-wrap items-center gap-2 border-t border-[var(--color-border-subtle)] pt-6">
                <span className="eyebrow">Tagged</span>
                {tagObjects.map((tag) => (
                  <Badge key={tag.id} variant="neutral">
                    {tag.name}
                  </Badge>
                ))}
              </div>
            )}
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
                    categories={allCategories}
                    tags={allTags}
                    onTaxonomyChanged={() => {
                      categories.reload();
                      tags.reload();
                    }}
                    featuredMedia={featuredMedia}
                    onPickFeatured={() =>
                      setPicking(() => (media: AdminMedia) => {
                        setFeaturedMedia(media);
                        patch({ featured_media_id: media.numeric_id });
                      })
                    }
                    onClearFeatured={() => {
                      setFeaturedMedia(null);
                      patch({ featured_media_id: null });
                    }}
                    allowedTransitions={post?.allowed_transitions}
                    onDelete={() => setDeleting(true)}
                  />
                )}

                {panelTab === "seo" && <SeoTab draft={draft} patch={patch} readOnly={readOnly} />}

                {panelTab === "revisions" && <RevisionsTab revisions={data?.revisions ?? []} />}
              </div>
            </div>
          </aside>
        )}
      </div>

      <MediaPicker
        open={picking !== null}
        onClose={() => setPicking(null)}
        onSelect={(media) => {
          picking?.(media);
          setPicking(null);
        }}
      />

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

/**
 * The permalink, spelled out.
 *
 * The full URL rather than the slug alone: what an editor needs to judge is the
 * address readers will see and share, and "how-to-grow" tells you nothing about
 * whether it will end up under /blog/ or somewhere else.
 */
function PermalinkBar({
  slug,
  published,
  readOnly,
  onChange,
}: {
  slug: string;
  published: boolean;
  readOnly: boolean;
  onChange: (slug: string) => void;
}) {
  const [editing, setEditing] = React.useState(false);
  const [copied, setCopied] = React.useState(false);

  const base = `${siteConfig.url}/blog/`;
  const url = `${base}${slug || "your-post"}`;

  async function copy() {
    try {
      await navigator.clipboard.writeText(url);
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    } catch {
      // Clipboard access can be refused; the URL is on screen either way.
    }
  }

  return (
    <div className="flex flex-wrap items-center gap-1.5 rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)]/70 px-2.5 py-1.5 font-mono text-xs">
      <span className="text-[var(--color-foreground-subtle)]">{base}</span>

      {editing && !readOnly ? (
        <input
          autoFocus
          value={slug}
          onChange={(event) => onChange(event.target.value)}
          onBlur={() => setEditing(false)}
          onKeyDown={(event) => {
            if (event.key === "Enter" || event.key === "Escape") setEditing(false);
          }}
          aria-label="Slug"
          className="min-w-32 flex-1 border-b border-[var(--color-primary)] bg-transparent text-[var(--color-foreground)] outline-none"
        />
      ) : (
        <span className="text-[var(--color-foreground)]">{slug || "your-post"}</span>
      )}

      {!readOnly && !editing && (
        <button
          type="button"
          onClick={() => setEditing(true)}
          className="ml-1 text-[var(--color-primary)] hover:underline"
        >
          Edit
        </button>
      )}

      <button
        type="button"
        onClick={() => void copy()}
        aria-label="Copy the full URL"
        title="Copy the full URL"
        className="ml-auto flex size-5 items-center justify-center rounded text-[var(--color-foreground-subtle)] transition-colors hover:text-[var(--color-foreground)]"
      >
        {copied ? (
          <Check className="size-3 text-[var(--color-success)]" aria-hidden="true" />
        ) : (
          <Copy className="size-3" aria-hidden="true" />
        )}
      </button>

      {published && (
        <span className="w-full text-[0.6875rem] text-[var(--color-warning)]">
          This post is live — changing the slug breaks every link pointing at it.
        </span>
      )}
    </div>
  );
}

/** The lead image, in the place and at the width the article gives it. */
function FeaturedImage({
  media,
  readOnly,
  onPick,
  onClear,
}: {
  media: AdminMedia | null;
  readOnly: boolean;
  onPick: () => void;
  onClear: () => void;
}) {
  if (!media?.url) {
    if (readOnly) return null;

    return (
      <button
        type="button"
        onClick={onPick}
        className="mt-8 flex w-full items-center justify-center gap-2 rounded-[var(--radius-xl)] border border-dashed border-[var(--color-border)] py-8 text-sm text-[var(--color-foreground-muted)] transition-colors hover:border-[var(--color-primary)]/50 hover:text-[var(--color-foreground)]"
      >
        <ImagePlus className="size-4" aria-hidden="true" />
        Set a featured image
      </button>
    );
  }

  return (
    <figure className="group relative mt-8">
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img
        src={media.url}
        alt={media.alt_text ?? ""}
        className="w-full rounded-[var(--radius-xl)] border border-[var(--color-border-subtle)] object-cover"
      />

      {!readOnly && (
        <div className="absolute right-3 top-3 flex gap-1.5 opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
          <Button variant="secondary" size="sm" onClick={onPick}>
            Replace
          </Button>
          <Button variant="secondary" size="sm" onClick={onClear} aria-label="Remove the featured image">
            <X className="size-4" aria-hidden="true" />
          </Button>
        </div>
      )}

      {!media.alt_text && !media.is_decorative && (
        <figcaption className="mt-2 text-center text-xs text-[var(--color-warning)]">
          This image has no alt text. Add it in the media library before publishing.
        </figcaption>
      )}
    </figure>
  );
}

function SettingsTab({
  draft,
  patch,
  readOnly,
  categories,
  tags,
  onTaxonomyChanged,
  featuredMedia,
  onPickFeatured,
  onClearFeatured,
  allowedTransitions,
  onDelete,
}: {
  draft: Draft;
  patch: (next: Partial<Draft>) => void;
  readOnly: boolean;
  categories: Taxonomy[];
  tags: Taxonomy[];
  onTaxonomyChanged: () => void;
  featuredMedia: AdminMedia | null;
  onPickFeatured: () => void;
  onClearFeatured: () => void;
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
    <div className="flex flex-col gap-5">
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

      <FeaturedImageBox
        media={featuredMedia}
        readOnly={readOnly}
        onPick={onPickFeatured}
        onClear={onClearFeatured}
      />

      <CategoryBox
        categories={categories}
        primary={draft.category_id}
        secondary={draft.category_ids}
        readOnly={readOnly}
        onChange={(primary, secondary) =>
          patch({ category_id: primary, category_ids: secondary })
        }
        onCreated={onTaxonomyChanged}
      />

      <TagBox
        tags={tags}
        selected={draft.tags}
        readOnly={readOnly}
        onChange={(next) => patch({ tags: next })}
        onCreated={onTaxonomyChanged}
      />

      <Field
        id="post-excerpt"
        label="Excerpt"
        hint="Also editable under the title. Used on cards and as the meta description fallback."
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
          className="inline-flex items-center gap-1.5 self-start text-xs text-[var(--color-danger)] hover:underline"
        >
          <Trash2 className="size-3.5" aria-hidden="true" />
          Move to trash
        </button>
      </Can>
    </div>
  );
}

/** A compact restatement of the featured image, for people working in the panel. */
function FeaturedImageBox({
  media,
  readOnly,
  onPick,
  onClear,
}: {
  media: AdminMedia | null;
  readOnly: boolean;
  onPick: () => void;
  onClear: () => void;
}) {
  return (
    <PanelBox title="Featured image">
      {media?.url ? (
        <div className="flex flex-col gap-2">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img
            src={media.url}
            alt=""
            className="aspect-[16/9] w-full rounded-[var(--radius-md)] border border-[var(--color-border-subtle)] object-cover"
          />
          <p className="truncate text-xs text-[var(--color-foreground-subtle)]">{media.filename}</p>

          {!readOnly && (
            <div className="flex gap-3 text-xs">
              <button
                type="button"
                onClick={onPick}
                className="text-[var(--color-primary)] hover:underline"
              >
                Replace
              </button>
              <button
                type="button"
                onClick={onClear}
                className="text-[var(--color-danger)] hover:underline"
              >
                Remove
              </button>
            </div>
          )}
        </div>
      ) : (
        <button
          type="button"
          onClick={onPick}
          disabled={readOnly}
          className="flex w-full items-center justify-center gap-1.5 rounded-[var(--radius-md)] border border-dashed border-[var(--color-border)] py-4 text-xs text-[var(--color-primary)] transition-colors hover:border-[var(--color-primary)]/50 disabled:opacity-50"
        >
          <ImagePlus className="size-3.5" aria-hidden="true" />
          Choose from the media library
        </button>
      )}
    </PanelBox>
  );
}

/**
 * Categories, the way WordPress does them and for the same reason.
 *
 * A post belongs to several shelves but has exactly one home: the primary category
 * is what the URL, the breadcrumb and the "more in this section" list are built
 * from, so it is declared rather than guessed. Ticking a box adds a shelf; the star
 * moves the home.
 */
function CategoryBox({
  categories,
  primary,
  secondary,
  readOnly,
  onChange,
  onCreated,
}: {
  categories: Taxonomy[];
  primary: number | null;
  secondary: number[];
  readOnly: boolean;
  onChange: (primary: number | null, secondary: number[]) => void;
  onCreated: () => void;
}) {
  const { notify, reportError } = useToast();

  const [adding, setAdding] = React.useState(false);
  const [name, setName] = React.useState("");
  const [creating, setCreating] = React.useState(false);

  function toggle(id: number) {
    if (id === primary) {
      // Unticking the home promotes the next shelf rather than leaving the post
      // homeless while it still claims to be in three categories.
      const [next, ...rest] = secondary;
      onChange(next ?? null, rest);
      return;
    }

    if (secondary.includes(id)) {
      onChange(primary, secondary.filter((entry) => entry !== id));
      return;
    }

    if (primary === null) {
      onChange(id, secondary);
      return;
    }

    onChange(primary, [...secondary, id]);
  }

  function makePrimary(id: number) {
    const rest = [...secondary.filter((entry) => entry !== id), ...(primary === null ? [] : [primary])];
    onChange(id, rest);
  }

  async function create() {
    if (name.trim() === "") return;

    setCreating(true);
    const result = await adminApi.taxonomy.saveCategory({ name: name.trim() });
    setCreating(false);

    if (!result.ok) {
      reportError(result.error);
      return;
    }

    // A category created from here is the one being reached for: select it.
    if (primary === null) {
      onChange(result.data.id, secondary);
    } else {
      onChange(primary, [...secondary, result.data.id]);
    }

    setName("");
    setAdding(false);
    onCreated();
    notify(`“${result.data.name}” created and added.`);
  }

  return (
    <PanelBox title="Categories">
      <ul className="scrollbar-slim flex max-h-56 flex-col gap-0.5 overflow-y-auto">
        {categories.length === 0 && (
          <li className="py-2 text-xs text-[var(--color-foreground-subtle)]">
            No categories yet.
          </li>
        )}

        {categories.map((category) => {
          const isPrimary = category.id === primary;
          const checked = isPrimary || secondary.includes(category.id);

          return (
            <li key={category.id} className="group flex items-center gap-2">
              <label className="flex min-w-0 flex-1 cursor-pointer items-center gap-2 py-1 text-sm">
                <input
                  type="checkbox"
                  checked={checked}
                  disabled={readOnly}
                  onChange={() => toggle(category.id)}
                  className="size-3.5 shrink-0 rounded border-[var(--color-border-strong)] accent-[var(--color-primary)]"
                />
                <span
                  className={cn(
                    "truncate",
                    checked
                      ? "text-[var(--color-foreground)]"
                      : "text-[var(--color-foreground-muted)]",
                  )}
                >
                  {category.name}
                </span>
              </label>

              {isPrimary ? (
                <span className="flex shrink-0 items-center gap-1 text-[0.625rem] font-medium text-[var(--color-primary)]">
                  <Star className="size-3 fill-current" aria-hidden="true" />
                  Primary
                </span>
              ) : (
                checked &&
                !readOnly && (
                  <button
                    type="button"
                    onClick={() => makePrimary(category.id)}
                    className="shrink-0 text-[0.625rem] text-[var(--color-foreground-subtle)] opacity-0 transition-opacity hover:text-[var(--color-primary)] group-hover:opacity-100 focus-visible:opacity-100"
                  >
                    Set primary
                  </button>
                )
              )}
            </li>
          );
        })}
      </ul>

      <Can permission="post_categories.create">
        {adding ? (
          <div className="mt-2 flex gap-1.5">
            <Input
              autoFocus
              value={name}
              onChange={(event) => setName(event.target.value)}
              onKeyDown={(event) => {
                if (event.key === "Enter") void create();
                if (event.key === "Escape") setAdding(false);
              }}
              placeholder="New category name"
              aria-label="New category name"
              className="h-8 text-xs"
            />
            <Button size="sm" onClick={() => void create()} loading={creating}>
              Add
            </Button>
          </div>
        ) : (
          !readOnly && (
            <button
              type="button"
              onClick={() => setAdding(true)}
              className="mt-2 inline-flex items-center gap-1 text-xs text-[var(--color-primary)] hover:underline"
            >
              <Plus className="size-3" aria-hidden="true" />
              Add a new category
            </button>
          )
        )}
      </Can>
    </PanelBox>
  );
}

/**
 * Tags: type to search, Enter to add, Enter again to create.
 *
 * The list is fetched once and filtered here — a tag vocabulary is a few hundred
 * short strings, and a request per keystroke buys nothing but latency.
 */
function TagBox({
  tags,
  selected,
  readOnly,
  onChange,
  onCreated,
}: {
  tags: Taxonomy[];
  selected: number[];
  readOnly: boolean;
  onChange: (next: number[]) => void;
  onCreated: () => void;
}) {
  const { reportError } = useToast();

  const [query, setQuery] = React.useState("");
  const [creating, setCreating] = React.useState(false);

  const chosen = tags.filter((tag) => selected.includes(tag.id));
  const needle = query.trim().toLowerCase();

  const matches =
    needle === ""
      ? []
      : tags
          .filter(
            (tag) => !selected.includes(tag.id) && tag.name.toLowerCase().includes(needle),
          )
          .slice(0, 8);

  const exact = tags.some((tag) => tag.name.toLowerCase() === needle);

  async function create() {
    if (needle === "" || creating) return;

    setCreating(true);
    const result = await adminApi.taxonomy.saveTag({ name: query.trim() });
    setCreating(false);

    if (!result.ok) {
      reportError(result.error);
      return;
    }

    onChange([...selected, result.data.id]);
    setQuery("");
    onCreated();
  }

  function add(id: number) {
    onChange([...selected, id]);
    setQuery("");
  }

  return (
    <PanelBox title="Tags">
      {chosen.length > 0 && (
        <div className="mb-2 flex flex-wrap gap-1.5">
          {chosen.map((tag) => (
            <span
              key={tag.id}
              className="inline-flex items-center gap-1 rounded-full border border-[var(--color-border)] px-2 py-0.5 text-xs text-[var(--color-foreground)]"
            >
              {tag.name}
              {!readOnly && (
                <button
                  type="button"
                  onClick={() => onChange(selected.filter((entry) => entry !== tag.id))}
                  aria-label={`Remove the tag ${tag.name}`}
                  className="text-[var(--color-foreground-subtle)] transition-colors hover:text-[var(--color-danger)]"
                >
                  <X className="size-3" aria-hidden="true" />
                </button>
              )}
            </span>
          ))}
        </div>
      )}

      {!readOnly && (
        <div className="relative">
          <Input
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            onKeyDown={(event) => {
              if (event.key !== "Enter") return;

              event.preventDefault();

              if (matches.length > 0) {
                add(matches[0].id);
              } else if (!exact) {
                void create();
              }
            }}
            placeholder="Search tags, or type a new one"
            aria-label="Search or create a tag"
            className="h-8 text-xs"
          />

          {needle !== "" && (
            <ul className="absolute z-10 mt-1 w-full overflow-hidden rounded-[var(--radius-md)] border border-[var(--color-border)] bg-[var(--app-surface)] shadow-[var(--shadow-popover)]">
              {matches.map((tag) => (
                <li key={tag.id}>
                  <button
                    type="button"
                    onClick={() => add(tag.id)}
                    className="flex w-full items-center justify-between gap-2 px-3 py-1.5 text-left text-xs text-[var(--color-foreground)] hover:bg-[var(--color-surface-sunken)]"
                  >
                    {tag.name}
                    <span className="tabular text-[0.625rem] text-[var(--color-foreground-subtle)]">
                      {tag.posts_count}
                    </span>
                  </button>
                </li>
              ))}

              {!exact && (
                <Can permission="tags.create">
                  <li>
                    <button
                      type="button"
                      onClick={() => void create()}
                      disabled={creating}
                      className="flex w-full items-center gap-1.5 border-t border-[var(--color-border-subtle)] px-3 py-1.5 text-left text-xs text-[var(--color-primary)] hover:bg-[var(--color-surface-sunken)]"
                    >
                      <Plus className="size-3" aria-hidden="true" />
                      Create “{query.trim()}”
                    </button>
                  </li>
                </Can>
              )}

              {matches.length === 0 && exact && (
                <li className="px-3 py-1.5 text-xs text-[var(--color-foreground-subtle)]">
                  Already added.
                </li>
              )}
            </ul>
          )}
        </div>
      )}
    </PanelBox>
  );
}

/** A titled section inside the side panel. */
function PanelBox({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section>
      <h3 className="mb-1.5 text-sm font-medium text-[var(--color-foreground)]">{title}</h3>
      {children}
    </section>
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

/**
 * Reading time for a post the server has not counted yet.
 *
 * The same 200-words-a-minute the backend uses, over the text it can see. Only
 * shown before the first save; after that the server's own number wins.
 */
function estimateMinutes(document: BlockDocument): number {
  const words = (document.blocks ?? [])
    .map((block) => Object.values(block.data ?? {}).filter((value) => typeof value === "string"))
    .flat()
    .join(" ")
    .replace(/<[^>]*>/g, " ")
    .split(/\s+/)
    .filter(Boolean).length;

  return Math.max(1, Math.round(words / 200));
}
