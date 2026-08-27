"use client";

import { Trash2, Upload } from "lucide-react";
import * as React from "react";

import { FormAlert } from "@/components/auth/form-alert";
import { useSession } from "@/components/auth/session-provider";
import { Button } from "@/components/ui/button";
import { Checkbox, Field, Input, Select } from "@/components/ui/field";
import { accountApi } from "@/lib/auth-api";
import type { ApiFailure } from "@/lib/http";

/**
 * Name, avatar, timezone and marketing opt-in.
 *
 * Email is displayed but not editable — it is the account identity (docs/06), and
 * saying so plainly here is better than a disabled input with no explanation.
 */
export function ProfileSettings() {
  const { user, setUser } = useSession();

  const [displayName, setDisplayName] = React.useState(user?.display_name ?? "");
  const [timezone, setTimezone] = React.useState(user?.timezone ?? "UTC");
  const [marketingOptIn, setMarketingOptIn] = React.useState(user?.marketing_opt_in ?? false);

  const [pending, setPending] = React.useState(false);
  const [saved, setSaved] = React.useState(false);
  const [failure, setFailure] = React.useState<ApiFailure | null>(null);
  const [avatarPending, setAvatarPending] = React.useState(false);

  const fileInput = React.useRef<HTMLInputElement>(null);

  const timezones = React.useMemo(() => {
    // Intl.supportedValuesOf is not in every engine we might be rendered by; the
    // fallback keeps the control usable rather than empty.
    try {
      return Intl.supportedValuesOf("timeZone");
    } catch {
      return [user?.timezone ?? "UTC", "UTC"];
    }
  }, [user?.timezone]);

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault();
    setPending(true);
    setFailure(null);
    setSaved(false);

    const result = await accountApi.updateProfile({
      display_name: displayName,
      timezone,
      marketing_opt_in: marketingOptIn,
    });

    setPending(false);

    if (!result.ok) {
      setFailure(result.error);
      return;
    }

    setUser(result.data);
    setSaved(true);
  }

  async function onAvatarChange(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    if (!file) return;

    setAvatarPending(true);
    setFailure(null);

    const result = await accountApi.uploadAvatar(file);
    setAvatarPending(false);

    // Reset the input so re-picking the same file still fires a change event.
    if (fileInput.current) fileInput.current.value = "";

    if (result.ok) {
      setUser(result.data);
    } else {
      setFailure(result.error);
    }
  }

  async function removeAvatar() {
    setAvatarPending(true);
    const result = await accountApi.removeAvatar();
    setAvatarPending(false);

    if (result.ok) setUser(result.data);
  }

  if (!user) return null;

  return (
    <form onSubmit={onSubmit} noValidate className="flex flex-col gap-6">
      {failure && !failure.fieldErrors && <FormAlert>{failure.message}</FormAlert>}
      {saved && <FormAlert tone="success">Profile saved.</FormAlert>}

      <div className="flex items-center gap-4">
        <div className="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-full border border-[var(--color-border)] bg-[var(--color-surface-raised)] text-lg font-semibold">
          {user.avatar_url ? (
            /* Same reasoning as the header avatar: configurable host, tiny render size. */
            // eslint-disable-next-line @next/next/no-img-element
            <img src={user.avatar_url} alt="" className="size-full object-cover" />
          ) : (
            user.initials
          )}
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <input
            ref={fileInput}
            id="avatar"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            className="sr-only"
            onChange={onAvatarChange}
          />

          <Button
            type="button"
            variant="secondary"
            size="sm"
            loading={avatarPending}
            onClick={() => fileInput.current?.click()}
          >
            <Upload className="size-4" aria-hidden="true" />
            {user.avatar_url ? "Replace photo" : "Upload photo"}
          </Button>

          {user.avatar_url && (
            <Button type="button" variant="ghost" size="sm" onClick={removeAvatar}>
              <Trash2 className="size-4" aria-hidden="true" />
              Remove
            </Button>
          )}

          {failure?.fieldErrors?.avatar?.[0] && (
            <p role="alert" className="w-full text-xs font-medium text-[var(--color-danger)]">
              {failure.fieldErrors.avatar[0]}
            </p>
          )}
        </div>
      </div>

      <Field
        id="display_name"
        label="Display name"
        error={failure?.fieldErrors?.display_name?.[0]}
      >
        {(props) => (
          <Input
            {...props}
            value={displayName}
            onChange={(event) => setDisplayName(event.target.value)}
          />
        )}
      </Field>

      <Field
        id="email"
        label="Email"
        hint="Your email is your account identity and can't be changed. Contact support to transfer an account."
      >
        {(props) => <Input {...props} value={user.email} readOnly disabled />}
      </Field>

      <Field
        id="timezone"
        label="Timezone"
        hint="Used for quota resets and the dates you see."
        error={failure?.fieldErrors?.timezone?.[0]}
      >
        {(props) => (
          <Select
            {...props}
            value={timezone}
            onChange={(event) => setTimezone(event.target.value)}
          >
            {timezones.map((zone) => (
              <option key={zone} value={zone}>
                {zone}
              </option>
            ))}
          </Select>
        )}
      </Field>

      <Checkbox
        label="Send me product updates"
        hint="New tools and occasional tips. Unsubscribe any time."
        checked={marketingOptIn}
        onChange={(event) => setMarketingOptIn(event.target.checked)}
      />

      <div>
        <Button type="submit" loading={pending}>
          Save changes
        </Button>
      </div>
    </form>
  );
}
