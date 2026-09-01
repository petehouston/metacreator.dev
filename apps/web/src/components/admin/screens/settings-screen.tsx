"use client";

import {
  ChevronRight,
  CreditCard,
  Eye,
  EyeOff,
  FileText,
  History,
  Lock,
  Mail,
  Plug,
  Send,
  SendHorizonal,
  Save,
  Search,
  ShieldAlert,
  Store,
  UserPlus,
  Wrench,
  type LucideIcon,
} from "lucide-react";
import * as React from "react";

import { AdminPageHeader, StatusPill } from "@/components/admin/admin-page";
import { useToast } from "@/components/admin/feedback";
import { LoadError } from "@/components/admin/load-error";
import { Button } from "@/components/ui/button";
import { CopyButton } from "@/components/ui/copy-button";
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
    description: "The name, the promise, and the one address the site publishes.",
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
    id: "changelog",
    label: "Changelog",
    icon: History,
    description:
      "The public release history. It is a whole surface like the blog, so it gets the same one switch — and nothing else to configure.",
    keys: ["features.changelog_enabled"],
    panels: [
      {
        id: "availability",
        label: "Availability",
        description:
          "Off, every changelog route 404s, the footer link disappears and the sitemap entries are dropped. Releases are not deleted, and they stay editable under Changelog in the sidebar.",
        keys: ["features.changelog_enabled"],
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
    id: "tools",
    label: "Tools",
    icon: Wrench,
    description:
      "How much of the catalog each kind of visitor gets, and what counts as trending. Every window below is enforced at once and the first one to run out is the one that walls, so a tier can have a generous day and a hard month. These are the pricing model rather than a technical detail, so they are settings — raising the free allowance for a launch weekend should not need a deploy.",
    groups: ["tools"],
    panels: [
      {
        id: "limits-daily",
        label: "Daily run limits",
        description:
          "Runs per day, per access tier. Anonymous visitors are counted per IP; everyone else per account. Use -1 to leave the day uncounted. Zero closes a tier entirely — an exhausted visitor is told to move up a tier or wait for the reset, and a tier set to zero says so rather than promising a reset that changes nothing.",
        keys: [
          "tools.limits.free.daily",
          "tools.limits.account.daily",
          "tools.limits.premium.daily",
        ],
      },
      {
        id: "limits-weekly",
        label: "Weekly run limits",
        description:
          "The same three tiers, counted over an ISO week that rolls over on Monday. Off by default (-1). A week is the useful middle ground: it survives someone saving their whole backlog for Sunday, which a daily cap only pushes into the next day.",
        keys: [
          "tools.limits.free.weekly",
          "tools.limits.account.weekly",
          "tools.limits.premium.weekly",
        ],
      },
      {
        id: "limits-monthly",
        label: "Monthly run limits",
        description:
          "Counted over the calendar month. This is the cost ceiling: it is the only window that lines up with what a metered provider actually bills, and it is the honest way to keep an “unlimited” plan from being one abusive account. Off by default (-1).",
        keys: [
          "tools.limits.free.monthly",
          "tools.limits.account.monthly",
          "tools.limits.premium.monthly",
        ],
      },
      {
        id: "trending",
        label: "Trending",
        description:
          "The window behind the catalog's Trending sort. Shorter reacts faster and is noisier; longer is steadier and converges on Popular. The minimum stops a single run on a quiet day from topping the list.",
        keys: ["tools.trending_days", "tools.trending_min_runs"],
      },
    ],
  },
  {
    id: "payments",
    label: "Payments",
    icon: CreditCard,
    description:
      "Whether the product sells anything at all, which gateway takes the money, and the credentials for it. One provider is live at a time; the others keep their keys, so switching back is a dropdown rather than a re-onboarding.",
    groups: ["payments"],
    keys: ["features.billing_enabled"],
    panels: [
      {
        id: "general",
        label: "General",
        description:
          "Billing enabled is the master switch: off, the product has no paid plans at all — pricing and billing pages 404, upgrade prompts disappear everywhere, and every Pro tool is gated at Account Required instead. Nothing is written to the tools table, so switching it back on restores the paywall exactly as it was. Payments enabled is narrower — it opens and closes checkout while the plans stay on show. Plans are defined under Billing; each carries the price identifier the chosen provider knows it by.",
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
    id: "mail",
    label: "Email",
    icon: SendHorizonal,
    description:
      "How transactional email leaves the building — password resets, receipts, ticket replies. This is the one integration whose failure is invisible from outside: the site looks fine while nobody can reset a password, which is why there is a delivery check at the top rather than a save button and hope.",
    groups: ["mail"],
    panels: [
      {
        id: "sender",
        label: "Sender",
        description:
          "Who the mail appears to come from. The From address has to sit on a domain whose SPF and DKIM records name the provider below — get that wrong and every message sends successfully and lands in spam, which looks identical to working from in here.",
        keys: ["mail.from_address", "mail.from_name", "mail.reply_to_address"],
      },
      {
        id: "provider",
        label: "Provider",
        description:
          "Which transport carries the mail. One is live at a time; the others keep their credentials, so switching back is a dropdown rather than a re-onboarding. Every field below is an override — left blank, the deployment's own MAIL_* environment keeps working.",
        keys: ["mail.provider"],
      },
      {
        id: "smtp",
        label: "SMTP",
        description:
          "Used when the provider above is SMTP. Works with anything that speaks it, including a provider's own relay.",
        match: prefixed("mail.smtp."),
      },
      {
        id: "mailgun",
        label: "Mailgun",
        description: "Used when the provider above is Mailgun.",
        match: prefixed("mail.mailgun."),
      },
      {
        id: "postmark",
        label: "Postmark",
        description: "Used when the provider above is Postmark.",
        match: prefixed("mail.postmark."),
      },
      {
        id: "resend",
        label: "Resend",
        description: "Used when the provider above is Resend.",
        match: prefixed("mail.resend."),
      },
      {
        id: "ses",
        label: "Amazon SES",
        description:
          "Used when the provider above is SES. The sending identity has to be verified in the region named here — an identity verified in one region does not exist in another.",
        match: prefixed("mail.ses."),
      },
      {
        id: "klaviyo",
        label: "Klaviyo",
        description:
          "Used when the provider above is Klaviyo. Klaviyo has no endpoint that sends a rendered message: this posts an event on the metric below carrying the subject and body, and a flow you build in Klaviyo does the actual sending. Until that flow exists every send succeeds and delivers nothing, and attachments cannot travel this way at all.",
        match: prefixed("mail.klaviyo."),
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
  {
    id: "integrations",
    label: "Integrations",
    icon: Plug,
    description:
      "Keys for the third-party APIs the tools call. A tool that needs a key it does not have says so plainly rather than failing as though the site were broken, so leaving one blank disables that tool and nothing else.",
    groups: ["providers"],
    panels: [
      {
        id: "youtube",
        label: "YouTube Data API",
        description:
          "A Data API v3 key from the Google Cloud console, on a project with the YouTube Data API enabled. Only the Comment Finder needs it today; every other YouTube tool works without one. The daily quota belongs to that project, and the key is shared by every visitor using the tool.",
        match: prefixed("providers.youtube."),
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
  "mail.provider": [
    { value: "smtp", label: "SMTP" },
    { value: "mailgun", label: "Mailgun" },
    { value: "postmark", label: "Postmark" },
    { value: "resend", label: "Resend" },
    { value: "ses", label: "Amazon SES" },
    { value: "klaviyo", label: "Klaviyo — delivered by a flow you build" },
    { value: "sendmail", label: "Sendmail — the local binary" },
    { value: "log", label: "Log only — nothing is delivered" },
  ],
  "mail.smtp.scheme": [
    { value: "auto", label: "Automatic — inferred from the port" },
    { value: "smtp", label: "STARTTLS (usually port 587)" },
    { value: "smtps", label: "Implicit TLS (usually port 465)" },
  ],
  "mail.mailgun.endpoint": [
    { value: "api.mailgun.net", label: "US — api.mailgun.net" },
    { value: "api.eu.mailgun.net", label: "EU — api.eu.mailgun.net" },
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
        count === 0
          ? "Nothing changed."
          : `${count} ${count === 1 ? "setting" : "settings"} saved.`,
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
              Anything pasted here runs on every public page. It is never injected into the admin or
              the customer dashboard, it loads after first paint, and it waits for consent where
              consent is required — but it is still code, and every change is attributed to you in
              the audit log.
            </p>
          )}

          {current.section.id === "mail" && (
            <MailDeliveryCheck hasUnsavedChanges={dirtyInSection > 0} canSend={current.canUpdate} />
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

    return {
      ...panel,
      settings: owned,
      isCatchAll: !panel.keys && !panel.match,
    };
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

  return data.groups.filter((group) => groups.has(group.group)).every((group) => group.can_update);
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
      <SecretField
        setting={setting}
        value={value}
        disabled={disabled}
        highlight={highlight}
        onChange={onChange}
        id={id}
        label={label}
      />
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
    // Run limits are the one place a negative is meaningful: -1 leaves that window
    // uncounted, and 0 closes the tier. Everywhere else a count below one is a mistake.
    const min = setting.key.startsWith("tools.limits.") ? -1 : 1;

    return (
      <Field id={id} label={label} hint={setting.description ?? undefined} className={highlight}>
        {(props) => (
          <Input
            {...props}
            type="number"
            min={min}
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

/**
 * A credential field.
 *
 * The reveal and copy controls act on what is *in the box*, which is only ever a
 * key the admin just typed or pasted: the stored value is never sent to the
 * browser, so there is nothing to unmask when the box is empty. Both controls are
 * disabled in that state rather than silently revealing nothing or copying an
 * empty string over whatever was on the clipboard — the "Set" pill beside them is
 * the honest answer to "is a key configured?".
 *
 * Reveal exists because a pasted key is otherwise unverifiable — a truncated paste
 * and a good one look identical as dots — and it starts hidden every render, so a
 * key is never left on screen by a save or a tab away.
 */
function SecretField({
  setting,
  id,
  label,
  value,
  disabled,
  highlight,
  onChange,
}: {
  setting: SettingItem;
  id: string;
  label: string;
  value: unknown;
  disabled: boolean;
  highlight?: string;
  onChange: (value: unknown) => void;
}) {
  const [revealed, setRevealed] = React.useState(false);
  const typed = String(value ?? "");
  const empty = typed === "";

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
            type={revealed ? "text" : "password"}
            autoComplete="off"
            spellCheck={false}
            value={typed}
            disabled={disabled}
            onChange={(event) => onChange(event.target.value)}
            placeholder={setting.is_set ? "••••••••••••" : "Paste the key"}
            className="font-mono text-xs"
          />

          <Button
            type="button"
            variant="ghost"
            size="sm"
            disabled={disabled || empty}
            aria-pressed={revealed}
            aria-controls={id}
            onClick={() => setRevealed((current) => !current)}
            title={empty ? "Nothing to show — the stored key is never sent here" : undefined}
          >
            {revealed ? <EyeOff /> : <Eye />}
            <span className="sr-only">{revealed ? `Hide ${label}` : `Show ${label}`}</span>
          </Button>

          <CopyButton
            value={typed}
            iconOnly
            label={`Copy ${label}`}
            copiedLabel={`${label} copied`}
            disabled={disabled || empty}
            title={empty ? "Nothing to copy — the stored key is never sent here" : undefined}
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

/**
 * The delivery check that sits above the mail fields.
 *
 * Saving mail settings proves nothing. Every other section on this screen changes
 * something an admin can immediately see — a flag flips, a template renders — but a
 * wrong SMTP password looks exactly like a right one until a customer cannot reset
 * their password, and the failure surfaces days later as a support ticket. So this
 * card does two things a save cannot: it says what the *stored* settings add up to,
 * naming the keys still empty rather than reporting a bare "not configured", and it
 * pushes a real message through the provider and reports the provider's own error
 * verbatim. "Domain not found" and "invalid API key" need different fixes, and a
 * paraphrase loses which one you have.
 *
 * It reads saved state, never the draft, which is why an unsaved change here is
 * called out rather than quietly tested around: a test that silently used the
 * pending values would be a green tick for a configuration nobody is running.
 */
function MailDeliveryCheck({
  hasUnsavedChanges,
  canSend,
}: {
  hasUnsavedChanges: boolean;
  canSend: boolean;
}) {
  const { data: status, loading, reload } = useAdminResource(() => adminApi.settings.mail.status(), []);

  const [recipient, setRecipient] = React.useState("");
  const [sending, setSending] = React.useState(false);
  // Narrower than the endpoint's payload on purpose: the card only reports the
  // outcome of the send, and the status beside it is re-read from the server rather
  // than taken from the response, so the two cannot disagree.
  const [result, setResult] = React.useState<{
    sent: boolean;
    recipient?: string;
    error?: string;
  } | null>(null);

  async function send() {
    setSending(true);
    setResult(null);

    const response = await adminApi.settings.mail.test(recipient.trim() || undefined);

    setSending(false);

    if (response.ok) {
      setResult(response.data);
      reload();
    } else {
      // A provider rejecting the message comes back as a 200 with `sent: false`;
      // reaching here means the request itself failed, which is a different problem
      // and deserves the wording the API gave it.
      setResult({ sent: false, error: response.error.message });
    }
  }

  return (
    <section className="app-card overflow-hidden">
      <header className="flex flex-wrap items-start justify-between gap-3 border-b border-[var(--color-border-subtle)] px-5 py-3.5">
        <div className="min-w-0">
          <h3 className="text-sm font-semibold text-[var(--color-foreground)]">Delivery check</h3>
          <p className="mt-0.5 max-w-2xl text-xs leading-relaxed text-[var(--color-foreground-subtle)]">
            What the saved settings add up to, and a real message through the configured provider.
          </p>
        </div>

        {status && (
          <StatusPill
            label={status.configured ? `${status.provider_label} ready` : "Incomplete"}
            tone={status.configured ? "success" : "warning"}
          />
        )}
      </header>

      <div className="flex flex-col gap-4 px-5 py-5">
        {loading && !status ? (
          <div
            className="h-16 animate-pulse rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)]"
            aria-hidden="true"
          />
        ) : status ? (
          <>
            <dl className="grid gap-3 text-xs sm:grid-cols-2">
              <div>
                <dt className="text-[var(--color-foreground-subtle)]">Provider</dt>
                <dd className="mt-0.5 font-medium text-[var(--color-foreground)]">
                  {status.provider_label}
                </dd>
              </div>
              <div>
                <dt className="text-[var(--color-foreground-subtle)]">From</dt>
                <dd className="mt-0.5 font-medium text-[var(--color-foreground)]">
                  {status.from_address || "Not set — the deployment's own MAIL_FROM_ADDRESS is used"}
                </dd>
              </div>
            </dl>

            {status.missing.length > 0 && (
              <p className="rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)] p-3 text-xs leading-relaxed text-[var(--color-foreground-muted)]">
                Still empty for this provider:{" "}
                <span className="font-mono">{status.missing.join(", ")}</span>. Until they are
                filled in, the deployment keeps using whatever its environment configured.
              </p>
            )}

            {status.delivers_via_flow && (
              <p className="flex items-start gap-2 rounded-[var(--radius-md)] border border-[var(--color-warning)]/30 bg-[var(--color-warning)]/8 p-3 text-xs leading-relaxed text-[var(--color-foreground-muted)]">
                <ShieldAlert
                  className="mt-0.5 size-3.5 shrink-0 text-[var(--color-warning)]"
                  aria-hidden="true"
                />
                Klaviyo does not send the message itself — it receives an event and a flow you
                build there sends the email. A green tick above means the API key works, not that
                anything is delivered. Build the flow on the metric below before you rely on this
                for password resets.
              </p>
            )}
          </>
        ) : null}

        {hasUnsavedChanges && (
          <p className="text-xs leading-relaxed text-[var(--color-foreground-muted)]" role="status">
            You have unsaved changes in this section. The test uses the <em>saved</em> settings —
            save first, or you will be testing the configuration you are replacing.
          </p>
        )}

        <div className="flex flex-wrap items-end gap-2">
          <Field
            id="mail-test-recipient"
            label="Send a test to"
            hint="Leave blank to send to your own address."
            className="min-w-56 flex-1"
          >
            {(props) => (
              <Input
                {...props}
                type="email"
                autoComplete="off"
                value={recipient}
                disabled={!canSend}
                placeholder="you@example.com"
                onChange={(event) => setRecipient(event.target.value)}
              />
            )}
          </Field>

          <Button
            type="button"
            variant="secondary"
            onClick={() => void send()}
            loading={sending}
            disabled={!canSend}
          >
            <Send className="size-4" aria-hidden="true" />
            Send test
          </Button>
        </div>

        {result && (
          <p
            role="status"
            className={cn(
              "rounded-[var(--radius-md)] p-3 text-xs leading-relaxed",
              result.sent
                ? "bg-[var(--color-success)]/10 text-[var(--color-foreground)]"
                : "bg-[var(--color-danger)]/10 text-[var(--color-foreground)]",
            )}
          >
            {result.sent ? (
              <>
                Sent to <span className="font-medium">{result.recipient}</span>. If it does not
                arrive within a minute, check the spam folder — landing there is a DNS problem
                (SPF, DKIM, DMARC on the From domain), not a credentials problem.
              </>
            ) : (
              <>
                Not sent. <span className="font-mono">{result.error}</span>
              </>
            )}
          </p>
        )}
      </div>
    </section>
  );
}

/**
 * Keys whose derived label would be wrong or unhelpful.
 *
 * `tools.limits.free.daily` would read as "Limits free daily", which is not what
 * the field is: it is the allowance for one *kind of visitor*, and naming the
 * visitor is the whole point of the row. The window is already the panel's title,
 * so repeating it on every row would only add noise.
 */
const LABEL_OVERRIDES: Record<string, string> = {
  // The mail panels are titled by provider, so repeating "SMTP" or "Mailgun" on
  // every row inside them would only add noise.
  "mail.reply_to_address": "Reply-to address",
  "mail.smtp.host": "Host",
  "mail.smtp.port": "Port",
  "mail.smtp.scheme": "Encryption",
  "mail.smtp.username": "Username",
  "mail.smtp.password": "Password",
  "mail.mailgun.domain": "Sending domain",
  "mail.mailgun.secret": "API key",
  "mail.mailgun.endpoint": "Region",
  "mail.postmark.token": "Server API token",
  "mail.postmark.message_stream": "Message stream",
  "mail.resend.key": "API key",
  "mail.ses.key": "Access key ID",
  "mail.ses.secret": "Secret access key",
  "mail.ses.region": "Region",
  "mail.klaviyo.api_key": "Private API key",
  "mail.klaviyo.metric": "Metric name",
  // Historically a support address; it is now the only one the site publishes,
  // and calling it "Support email" hides that the legal pages point at it too.
  "site.support_email": "Contact email",
  "tools.limits.free.daily": "Anonymous visitor (per IP)",
  "tools.limits.account.daily": "Signed-in, no paid plan",
  "tools.limits.premium.daily": "Subscriber or pass holder",
  "tools.limits.free.weekly": "Anonymous visitor (per IP)",
  "tools.limits.account.weekly": "Signed-in, no paid plan",
  "tools.limits.premium.weekly": "Subscriber or pass holder",
  "tools.limits.free.monthly": "Anonymous visitor (per IP)",
  "tools.limits.account.monthly": "Signed-in, no paid plan",
  "tools.limits.premium.monthly": "Subscriber or pass holder",
  "tools.trending_days": "Look back this many days",
  "tools.trending_min_runs": "Minimum runs to qualify",
};

/** `tracking.ga4_id` → "GA4 id". Cheap, and better than showing the raw key. */
function labelFor(key: string): string {
  const override = LABEL_OVERRIDES[key];
  if (override !== undefined) return override;

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
    .replace(/\byoutube\b/i, "YouTube")
    .replace(/\bpaypal\b/i, "PayPal")
    .replace(/\bstripe\b/i, "Stripe")
    .replace(/\bbraintree\b/i, "Braintree")
    .replace(/^\w/, (character) => character.toUpperCase());
}
