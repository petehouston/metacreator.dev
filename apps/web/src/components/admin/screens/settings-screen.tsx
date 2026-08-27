"use client";

import { Lock, Save, ShieldAlert } from "lucide-react";
import * as React from "react";

import { AdminPageHeader, AdminPanel, StatusPill } from "@/components/admin/admin-page";
import { useToast } from "@/components/admin/feedback";
import { LoadError } from "@/components/admin/load-error";
import { Button } from "@/components/ui/button";
import { Checkbox, Field, Input, Textarea } from "@/components/ui/field";
import { adminApi } from "@/lib/admin/api";
import type { SettingGroup, SettingItem } from "@/lib/admin/types";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import { cn } from "@/lib/utils";

const GROUP_LABELS: Record<string, { title: string; description: string }> = {
  branding: {
    title: "Branding",
    description: "The name, the promise, and where support mail goes.",
  },
  features: {
    title: "Feature flags",
    description: "Turn whole areas of the product on and off without a deploy.",
  },
  scripts: {
    title: "Tracking & scripts",
    description:
      "Third-party tags and raw HTML. This is arbitrary code on every public page, so it has its own permission.",
  },
  newsletter: {
    title: "Newsletter",
    description: "Which provider the list syncs to, and the credentials for it.",
  },
  seo: {
    title: "SEO defaults",
    description: "Title templates, the fallback share image, and search-console verification.",
  },
};

const GROUP_ORDER = ["branding", "features", "seo", "scripts", "newsletter"];

/**
 * Site configuration.
 *
 * Three permissions guard one table and the screen makes the split visible rather
 * than silently refusing a save: `settings.update` for ordinary values,
 * `settings.scripts.update` because raw HTML in `<head>` is code execution on every
 * page, and `settings.secrets.update` because provider keys are credentials. A
 * group the actor cannot write renders read-only with the reason attached.
 *
 * Secrets are never sent to the browser — only whether one is set. A blank secret
 * field means "leave it alone", which is why saving this form does not wipe an API
 * key it was never shown.
 */
export function SettingsScreen() {
  const { notify, reportError } = useToast();

  const { data, error, loading, reload } = useAdminResource(() => adminApi.settings.get(), []);

  const [draft, setDraft] = React.useState<Record<string, unknown>>({});
  const [saving, setSaving] = React.useState(false);

  const dirty = Object.keys(draft).length > 0;

  if (error) return <LoadError error={error} onRetry={reload} />;

  if (loading && !data) {
    return (
      <div className="flex flex-col gap-4">
        {[0, 1, 2].map((panel) => (
          <div
            key={panel}
            className="h-56 animate-pulse rounded-[var(--radius-lg)] bg-[var(--color-surface-sunken)]"
            aria-hidden="true"
          />
        ))}
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

  const groups = [...data.groups].sort(
    (a, b) => GROUP_ORDER.indexOf(a.group) - GROUP_ORDER.indexOf(b.group),
  );

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

      <div className="flex max-w-3xl flex-col gap-4">
        {groups.map((group) => (
          <SettingsGroup
            key={group.group}
            group={group}
            valueOf={valueOf}
            onChange={set}
            dirtyKeys={Object.keys(draft)}
          />
        ))}
      </div>
    </>
  );
}

function SettingsGroup({
  group,
  valueOf,
  onChange,
  dirtyKeys,
}: {
  group: SettingGroup;
  valueOf: (setting: SettingItem) => unknown;
  onChange: (key: string, value: unknown) => void;
  dirtyKeys: string[];
}) {
  const meta = GROUP_LABELS[group.group] ?? {
    title: group.group,
    description: "",
  };

  const isScripts = group.group === "scripts";

  return (
    <AdminPanel
      title={meta.title}
      description={meta.description}
      action={
        !group.can_update ? (
          <StatusPill label="Read only" tone="muted" />
        ) : isScripts ? (
          <StatusPill label="Runs on every page" tone="warning" />
        ) : null
      }
    >
      {!group.can_update && (
        <p className="mb-4 flex items-start gap-2 rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)] p-3 text-xs leading-relaxed text-[var(--color-foreground-muted)]">
          <Lock className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
          Your role can read these but not change them.{" "}
          {isScripts
            ? "Editing tracking scripts needs settings.scripts.update — it is separate because a pasted snippet is arbitrary code on every public page."
            : "An administrator can grant the permission on the roles screen."}
        </p>
      )}

      {isScripts && group.can_update && (
        <p className="mb-4 flex items-start gap-2 rounded-[var(--radius-md)] border border-[var(--color-warning)]/30 bg-[var(--color-warning)]/8 p-3 text-xs leading-relaxed text-[var(--color-foreground-muted)]">
          <ShieldAlert className="mt-0.5 size-3.5 shrink-0 text-[var(--color-warning)]" aria-hidden="true" />
          Anything pasted here runs on every public page. It is never injected into
          the admin or the customer dashboard, it loads after first paint, and it
          waits for consent where consent is required — but it is still code, and
          every change is attributed to you in the audit log.
        </p>
      )}

      <div className="flex flex-col gap-4">
        {group.settings.map((setting) => (
          <SettingField
            key={setting.key}
            setting={setting}
            value={valueOf(setting)}
            disabled={!group.can_update}
            dirty={dirtyKeys.includes(setting.key)}
            onChange={(value) => onChange(setting.key, value)}
          />
        ))}
      </div>
    </AdminPanel>
  );
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

  if (setting.type === "bool") {
    return (
      <div className={cn(dirty && "rounded-[var(--radius-md)] bg-[var(--color-primary-subtle)]/40 p-2 -m-2")}>
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
        className={cn(dirty && "rounded-[var(--radius-md)] bg-[var(--color-primary-subtle)]/40 p-2 -m-2")}
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

  // Raw HTML fields get a code-shaped textarea; short strings get an input.
  const multiline = setting.key.startsWith("scripts.");

  return (
    <Field
      id={id}
      label={label}
      hint={setting.description ?? undefined}
      className={cn(dirty && "rounded-[var(--radius-md)] bg-[var(--color-primary-subtle)]/40 p-2 -m-2")}
    >
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
    .replace(/_/g, " ")
    .replace(/\bga4\b/i, "GA4")
    .replace(/\bgtm\b/i, "GTM")
    .replace(/\bog\b/i, "OG")
    .replace(/\bseo\b/i, "SEO")
    .replace(/\bid\b/i, "ID")
    .replace(/\bapi\b/i, "API")
    .replace(/^\w/, (character) => character.toUpperCase());
}
