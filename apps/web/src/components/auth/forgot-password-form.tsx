"use client";

import * as React from "react";

import { FormAlert } from "@/components/auth/form-alert";
import { Button } from "@/components/ui/button";
import { Field, Input } from "@/components/ui/field";
import { authApi } from "@/lib/auth-api";
import type { ApiFailure } from "@/lib/http";

export function ForgotPasswordForm() {
  const [email, setEmail] = React.useState("");
  const [pending, setPending] = React.useState(false);
  const [sent, setSent] = React.useState(false);
  const [failure, setFailure] = React.useState<ApiFailure | null>(null);

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault();
    setPending(true);
    setFailure(null);

    const result = await authApi.forgotPassword(email);
    setPending(false);

    if (result.ok) {
      setSent(true);
    } else {
      setFailure(result.error);
    }
  }

  if (sent) {
    return (
      // Worded so it is true whether or not the address has an account — the API
      // deliberately does not say, and this screen must not say either.
      <FormAlert tone="success">
        If <span className="font-medium">{email}</span> has an account, a reset link is on its
        way. It expires in 60 minutes.
      </FormAlert>
    );
  }

  return (
    <form onSubmit={onSubmit} noValidate className="flex flex-col gap-5">
      {failure && <FormAlert>{failure.message}</FormAlert>}

      <Field id="email" label="Email" error={failure?.fieldErrors?.email?.[0]} required>
        {(props) => (
          <Input
            {...props}
            type="email"
            name="email"
            autoComplete="email"
            autoFocus
            placeholder="you@example.com"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
          />
        )}
      </Field>

      <Button type="submit" size="lg" loading={pending}>
        Send reset link
      </Button>
    </form>
  );
}
