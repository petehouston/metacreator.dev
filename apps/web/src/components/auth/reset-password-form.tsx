"use client";

import { useRouter, useSearchParams } from "next/navigation";
import * as React from "react";

import { FormAlert } from "@/components/auth/form-alert";
import { Button } from "@/components/ui/button";
import { Field, Input } from "@/components/ui/field";
import { authApi } from "@/lib/auth-api";
import type { ApiFailure } from "@/lib/http";

export function ResetPasswordForm() {
  const router = useRouter();
  const searchParams = useSearchParams();

  const token = searchParams.get("token") ?? "";
  const email = searchParams.get("email") ?? "";

  const [password, setPassword] = React.useState("");
  const [confirmation, setConfirmation] = React.useState("");
  const [pending, setPending] = React.useState(false);
  const [failure, setFailure] = React.useState<ApiFailure | null>(null);
  const [done, setDone] = React.useState(false);

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault();
    setPending(true);
    setFailure(null);

    const result = await authApi.resetPassword({
      token,
      email,
      password,
      password_confirmation: confirmation,
    });

    setPending(false);

    if (!result.ok) {
      setFailure(result.error);
      return;
    }

    setDone(true);
    // A beat so the confirmation is actually read before the redirect.
    setTimeout(() => router.push("/login"), 1500);
  }

  if (!token || !email) {
    return (
      <FormAlert>
        That reset link looks incomplete. Request a new one from the forgot-password page.
      </FormAlert>
    );
  }

  if (done) {
    return <FormAlert tone="success">Password updated. Taking you to sign in…</FormAlert>;
  }

  return (
    <form onSubmit={onSubmit} noValidate className="flex flex-col gap-5">
      {failure && !failure.fieldErrors && <FormAlert>{failure.message}</FormAlert>}
      {failure?.fieldErrors?.email?.[0] && <FormAlert>{failure.fieldErrors.email[0]}</FormAlert>}

      <p className="text-sm text-[var(--color-foreground-muted)]">
        Setting a new password for <span className="font-medium">{email}</span>.
      </p>

      <Field
        id="password"
        label="New password"
        hint="At least 10 characters."
        error={failure?.fieldErrors?.password?.[0]}
        required
      >
        {(props) => (
          <Input
            {...props}
            type="password"
            autoComplete="new-password"
            autoFocus
            value={password}
            onChange={(event) => setPassword(event.target.value)}
          />
        )}
      </Field>

      <Field id="password_confirmation" label="Confirm new password" required>
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

      <Button type="submit" size="lg" loading={pending}>
        Set new password
      </Button>
    </form>
  );
}
