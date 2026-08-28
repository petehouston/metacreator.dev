"use client";

import {
  ChevronRight,
  CreditCard,
  FileText,
  Lock,
  Mail,
  Save,
  Search,
  ShieldAlert,
  Store,
  UserPlus,
  type LucideIcon,
} from "lucide-react";
import * as React from "react";

import { AdminPageHeader, StatusPill } from "@/components/admin/admin-page";
import { useToast } from "@/components/admin/feedback";
import { LoadError } from "@/components/admin/load-error";
import { Button } from "@/components/ui/button";
import { Checkbox, Field, Input, Select, Textarea } from "@/components/ui/field";
import { adminApi } from "@/lib/admin/api";
import type { SettingItem, SettingsPayload } from "@/lib/admin/types";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import { cn } from "@/lib/utils";

/**
 * One card inside a section.
 *
 * This is the unit that makes the screen navigable. A section like Payments is not
 * a list of thirteen fields — it is *general options*, then Stripe, then PayPal,
 * then Braintree, each in its own bordered block with its own explanation. That is
 * how every configuration screen a merchant has already used is laid out, and the
 * reason is not decoration: three providers' credentials in one flat column is a
 * screen where a Stripe key gets pasted into a PayPal field.
 *
 * `keys` is exact and ordered; `match` catches whatever a prefix owns. A panel
 * declaring neither takes everything the section has left over — which is what
 * makes adding a setting server-side land somewhere sensible without a UI change.
 */
interface SettingsPanel {
  id: string;
  label: string;
  description?: string;
  /** Exact keys, rendered in this order. Claimed before any `match` runs. */
  keys?: string[];
  /** Predicate over the remaining keys, usually a prefix test. */
  match?: (key: string) => boolean;
}

/**
 * A settings section: what it is called, which keys belong to it, and how those
 * keys are grouped once inside.
 *
 * Sections are declared here rather than derived from the `group` column, because
 * the two answer different questions. `group` is a permission and storage concern —
 * everything under `scripts` needs `settings.scripts.update`. A section is where a
 * human would look for something, and "turn the blog off" belongs beside the blog,
 * not in a list called Feature flags with five unrelated switches.
 *
 * That is why there is no Features tab. Every `features.*` flag is claimed by the
 * section it governs: the blog switch sits at the top of Blog, the checkout switch
 * at the top of Payments. A tab of unrelated toggles is a tab you visit to turn
 * something on and then have to visit a second tab to configure.
 */
interface SettingsSection {
  id: string;
  label: string;
  icon: LucideIcon;
  description: string;
  /** Every key in these groups, minus anything another section claims explicitly. */
  groups?: string[];
  /** Keys pulled into this section by name. These win over `groups`. */
  keys?: string[];
  /** How the section's own keys are split into cards. */
  panels: SettingsPanel[];
}

/** Everything a prefix owns, e.g. `payments.stripe.`. */
const prefixed = (prefix: string) => (key: string) => key.startsWith(prefix);

const SECTIONS: SettingsSection[] = [
  {
    id: "general",
    label: "General",
    icon: Store,
    description: "The name, the promise, and where support mail goes.",
    groups: ["branding"],
    panels: [
      {
        id: "identity",
        label: "Site identity",
        description: "Used in page titles, share cards and transactional email.",
      },
    ],
  },
  {
    id: "blog",
    label: "Blog",
    icon: FileText,
    description:
      "The blog is a whole public surface: its routes, its sitemap entries and its navigation links all hang off one switch — and what an article puts on the page is set here too.",
    groups: ["blog"],
    keys: ["features.blog_enabled"],
    panels: [
      {
        id: "availability",
        label: "Availability",
        description:
          "Off, every blog route 404s, the nav links disappear and the sitemap entries are dropped. Posts are not deleted.",
        keys: ["features.blog_enabled"],
      },
      {
        id: "article",
        label: "Post display",
        description:
          "What an article shows around its content. These apply to the public blog, on both the listing cards and the post itself.",
        keys: [
          "blog.show_author",
          "blog.show_published_date",
          "blog.show_reading_time",
          "blog.show_featured_image",
          "blog.show_categories",
          "blog.show_tags",
          "blog.show_related_posts",
        ],
      },
      {
        id: "listing",
        label: "Listing",
        description: "How the archive pages are paged.",
        keys: ["blog.posts_per_page"],
      },
    ],
  },
  {
    id: "accounts",
    label: "Accounts & sign-in",
    icon: UserPlus,
    description:
      "Whether anyone new can join, and which ways in are open. Turning a method off never signs out the people already using it.",
    keys: [
      "features.registration_enabled",
      "features.google_login_enabled",
      "features.magic_link_enabled",
    ],
    panels: [
      {
        id: "registration",
        label: "Registration",
        description: "Off, the sign-up form is closed. Existing accounts are unaffected.",
        keys: ["features.registration_enabled"],
      },
      {
        id: "methods",
        label: "Sign-in methods",
        description:
          "Password sign-in is always available; these are the alternatives to it. Closing the last one open would lock the product, so at least one should stay on.",
        keys: ["features.google_login_enabled", "features.magic_link_enabled"],
      },
    ],
  },
  {
    id: "payments",
    label: "Payments",
    icon: CreditCard,
    description:
      "Which gateway takes the money, and the credentials for it. One provider is live at a time; the others keep their keys, so switching back is a dropdown rather than a re-onboarding.",
    groups: ["payments"],
    keys: ["features.billing_enabled"],
    panels: [
      {
        id: "general",
        label: "General",
        description:
          "The provider that takes the money, and whether checkout is open at all. Plans are defined under Billing; each carries the price identifier the chosen provider knows it by.",
        keys: [
          "features.billing_enabled",
          "payments.enabled",
          "payments.provider",
          "payments.test_mode",
          "payments.currency",
        ],
      },
      {
        id: "stripe",
        label: "Stripe",
        description:
          "Used when the provider above is Stripe. The webhook secret is what makes an incoming event trustworthy — without it, subscription state cannot be believed.",
        match: prefixed("payments.stripe."),
      },
      {
        id: "paypal",
        label: "PayPal",
        description: "Used when the provider above is PayPal.",
        match: prefixed("payments.paypal."),
      },
      {
        id: "braintree",
        label: "Braintree",
        description: "Used when the provider above is Braintree.",
        match: prefixed("payments.braintree."),
      },
    ],
  },
  {
    id: "seo",
    label: "SEO",
    icon: Search,
    description: "Title templates, the fallback share image, and search-console verification.",
    groups: ["seo"],
    panels: [
      {
        id: "templates",
        label: "Title templates",
        description: "`{{title}}` and `{{name}}` are replaced per page.",
        keys: ["seo.title_template", "seo.tool_title_template"],
      },
      {
        id: "sharing",
        label: "Sharing",
        description: "The image used when a page has none of its own.",
        keys: ["seo.default_og_image"],
      },
      {
        id: "verification",
        label: "Search console verification",
        description: "The tokens each engine asks you to publish to prove ownership.",
      },
    ],
  },
  {
    id: "scripts",
    label: "Tracking & scripts",
    icon: ShieldAlert,
    description:
      "Third-party tags and raw HTML. This is arbitrary code on every public page, so it has its own permission.",
    groups: ["scripts"],
    panels: [
      {
        id: "ids",
        label: "Analytics identifiers",
        description:
          "The measurement IDs. Preferred over pasting a provider's snippet below — these load the tag the same way every time.",
        match: prefixed("tracking."),
      },
      {
        id: "raw",
        label: "Custom HTML",
        description:
          "Injected verbatim into every public page. Never into the admin or the customer dashboard.",
        match: prefixed("scripts."),
      },
    ],
  },
  {
    id: "newsletter",
    label: "Newsletter",
    icon: Mail,
    description: "Which provider the list syncs to, and the credentials for it.",
    groups: ["newsletter"],
    keys: ["features.newsletter_enabled"],
    panels: [
      {
        id: "general",
        label: "General",
        description: "Whether the sign-up forms appear, and how a subscription is confirmed.",
        keys: ["features.newsletter_enabled", "newsletter.double_opt_in"],
      },
      {
        id: "provider",
        label: "Provider",
        description:
          "Where the list actually lives. `Local list only` keeps every subscriber here and syncs nothing outward.",
        keys: ["newsletter.provider", "newsletter.list_id", "newsletter.api_key"],
      },
    ],
  },
];

/** Keys some section claims by name — excluded from whichever group holds them. */
const CLAIMED = new Set(SECTIONS.flatMap((section) => section.keys ?? []));

/** Options for the few settings whose value is one of a fixed set. */
const CHOICES: Record<string, { value: string; label: string }[]> = {
  "payments.provider": [
    { value: "none", label: "No gateway — checkout disabled" },
    { value: "stripe", label: "Stripe" },
    { value: "paypal", label: "PayPal" },
    { value: "braintree", label: "Braintree" },
  ],
  "newsletter.provider": [
    { value: "local", label: "Local list only" },
    { value: "mailchimp", label: "MailChimp" },
    { value: "mailerlite", label: "MailerLite" },
    { value: "moosend", label: "Moosend" },
    { value: "sendy", label: "Sendy" },
    { value: "brevo", label: "Brevo" },
  ],
};

/**
 * Site configuration.
 *
 * A left rail of sections, and each section a stack of titled cards, the way every
 * settings screen a merchant has already used is laid out: a single scrolling
 * column of forty fields is a screen where nobody finds anything twice.
 *
 * The permission split is still visible rather than silently refusing a save:
 * `settings.update` for ordinary values, `settings.scripts.update` because raw HTML
 * in `<head>` is code execution on every page, and `settings.secrets.update` because
 * provider keys are credentials. A section the actor cannot write renders read-only
 * with the reason attached.
 *
 * Secrets are never sent to the browser — only whether one is set. A blank secret
 * field means "leave it alone", which is why saving this form does not wipe an API
 * key it was never shown.
 */
export function SettingsScreen() {
  const { notify, reportError } = useToast();

  const { data, error, loading, reload } = useAdminResource(() => adminApi.settings.get(), []);

  const [active, setActive] = React.useState("general");
  const [draft, setDraft] = React.useState<Record<string, unknown>>({});
  const [saving, setSaving] = React.useState(false);

  const dirty = Object.keys(draft).length > 0;

  if (error) return <LoadError error={error} onRetry={reload} />;

  if (loading && !data) {
    return (
      <div className="flex gap-6">
        <div
          className="hidden h-96 w-56 shrink-0 animate-pulse rounded-[var(--radius-lg)] bg-[var(--color-surface-sunken)] md:block"
          aria-hidden="true"
        />
        <div
          className="h-96 flex-1 animate-pulse rounded-[var(--radius-lg)] bg-[var(--color-surface-sunken)]"
          aria-hidden="true"
        />
      </div>
    );
  }

  if (!data) return null;

  function set(key: string, value: unknown) {
    setDraft((current) => ({ ...current, [key]: value }));
  }

  function valueOf(setting: SettingItem): unknown {
    return Object.prototype.hasOwnProperty.call(draft, setting.key)
      ? draft[setting.key]
      : setting.value;
  }

  async function save() {
    setSaving(true);

    const result = await adminApi.settings.update(
      Object.entries(draft).map(([key, value]) => ({ key, value })),
    );

    setSaving(false);

    if (result.ok) {
      const count = result.data.updated.length;
      notify(
        count === 0 ? "Nothing changed." : `${count} ${count === 1 ? "setting" : "settings"} saved.`,
      );
      setDraft({});
      reload();
    } else {
      reportError(result.error);
    }
  }

  // Sections with nothing in them are dropped rather than rendered empty: a
  // deployment without a payments group should not offer a Payments tab.
  const sections = SECTIONS.map((section) => ({
    section,
    settings: settingsFor(section, data),
    canUpdate: canUpdateSection(section, data),
  })).filter((entry) => entry.settings.length > 0);

  const current = sections.find((entry) => entry.section.id === active) ?? sections[0];

  if (!current) return null;

  const panels = panelsFor(current.section, current.settings);

  const dirtyInSection = current.settings.filter((setting) =>
    Object.prototype.hasOwnProperty.call(draft, setting.key),
  ).length;

  return (
    <>
      <AdminPageHeader
        eyebrow="Platform"
        title="Settings"
        description="Everything an admin can change without a deploy. Every save is written to the audit log with the actor and the diff."
        actions={
          <Button size="sm" onClick={() => void save()} loading={saving} disabled={!dirty}>
            <Save className="size-4" aria-hidden="true" />
            {dirty
              ? `Save ${Object.keys(draft).length} ${Object.keys(draft).length === 1 ? "change" : "changes"}`
              : "Saved"}
          </Button>
        }
      />

      <div className="flex flex-col gap-4 lg:flex-row lg:gap-6">
        <nav aria-label="Settings sections" className="shrink-0 lg:w-56">
          {/* A horizontal strip on narrow screens, a rail on wide ones. The same
              list either way, so nothing is reachable from only one of them. */}
          <ul className="scrollbar-slim flex gap-1 overflow-x-auto lg:sticky lg:top-20 lg:flex-col lg:overflow-visible">
            {sections.map(({ section, settings }) => {
              const Icon = section.icon;
              const isActive = section.id === current.section.id;
              const pending = settings.filter((setting) =>
                Object.prototype.hasOwnProperty.call(draft, setting.key),
              ).length;

              return (
                <li key={section.id}>
                  <button
                    type="button"
                    onClick={() => setActive(section.id)}
                    aria-current={isActive ? "page" : undefined}
                    className={cn(
                      "flex w-full items-center gap-2.5 whitespace-nowrap rounded-[var(--radius-md)] px-3 py-2 text-sm transition-colors",
                      isActive
                        ? "bg-[var(--color-primary-subtle)] font-medium text-[var(--color-foreground)]"
                        : "text-[var(--color-foreground-muted)] hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-foreground)]",
                    )}
                  >
                    <Icon
                      className={cn(
                        "size-4 shrink-0",
                        isActive ? "text-[var(--color-primary)]" : "",
                      )}
                      aria-hidden="true"
                    />
                    <span className="flex-1 text-left">{section.label}</span>

                    {pending > 0 && (
                      <span
                        className="tabular rounded-full bg-[var(--color-primary)] px-1.5 text-[0.625rem] font-medium text-[var(--color-primary-foreground)]"
                        aria-label={`${pending} unsaved`}
                      >
                        {pending}
                      </span>
                    )}

                    <ChevronRight
                      className="hidden size-3.5 shrink-0 text-[var(--color-foreground-subtle)] lg:block"
                      aria-hidden="true"
                    />
                  </button>
                </li>
              );
            })}
          </ul>
        </nav>

        <div className="flex min-w-0 flex-1 flex-col gap-4">
          <header className="flex flex-wrap items-start justify-between gap-3">
            <div className="min-w-0">
              <h2 className="text-base font-semibold text-[var(--color-foreground)]">
                {current.section.label}
              </h2>
              <p className="mt-1 max-w-2xl text-xs leading-relaxed text-[var(--color-foreground-subtle)]">
                {current.section.description}
              </p>
            </div>

            {!current.canUpdate ? (
              <StatusPill label="Read only" tone="muted" />
            ) : current.section.id === "scripts" ? (
              <StatusPill label="Runs on every page" tone="warning" />
            ) : null}
          </header>

          {!current.canUpdate && (
            <p className="flex items-start gap-2 rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)] p-3 text-xs leading-relaxed text-[var(--color-foreground-muted)]">
              <Lock className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
              Your role can read these but not change them.{" "}
              {current.section.id === "scripts"
                ? "Editing tracking scripts needs settings.scripts.update — it is separate because a pasted snippet is arbitrary code on every public page."
                : "An administrator can grant the permission on the roles screen."}
            </p>
          )}

          {current.section.id === "scripts" && current.canUpdate && (
            <p className="flex items-start gap-2 rounded-[var(--radius-md)] border border-[var(--color-warning)]/30 bg-[var(--color-warning)]/8 p-3 text-xs leading-relaxed text-[var(--color-foreground-muted)]">
              <ShieldAlert
                className="mt-0.5 size-3.5 shrink-0 text-[var(--color-warning)]"
                aria-hidden="true"
              />
              Anything pasted here runs on every public page. It is never injected into
              the admin or the customer dashboard, it loads after first paint, and it
              waits for consent where consent is required — but it is still code, and
              every change is attributed to you in the audit log.
            </p>
          )}

          {panels.map((panel) => (
            <section key={panel.id} className="app-card overflow-hidden">
              <header className="border-b border-[var(--color-border-subtle)] px-5 py-3.5">
                <h3 className="text-sm font-semibold text-[var(--color-foreground)]">
                  {panel.label}
                </h3>
                {panel.description && (
                  <p className="mt-0.5 max-w-2xl text-xs leading-relaxed text-[var(--color-foreground-subtle)]">
                    {panel.description}
                  </p>
                )}
              </header>

              <div className="flex max-w-2xl flex-col gap-5 px-5 py-5">
                {panel.settings.map((setting) => (
                  <SettingField
                    key={setting.key}
                    setting={setting}
                    value={valueOf(setting)}
                    // A secret has its own permission, whichever section it sits in.
                    disabled={
                      setting.is_secret
                        ? data.permissions["settings.secrets.update"] !== true
                        : !current.canUpdate
                    }
                    dirty={Object.prototype.hasOwnProperty.call(draft, setting.key)}
                    onChange={(value) => set(setting.key, value)}
                  />
                ))}
              </div>
            </section>
          ))}

          {dirtyInSection > 0 && (
            <p className="text-xs text-[var(--color-foreground-subtle)]" role="status">
              {dirtyInSection} unsaved {dirtyInSection === 1 ? "change" : "changes"} in this
              section. Saving writes every pending change, in every section.
            </p>
          )}
        </div>
      </div>
    </>
  );
}

/** The settings a section owns, in the order the API returned them. */
function settingsFor(section: SettingsSection, data: SettingsPayload): SettingItem[] {
  const all = data.groups.flatMap((group) => group.settings);

  if (section.keys) {
    const byKey = new Map(all.map((setting) => [setting.key, setting]));
    const explicit = section.keys
      .map((key) => byKey.get(key))
      .filter((setting): setting is SettingItem => setting !== undefined);

    if (!section.groups) return explicit;

    return [
      ...explicit,
      ...all.filter(
        (setting) =>
          section.groups!.includes(setting.group) && !section.keys!.includes(setting.key),
      ),
    ];
  }

  // A key another section claimed by name is not also listed here — otherwise the
  // blog switch appears twice and turning it off in one place looks like a no-op.
  return all.filter(
    (setting) => section.groups?.includes(setting.group) === true && !CLAIMED.has(setting.key),
  );
}

/**
 * Deal the section's settings out into its cards.
 *
 * Every setting lands somewhere. A key no panel names and no predicate matches goes
 * to the section's catch-all panel, or — if the section declared none — to a final
 * "Other" card. That is deliberate: a setting added server-side must show up in the
 * UI without a matching frontend change, because the alternative is a value an
 * admin has been told exists and cannot find.
 */
function panelsFor(
  section: SettingsSection,
  settings: SettingItem[],
): (SettingsPanel & { settings: SettingItem[] })[] {
  const remaining = new Map(settings.map((setting) => [setting.key, setting]));

  const filled = section.panels.map((panel) => {
    const owned: SettingItem[] = [];

    for (const key of panel.keys ?? []) {
      const setting = remaining.get(key);

      if (setting) {
        owned.push(setting);
        remaining.delete(key);
      }
    }

    if (panel.match) {
      for (const [key, setting] of remaining) {
        if (panel.match(key)) {
          owned.push(setting);
          remaining.delete(key);
        }
      }
    }

    return { ...panel, settings: owned, isCatchAll: !panel.keys && !panel.match };
  });

  // The catch-all takes whatever nothing else claimed, in the order the API sent it.
  const catchAll = filled.find((panel) => panel.isCatchAll);

  if (catchAll) {
    catchAll.settings.push(...remaining.values());
  } else if (remaining.size > 0) {
    filled.push({
      id: "other",
      label: "Other",
      description: "Settings this deployment defines that the console has no card for yet.",
      settings: [...remaining.values()],
      isCatchAll: true,
    });
  }

  return filled.filter((panel) => panel.settings.length > 0);
}

/** Whether the actor can write a section: the strictest of the groups it spans. */
function canUpdateSection(section: SettingsSection, data: SettingsPayload): boolean {
  const owned = settingsFor(section, data);
  const groups = new Set(owned.map((setting) => setting.group));

  return data.groups
    .filter((group) => groups.has(group.group))
    .every((group) => group.can_update);
}

function SettingField({
  setting,
  value,
  disabled,
  dirty,
  onChange,
}: {
  setting: SettingItem;
  value: unknown;
  disabled: boolean;
  dirty: boolean;
  onChange: (value: unknown) => void;
}) {
  const id = `setting-${setting.key.replace(/\./g, "-")}`;
  const label = labelFor(setting.key);
  const highlight = dirty
    ? "-m-2 rounded-[var(--radius-md)] bg-[var(--color-primary-subtle)]/40 p-2"
    : undefined;

  if (setting.type === "bool") {
    return (
      <div className={highlight}>
        <Checkbox
          label={label}
          hint={setting.description ?? undefined}
          checked={value === true}
          disabled={disabled}
          onChange={(event) => onChange(event.target.checked)}
        />
      </div>
    );
  }

  if (setting.is_secret) {
    return (
      <Field
        id={id}
        label={label}
        hint={
          setting.is_set
            ? "A key is stored. Leave this blank to keep it — it is never sent to the browser."
            : "No key stored yet."
        }
        className={highlight}
      >
        {(props) => (
          <div className="flex items-center gap-2">
            <Input
              {...props}
              type="password"
              autoComplete="off"
              value={String(value ?? "")}
              disabled={disabled}
              onChange={(event) => onChange(event.target.value)}
              placeholder={setting.is_set ? "••••••••••••" : "Paste the key"}
              className="font-mono text-xs"
            />
            <StatusPill
              label={setting.is_set ? "Set" : "Not set"}
              tone={setting.is_set ? "success" : "muted"}
            />
          </div>
        )}
      </Field>
    );
  }

  const choices = CHOICES[setting.key];

  if (choices) {
    return (
      <Field id={id} label={label} hint={setting.description ?? undefined} className={highlight}>
        {(props) => (
          <Select
            {...props}
            value={String(value ?? "")}
            disabled={disabled}
            onChange={(event) => onChange(event.target.value)}
          >
            {choices.map((choice) => (
              <option key={choice.value} value={choice.value}>
                {choice.label}
              </option>
            ))}
          </Select>
        )}
      </Field>
    );
  }

  if (setting.type === "int") {
    return (
      <Field id={id} label={label} hint={setting.description ?? undefined} className={highlight}>
        {(props) => (
          <Input
            {...props}
            type="number"
            min={1}
            value={String(value ?? "")}
            disabled={disabled}
            onChange={(event) => onChange(Number(event.target.value))}
          />
        )}
      </Field>
    );
  }

  // Raw HTML fields get a code-shaped textarea; short strings get an input.
  const multiline = setting.key.startsWith("scripts.");

  return (
    <Field id={id} label={label} hint={setting.description ?? undefined} className={highlight}>
      {(props) =>
        multiline ? (
          <Textarea
            {...props}
            value={String(value ?? "")}
            disabled={disabled}
            spellCheck={false}
            onChange={(event) => onChange(event.target.value)}
            placeholder="<!-- nothing injected -->"
            className="min-h-24 font-mono text-xs"
          />
        ) : (
          <Input
            {...props}
            value={String(value ?? "")}
            disabled={disabled}
            onChange={(event) => onChange(event.target.value)}
          />
        )
      }
    </Field>
  );
}

/** `tracking.ga4_id` → "GA4 id". Cheap, and better than showing the raw key. */
function labelFor(key: string): string {
  const leaf = key.includes(".") ? key.slice(key.indexOf(".") + 1) : key;

  return leaf
    .replace(/\./g, " ")
    .replace(/_/g, " ")
    .replace(/\bga4\b/i, "GA4")
    .replace(/\bgtm\b/i, "GTM")
    .replace(/\bog\b/i, "OG")
    .replace(/\bseo\b/i, "SEO")
    .replace(/\bid\b/i, "ID")
    .replace(/\bapi\b/i, "API")
    .replace(/\bpaypal\b/i, "PayPal")
    .replace(/\bstripe\b/i, "Stripe")
    .replace(/\bbraintree\b/i, "Braintree")
    .replace(/^\w/, (character) => character.toUpperCase());
}
