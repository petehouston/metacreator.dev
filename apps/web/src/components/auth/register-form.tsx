"use client";

import { useRouter, useSearchParams } from "next/navigation";
import * as React from "react";

import { FormAlert } from "@/components/auth/form-alert";
import { useSession } from "@/components/auth/session-provider";
import { Button } from "@/components/ui/button";
import { Checkbox, Field, Input } from "@/components/ui/field";
import { authApi } from "@/lib/auth-api";
import type { ApiFailure } from "@/lib/http";

export function RegisterForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { setUser } = useSession();

  const [email, setEmail] = React.useState("");
  const [password, setPassword] = React.useState("");
  const [displayName, setDisplayName] = React.useState("");
  const [marketingOptIn, setMarketingOptIn] = React.useState(false);
  const [pending, setPending] = React.useState(false);
  const [failure, setFailure] = React.useState<ApiFailure | null>(null);

  const rawNext = searchParams.get("next");
  const next = rawNext && rawNext.startsWith("/") && !rawNext.startsWith("//") ? rawNext : "/dashboard";

  const fieldError = (name: string) => failure?.fieldErrors?.[name]?.[0];

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault();
    setPending(true);
    setFailure(null);

    const result = await authApi.register({
      email,
      password,
      display_name: displayName || undefined,
      marketing_opt_in: marketingOptIn,
      // Sending the browser's zone means dates and quota resets read correctly from
      // the first page view, without ever asking the user to pick one.
      timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
    });

    setPending(false);

    if (!result.ok) {
      setFailure(result.error);
      return;
    }

    setUser(result.data);
    router.refresh();
    router.push(next);
  }

  return (
    <form onSubmit={onSubmit} noValidate className="flex flex-col gap-5">
      {failure && !failure.fieldErrors && <FormAlert>{failure.message}</FormAlert>}

      <Field
        id="display_name"
        label="Name"
        hint="What we'll call you. You can change this later."
        error={fieldError("display_name")}
      >
        {(props) => (
          <Input
            {...props}
            name="display_name"
            autoComplete="nickname"
            autoFocus
            placeholder="Ada"
            value={displayName}
            onChange={(event) => setDisplayName(event.target.value)}
          />
        )}
      </Field>

      <Field
        id="email"
        label="Email"
        hint="This becomes your account identity and can't be changed later."
        error={fieldError("email")}
        required
      >
        {(props) => (
          <Input
            {...props}
            type="email"
            name="email"
            autoComplete="email"
            placeholder="you@example.com"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
          />
        )}
      </Field>

      <Field
        id="password"
        label="Password"
        hint="At least 10 characters. A short phrase beats a clever word."
        error={fieldError("password")}
        required
      >
        {(props) => (
          <Input
            {...props}
            type="password"
            name="password"
            autoComplete="new-password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
          />
        )}
      </Field>

      {/* Honeypot: off-screen and not tabbable, so only a bot fills it in. */}
      <div aria-hidden="true" className="sr-only">
        <label htmlFor="website">Leave this field empty</label>
        <input id="website" name="website" type="text" tabIndex={-1} autoComplete="off" />
      </div>

      <Checkbox
        label="Send me product updates"
        hint="New tools and occasional tips. No more than twice a month."
        checked={marketingOptIn}
        onChange={(event) => setMarketingOptIn(event.target.checked)}
      />

      <Button type="submit" size="lg" loading={pending}>
        Create free account
      </Button>
    </form>
  );
}
