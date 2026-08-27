"use client";

import * as React from "react";

import { FormAlert } from "@/components/auth/form-alert";
import { useSession } from "@/components/auth/session-provider";
import { Button } from "@/components/ui/button";
import { Field, Input } from "@/components/ui/field";
import { accountApi, authApi } from "@/lib/auth-api";
import type { ApiFailure } from "@/lib/http";

/**
 * Change password, including the re-authentication step.
 *
 * The API answers 423 when the last confirmation is stale; rather than surfacing that
 * as an error, the form swaps to a confirm step and retries the save afterwards — the
 * user asked to change their password, not to be told about HTTP status codes.
 */
export function PasswordSettings() {
  const { user } = useSession();

  const [currentPassword, setCurrentPassword] = React.useState("");
  const [password, setPassword] = React.useState("");
  const [confirmation, setConfirmation] = React.useState("");

  const [confirmNeeded, setConfirmNeeded] = React.useState(false);
  const [confirmPassword, setConfirmPassword] = React.useState("");

  const [pending, setPending] = React.useState(false);
  const [saved, setSaved] = React.useState(false);
  const [failure, setFailure] = React.useState<ApiFailure | null>(null);

  const hasPassword = user?.has_password ?? true;

  async function save() {
    const result = await accountApi.changePassword({
      current_password: hasPassword ? currentPassword : undefined,
      password,
      password_confirmation: confirmation,
    });

    if (result.ok) {
      setSaved(true);
      setConfirmNeeded(false);
      setCurrentPassword("");
      setPassword("");
      setConfirmation("");
      setConfirmPassword("");
      return true;
    }

    if (result.error.status === 423) {
      setConfirmNeeded(true);
      return false;
    }

    setFailure(result.error);
    return false;
  }

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault();
    setPending(true);
    setFailure(null);
    setSaved(false);

    await save();
    setPending(false);
  }

  async function onConfirm(event: React.FormEvent) {
    event.preventDefault();
    setPending(true);
    setFailure(null);

    const confirmed = await authApi.confirmPassword(confirmPassword);

    if (!confirmed.ok) {
      setPending(false);
      setFailure(confirmed.error);
      return;
    }

    await save();
    setPending(false);
  }

  if (confirmNeeded) {
    return (
      <form onSubmit={onConfirm} noValidate className="flex flex-col gap-5">
        <FormAlert tone="success">
          For your security, confirm your current password to finish this change.
        </FormAlert>

        {failure && <FormAlert>{failure.message}</FormAlert>}

        <Field
          id="confirm_password"
          label="Current password"
          error={failure?.fieldErrors?.password?.[0]}
          required
        >
          {(props) => (
            <Input
              {...props}
              type="password"
              autoComplete="current-password"
              autoFocus
              value={confirmPassword}
              onChange={(event) => setConfirmPassword(event.target.value)}
            />
          )}
        </Field>

        <div className="flex gap-2">
          <Button type="submit" loading={pending}>
            Confirm and save
          </Button>
          <Button type="button" variant="ghost" onClick={() => setConfirmNeeded(false)}>
            Cancel
          </Button>
        </div>
      </form>
    );
  }

  return (
    <form onSubmit={onSubmit} noValidate className="flex flex-col gap-5">
      {failure && !failure.fieldErrors && <FormAlert>{failure.message}</FormAlert>}
      {saved && <FormAlert tone="success">Password updated. Other sessions were signed out.</FormAlert>}

      {!hasPassword && (
        <p className="text-sm text-[var(--color-foreground-muted)]">
          You sign in with an email link. Setting a password gives you a second way in.
        </p>
      )}

      {hasPassword && (
        <Field
          id="current_password"
          label="Current password"
          error={failure?.fieldErrors?.current_password?.[0]}
          required
        >
          {(props) => (
            <Input
              {...props}
              type="password"
              autoComplete="current-password"
              value={currentPassword}
              onChange={(event) => setCurrentPassword(event.target.value)}
            />
          )}
        </Field>
      )}

      <Field
        id="new_password"
        label={hasPassword ? "New password" : "Password"}
        hint="At least 10 characters."
        error={failure?.fieldErrors?.password?.[0]}
        required
      >
        {(props) => (
          <Input
            {...props}
            type="password"
            autoComplete="new-password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
          />
        )}
      </Field>

      <Field id="new_password_confirmation" label="Confirm password" required>
        {(props) => (
          <Input
            {...props}
            type="password"
            autoComplete="new-password"
            value={confirmation}
            onChange={(event) => setConfirmation(event.target.value)}
          />
        )}
      </Field>

      <div>
        <Button type="submit" loading={pending}>
          {hasPassword ? "Update password" : "Set password"}
        </Button>
      </div>
    </form>
  );
}
