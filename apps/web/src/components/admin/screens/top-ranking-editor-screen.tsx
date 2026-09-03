"use client";

import {
  AlertTriangle,
  ArrowLeft,
  ChevronDown,
  ChevronUp,
  Database,
  ExternalLink,
  Gauge,
  ImageOff,
  ListOrdered,
  Pin,
  PinOff,
  Plus,
  RefreshCw,
  Save,
  Search,
  Trash2,
  Type,
} from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import * as React from "react";

import {
  AdminPageHeader,
  AdminPanel,
  StatusPill,
} from "@/components/admin/admin-page";
import { Can, useCan } from "@/components/admin/can";
import { ConfirmDialog, useToast } from "@/components/admin/feedback";
import { LoadError } from "@/components/admin/load-error";
import { MediaPicker } from "@/components/admin/media-picker";
import { SeoPanel } from "@/components/admin/seo-panel";
import { Button } from "@/components/ui/button";
import {
  Checkbox,
  Field,
  Input,
  Select,
  Textarea,
} from "@/components/ui/field";
import { adminApi } from "@/lib/admin/api";
import type {
  AdminMedia,
  AdminTopRankingEntry,
  AdminTopRankingPage,
  SeoOverrides,
} from "@/lib/admin/types";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import { cn, relativeTime } from "@/lib/utils";

/**
 * The editor's sections.
 *
 * Tabs rather than a sidebar, matching the tool editor — and for the same reason
 * it chose them: a rail beside the content is width the rows table does not have
 * to spare, and a fifty-row list squeezed into two thirds of the page is the one
 * thing this screen exists to show.
 */
const SECTIONS = [
  { id: "rows", label: "Rows", icon: ListOrdered },
  { id: "presentation", label: "Presentation", icon: Type },
  { id: "source", label: "Source & sync", icon: Database },
  { id: "metrics", label: "Metrics", icon: Gauge },
  { id: "seo", label: "SEO & sharing", icon: Search },
] as const;

type SectionId = (typeof SECTIONS)[number]["id"];

/**
 * The platforms a ranking may belong to.
 *
 * Mirrors the API enum. Listed here rather than read from `meta.platforms` because
 * the editor is reachable for a page that has not loaded a list response — and a
 * `<select>` whose options arrive a beat late renders the current value as blank.
 */
const PLATFORMS = [
  { value: "youtube", label: "YouTube" },
  { value: "instagram", label: "Instagram" },
  { value: "tiktok", label: "TikTok" },
  { value: "x", label: "X" },
  { value: "facebook", label: "Facebook" },
  { value: "twitch", label: "Twitch" },
  { value: "bluesky", label: "Bluesky" },
] as const;

const UNITS = [
  { value: "exact", label: "Exact count" },
  { value: "thousands", label: "Thousands (K)" },
  { value: "millions", label: "Millions (M)" },
  { value: "billions", label: "Billions (B)" },
];

/**
 * One ranking page: its settings, and its rows.
 *
 * **The two halves save differently, on purpose.** The settings form is a draft
 * until you press Save — it is a form, and half-typed values must not reach the
 * public page. The row list is the opposite: every action on it (reorder, pin,
 * delete, resolve a picture) writes immediately, because they are single
 * unambiguous acts and a fifty-row table behind a Save button means one mistake
 * discards forty-nine good edits.
 *
 * **Reorder is optimistic, and reverts.** The rows move the instant the arrow is
 * pressed and the request goes out behind it; a failure puts the list back where it
 * was and says so. Waiting for a round trip per press makes rearranging a long
 * table feel broken even when it is working.
 */
export function TopRankingEditorScreen({ id }: { id: string }) {
  const router = useRouter();
  const { notify, reportError } = useToast();
  const can = useCan();
  const creating = id === "new";

  const { data, error, loading, reload } = useAdminResource(
    () =>
      creating
        ? Promise.resolve({ ok: true as const, data: blankPage() })
        : adminApi.topRankings.get(id),
    [id],
  );

  if (error) return <LoadError error={error} onRetry={reload} />;
  if (loading || !data) return <EditorSkeleton />;

  return (
    <Editor
      key={data.id}
      page={data}
      creating={creating}
      canEdit={can("top_rankings.update")}
      canSync={can("top_rankings.sync")}
      onSaved={(page) => {
        if (creating) router.replace(`/c0ns0le/top-rankings/${page.id}`);
        else reload();
      }}
      notify={notify}
      reportError={reportError}
    />
  );
}

function Editor({
  page,
  creating,
  canEdit,
  canSync,
  onSaved,
  notify,
  reportError,
}: {
  page: AdminTopRankingPage;
  creating: boolean;
  canEdit: boolean;
  canSync: boolean;
  onSaved: (page: AdminTopRankingPage) => void;
  notify: (message: string) => void;
  reportError: (
    error: Parameters<ReturnType<typeof useToast>["reportError"]>[0],
  ) => void;
}) {
  const [section, setSection] = React.useState<SectionId>(
    creating ? "presentation" : "rows",
  );
  const [form, setForm] = React.useState(() => toForm(page));
  const [entries, setEntries] = React.useState<AdminTopRankingEntry[]>(
    page.entries ?? [],
  );
  const [saving, setSaving] = React.useState(false);
  const [busy, setBusy] = React.useState<"sync" | "avatars" | null>(null);
  const [deleting, setDeleting] = React.useState<AdminTopRankingEntry | null>(
    null,
  );
  const [adding, setAdding] = React.useState(false);

  // SEO lives beside the rest of the form rather than in its own request — the
  // same contract the tool editor makes, and the reason both share one panel.
  const [seo, setSeo] = React.useState<SeoOverrides>({
    title: page.seo?.title ?? "",
    description: page.seo?.description ?? "",
    focus_keyword: page.seo?.focus_keyword ?? "",
    canonical_url: page.seo?.canonical_url ?? "",
    robots: page.seo?.robots ?? "index,follow",
    og_title: page.seo?.og_title ?? "",
    og_description: page.seo?.og_description ?? "",
    og_media_id: page.seo?.og_media_id ?? null,
    twitter_card: page.seo?.twitter_card ?? "summary_large_image",
    schema_type: page.seo?.schema_type ?? "ItemList",
  });

  const [ogImageUrl, setOgImageUrl] = React.useState<string | null>(
    page.seo?.og_image_url ?? null,
  );
  const [pickingImage, setPickingImage] = React.useState(false);

  function patchSeo(next: Partial<SeoOverrides>) {
    setSeo((current) => ({ ...current, ...next }));
  }

  const set = <K extends keyof ReturnType<typeof toForm>>(
    key: K,
    value: ReturnType<typeof toForm>[K],
  ) => setForm((current) => ({ ...current, [key]: value }));

  const missing = entries.filter(
    (entry) => entry.avatar_status !== "ok",
  ).length;

  const current = SECTIONS.find((entry) => entry.id === section) ?? SECTIONS[0];

  // A ranking that has never synced has no rows to show, so Rows would open on an
  // empty panel. Hidden rather than disabled: a tab you cannot use is a tab worth
  // not drawing.
  const sections = creating
    ? SECTIONS.filter((entry) => entry.id !== "rows")
    : SECTIONS;

  async function save() {
    setSaving(true);

    const body = {
      title: form.title,
      slug: form.slug.trim() === "" ? null : form.slug.trim(),
      platform: form.platform,
      metric_label: form.metric_label,
      metric_unit: form.metric_unit,
      secondary_metric_label:
        form.secondary_metric_label.trim() === ""
          ? null
          : form.secondary_metric_label.trim(),
      secondary_metric_unit:
        form.secondary_metric_label.trim() === ""
          ? null
          : form.secondary_metric_unit,
      intro: form.intro.trim() === "" ? null : form.intro.trim(),
      // Sent with the rest of the form rather than in its own request: the whole
      // editor is one save, so an edit left in SEO is still there when Save is
      // pressed from Rows. An empty string becomes null, or the public page's
      // `?? fallback` would stop firing and publish a blank meta title.
      seo: Object.fromEntries(
        Object.entries(seo).map(([key, value]) => [
          key,
          value === "" ? null : value,
        ]),
      ),
      source_page: form.source_page,
      source_table: Number(form.source_table) || 0,
      row_limit: Number(form.row_limit) || 50,
      is_published: form.is_published,
      sort_order: Number(form.sort_order) || 0,
    };

    const result = creating
      ? await adminApi.topRankings.create(body)
      : await adminApi.topRankings.update(page.id, body);

    setSaving(false);

    if (result.ok) {
      notify(creating ? "Ranking created." : "Saved.");
      onSaved(result.data);
    } else {
      reportError(result.error);
    }
  }

  async function syncFromWikipedia() {
    setBusy("sync");
    const result = await adminApi.topRankings.sync(page.id);
    setBusy(null);

    if (result.ok) {
      setEntries(result.data.entries ?? []);
      notify(result.data.sync_message ?? "Synced.");
      onSaved(result.data);
    } else {
      reportError(result.error);
    }
  }

  async function syncAvatars(force: boolean) {
    setBusy("avatars");
    const result = await adminApi.topRankings.syncAvatars(page.id, force);
    setBusy(null);

    if (result.ok) {
      setEntries(result.data.entries ?? []);
      const unresolved = (result.data.entries ?? []).filter(
        (entry) => entry.avatar_status !== "ok",
      ).length;
      notify(
        unresolved === 0
          ? "Every row has a picture."
          : `Done — ${unresolved} row(s) still have no picture. Paste a URL on those rows if you want one.`,
      );
    } else {
      reportError(result.error);
    }
  }

  async function syncOneAvatar(entry: AdminTopRankingEntry) {
    const result = await adminApi.topRankings.entries.syncAvatar(
      page.id,
      entry.id,
    );

    if (result.ok) {
      setEntries((current) =>
        current.map((row) => (row.id === entry.id ? result.data : row)),
      );

      if (result.data.avatar_status !== "ok") {
        // Said plainly rather than as an error. Some platforms simply will not
        // answer an anonymous request, and a red toast would suggest something is
        // broken that is not.
        notify(
          `${entry.name}: no picture available from ${page.platform_label}.`,
        );
      }
    } else {
      reportError(result.error);
    }
  }

  async function move(entry: AdminTopRankingEntry, direction: -1 | 1) {
    const index = entries.findIndex((row) => row.id === entry.id);
    const target = index + direction;

    if (index === -1 || target < 0 || target >= entries.length) return;

    const previous = entries;
    const next = [...entries];
    [next[index], next[target]] = [next[target], next[index]];

    // Renumbered locally so the rank column matches the new positions immediately;
    // the server does the same and returns its own numbering.
    setEntries(next.map((row, position) => ({ ...row, rank: position + 1 })));

    const result = await adminApi.topRankings.entries.reorder(
      page.id,
      next.map((row) => row.id),
    );

    if (!result.ok) {
      setEntries(previous);
      reportError(result.error);
    }
  }

  async function patchEntry(
    entry: AdminTopRankingEntry,
    body: Record<string, unknown>,
  ) {
    const result = await adminApi.topRankings.entries.update(
      page.id,
      entry.id,
      body,
    );

    if (result.ok) {
      setEntries((current) =>
        current.map((row) => (row.id === entry.id ? result.data : row)),
      );
    } else {
      reportError(result.error);
    }
  }

  async function removeEntry() {
    if (!deleting) return;

    const result = await adminApi.topRankings.entries.remove(
      page.id,
      deleting.id,
    );

    if (result.ok) {
      setEntries((current) =>
        current
          .filter((row) => row.id !== deleting.id)
          .map((row, position) => ({ ...row, rank: position + 1 })),
      );
      notify(`“${deleting.name}” removed.`);
      setDeleting(null);
    } else {
      reportError(result.error);
    }
  }

  async function addEntry(values: {
    name: string;
    handle: string;
    owner: string;
    metric: string;
  }) {
    const result = await adminApi.topRankings.entries.create(page.id, {
      name: values.name,
      handle:
        values.handle.trim() === ""
          ? null
          : values.handle.trim().replace(/^@/, ""),
      owner: values.owner.trim() === "" ? null : values.owner.trim(),
      metric_value: values.metric.trim() === "" ? null : Number(values.metric),
    });

    if (result.ok) {
      setEntries((current) => [...current, result.data]);
      setAdding(false);
      notify(`“${result.data.name}” added. It will survive every future sync.`);
    } else {
      reportError(result.error);
    }
  }

  return (
    <>
      <Link
        href="/c0ns0le/top-rankings"
        className="mb-4 inline-flex items-center gap-1.5 text-sm text-[var(--color-foreground-muted)] transition-colors hover:text-[var(--color-foreground)]"
      >
        <ArrowLeft className="size-4" aria-hidden="true" />
        All rankings
      </Link>

      <AdminPageHeader
        eyebrow={creating ? "New ranking" : page.platform_label}
        title={creating ? "New ranking" : page.title}
        description={
          creating
            ? "Name the Wikipedia article this page reads, then sync to pull its rows in."
            : undefined
        }
        actions={
          <>
            {!creating && (
              <Button asChild variant="ghost" size="sm">
                <Link
                  href={`/top-ranking/${page.slug}`}
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  <ExternalLink className="size-4" aria-hidden="true" />
                  View
                </Link>
              </Button>
            )}

            <Can permission="top_rankings.update">
              <Button size="sm" onClick={save} loading={saving}>
                <Save className="size-4" aria-hidden="true" />
                Save
              </Button>
            </Can>
          </>
        }
      />

      {/* Above the tabs, not inside Source. A failed sync is the one thing on this
          screen an editor has to see whichever section they opened. */}
      {!creating && page.sync_status !== "ok" && page.sync_message && (
        <div className="mb-5 flex items-start gap-2.5 rounded-[var(--radius-lg)] border border-[var(--color-warning)]/35 bg-[var(--color-warning)]/8 p-3.5 text-sm">
          <AlertTriangle
            className="mt-0.5 size-4 shrink-0 text-[var(--color-warning)]"
            aria-hidden="true"
          />
          <div>
            <p className="font-medium">
              Last sync: {page.sync_status_label.toLowerCase()}
            </p>
            <p className="mt-0.5 text-[var(--color-foreground-muted)]">
              {page.sync_message}
            </p>
          </div>
        </div>
      )}

      {/* One editor, one Save, several sections — the whole form is still submitted
          at once, so switching tabs never loses a pending change. Row actions are
          the exception and write immediately; they are single unambiguous acts, and
          a fifty-row table behind a Save button means one mistake discards
          forty-nine good edits. */}
      <div className="flex flex-col gap-4">
        <nav
          aria-label="Ranking sections"
          className="border-b border-[var(--color-border-subtle)]"
        >
          {/* Scrolls sideways rather than wrapping: a tab strip that reflows to a
              second row moves every tab under the cursor as the active one
              changes. */}
          <ul className="scrollbar-slim -mb-px flex gap-1 overflow-x-auto">
            {sections.map((entry) => {
              const Icon = entry.icon;
              const isActive = entry.id === current.id;

              return (
                <li key={entry.id}>
                  <button
                    type="button"
                    onClick={() => setSection(entry.id)}
                    aria-current={isActive ? "page" : undefined}
                    className={cn(
                      "flex items-center gap-2 whitespace-nowrap border-b-2 px-3 py-2.5 text-sm transition-colors",
                      isActive
                        ? "border-[var(--color-primary)] font-medium text-[var(--color-foreground)]"
                        : "border-transparent text-[var(--color-foreground-muted)] hover:border-[var(--color-border)] hover:text-[var(--color-foreground)]",
                    )}
                  >
                    <Icon
                      className={cn(
                        "size-4 shrink-0",
                        isActive ? "text-[var(--color-primary)]" : "",
                      )}
                      aria-hidden="true"
                    />
                    {entry.label}

                    {/* The count an editor came here for, on the tab itself, so
                        "does this page need a pass?" is answered without opening it. */}
                    {entry.id === "rows" && missing > 0 && (
                      <span className="tabular rounded-full bg-[var(--color-warning)]/15 px-1.5 text-[0.625rem] font-medium text-[var(--color-warning)]">
                        {missing}
                      </span>
                    )}
                  </button>
                </li>
              );
            })}
          </ul>
        </nav>

        <div className="flex min-w-0 flex-col gap-5">
          {section === "rows" && !creating && (
            <AdminPanel
              title="Rows"
              description={`${entries.length} on the page${missing > 0 ? ` · ${missing} without a picture` : ""}`}
              bodyClassName="p-0"
              action={
                <Can permission="top_rankings.update">
                  <Button
                    variant="secondary"
                    size="sm"
                    onClick={() => setAdding((value) => !value)}
                  >
                    <Plus className="size-3.5" aria-hidden="true" />
                    Add row
                  </Button>
                </Can>
              }
            >
              {adding && (
                <AddEntryForm
                  onCancel={() => setAdding(false)}
                  onSubmit={addEntry}
                />
              )}

              {entries.length === 0 ? (
                <p className="px-4 py-12 text-center text-sm text-[var(--color-foreground-muted)]">
                  No rows yet. Open <strong>Source &amp; sync</strong> and press{" "}
                  <strong>Sync from Wikipedia</strong> to pull them in.
                </p>
              ) : (
                <ul className="divide-y divide-[var(--color-border-subtle)]">
                  {entries.map((entry, index) => (
                    <EntryRow
                      key={entry.id}
                      entry={entry}
                      accent={page.platform_accent}
                      isFirst={index === 0}
                      isLast={index === entries.length - 1}
                      canEdit={canEdit}
                      canSync={canSync}
                      onMove={(direction) => void move(entry, direction)}
                      onTogglePin={() =>
                        void patchEntry(entry, { is_pinned: !entry.is_pinned })
                      }
                      onSetAvatar={(url) =>
                        void patchEntry(entry, { avatar_url: url })
                      }
                      onSyncAvatar={() => syncOneAvatar(entry)}
                      onRemove={() => setDeleting(entry)}
                    />
                  ))}
                </ul>
              )}
            </AdminPanel>
          )}

          {section === "presentation" && (
            <AdminPanel
              title="Presentation"
              description="How the public page introduces itself"
            >
              <fieldset
                disabled={!canEdit}
                className="grid max-w-3xl gap-4 sm:grid-cols-2"
              >
                <Field
                  id="ranking-title"
                  label="Title"
                  className="sm:col-span-2"
                  required
                >
                  {(props) => (
                    <Input
                      {...props}
                      value={form.title}
                      onChange={(e) => set("title", e.target.value)}
                      maxLength={200}
                    />
                  )}
                </Field>

                <Field
                  id="ranking-slug"
                  label="Slug"
                  hint="Leave blank to generate it from the title."
                >
                  {(props) => (
                    <Input
                      {...props}
                      value={form.slug}
                      onChange={(e) => set("slug", e.target.value)}
                      placeholder="most-followed-…"
                      className="font-mono text-xs"
                    />
                  )}
                </Field>

                <Field
                  id="ranking-order"
                  label="Menu order"
                  hint="Lower sorts higher in the header menu."
                >
                  {(props) => (
                    <Input
                      {...props}
                      type="number"
                      value={form.sort_order}
                      onChange={(e) => set("sort_order", e.target.value)}
                    />
                  )}
                </Field>

                <Field
                  id="ranking-intro"
                  label="Intro"
                  className="sm:col-span-2"
                  hint="The paragraph under the heading. Also the fallback meta description."
                  counter={`${form.intro.length}/2000`}
                >
                  {(props) => (
                    <Textarea
                      {...props}
                      value={form.intro}
                      onChange={(e) => set("intro", e.target.value)}
                      rows={4}
                      maxLength={2000}
                    />
                  )}
                </Field>

                <div className="sm:col-span-2">
                  <Checkbox
                    checked={form.is_published}
                    onChange={(e) => set("is_published", e.target.checked)}
                    label="Published"
                    hint="Off, the page 404s and drops out of the header menu."
                    disabled={!canEdit}
                  />
                </div>
              </fieldset>
            </AdminPanel>
          )}

          {section === "source" && (
            <div className="grid gap-5 lg:grid-cols-2">
              <AdminPanel
                title="Source"
                description="The article the rows come from"
              >
                <fieldset disabled={!canEdit} className="flex flex-col gap-4">
                  <Field
                    id="ranking-source-page"
                    label="Wikipedia article"
                    hint="The article title, exactly as Wikipedia writes it."
                    required
                  >
                    {(props) => (
                      <Input
                        {...props}
                        value={form.source_page}
                        onChange={(e) => set("source_page", e.target.value)}
                        placeholder="List of most-followed TikTok accounts"
                        maxLength={200}
                      />
                    )}
                  </Field>

                  <Field
                    id="ranking-source-table"
                    label="Table index"
                    hint="Most of these articles carry more than one table. 0 is the first."
                  >
                    {(props) => (
                      <Input
                        {...props}
                        type="number"
                        min={0}
                        value={form.source_table}
                        onChange={(e) => set("source_table", e.target.value)}
                      />
                    )}
                  </Field>

                  <Field
                    id="ranking-row-limit"
                    label="Row limit"
                    hint="How many rows to keep, at most."
                  >
                    {(props) => (
                      <Input
                        {...props}
                        type="number"
                        min={1}
                        max={250}
                        value={form.row_limit}
                        onChange={(e) => set("row_limit", e.target.value)}
                      />
                    )}
                  </Field>

                  {!creating && (
                    <a
                      href={page.source_url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex items-center gap-1.5 text-xs text-[var(--color-primary)] hover:underline"
                    >
                      <ExternalLink className="size-3.5" aria-hidden="true" />
                      Open the article
                    </a>
                  )}
                </fieldset>
              </AdminPanel>

              {!creating && (
                <AdminPanel
                  title="Sync"
                  description="Both run immediately and report what happened"
                >
                  <div className="flex flex-col gap-3">
                    <Can
                      permission="top_rankings.sync"
                      fallback={
                        <p className="text-xs text-[var(--color-foreground-muted)]">
                          You do not have permission to run a sync.
                        </p>
                      }
                    >
                      <Button
                        variant="secondary"
                        className="w-full justify-center"
                        onClick={syncFromWikipedia}
                        loading={busy === "sync"}
                        disabled={busy !== null}
                      >
                        <RefreshCw className="size-4" aria-hidden="true" />
                        Sync from Wikipedia
                      </Button>

                      <Button
                        variant="secondary"
                        className="w-full justify-center"
                        onClick={() => syncAvatars(false)}
                        loading={busy === "avatars"}
                        disabled={busy !== null}
                      >
                        <ImageOff className="size-4" aria-hidden="true" />
                        Sync missing pictures
                      </Button>

                      <button
                        type="button"
                        onClick={() => syncAvatars(true)}
                        disabled={busy !== null}
                        className="text-left text-xs text-[var(--color-foreground-subtle)] underline-offset-2 hover:underline disabled:opacity-50"
                      >
                        Re-check every picture, including the good ones
                      </button>
                    </Can>

                    <dl className="mt-1 flex flex-col gap-1.5 border-t border-[var(--color-border-subtle)] pt-3 text-xs">
                      <div className="flex items-center justify-between gap-2">
                        <dt className="text-[var(--color-foreground-subtle)]">
                          Last sync
                        </dt>
                        <dd className="flex items-center gap-1.5">
                          <StatusPill
                            label={page.sync_status_label}
                            tone={
                              page.sync_status === "ok"
                                ? "success"
                                : page.sync_status === "never"
                                  ? "muted"
                                  : page.sync_status === "partial"
                                    ? "warning"
                                    : "danger"
                            }
                          />
                          {page.synced_at && (
                            <span className="text-[var(--color-foreground-muted)]">
                              {relativeTime(page.synced_at)}
                            </span>
                          )}
                        </dd>
                      </div>

                      <div className="flex items-center justify-between gap-2">
                        <dt className="text-[var(--color-foreground-subtle)]">
                          Pictures
                        </dt>
                        <dd className="text-[var(--color-foreground-muted)]">
                          {page.avatars_synced_at
                            ? relativeTime(page.avatars_synced_at)
                            : "Never"}
                        </dd>
                      </div>
                    </dl>

                    <p className="text-xs leading-relaxed text-[var(--color-foreground-subtle)]">
                      A scheduled job already does both of these every Sunday.
                      Nothing here is required for the page to stay current.
                    </p>
                  </div>
                </AdminPanel>
              )}
            </div>
          )}

          {section === "metrics" && (
            <AdminPanel
              title="Metrics"
              description="What the numbers on the page mean"
            >
              <fieldset
                disabled={!canEdit}
                className="grid max-w-3xl gap-4 sm:grid-cols-2"
              >
                <Field id="ranking-platform" label="Platform">
                  {(props) => (
                    <Select
                      {...props}
                      value={form.platform}
                      onChange={(e) => set("platform", e.target.value)}
                    >
                      {PLATFORMS.map((option) => (
                        <option key={option.value} value={option.value}>
                          {option.label}
                        </option>
                      ))}
                    </Select>
                  )}
                </Field>

                <Field
                  id="ranking-metric-label"
                  label="Metric label"
                  hint="The column heading — Followers, Subscribers, Views."
                >
                  {(props) => (
                    <Input
                      {...props}
                      value={form.metric_label}
                      onChange={(e) => set("metric_label", e.target.value)}
                      maxLength={40}
                    />
                  )}
                </Field>

                <Field
                  id="ranking-metric-unit"
                  label="Metric unit"
                  className="sm:col-span-2"
                  hint="Most articles publish “515” under a header saying “(millions)”. Twitch's subscriber list publishes the exact count."
                >
                  {(props) => (
                    <Select
                      {...props}
                      value={form.metric_unit}
                      onChange={(e) => set("metric_unit", e.target.value)}
                    >
                      {UNITS.map((unit) => (
                        <option key={unit.value} value={unit.value}>
                          {unit.label}
                        </option>
                      ))}
                    </Select>
                  )}
                </Field>

                <Field
                  id="ranking-second-metric"
                  label="Second metric"
                  hint="Leave blank if the article publishes only one."
                >
                  {(props) => (
                    <Input
                      {...props}
                      value={form.secondary_metric_label}
                      onChange={(e) =>
                        set("secondary_metric_label", e.target.value)
                      }
                      placeholder="Likes"
                      maxLength={40}
                    />
                  )}
                </Field>

                {form.secondary_metric_label.trim() !== "" && (
                  <Field id="ranking-second-unit" label="Second metric unit">
                    {(props) => (
                      <Select
                        {...props}
                        value={form.secondary_metric_unit}
                        onChange={(e) =>
                          set("secondary_metric_unit", e.target.value)
                        }
                      >
                        {UNITS.map((unit) => (
                          <option key={unit.value} value={unit.value}>
                            {unit.label}
                          </option>
                        ))}
                      </Select>
                    )}
                  </Field>
                )}
              </fieldset>
            </AdminPanel>
          )}

          {section === "seo" && (
            <SeoPanel
              seo={seo}
              patch={patchSeo}
              editable={canEdit}
              idPrefix="ranking"
              noun="ranking"
              breadcrumb={`metacreator.dev › top-ranking › ${form.slug || "…"}`}
              canonicalPlaceholder={`https://metacreator.dev/top-ranking/${form.slug || "…"}`}
              fallbackTitle={form.title}
              fallbackDescription={form.intro}
              imageUrl={ogImageUrl}
              onPickImage={() => setPickingImage(true)}
              onClearImage={() => {
                patchSeo({ og_media_id: null });
                setOgImageUrl(null);
              }}
            />
          )}
        </div>
      </div>

      <MediaPicker
        open={pickingImage}
        onClose={() => setPickingImage(false)}
        title="Choose a social share image"
        onSelect={(media: AdminMedia) => {
          patchSeo({ og_media_id: media.numeric_id });
          setOgImageUrl(media.url);
          setPickingImage(false);
        }}
      />

      <ConfirmDialog
        open={deleting !== null}
        title="Remove this row?"
        description={
          deleting
            ? deleting.source === "wikipedia"
              ? `“${deleting.name}” will be removed, and the next sync will put it back — it is still in the article. Pin it instead if you want it gone for good, or edit the article.`
              : `“${deleting.name}” was added by hand, so nothing will restore it.`
            : ""
        }
        confirmLabel="Remove"
        destructive
        onConfirm={removeEntry}
        onCancel={() => setDeleting(null)}
      />
    </>
  );
}

/**
 * One row.
 *
 * The picture, its status and the two ways to fix it sit together, because that is
 * one job: see that a row has no image, press retry, and if the platform still will
 * not say, paste a URL. Splitting those across a row and a modal would make the
 * common case — thirty-five Facebook Pages with no handle — a thirty-five-step task.
 */
function EntryRow({
  entry,
  accent,
  isFirst,
  isLast,
  canEdit,
  canSync,
  onMove,
  onTogglePin,
  onSetAvatar,
  onSyncAvatar,
  onRemove,
}: {
  entry: AdminTopRankingEntry;
  accent: string;
  isFirst: boolean;
  isLast: boolean;
  canEdit: boolean;
  canSync: boolean;
  onMove: (direction: -1 | 1) => void;
  onTogglePin: () => void;
  onSetAvatar: (url: string | null) => void;
  onSyncAvatar: () => Promise<void>;
  onRemove: () => void;
}) {
  const [editingAvatar, setEditingAvatar] = React.useState(false);
  const [draft, setDraft] = React.useState(entry.avatar_url ?? "");
  const [resolving, setResolving] = React.useState(false);

  const tone =
    entry.avatar_status === "ok"
      ? "success"
      : entry.avatar_status === "expired"
        ? "warning"
        : entry.avatar_status === "unavailable"
          ? "danger"
          : "muted";

  return (
    <li className="flex items-center gap-3 px-4 py-2.5">
      <span className="tabular w-7 shrink-0 text-center text-xs font-medium text-[var(--color-foreground-subtle)]">
        {entry.rank}
      </span>

      <span
        className="relative flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-full text-[0.625rem] font-semibold ring-1 ring-inset ring-[var(--color-border)]"
        style={
          entry.avatar_status === "ok"
            ? undefined
            : {
                backgroundColor: `oklch(${accent} / 0.14)`,
                color: `oklch(${accent})`,
              }
        }
      >
        {entry.avatar_status === "ok" && entry.avatar_url ? (
          /* Same reasoning as the public table: seven CDNs whose hostnames rotate,
             so next/image would need an allow-list that is wrong on arrival. */
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={entry.avatar_url}
            alt=""
            loading="lazy"
            referrerPolicy="no-referrer"
            className="size-full object-cover"
          />
        ) : (
          entry.initials
        )}
      </span>

      <span className="flex min-w-0 flex-1 flex-col gap-0.5">
        <span className="flex min-w-0 items-center gap-1.5">
          <span className="truncate text-sm font-medium text-[var(--color-foreground)]">
            {entry.owner ?? entry.name}
          </span>

          {entry.is_pinned && (
            <Pin
              className="size-3 shrink-0 text-[var(--color-primary)]"
              aria-label="Pinned"
            />
          )}

          {entry.source === "manual" && (
            <StatusPill label="By hand" tone="info" />
          )}
        </span>

        <span className="flex min-w-0 items-center gap-2">
          <span className="truncate font-mono text-[0.6875rem] text-[var(--color-foreground-subtle)]">
            {entry.handle ? `@${entry.handle}` : "no handle"}
          </span>

          <StatusPill label={entry.avatar_status_label} tone={tone} />
        </span>

        {editingAvatar && (
          <span className="mt-1.5 flex items-center gap-1.5">
            <Input
              value={draft}
              onChange={(event) => setDraft(event.target.value)}
              placeholder="https://…/avatar.jpg"
              className="h-7 text-xs"
            />

            <Button
              size="sm"
              variant="secondary"
              onClick={() => {
                onSetAvatar(draft.trim() === "" ? null : draft.trim());
                setEditingAvatar(false);
              }}
            >
              Set
            </Button>

            <Button
              size="sm"
              variant="ghost"
              onClick={() => setEditingAvatar(false)}
            >
              Cancel
            </Button>
          </span>
        )}
      </span>

      <span className="tabular hidden w-24 shrink-0 text-right text-sm font-semibold sm:block">
        {entry.metric ?? "—"}
      </span>

      <span className="flex shrink-0 items-center gap-0.5">
        {entry.profile_url && (
          <RowButton label={`Open ${entry.name}`} href={entry.profile_url}>
            <ExternalLink className="size-3.5" aria-hidden="true" />
          </RowButton>
        )}

        {canSync && (
          <RowButton
            label={`Resolve picture for ${entry.name}`}
            disabled={resolving}
            onClick={async () => {
              setResolving(true);
              await onSyncAvatar();
              setResolving(false);
            }}
          >
            <RefreshCw
              className={cn("size-3.5", resolving && "animate-spin")}
              aria-hidden="true"
            />
          </RowButton>
        )}

        {canEdit && (
          <>
            <RowButton
              label={`Paste a picture URL for ${entry.name}`}
              onClick={() => {
                setDraft(entry.avatar_url ?? "");
                setEditingAvatar((value) => !value);
              }}
            >
              <ImageOff className="size-3.5" aria-hidden="true" />
            </RowButton>

            <RowButton
              label={
                entry.is_pinned ? `Unpin ${entry.name}` : `Pin ${entry.name}`
              }
              onClick={onTogglePin}
            >
              {entry.is_pinned ? (
                <PinOff className="size-3.5" />
              ) : (
                <Pin className="size-3.5" />
              )}
            </RowButton>

            <RowButton
              label={`Move ${entry.name} up`}
              onClick={() => onMove(-1)}
              disabled={isFirst}
            >
              <ChevronUp className="size-3.5" aria-hidden="true" />
            </RowButton>

            <RowButton
              label={`Move ${entry.name} down`}
              onClick={() => onMove(1)}
              disabled={isLast}
            >
              <ChevronDown className="size-3.5" aria-hidden="true" />
            </RowButton>

            <RowButton
              label={`Remove ${entry.name}`}
              onClick={onRemove}
              destructive
            >
              <Trash2 className="size-3.5" aria-hidden="true" />
            </RowButton>
          </>
        )}
      </span>
    </li>
  );
}

function RowButton({
  label,
  onClick,
  href,
  disabled,
  destructive,
  children,
}: {
  label: string;
  onClick?: () => void;
  href?: string;
  disabled?: boolean;
  destructive?: boolean;
  children: React.ReactNode;
}) {
  const className = cn(
    "flex size-7 items-center justify-center rounded-[var(--radius-sm)] transition-colors disabled:opacity-30",
    destructive
      ? "text-[var(--color-danger)] hover:bg-[var(--color-danger)]/10"
      : "text-[var(--color-foreground-subtle)] hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-foreground)]",
  );

  if (href) {
    return (
      <a
        href={href}
        target="_blank"
        rel="noopener noreferrer"
        aria-label={label}
        title={label}
        className={className}
      >
        {children}
      </a>
    );
  }

  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      aria-label={label}
      title={label}
      className={className}
    >
      {children}
    </button>
  );
}

function AddEntryForm({
  onCancel,
  onSubmit,
}: {
  onCancel: () => void;
  onSubmit: (values: {
    name: string;
    handle: string;
    owner: string;
    metric: string;
  }) => Promise<void>;
}) {
  const [values, setValues] = React.useState({
    name: "",
    handle: "",
    owner: "",
    metric: "",
  });
  const [pending, setPending] = React.useState(false);

  return (
    <form
      onSubmit={async (event) => {
        event.preventDefault();
        setPending(true);
        await onSubmit(values);
        setPending(false);
      }}
      className="flex flex-col gap-3 border-b border-[var(--color-border-subtle)] bg-[var(--color-surface-sunken)] px-4 py-3"
    >
      <div className="grid gap-2 sm:grid-cols-4">
        <Input
          value={values.name}
          onChange={(e) => setValues((v) => ({ ...v, name: e.target.value }))}
          placeholder="Name"
          required
          maxLength={200}
        />
        <Input
          value={values.handle}
          onChange={(e) => setValues((v) => ({ ...v, handle: e.target.value }))}
          placeholder="@handle"
          maxLength={120}
        />
        <Input
          value={values.owner}
          onChange={(e) => setValues((v) => ({ ...v, owner: e.target.value }))}
          placeholder="Owner"
          maxLength={200}
        />
        <Input
          value={values.metric}
          onChange={(e) => setValues((v) => ({ ...v, metric: e.target.value }))}
          placeholder="Metric"
          type="number"
          step="any"
        />
      </div>

      <div className="flex items-center gap-2">
        <Button type="submit" size="sm" loading={pending}>
          Add row
        </Button>

        <Button type="button" size="sm" variant="ghost" onClick={onCancel}>
          Cancel
        </Button>

        <p className="ml-auto text-xs text-[var(--color-foreground-subtle)]">
          Added at the bottom. It survives every future sync — reorder it from
          there.
        </p>
      </div>
    </form>
  );
}

function toForm(page: AdminTopRankingPage) {
  return {
    title: page.title,
    slug: page.slug,
    platform: page.platform,
    metric_label: page.metric_label,
    metric_unit: page.metric_unit as string,
    secondary_metric_label: page.secondary_metric_label ?? "",
    secondary_metric_unit: (page.secondary_metric_unit ?? "billions") as string,
    intro: page.intro ?? "",
    source_page: page.source_page,
    source_table: String(page.source_table),
    row_limit: String(page.row_limit),
    is_published: page.is_published,
    sort_order: String(page.sort_order),
  };
}

function blankPage(): AdminTopRankingPage {
  return {
    id: "new",
    slug: "",
    title: "",
    platform: "youtube",
    platform_label: "YouTube",
    platform_accent: "0.58 0.235 25",
    metric_label: "Followers",
    metric_unit: "millions",
    secondary_metric_label: null,
    secondary_metric_unit: null,
    intro: null,
    source_page: "",
    source_table: 0,
    source_url: "",
    row_limit: 50,
    is_published: false,
    sort_order: 100,
    sync_status: "never",
    sync_status_label: "Never synced",
    sync_message: null,
    synced_at: null,
    days_since_sync: null,
    avatars_synced_at: null,
    entries_count: 0,
    entries: [],
  };
}

function EditorSkeleton() {
  return (
    <div className="flex flex-col gap-5">
      <div className="h-10 w-64 animate-pulse rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)]" />
      <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div className="h-96 animate-pulse rounded-[var(--radius-lg)] bg-[var(--color-surface-sunken)]" />
        <div className="h-64 animate-pulse rounded-[var(--radius-lg)] bg-[var(--color-surface-sunken)]" />
      </div>
    </div>
  );
}
