"use client";

import {
  ArrowLeft,
  ChevronDown,
  ChevronUp,
  ListOrdered,
  Plus,
  Rocket,
  Save,
  Trash2,
} from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import * as React from "react";

import { AdminPageHeader, AdminPanel, StatusPill } from "@/components/admin/admin-page";
import { Can } from "@/components/admin/can";
import { useToast } from "@/components/admin/feedback";
import { LoadError } from "@/components/admin/load-error";
import { tone } from "@/components/admin/status-tone";
import { ReleaseEntry } from "@/components/changelog/release-entry";
import { Button } from "@/components/ui/button";
import { Checkbox, Field, Input, Select, Textarea } from "@/components/ui/field";
import { adminApi } from "@/lib/admin/api";
import type { AdminChangelogRelease } from "@/lib/admin/types";
import type { ChangelogRelease, ChangeTypeOption, ChangeTypeValue } from "@/lib/types";
import type { ApiResult } from "@/lib/http";
import { useAdminResource } from "@/lib/admin/use-admin-resource";

/** A row being edited. `key` is local and never sent — React needs a stable id. */
interface DraftItem {
  key: string;
  type: ChangeTypeValue;
  title: string;
  description: string;
}

let nextKey = 0;
const newKey = () => `item-${nextKey++}`;

export function ChangelogEditorScreen({ id }: { id?: string }) {
  // "New" is a resource that resolves to nothing rather than a separate branch, so
  // the hook order is identical on both paths.
  const release = useAdminResource<AdminChangelogRelease | null>(
    () =>
      id === undefined
        ? Promise.resolve<ApiResult<AdminChangelogRelease | null>>({ ok: true, data: null })
        : adminApi.changelog.get(id),
    [id],
  );

  // The type catalog comes from the API, so a change type added server-side appears
  // in this picker without a frontend deploy.
  const catalog = useAdminResource(() => adminApi.changelog.types(), []);

  if (release.error) return <LoadError error={release.error} onRetry={release.reload} />;

  if (release.loading && !release.data) {
    return (
      <div
        className="h-96 animate-pulse rounded-[var(--radius-lg)] bg-[var(--color-surface-sunken)]"
        aria-hidden="true"
      />
    );
  }

  if (id !== undefined && !release.data) return null;

  return (
    <ReleaseForm
      key={release.data?.id ?? "new"}
      release={release.data}
      types={catalog.data?.types ?? []}
    />
  );
}

function ReleaseForm({
  release,
  types,
}: {
  release: AdminChangelogRelease | null;
  types: ChangeTypeOption[];
}) {
  const router = useRouter();
  const { notify, reportError } = useToast();

  const [title, setTitle] = React.useState(release?.title ?? "");
  const [version, setVersion] = React.useState(release?.version ?? "");
  const [summary, setSummary] = React.useState(release?.summary ?? "");
  const [status, setStatus] = React.useState(release?.status ?? "draft");
  const [isMajor, setIsMajor] = React.useState(release?.is_major ?? false);
  const [releasedAt, setReleasedAt] = React.useState(toDateInput(release?.released_at));

  const [items, setItems] = React.useState<DraftItem[]>(() =>
    release?.items.length
      ? release.items.map((item) => ({
          key: newKey(),
          type: item.type,
          title: item.title,
          description: item.description ?? "",
        }))
      : [{ key: newKey(), type: "added", title: "", description: "" }],
  );

  const [saving, setSaving] = React.useState(false);
  const [errors, setErrors] = React.useState<Record<string, string[]>>({});

  // The default when the catalog has not loaded yet, so a row added in that window
  // still carries a valid type.
  const fallbackType: ChangeTypeValue = types[0]?.value ?? "added";

  function patchItem(key: string, patch: Partial<DraftItem>) {
    setItems((current) =>
      current.map((item) => (item.key === key ? { ...item, ...patch } : item)),
    );
  }

  function addItem(afterKey?: string) {
    setItems((current) => {
      const row: DraftItem = { key: newKey(), type: fallbackType, title: "", description: "" };
      const index = afterKey ? current.findIndex((item) => item.key === afterKey) : -1;

      if (index === -1) return [...current, row];

      // Inserted directly below the row it came from: an editor pressing Enter
      // mid-list expects the new line there, not at the bottom of the page.
      return [...current.slice(0, index + 1), row, ...current.slice(index + 1)];
    });
  }

  function removeItem(key: string) {
    setItems((current) => current.filter((item) => item.key !== key));
  }

  function move(key: string, direction: -1 | 1) {
    setItems((current) => {
      const index = current.findIndex((item) => item.key === key);
      const target = index + direction;

      if (index === -1 || target < 0 || target >= current.length) return current;

      const next = [...current];
      [next[index], next[target]] = [next[target], next[index]];

      return next;
    });
  }

  /**
   * Reorder into the canonical reading order — new first, housekeeping last.
   *
   * The weights come from the API, so this button and the enum can never disagree.
   * `sort` on a copy, and stable, so the order an editor chose *within* a type is
   * preserved rather than reshuffled.
   */
  function groupByType() {
    const weight = new Map(types.map((option) => [option.value, option.weight]));

    setItems((current) =>
      [...current].sort(
        (a, b) => (weight.get(a.type) ?? 99) - (weight.get(b.type) ?? 99),
      ),
    );
  }

  async function save(publish = false) {
    setSaving(true);
    setErrors({});

    const body = {
      title,
      version: version.trim() === "" ? null : version.trim(),
      summary: summary.trim() === "" ? null : summary.trim(),
      status: publish ? "published" : status,
      // A date input gives a bare `YYYY-MM-DD`; the API takes any parseable date.
      released_at: releasedAt === "" ? null : releasedAt,
      is_major: isMajor,
      items: items
        .filter((item) => item.title.trim() !== "")
        .map((item) => ({
          type: item.type,
          title: item.title.trim(),
          description: item.description.trim() === "" ? null : item.description.trim(),
        })),
    };

    const result = release
      ? await adminApi.changelog.update(release.id, body)
      : await adminApi.changelog.create(body);

    setSaving(false);

    if (result.ok) {
      notify(release ? `“${title}” saved.` : `“${title}” created.`);
      router.push("/c0ns0le/changelog");
    } else {
      setErrors(result.error.fieldErrors ?? {});
      reportError(result.error);
    }
  }

  const filled = items.filter((item) => item.title.trim() !== "");
  const canSave = title.trim() !== "" && filled.length > 0;

  return (
    <>
      <AdminPageHeader
        eyebrow="Content · Changelog"
        title={release ? `Edit ${release.version ?? release.title}` : "New release"}
        description="Write it the way you would explain it to a customer who has been away for a month. One line per change, plainest words available."
        actions={
          <>
            <Button variant="secondary" size="sm" asChild>
              <Link href="/c0ns0le/changelog">
                <ArrowLeft className="size-4" aria-hidden="true" />
                Back to changelog
              </Link>
            </Button>

            <Button
              variant="secondary"
              size="sm"
              onClick={() => void save()}
              loading={saving}
              disabled={!canSave}
            >
              <Save className="size-4" aria-hidden="true" />
              Save
            </Button>

            {/* Publishing is its own permission, so it is its own button rather than
                a status an editor without the right could set and have rejected. */}
            <Can permission="changelog.publish">
              <Button size="sm" onClick={() => void save(true)} disabled={!canSave || saving}>
                <Rocket className="size-4" aria-hidden="true" />
                {release?.is_live ? "Save & keep live" : "Publish now"}
              </Button>
            </Can>
          </>
        }
      />

      <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_26rem]">
        <div className="flex min-w-0 flex-col gap-4">
          <AdminPanel title="The release" description="What this batch of work is called">
            <div className="flex flex-col gap-4">
              <Field
                id="release-title"
                label="Title"
                hint="A sentence, not a version number. “Saved tools and a faster catalog” beats “Release 3.0”."
                error={errors.title?.[0]}
                required
              >
                {(props) => (
                  <Input
                    {...props}
                    value={title}
                    onChange={(event) => setTitle(event.target.value)}
                    maxLength={200}
                    autoFocus
                  />
                )}
              </Field>

              <div className="grid gap-4 sm:grid-cols-2">
                <Field
                  id="release-version"
                  label="Version"
                  hint="Optional. Leave blank if you date releases instead of numbering them."
                  error={errors.version?.[0]}
                >
                  {(props) => (
                    <Input
                      {...props}
                      value={version}
                      onChange={(event) => setVersion(event.target.value)}
                      placeholder="3.0.0"
                      className="font-mono text-sm"
                    />
                  )}
                </Field>

                <Field
                  id="release-date"
                  label="Release date"
                  hint="The day it reached customers. Back-date freely — the timeline reads by this."
                  error={errors.released_at?.[0]}
                >
                  {(props) => (
                    <Input
                      {...props}
                      type="date"
                      value={releasedAt}
                      onChange={(event) => setReleasedAt(event.target.value)}
                    />
                  )}
                </Field>
              </div>

              <Field
                id="release-summary"
                label="Summary"
                hint="Optional. One or two sentences above the list, for a release worth framing."
                error={errors.summary?.[0]}
              >
                {(props) => (
                  <Textarea
                    {...props}
                    value={summary}
                    onChange={(event) => setSummary(event.target.value)}
                    className="min-h-20 text-sm"
                    maxLength={2000}
                  />
                )}
              </Field>

              <div className="grid gap-4 sm:grid-cols-2 sm:items-end">
                <Field
                  id="release-status"
                  label="Status"
                  hint={
                    status === "scheduled"
                      ? "Goes live on its own when the date arrives — no further action needed."
                      : status === "published"
                        ? "Public as soon as the release date has passed."
                        : "Staff-only until you publish or schedule it."
                  }
                  error={errors.status?.[0]}
                >
                  {(props) => (
                    <Select
                      {...props}
                      value={status}
                      onChange={(event) => setStatus(event.target.value)}
                    >
                      <option value="draft">Draft</option>
                      <option value="scheduled">Scheduled</option>
                      <option value="published">Published</option>
                    </Select>
                  )}
                </Field>

                <Checkbox
                  label="Major release"
                  hint="Highlights it on the timeline. Worth reserving for two or three a year."
                  checked={isMajor}
                  onChange={(event) => setIsMajor(event.target.checked)}
                />
              </div>
            </div>
          </AdminPanel>

          <AdminPanel
            title="Changes"
            description={`${filled.length} ${filled.length === 1 ? "entry" : "entries"}`}
            action={
              <div className="flex items-center gap-2">
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={groupByType}
                  disabled={items.length < 2}
                  title="Reorder into the standard reading order: new first, housekeeping last"
                >
                  <ListOrdered className="size-4" aria-hidden="true" />
                  Group by type
                </Button>

                <Button variant="secondary" size="sm" onClick={() => addItem()}>
                  <Plus className="size-4" aria-hidden="true" />
                  Add change
                </Button>
              </div>
            }
          >
            {errors.items?.[0] && (
              <p role="alert" className="mb-3 text-xs font-medium text-[var(--color-danger)]">
                {errors.items[0]}
              </p>
            )}

            <ol className="flex flex-col gap-3">
              {items.map((item, index) => (
                <ItemRow
                  key={item.key}
                  item={item}
                  index={index}
                  total={items.length}
                  types={types}
                  errors={errors}
                  onChange={(patch) => patchItem(item.key, patch)}
                  onAddBelow={() => addItem(item.key)}
                  onRemove={() => removeItem(item.key)}
                  onMove={(direction) => move(item.key, direction)}
                />
              ))}
            </ol>

            {items.length === 0 && (
              <div className="flex flex-col items-center gap-3 py-8 text-center">
                <p className="text-sm text-[var(--color-foreground-muted)]">
                  No changes yet. A release needs at least one.
                </p>
                <Button variant="secondary" size="sm" onClick={() => addItem()}>
                  <Plus className="size-4" aria-hidden="true" />
                  Add the first change
                </Button>
              </div>
            )}
          </AdminPanel>
        </div>

        <Preview
          release={release}
          title={title}
          version={version}
          summary={summary}
          isMajor={isMajor}
          releasedAt={releasedAt}
          status={status}
          items={items}
          types={types}
        />
      </div>
    </>
  );
}

function ItemRow({
  item,
  index,
  total,
  types,
  errors,
  onChange,
  onAddBelow,
  onRemove,
  onMove,
}: {
  item: DraftItem;
  index: number;
  total: number;
  types: ChangeTypeOption[];
  errors: Record<string, string[]>;
  onChange: (patch: Partial<DraftItem>) => void;
  onAddBelow: () => void;
  onRemove: () => void;
  onMove: (direction: -1 | 1) => void;
}) {
  const titleId = `item-${index}-title`;
  // The API reports item errors positionally (`items.3.title`), which is exactly
  // how they are indexed here.
  const titleError = errors[`items.${index}.title`]?.[0];

  return (
    <li className="rounded-[var(--radius-md)] border border-[var(--color-border-subtle)] bg-[var(--color-surface-sunken)]/40 p-3">
      <div className="flex flex-wrap items-start gap-2">
        <label className="sr-only" htmlFor={`item-${index}-type`}>
          Change type for entry {index + 1}
        </label>

        <Select
          id={`item-${index}-type`}
          value={item.type}
          onChange={(event) => onChange({ type: event.target.value as ChangeTypeValue })}
          className="h-9 w-32 shrink-0 py-0 text-xs"
        >
          {types.length === 0 ? (
            <option value={item.type}>{item.type}</option>
          ) : (
            types.map((option) => (
              <option key={option.value} value={option.value} title={option.hint}>
                {option.label}
              </option>
            ))
          )}
        </Select>

        <div className="min-w-[12rem] flex-1">
          <label className="sr-only" htmlFor={titleId}>
            Title for entry {index + 1}
          </label>

          <Input
            id={titleId}
            value={item.title}
            onChange={(event) => onChange({ title: event.target.value })}
            onKeyDown={(event) => {
              // Enter adds the next line rather than submitting: writing a release
              // is a list, and reaching for the mouse between every entry is what
              // makes editors write four-line changelogs.
              if (event.key === "Enter") {
                event.preventDefault();
                onAddBelow();
              }
            }}
            placeholder="What changed, in one line"
            maxLength={255}
            aria-invalid={titleError ? true : undefined}
            className="h-9"
          />

          {titleError && (
            <p role="alert" className="mt-1 text-xs font-medium text-[var(--color-danger)]">
              {titleError}
            </p>
          )}
        </div>

        <div className="flex shrink-0 items-center gap-0.5">
          <RowButton
            label={`Move entry ${index + 1} up`}
            onClick={() => onMove(-1)}
            disabled={index === 0}
          >
            <ChevronUp className="size-3.5" aria-hidden="true" />
          </RowButton>

          <RowButton
            label={`Move entry ${index + 1} down`}
            onClick={() => onMove(1)}
            disabled={index === total - 1}
          >
            <ChevronDown className="size-3.5" aria-hidden="true" />
          </RowButton>

          <RowButton label={`Remove entry ${index + 1}`} onClick={onRemove} destructive>
            <Trash2 className="size-3.5" aria-hidden="true" />
          </RowButton>
        </div>
      </div>

      <label className="sr-only" htmlFor={`item-${index}-description`}>
        Detail for entry {index + 1}
      </label>

      <Textarea
        id={`item-${index}-description`}
        value={item.description}
        onChange={(event) => onChange({ description: event.target.value })}
        placeholder="Optional detail — why it matters, or what it replaces"
        maxLength={2000}
        className="mt-2 min-h-0 rounded-[var(--radius-sm)] py-1.5 text-xs [field-sizing:content]"
        rows={1}
      />
    </li>
  );
}

function RowButton({
  label,
  onClick,
  disabled,
  destructive,
  children,
}: {
  label: string;
  onClick: () => void;
  disabled?: boolean;
  destructive?: boolean;
  children: React.ReactNode;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      aria-label={label}
      className={
        "flex size-7 items-center justify-center rounded-[var(--radius-sm)] text-[var(--color-foreground-subtle)] transition-colors disabled:opacity-30 " +
        (destructive
          ? "hover:bg-[var(--color-danger)]/10 hover:text-[var(--color-danger)]"
          : "hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-foreground)]")
      }
    >
      {children}
    </button>
  );
}

/**
 * The release exactly as the site will render it.
 *
 * The public `ReleaseEntry` component, not a lookalike — which is the only version
 * of a preview worth having. An editor arranging entries by hand needs to see the
 * arrangement, and a preview that approximates the real thing teaches the wrong
 * lesson the first time they diverge.
 */
function Preview({
  release,
  title,
  version,
  summary,
  isMajor,
  releasedAt,
  status,
  items,
  types,
}: {
  release: AdminChangelogRelease | null;
  title: string;
  version: string;
  summary: string;
  isMajor: boolean;
  releasedAt: string;
  status: string;
  items: DraftItem[];
  types: ChangeTypeOption[];
}) {
  const meta = new Map(types.map((option) => [option.value, option]));

  const preview: ChangelogRelease = {
    id: release?.id ?? "preview",
    slug: release?.slug ?? "preview",
    version: version.trim() === "" ? null : version.trim(),
    title: title.trim() === "" ? "Untitled release" : title,
    summary: summary.trim() === "" ? null : summary,
    is_major: isMajor,
    released_at: fromDateInput(releasedAt),
    items: items
      .filter((item) => item.title.trim() !== "")
      .map((item, index) => ({
        id: index,
        type: item.type,
        type_label: meta.get(item.type)?.label ?? item.type,
        tone: meta.get(item.type)?.tone ?? "muted",
        title: item.title,
        description: item.description.trim() === "" ? null : item.description,
      })),
  };

  const live = release?.is_live ?? false;

  return (
    <aside className="xl:sticky xl:top-6 xl:h-fit">
      <AdminPanel
        title="Preview"
        description="How this reads on the site"
        action={
          <div className="flex items-center gap-1.5">
            <StatusPill
              label={statusLabel(status)}
              tone={tone.release(status)}
            />
            {!live && status !== "draft" && releasedAt !== "" && new Date(`${releasedAt}T00:00:00`) > new Date() && (
              <StatusPill label="Not public yet" tone="warning" />
            )}
          </div>
        }
        bodyClassName="p-3"
      >
        {preview.items.length === 0 ? (
          <p className="px-2 py-8 text-center text-sm text-[var(--color-foreground-muted)]">
            Add a change to see the preview.
          </p>
        ) : (
          // Scaled down: a full-width release card does not fit a sidebar, and
          // shrinking the type rather than the layout would misrepresent it.
          <div className="origin-top text-[0.9rem]">
            <ReleaseEntry release={preview} standalone />
          </div>
        )}
      </AdminPanel>
    </aside>
  );
}

function statusLabel(status: string): string {
  return { draft: "Draft", scheduled: "Scheduled", published: "Published" }[status] ?? status;
}

/**
 * `<input type="date">` speaks `YYYY-MM-DD` and nothing else.
 *
 * Built from the *local* date parts rather than `toISOString().slice(0, 10)`, which
 * converts to UTC first: for an editor behind UTC that silently shows the day
 * before the one the release actually carries.
 */
function toDateInput(iso: string | null | undefined): string {
  if (!iso) return "";

  const date = new Date(iso);

  if (Number.isNaN(date.getTime())) return "";

  const month = `${date.getMonth() + 1}`.padStart(2, "0");
  const day = `${date.getDate()}`.padStart(2, "0");

  return `${date.getFullYear()}-${month}-${day}`;
}

/**
 * The reverse: a bare `YYYY-MM-DD` back to an instant.
 *
 * The `T00:00:00` matters. Without it the string is parsed as UTC midnight, and the
 * preview — which formats in local time — then renders the previous day for anyone
 * west of Greenwich. The date an editor typed must be the date they see.
 */
function fromDateInput(value: string): string | null {
  if (value === "") return null;

  const date = new Date(`${value}T00:00:00`);

  return Number.isNaN(date.getTime()) ? null : date.toISOString();
}
