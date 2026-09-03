"use client";

import {
  Activity,
  ArrowLeft,
  ExternalLink,
  Eye,
  Gauge,
  ImageIcon,
  MonitorPlay,
  Save,
  Search,
  ShieldCheck,
  SlidersHorizontal,
  Type,
  X,
} from "lucide-react";
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
import { useBillingEnabled } from "@/components/site/features-provider";
import { ToolForm as ToolPageForm } from "@/components/tools/tool-form";
import { Button } from "@/components/ui/button";
import { Checkbox, Field, Input, Select, Textarea } from "@/components/ui/field";
import { adminApi } from "@/lib/admin/api";
import type { AdminMedia, AdminTool, SeoOverrides, ToolFieldOverride } from "@/lib/admin/types";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import type { JsonSchema, JsonSchemaProperty } from "@/lib/types";
import { cn, formatNumber } from "@/lib/utils";

/**
 * One catalog entry, on its own page.
 *
 * A page rather than a panel over the list: `/c0ns0le/tools/pdf-merge` survives a
 * refresh, can be pasted into a ticket, and is what the back button leaves rather
 * than what it half-closes.
 *
 * What is editable here is what a tool *is* — its tier, its visibility, its copy.
 * What it *does* lives in the runner bound to its key, and the key is not editable
 * from any screen: a catalog row whose key drifted from its runner is a 500 on the
 * next run, which is exactly the failure the architecture test exists to prevent.
 */
export function ToolEditorScreen({ slug }: { slug: string }) {
  const { data, error, reload } = useAdminResource(() => adminApi.tools.get(slug), [slug]);

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

/**
 * The windows a tool can cap itself over, in the order they are enforced.
 *
 * Shortest first, because that is the order a visitor hits them, and the same
 * order the global settings screen uses — two screens describing one mechanism
 * should not disagree about which end of it comes first.
 */
const RUN_LIMIT_WINDOWS = [
  {
    id: "daily",
    label: "Per day",
    hint: "Resets at midnight, site time.",
  },
  {
    id: "weekly",
    label: "Per week",
    hint: "Resets Monday. Catches a backlog saved up for one sitting.",
  },
  {
    id: "monthly",
    label: "Per month",
    hint: "Resets on the 1st. The window a metered provider actually bills on.",
  },
] as const;

type RunLimitWindow = (typeof RUN_LIMIT_WINDOWS)[number]["id"];

/**
 * The editor's sections, in the order an admin works through them.
 *
 * A tool row carries four unrelated kinds of decision — its copy, its gating, its
 * caps, its form wording — and a single column of them is a screen nobody reads to
 * the bottom. Splitting them costs nothing, because the whole form is still one
 * piece of state saved by one button: switching sections is a view change, not a
 * commit, and an edit left in Copy is still there when Save is pressed from SEO.
 *
 * `Runtime` is last and is read-only. It is the runner's own account of itself.
 */
const SECTIONS = [
  { id: "copy", label: "Catalog copy", icon: Type },
  { id: "access", label: "Access & placement", icon: ShieldCheck },
  { id: "limits", label: "Run limits", icon: Gauge },
  { id: "fields", label: "Form fields", icon: SlidersHorizontal },
  { id: "preview", label: "Form preview", icon: MonitorPlay },
  { id: "seo", label: "SEO & sharing", icon: Search },
  { id: "runtime", label: "Runtime", icon: Activity },
] as const;

type SectionId = (typeof SECTIONS)[number]["id"];

/** The stored cap for one window as a form value — "" when the tool defers. */
function limitValue(tool: AdminTool, window: RunLimitWindow): string {
  const limit = tool.run_limits?.[window];

  return typeof limit === "number" ? String(limit) : "";
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

  const billingEnabled = useBillingEnabled();

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

  // Blank is a real value here — it means "defer to the tier" — so the caps are
  // held as strings and only become numbers (or null) on the way out. A number
  // state would have to spell blank as 0, which is a very different instruction.
  const [limits, setLimits] = React.useState<Record<RunLimitWindow, string>>({
    daily: limitValue(tool, "daily"),
    weekly: limitValue(tool, "weekly"),
    monthly: limitValue(tool, "monthly"),
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

  // Keyed by field name, and only the fields an admin has actually filled in — an
  // empty box here means "use whatever the runner's schema says", which is a
  // different instruction from "show nothing".
  const [fieldOverrides, setFieldOverrides] = React.useState<Record<string, ToolFieldOverride>>(
    () => ({ ...(tool.field_overrides ?? {}) }),
  );

  const [ogImageUrl, setOgImageUrl] = React.useState<string | null>(tool.seo?.og_image_url ?? null);

  const [section, setSection] = React.useState<SectionId>("copy");

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
      // Only `limits` is sent: the API merges `config` rather than assigning it, so
      // a runner's own settings survive a save from this form.
      config: {
        limits: Object.fromEntries(
          RUN_LIMIT_WINDOWS.map(({ id }) => [id, limits[id] === "" ? null : Number(limits[id])]),
        ),
        // Sent whole rather than as a patch: the panel below renders every field the
        // form has, so what is missing here is what was cleared.
        field_overrides: fieldOverrides,
      },
      // Blank goes over the wire as null: the API treats null as "no override" and
      // falls back to the tool's own copy, where "" would publish an empty tag.
      seo: Object.fromEntries(
        Object.entries(seo).map(([key, value]) => [key, value === "" ? null : value]),
      ),
    } as Partial<AdminTool>);

    setSaving(false);

    if (result.ok) {
      notify(`${form.name} saved.`);
      router.push("/c0ns0le/tools");
    } else {
      reportError(result.error);
    }
  }

  const current = SECTIONS.find((entry) => entry.id === section) ?? SECTIONS[0];

  return (
    <>
      <AdminPageHeader
        eyebrow="Product · Tools"
        title={tool.name}
        description={`v${tool.version} · ${tool.key} — the key, the slug, the version and the input schema belong to the runner, so none of them are editable here.`}
        actions={
          <>
            <Button variant="secondary" size="sm" asChild>
              <Link href="/c0ns0le/tools">
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

      {/* The four facts an admin checks before they read anything else: is it
          live, where does it sit, who can run it, is it promoted. Each is
          editable further down — this reads the live form state rather than the
          saved tool, so it answers "what am I about to save" and not "what was
          true when the page loaded". */}
      <dl className="mb-4 grid gap-3 rounded-[var(--radius-md)] border border-[var(--color-border-subtle)] bg-[var(--color-surface-sunken)] p-3 text-sm sm:grid-cols-4">
        <div>
          <dt className="font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
            Status
          </dt>
          <dd className="mt-1">
            <StatusPill label={humanise(form.status)} tone={tone.tool(form.status)} />
          </dd>
        </div>
        <div>
          <dt className="font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
            Category
          </dt>
          <dd className="mt-1 font-medium text-[var(--color-foreground)]">
            {categories.find((category) => category.id === categoryId)?.name ??
              tool.category?.name ??
              "Uncategorised"}
          </dd>
        </div>
        <div>
          <dt className="font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
            Access tier
          </dt>
          <dd className="mt-1">
            <StatusPill label={humanise(form.tier)} tone={tone.tier(form.tier)} />
          </dd>
        </div>
        <div>
          <dt className="font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
            Featured
          </dt>
          <dd className="mt-1">
            <StatusPill
              label={form.is_featured ? "Featured" : "Not featured"}
              tone={form.is_featured ? "success" : "muted"}
            />
          </dd>
        </div>
      </dl>

      {/* One editor, one save button, several sections — the whole form is still
          submitted at once, so switching tabs never loses a pending change.

          Tabs rather than a rail: a 14rem rail beside the content is 14rem the
          tool page does not spend, which left the form preview drawn narrower
          than the page it previews. Across the top, every section gets the full
          measure, and the preview is the real thing at the real width. */}
      <div className="flex flex-col gap-4">
        <nav aria-label="Tool sections" className="border-b border-[var(--color-border-subtle)]">
          {/* Scrolls sideways rather than wrapping: a tab strip that reflows to a
              second row moves every tab under the cursor as the active one
              changes. */}
          <ul className="scrollbar-slim -mb-px flex gap-1 overflow-x-auto">
            {SECTIONS.map((entry) => {
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
                  </button>
                </li>
              );
            })}
          </ul>
        </nav>

        <div className="flex min-w-0 flex-1 flex-col gap-5">
          {section === "copy" && (
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
          )}

          {section === "access" && (
            <AdminPanel
              title="Access & placement"
              description="Who can run it, and where it appears"
            >
              <fieldset disabled={!editable} className="grid gap-4 sm:grid-cols-2">
                {/* The stored tier is what this field edits, and Premium stays
                    selectable with billing off — but it behaves as Account until
                    billing is switched back on, and an admin who is not told that
                    will think the paywall is broken. */}
                <Field
                  id="tool-tier"
                  label="Access tier"
                  hint={
                    billingEnabled
                      ? "Who can run this without paying."
                      : "Who can run this. Billing is off site-wide, so Premium currently behaves as Account — the setting is kept and takes effect again when billing is switched back on."
                  }
                >
                  {(props) => (
                    <Select
                      {...props}
                      value={form.tier}
                      onChange={(event) => setForm({ ...form, tier: event.target.value })}
                    >
                      <option value="free">Free — anyone</option>
                      <option value="account">Account — signed in</option>
                      <option value="premium">
                        {billingEnabled
                          ? "Premium — subscribers"
                          : "Premium — subscribers (inactive: billing is off)"}
                      </option>
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
                        setForm({
                          ...form,
                          category_id: Number(event.target.value),
                        })
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
                        setForm({
                          ...form,
                          sort_order: Number(event.target.value),
                        })
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
          )}

          {section === "limits" && (
            <AdminPanel
              title="Run limits"
              description="This tool's own caps, on top of the global ones"
            >
              <fieldset disabled={!editable} className="flex flex-col gap-4">
                <p className="text-sm text-[var(--color-foreground-muted)]">
                  Leave a window blank to use the site-wide allowance from{" "}
                  <Link
                    href="/c0ns0le/settings"
                    className="underline decoration-dotted underline-offset-2"
                  >
                    Settings → Tools
                  </Link>
                  , which is what almost every tool should do. A number here only ever{" "}
                  <em>narrows</em> the visitor&rsquo;s allowance — it cannot raise it above their
                  tier, so a cap set here is a ceiling for everyone including subscribers. That is
                  the point: a tool that spends metered third-party credit should not be unlimited
                  just because somebody&rsquo;s plan is.
                </p>

                <div className="grid gap-4 sm:grid-cols-3">
                  {RUN_LIMIT_WINDOWS.map((window) => (
                    <Field
                      key={window.id}
                      id={`tool-limit-${window.id}`}
                      label={window.label}
                      hint={window.hint}
                    >
                      {(props) => (
                        <Input
                          {...props}
                          type="number"
                          min={1}
                          inputMode="numeric"
                          placeholder="Tier default"
                          value={limits[window.id]}
                          onChange={(event) =>
                            setLimits({
                              ...limits,
                              [window.id]: event.target.value,
                            })
                          }
                        />
                      )}
                    </Field>
                  ))}
                </div>
              </fieldset>
            </AdminPanel>
          )}

          {section === "fields" && (
            <FormFieldsPanel
              schema={tool.input_schema ?? null}
              example={tool.example ?? null}
              overrides={fieldOverrides}
              editable={editable}
              onChange={setFieldOverrides}
            />
          )}

          {section === "preview" && (
            <FormPreviewPanel
              schema={tool.input_schema ?? null}
              example={tool.example ?? null}
              overrides={fieldOverrides}
              toolName={form.name}
              toolSlug={tool.slug}
            />
          )}

          {section === "seo" && (
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
          )}

          {section === "runtime" && (
            <AdminPanel title="Runtime" description="Written by the runner, not by this form">
              <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center gap-1.5">
                  <StatusPill label={humanise(tool.status)} tone={tone.tool(tool.status)} />
                  <StatusPill label={tool.tier} tone={tone.tier(tool.tier)} />
                </div>

                <dl className="grid gap-3 rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)] p-3 text-sm sm:grid-cols-4">
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
                        <Link
                          href="/c0ns0le/grants"
                          className="text-[var(--color-primary)] hover:underline"
                        >
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
                  The slug, the key, the version and the input schema are fixed on purpose. Changing
                  a slug breaks every link pointing at it, and the other three belong to the runner
                  — they move with a deploy, not with a form.
                </p>
              </div>
            </AdminPanel>
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
    </>
  );
}

/**
 * The per-field wording of the generated form.
 *
 * The form itself is generated from the runner's JSON Schema and stays that way —
 * which fields exist, what they accept and what is required all move with a deploy.
 * What goes stale between deploys is the *copy*: a sample post gets deleted, a link
 * rots, a hint describes an input the tool stopped accepting two versions ago. The
 * tool's most persuasive element — the empty box that shows you what to paste, and
 * the button that runs it for you — is then quietly demonstrating a 404. That is a
 * five-second fix that should not need an engineer, so it lives here.
 *
 * One box per fact. `Sample value` fills the placeholder *and* the "Try with sample
 * data" run, because they are the same claim about what good input looks like;
 * `Starting value` is the separate, rarer case of a field that should arrive
 * already filled in; `Hint` is the line of help printed under the box.
 */
function FormFieldsPanel({
  schema,
  example,
  overrides,
  editable,
  onChange,
}: {
  schema: JsonSchema | null;
  example: { input: Record<string, unknown>; note?: string } | null;
  overrides: Record<string, ToolFieldOverride>;
  editable: boolean;
  onChange: (next: Record<string, ToolFieldOverride>) => void;
}) {
  const fields = Object.entries(schema?.properties ?? {});

  if (fields.length === 0) {
    return (
      <AdminPanel title="Form fields" description="What the tool asks a visitor for">
        <p className="text-sm text-[var(--color-foreground-muted)]">
          This tool&rsquo;s runner declares no input fields, so there is nothing to fill in.
        </p>
      </AdminPanel>
    );
  }

  const required = new Set(schema?.required ?? []);

  function patch(field: string, key: keyof ToolFieldOverride, value: string) {
    const next = {
      ...overrides,
      [field]: { ...overrides[field], [key]: value },
    };

    // A cleared box is the absence of an override, not an empty one: leaving `""`
    // behind would publish a placeholder-less field and store a value that means
    // nothing. Dropping the key hands the field back to the runner's schema.
    if (value === "") delete next[field][key];
    if (Object.keys(next[field]).length === 0) delete next[field];

    onChange(next);
  }

  return (
    <AdminPanel
      title="Form fields"
      description="The label copy, example and starting value each field shows"
    >
      <fieldset disabled={!editable} className="flex flex-col gap-5">
        <p className="text-sm text-[var(--color-foreground-muted)]">
          Which fields exist and what they accept belong to the runner and move with a deploy. What
          you can change here is what each one <em>says</em>: leave a box blank to use the wording
          the runner ships with.{" "}
          <strong className="font-medium text-[var(--color-foreground)]">Form preview</strong>, in
          the menu, draws the real form from whatever is typed here — before it is saved.
        </p>

        {fields.map(([field, property]) => {
          const override = overrides[field] ?? {};
          const shipped = property.examples?.[0];
          const shippedSample = shipped == null ? "" : String(shipped);

          return (
            <div
              key={field}
              className="flex flex-col gap-3 rounded-[var(--radius-md)] border border-[var(--color-border-subtle)] p-3"
            >
              <div className="flex flex-wrap items-baseline gap-2">
                <span className="font-medium text-[var(--color-foreground)]">
                  {property.title ?? field}
                </span>
                <code className="font-mono text-xs text-[var(--color-foreground-subtle)]">
                  {field}
                </code>
                <span className="text-xs text-[var(--color-foreground-subtle)]">
                  {property.type ?? "string"}
                  {required.has(field) ? " · required" : ""}
                </span>
              </div>

              <Field
                id={`tool-field-hint-${field}`}
                label="Hint"
                hint="The line of help under the box. Blank leaves the runner's own wording."
                counter={override.hint ? `${override.hint.length}/300` : undefined}
              >
                {(props) => (
                  <Textarea
                    {...props}
                    maxLength={300}
                    rows={2}
                    value={override.hint ?? ""}
                    placeholder={property.description ?? "No hint shipped"}
                    onChange={(event) => patch(field, "hint", event.target.value)}
                    className="min-h-16 text-sm"
                  />
                )}
              </Field>

              <div className="grid gap-4 sm:grid-cols-2">
                <Field
                  id={`tool-field-sample-${field}`}
                  label="Sample value"
                  hint="Shown greyed out in the empty box, and used by “Try with sample data”."
                >
                  {(props) => (
                    <Input
                      {...props}
                      maxLength={2000}
                      value={override.sample ?? ""}
                      placeholder={shippedSample || "No example shipped"}
                      onChange={(event) => patch(field, "sample", event.target.value)}
                      className="font-mono text-xs"
                    />
                  )}
                </Field>

                <Field
                  id={`tool-field-default-${field}`}
                  label="Starting value"
                  hint="Pre-fills the field. Usually blank — most fields should start empty."
                >
                  {(props) => (
                    <Input
                      {...props}
                      maxLength={2000}
                      value={override.default ?? ""}
                      placeholder={property.default == null ? "Empty" : String(property.default)}
                      onChange={(event) => patch(field, "default", event.target.value)}
                      className="font-mono text-xs"
                    />
                  )}
                </Field>
              </div>
            </div>
          );
        })}

        {example?.note && (
          <p className="text-xs text-[var(--color-foreground-subtle)]">
            The sample run is captioned on the tool page with: “{example.note}”
          </p>
        )}
      </fieldset>
    </AdminPanel>
  );
}

/**
 * The tool page's own form, drawn from the pending overrides.
 *
 * This is the real `ToolForm` component the site renders, fed a schema with the
 * overrides layered on — the same layering the API does on save. A mock-up would be
 * the wrong thing here: the point of the preview is to answer "does my hint fit
 * under that box", and only the actual component can answer that.
 *
 * It gets a section of its own rather than a column beside the editors, because
 * the form lays itself out on a twelve-column grid sized to the *content* of each
 * field. Squeezed into a sidebar, a tool with eight inputs stacks into something
 * that looks broken and is not what a visitor will see — which makes the preview
 * worse than useless, since it misleads about the one thing it exists to show. It
 * is why this screen navigates by tabs: across the top, the content keeps the full
 * measure, and the preview is the tool page's layout at the tool page's width.
 *
 * Nothing here submits. The buttons are the ones a visitor sees, because a preview
 * that hides them would misrepresent the space they take, but this screen has no
 * business running the tool.
 */
function FormPreviewPanel({
  schema,
  example,
  overrides,
  toolName,
  toolSlug,
}: {
  schema: JsonSchema | null;
  example: { input: Record<string, unknown>; note?: string } | null;
  overrides: Record<string, ToolFieldOverride>;
  toolName: string;
  toolSlug: string;
}) {
  const presented = React.useMemo(
    () => (schema === null ? null : presentSchema(schema, overrides)),
    [schema, overrides],
  );

  // `ToolForm` seeds the values it holds from the schema's defaults, once, on
  // mount — so an edited starting value has to re-mount it to show up in the box
  // rather than beside it. Everything else (labels, hints, placeholders) is read
  // from props on every render, and keying on those too would throw away whatever
  // has been typed into the preview on each keystroke in the panel next to it.
  const defaultsKey = React.useMemo(
    () =>
      JSON.stringify(
        Object.entries(presented?.properties ?? {}).map(([field, property]) => [
          field,
          property.default ?? null,
        ]),
      ),
    [presented],
  );

  const presentedExample = React.useMemo(
    () => (schema === null ? null : presentExample(schema, example, overrides)),
    [schema, example, overrides],
  );

  if (presented === null || Object.keys(presented.properties ?? {}).length === 0) {
    return null;
  }

  return (
    <div className="flex flex-col gap-3">
      <div>
        <h2 className="text-sm font-semibold text-[var(--color-foreground)]">Form preview</h2>
        <p className="mt-0.5 text-xs text-[var(--color-foreground-subtle)]">
          The tool page&rsquo;s own component, chrome and measure — not a mock-up of it
        </p>
      </div>

      {/* The tool page's card, reproduced rather than approximated: the same
          `panel` class, the same padding, the same hairline of light along the top
          edge. The form inside sizes its fields as fractions of whatever it is
          given, so anything above the `md` breakpoint arranges them identically —
          which makes the width the only thing left that can lie, and it is capped
          here at exactly what the public page allows: the 80rem measure, less its
          1.5rem gutters, giving the panel 77rem to hold. Wider admin screens stop
          there instead of drawing a form nobody will ever see that wide. */}
      <section className="panel relative w-full max-w-[77rem] overflow-hidden p-5 shadow-[var(--shadow-card)] sm:p-7">
        <span
          aria-hidden="true"
          className="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-[var(--color-primary)] to-transparent opacity-70"
        />

        <ToolPageForm
          key={defaultsKey}
          schema={presented}
          example={presentedExample}
          pending={false}
          toolName={toolName}
          toolSlug={toolSlug}
          onSubmit={() => {}}
        />
      </section>

      <p className="text-xs text-[var(--color-foreground-subtle)]">
        A preview — the buttons do nothing here. Unsaved edits made in{" "}
        <strong className="font-medium text-[var(--color-foreground-muted)]">Form fields</strong>{" "}
        show up immediately; the live page picks them up once you save.
      </p>
    </div>
  );
}

/**
 * The runner's schema with an admin's wording layered on — the browser's copy of
 * `Tool::presentedInputSchema()`.
 *
 * Kept deliberately in step with the PHP, including the cases where blank is a
 * decision rather than a gap: a cleared sample means "show no placeholder", not
 * "fall back to the runner's".
 */
function presentSchema(
  schema: JsonSchema,
  overrides: Record<string, ToolFieldOverride>,
): JsonSchema {
  const properties: Record<string, JsonSchemaProperty> = {};

  for (const [field, property] of Object.entries(schema.properties ?? {})) {
    const override = overrides[field];

    if (!override) {
      properties[field] = property;
      continue;
    }

    const next: JsonSchemaProperty = { ...property };

    if (override.hint !== undefined) {
      const hint = override.hint.trim();

      if (hint === "") delete next.description;
      else next.description = hint;
    }

    if (override.sample !== undefined) {
      const sample = castForField(property, override.sample);

      next.examples = sample === null ? [] : [sample];
    }

    if (override.default !== undefined) {
      const value = castForField(property, override.default);

      if (value === null) delete next.default;
      else next.default = value;
    }

    properties[field] = next;
  }

  return { ...schema, properties };
}

/** The worked example behind "Try with sample data", with the pending samples. */
function presentExample(
  schema: JsonSchema,
  example: { input: Record<string, unknown>; note?: string } | null,
  overrides: Record<string, ToolFieldOverride>,
): { input: Record<string, unknown>; note?: string } | null {
  const input = { ...(example?.input ?? {}) };

  for (const [field, override] of Object.entries(overrides)) {
    if (override.sample === undefined) continue;

    const sample = castForField(schema.properties?.[field] ?? {}, override.sample);

    if (sample === null) delete input[field];
    else input[field] = sample;
  }

  // An example with nothing to fill in is not an example — the button would run
  // the tool on a blank form and show the visitor a validation error.
  return Object.keys(input).length === 0 ? null : { ...example, input };
}

/**
 * Coerce an admin-typed value to what the field's schema accepts.
 *
 * Every override is typed into a text box. Left a string, a "50" on an integer
 * field would be posted back by "Try with sample data" and rejected by the very
 * schema the value is meant to demonstrate.
 */
function castForField(property: JsonSchemaProperty, value: string): unknown {
  if (value === "") return null;

  switch (property.type) {
    case "integer": {
      const parsed = Number(value);

      return Number.isFinite(parsed) ? Math.trunc(parsed) : null;
    }
    case "number": {
      const parsed = Number(value);

      return Number.isFinite(parsed) ? parsed : null;
    }
    case "boolean": {
      const normalised = value.trim().toLowerCase();

      if (["1", "true", "yes", "on"].includes(normalised)) return true;
      if (["0", "false", "no", "off"].includes(normalised)) return false;

      return null;
    }
    default:
      return value;
  }
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
                1200×630 is the size every network crops to. With none set, the site-wide card is
                used — never nothing, so a share is never a grey box.
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
