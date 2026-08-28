"use client";

import { ArrowLeft, ExternalLink, Eye, ImageIcon, Save, X } from "lucide-react";
import Image from "next/image";
import Link from "next/link";
import { useRouter } from "next/navigation";
import * as React from "react";

import { AdminPageHeader, AdminPanel, StatusPill } from "@/components/admin/admin-page";
import { useCan } from "@/components/admin/can";
import { useToast } from "@/components/admin/feedback";
import { LoadError } from "@/components/admin/load-error";
import { MediaPicker } from "@/components/admin/media-picker";
import { humanise, tone } from "@/components/admin/status-tone";
import { Button } from "@/components/ui/button";
import { Checkbox, Field, Input, Select, Textarea } from "@/components/ui/field";
import { adminApi } from "@/lib/admin/api";
import type { AdminMedia, AdminTool, SeoOverrides } from "@/lib/admin/types";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import { formatNumber } from "@/lib/utils";

/**
 * One catalog entry, on its own page.
 *
 * A page rather than a panel over the list: `/admin/tools/pdf-merge` survives a
 * refresh, can be pasted into a ticket, and is what the back button leaves rather
 * than what it half-closes.
 *
 * What is editable here is what a tool *is* — its tier, its visibility, its copy.
 * What it *does* lives in the runner bound to its key, and the key is not editable
 * from any screen: a catalog row whose key drifted from its runner is a 500 on the
 * next run, which is exactly the failure the architecture test exists to prevent.
 */
export function ToolEditorScreen({ slug }: { slug: string }) {
  const { data, error, reload } = useAdminResource(
    () => adminApi.tools.get(slug),
    [slug],
  );

  const categories = useAdminResource(() => adminApi.tools.categories(), []);

  if (error) return <LoadError error={error} onRetry={reload} />;

  if (!data) {
    return (
      <div
        className="h-96 animate-pulse rounded-[var(--radius-lg)] bg-[var(--color-surface-sunken)]"
        aria-hidden="true"
      />
    );
  }

  // Keyed on the loaded tool so the form state is built once the values exist,
  // rather than initialised empty and then patched by an effect.
  return (
    <ToolForm
      key={data.id}
      tool={data}
      categories={(categories.data?.data ?? []).map((entry) => ({
        id: entry.id,
        name: entry.name,
      }))}
      loadingCategories={categories.loading}
    />
  );
}

function ToolForm({
  tool,
  categories,
  loadingCategories,
}: {
  tool: AdminTool;
  categories: { id: number; name: string }[];
  loadingCategories: boolean;
}) {
  const router = useRouter();
  const can = useCan();
  const { notify, reportError } = useToast();

  const editable = can("tools.update");

  const [form, setForm] = React.useState({
    name: tool.name,
    tagline: tool.tagline ?? "",
    description: tool.description ?? "",
    tier: tool.tier,
    status: tool.status,
    is_visible: tool.is_visible,
    is_featured: tool.is_featured,
    sort_order: tool.sort_order,
    category_id: tool.category?.id ?? 0,
  });

  // SEO lives beside the rest of the form rather than in its own request: a tool
  // and its metadata are saved by one button, so they cannot end up half-applied.
  const [seo, setSeo] = React.useState<SeoOverrides>({
    title: tool.seo?.title ?? "",
    description: tool.seo?.description ?? "",
    focus_keyword: tool.seo?.focus_keyword ?? "",
    canonical_url: tool.seo?.canonical_url ?? "",
    robots: tool.seo?.robots ?? "index,follow",
    og_title: tool.seo?.og_title ?? "",
    og_description: tool.seo?.og_description ?? "",
    og_media_id: tool.seo?.og_media_id ?? null,
    twitter_card: tool.seo?.twitter_card ?? "summary_large_image",
  });

  const [ogImageUrl, setOgImageUrl] = React.useState<string | null>(
    tool.seo?.og_image_url ?? null,
  );

  const [pickingImage, setPickingImage] = React.useState(false);
  const [saving, setSaving] = React.useState(false);

  function patchSeo(next: Partial<SeoOverrides>) {
    setSeo((current) => ({ ...current, ...next }));
  }

  // The category list arrives after the tool does. Falling back to the first
  // option only matters for a tool that has no category yet, and only once the
  // options are actually known — defaulting to 0 before then would silently move
  // a categorised tool the moment somebody pressed save.
  const categoryId =
    form.category_id !== 0 || categories.length === 0 ? form.category_id : categories[0].id;

  async function save() {
    setSaving(true);

    const result = await adminApi.tools.update(tool.slug, {
      ...form,
      category_id: categoryId,
      tagline: form.tagline || null,
      description: form.description || null,
      // Blank goes over the wire as null: the API treats null as "no override" and
      // falls back to the tool's own copy, where "" would publish an empty tag.
      seo: Object.fromEntries(
        Object.entries(seo).map(([key, value]) => [key, value === "" ? null : value]),
      ),
    } as Partial<AdminTool>);

    setSaving(false);

    if (result.ok) {
      notify(`${form.name} saved.`);
      router.push("/admin/tools");
    } else {
      reportError(result.error);
    }
  }

  return (
    <>
      <AdminPageHeader
        eyebrow="Product · Tools"
        title={tool.name}
        description={`v${tool.version} · ${tool.key} — the key, the slug, the version and the input schema belong to the runner, so none of them are editable here.`}
        actions={
          <>
            <Button variant="secondary" size="sm" asChild>
              <Link href="/admin/tools">
                <ArrowLeft className="size-4" aria-hidden="true" />
                Back to tools
              </Link>
            </Button>

            <Button variant="secondary" size="sm" asChild>
              <Link href={`/tools/${tool.slug}`} target="_blank" rel="noreferrer">
                <ExternalLink className="size-4" aria-hidden="true" />
                View on the site
              </Link>
            </Button>

            {editable && (
              <Button size="sm" onClick={() => void save()} loading={saving}>
                <Save className="size-4" aria-hidden="true" />
                Save tool
              </Button>
            )}
          </>
        }
      />

      <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
        <div className="flex flex-col gap-5">
          <AdminPanel title="Catalog copy" description="What a visitor reads before running it">
            <fieldset disabled={!editable} className="flex max-w-xl flex-col gap-4">
              <Field id="tool-name" label="Name" required>
                {(props) => (
                  <Input
                    {...props}
                    value={form.name}
                    onChange={(event) => setForm({ ...form, name: event.target.value })}
                  />
                )}
              </Field>

              <Field
                id="tool-tagline"
                label="Tagline"
                hint="One line, shown on the catalog card and in search results."
                counter={`${form.tagline.length}/220`}
              >
                {(props) => (
                  <Input
                    {...props}
                    maxLength={220}
                    value={form.tagline}
                    onChange={(event) => setForm({ ...form, tagline: event.target.value })}
                  />
                )}
              </Field>

              <Field id="tool-description" label="Description">
                {(props) => (
                  <Textarea
                    {...props}
                    value={form.description}
                    onChange={(event) => setForm({ ...form, description: event.target.value })}
                  />
                )}
              </Field>
            </fieldset>
          </AdminPanel>

          <AdminPanel title="Access & placement" description="Who can run it, and where it appears">
            <fieldset disabled={!editable} className="grid gap-4 sm:grid-cols-2">
              <Field id="tool-tier" label="Access tier" hint="Who can run this without paying.">
                {(props) => (
                  <Select
                    {...props}
                    value={form.tier}
                    onChange={(event) => setForm({ ...form, tier: event.target.value })}
                  >
                    <option value="free">Free — anyone</option>
                    <option value="account">Account — signed in</option>
                    <option value="premium">Premium — subscribers</option>
                  </Select>
                )}
              </Field>

              <Field id="tool-status" label="Status">
                {(props) => (
                  <Select
                    {...props}
                    value={form.status}
                    onChange={(event) => setForm({ ...form, status: event.target.value })}
                  >
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                    <option value="hidden">Hidden</option>
                    <option value="deprecated">Deprecated</option>
                  </Select>
                )}
              </Field>

              <Field id="tool-category" label="Category">
                {(props) => (
                  <Select
                    {...props}
                    value={String(categoryId)}
                    disabled={!editable || loadingCategories}
                    onChange={(event) =>
                      setForm({ ...form, category_id: Number(event.target.value) })
                    }
                  >
                    {categories.map((category) => (
                      <option key={category.id} value={category.id}>
                        {category.name}
                      </option>
                    ))}
                  </Select>
                )}
              </Field>

              <Field
                id="tool-sort"
                label="Sort order"
                hint="Lower sorts first within its category."
              >
                {(props) => (
                  <Input
                    {...props}
                    type="number"
                    min={0}
                    max={9999}
                    value={form.sort_order}
                    onChange={(event) =>
                      setForm({ ...form, sort_order: Number(event.target.value) })
                    }
                  />
                )}
              </Field>

              <div className="flex flex-col gap-1 rounded-[var(--radius-md)] border border-[var(--color-border-subtle)] p-3 sm:col-span-2">
                <Checkbox
                  label="Visible in the catalog"
                  hint="Turning this off removes it from listings and search without unpublishing it. Anyone with a direct link still gets the page."
                  checked={form.is_visible}
                  onChange={(event) => setForm({ ...form, is_visible: event.target.checked })}
                />

                <Checkbox
                  label="Feature on the catalog"
                  hint="Featured tools sort to the top of the listing."
                  checked={form.is_featured}
                  onChange={(event) => setForm({ ...form, is_featured: event.target.checked })}
                />
              </div>
            </fieldset>
          </AdminPanel>

          <SeoPanel
            seo={seo}
            patch={patchSeo}
            editable={editable}
            slug={tool.slug}
            fallbackTitle={`${form.name} — Free Online Tool`}
            fallbackDescription={form.tagline}
            imageUrl={ogImageUrl}
            onPickImage={() => setPickingImage(true)}
            onClearImage={() => {
              patchSeo({ og_media_id: null });
              setOgImageUrl(null);
            }}
          />
        </div>

        <AdminPanel title="Runtime" description="Written by the runner, not by this form">
          <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center gap-1.5">
              <StatusPill label={humanise(tool.status)} tone={tone.tool(tool.status)} />
              <StatusPill label={tool.tier} tone={tone.tier(tool.tier)} />
            </div>

            <dl className="grid grid-cols-2 gap-3 rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)] p-3 text-sm">
              <div>
                <dt className="font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
                  Lifetime runs
                </dt>
                <dd className="tabular mt-0.5 font-medium text-[var(--color-foreground)]">
                  {formatNumber(tool.stats.runs)}
                </dd>
              </div>
              <div>
                <dt className="font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
                  Success rate
                </dt>
                <dd
                  className="tabular mt-0.5 font-medium"
                  style={{
                    color:
                      tool.stats.success_rate < 95
                        ? "var(--color-danger)"
                        : "var(--color-foreground)",
                  }}
                >
                  {tool.stats.success_rate}%
                </dd>
              </div>
              <div>
                <dt className="font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
                  Average duration
                </dt>
                <dd className="tabular mt-0.5 font-medium text-[var(--color-foreground)]">
                  {formatNumber(tool.stats.avg_duration_ms)}ms
                </dd>
              </div>
              <div>
                <dt className="font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
                  Comped
                </dt>
                <dd className="tabular mt-0.5 font-medium text-[var(--color-foreground)]">
                  {tool.stats.grants ? (
                    <Link href="/admin/grants" className="text-[var(--color-primary)] hover:underline">
                      {tool.stats.grants}
                    </Link>
                  ) : (
                    "0"
                  )}
                </dd>
              </div>
            </dl>

            <p className="flex items-start gap-2 text-xs leading-relaxed text-[var(--color-foreground-subtle)]">
              <Eye className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
              The slug, the key, the version and the input schema are fixed on purpose.
              Changing a slug breaks every link pointing at it, and the other three
              belong to the runner — they move with a deploy, not with a form.
            </p>
          </div>
        </AdminPanel>
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
    </>
  );
}

/**
 * Per-tool SEO overrides.
 *
 * Every field is optional, and blank is the right answer for most of the catalog:
 * the public page already falls back to the tool's own name and tagline, then to
 * the site template. This panel exists for the handful of tools worth hand-tuning,
 * so it shows what will actually be published — the search snippet and the social
 * card — rather than a column of inputs whose effect you have to imagine.
 */
function SeoPanel({
  seo,
  patch,
  editable,
  slug,
  fallbackTitle,
  fallbackDescription,
  imageUrl,
  onPickImage,
  onClearImage,
}: {
  seo: SeoOverrides;
  patch: (next: Partial<SeoOverrides>) => void;
  editable: boolean;
  slug: string;
  fallbackTitle: string;
  fallbackDescription: string;
  imageUrl: string | null;
  onPickImage: () => void;
  onClearImage: () => void;
}) {
  const title = seo.title || fallbackTitle;
  const description = seo.description || fallbackDescription;
  const socialTitle = seo.og_title || title;
  const socialDescription = seo.og_description || description;

  return (
    <AdminPanel
      title="SEO & sharing"
      description="How this tool appears in search results and when its link is shared"
    >
      <fieldset disabled={!editable} className="flex flex-col gap-4">
        {/* Counters tell you a number. This tells you what actually gets
            truncated, which is the thing being decided. */}
        <div className="rounded-[var(--radius-md)] border border-[var(--color-border-subtle)] bg-[var(--color-surface-sunken)] p-3">
          <p className="mb-2 font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
            Search preview
          </p>
          <p className="truncate text-xs text-[var(--color-foreground-subtle)]">
            metacreator.dev › tools › {slug}
          </p>
          <p className="mt-0.5 line-clamp-1 text-sm font-medium text-[var(--color-primary)]">
            {title}
          </p>
          <p className="mt-0.5 line-clamp-2 text-xs leading-snug text-[var(--color-foreground-muted)]">
            {description ||
              "No description — search engines will pick a snippet from the page themselves."}
          </p>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <Field
            id="tool-seo-title"
            label="Meta title"
            hint="Left blank, the tool name is used with the site template."
            counter={`${(seo.title ?? "").length}/60`}
          >
            {(props) => (
              <Input
                {...props}
                maxLength={255}
                value={seo.title ?? ""}
                placeholder={fallbackTitle}
                onChange={(event) => patch({ title: event.target.value })}
              />
            )}
          </Field>

          <Field
            id="tool-seo-keyword"
            label="Focus keyword"
            hint="What this tool is meant to rank for. Recorded, not enforced."
          >
            {(props) => (
              <Input
                {...props}
                maxLength={120}
                value={seo.focus_keyword ?? ""}
                onChange={(event) => patch({ focus_keyword: event.target.value })}
              />
            )}
          </Field>
        </div>

        <Field
          id="tool-seo-description"
          label="Meta description"
          hint="Aim for 140–160 characters. Longer is truncated; shorter wastes the slot."
          counter={`${(seo.description ?? "").length}/160`}
        >
          {(props) => (
            <Textarea
              {...props}
              maxLength={500}
              value={seo.description ?? ""}
              placeholder={fallbackDescription}
              onChange={(event) => patch({ description: event.target.value })}
              className="min-h-20 text-sm"
            />
          )}
        </Field>

        <div className="grid gap-4 sm:grid-cols-2">
          <Field
            id="tool-seo-canonical"
            label="Canonical URL"
            hint="Only when this page duplicates one that lives elsewhere."
          >
            {(props) => (
              <Input
                {...props}
                type="url"
                value={seo.canonical_url ?? ""}
                placeholder={`https://metacreator.dev/tools/${slug}`}
                onChange={(event) => patch({ canonical_url: event.target.value })}
                className="font-mono text-xs"
              />
            )}
          </Field>

          <Field
            id="tool-seo-robots"
            label="Robots"
            hint="A tool set to no-index is still reachable — it just stops competing in search."
          >
            {(props) => (
              <Select
                {...props}
                value={seo.robots ?? "index,follow"}
                onChange={(event) => patch({ robots: event.target.value })}
              >
                <option value="index,follow">Index and follow</option>
                <option value="noindex,follow">Do not index, follow links</option>
                <option value="index,nofollow">Index, do not follow links</option>
                <option value="noindex,nofollow">Do not index or follow</option>
              </Select>
            )}
          </Field>
        </div>

        <hr className="border-[var(--color-border-subtle)]" />

        {/* The same fields Open Graph and Twitter both read, because both cards
            should say the same thing and nobody wants to type it twice. */}
        <div className="grid gap-4 sm:grid-cols-2">
          <Field
            id="tool-seo-og-title"
            label="Social title"
            hint="Shown when the link is shared. Falls back to the meta title."
          >
            {(props) => (
              <Input
                {...props}
                maxLength={255}
                value={seo.og_title ?? ""}
                placeholder={title}
                onChange={(event) => patch({ og_title: event.target.value })}
              />
            )}
          </Field>

          <Field id="tool-seo-card" label="Card type">
            {(props) => (
              <Select
                {...props}
                value={seo.twitter_card ?? "summary_large_image"}
                onChange={(event) => patch({ twitter_card: event.target.value })}
              >
                <option value="summary_large_image">Large image</option>
                <option value="summary">Summary</option>
              </Select>
            )}
          </Field>
        </div>

        <Field
          id="tool-seo-og-description"
          label="Social description"
          hint="Falls back to the meta description."
        >
          {(props) => (
            <Textarea
              {...props}
              maxLength={500}
              value={seo.og_description ?? ""}
              placeholder={description}
              onChange={(event) => patch({ og_description: event.target.value })}
              className="min-h-16 text-sm"
            />
          )}
        </Field>

        <div className="flex flex-col gap-2">
          <span className="text-sm font-medium text-[var(--color-foreground)]">Share image</span>

          <div className="flex flex-wrap items-start gap-3">
            <div className="relative h-[6.5rem] w-[12.4rem] shrink-0 overflow-hidden rounded-[var(--radius-md)] border border-[var(--color-border-subtle)] bg-[var(--color-surface-sunken)]">
              {imageUrl ? (
                <Image
                  src={imageUrl}
                  alt=""
                  fill
                  sizes="200px"
                  className="object-cover"
                  unoptimized
                />
              ) : (
                <div className="flex h-full flex-col items-center justify-center gap-1 text-[var(--color-foreground-subtle)]">
                  <ImageIcon className="size-5" aria-hidden="true" />
                  <span className="text-[0.6875rem]">Site default</span>
                </div>
              )}
            </div>

            <div className="flex min-w-[12rem] flex-1 flex-col gap-2">
              <p className="text-xs leading-relaxed text-[var(--color-foreground-subtle)]">
                1200×630 is the size every network crops to. With none set, the
                site-wide card is used — never nothing, so a share is never a grey box.
              </p>

              <div className="flex gap-2">
                <Button type="button" variant="secondary" size="sm" onClick={onPickImage}>
                  <ImageIcon className="size-4" aria-hidden="true" />
                  {imageUrl ? "Replace" : "Choose image"}
                </Button>

                {imageUrl && (
                  <Button type="button" variant="ghost" size="sm" onClick={onClearImage}>
                    <X className="size-4" aria-hidden="true" />
                    Clear
                  </Button>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* What the card will actually look like, at the width the networks use. */}
        <div className="rounded-[var(--radius-md)] border border-[var(--color-border-subtle)] bg-[var(--color-surface-sunken)] p-3">
          <p className="mb-2 font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
            Social preview
          </p>
          <p className="line-clamp-1 text-sm font-medium text-[var(--color-foreground)]">
            {socialTitle}
          </p>
          <p className="mt-0.5 line-clamp-2 text-xs leading-snug text-[var(--color-foreground-muted)]">
            {socialDescription || "No description set."}
          </p>
          <p className="mt-1 font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
            metacreator.dev
          </p>
        </div>
      </fieldset>
    </AdminPanel>
  );
}
