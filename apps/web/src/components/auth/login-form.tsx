"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import * as React from "react";

import { FormAlert } from "@/components/auth/form-alert";
import { useSession } from "@/components/auth/session-provider";
import { Button } from "@/components/ui/button";
import { Checkbox, Field, Input } from "@/components/ui/field";
import { authApi } from "@/lib/auth-api";
import type { ApiFailure } from "@/lib/http";

type Mode = "password" | "link";

/**
 * Sign-in, with the passwordless path given equal billing rather than buried.
 *
 * Most creators reach for a link over remembering a password, and the two modes share
 * one email field so switching never costs a retype.
 */
export function LoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { setUser } = useSession();

  const [mode, setMode] = React.useState<Mode>("password");
  const [email, setEmail] = React.useState("");
  const [password, setPassword] = React.useState("");
  const [remember, setRemember] = React.useState(true);
  const [pending, setPending] = React.useState(false);
  const [failure, setFailure] = React.useState<ApiFailure | null>(null);
  const [linkSent, setLinkSent] = React.useState(false);

  // Only same-site paths: an attacker-supplied `?next=` must never be able to bounce
  // a freshly authenticated user off to another origin.
  const rawNext = searchParams.get("next");
  const next = rawNext && rawNext.startsWith("/") && !rawNext.startsWith("//") ? rawNext : "/dashboard";

  const fieldError = (name: string) => failure?.fieldErrors?.[name]?.[0];

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault();
    setPending(true);
    setFailure(null);

    if (mode === "link") {
      const result = await authApi.requestMagicLink(email, next);
      setPending(false);

      if (result.ok) {
        setLinkSent(true);
      } else {
        setFailure(result.error);
      }

      return;
    }

    const result = await authApi.login(email, password, remember);
    setPending(false);

    if (!result.ok) {
      setFailure(result.error);
      return;
    }

    setUser(result.data);
    router.refresh();
    router.push(next);
  }

  if (linkSent) {
    return (
      <div className="flex flex-col gap-4">
        <FormAlert tone="success">
          If <span className="font-medium">{email}</span> has an account, a sign-in link is on
          its way. It expires in 15 minutes and works once.
        </FormAlert>

        <Button variant="secondary" onClick={() => setLinkSent(false)}>
          Use a different email
        </Button>
      </div>
    );
  }

  return (
    <form onSubmit={onSubmit} noValidate className="flex flex-col gap-5">
      {failure && !failure.fieldErrors && <FormAlert>{failure.message}</FormAlert>}

      <Field id="email" label="Email" error={fieldError("email")} required>
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

      {mode === "password" && (
        <Field id="password" label="Password" error={fieldError("password")} required>
          {(props) => (
            <Input
              {...props}
              type="password"
              name="password"
              autoComplete="current-password"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
            />
          )}
        </Field>
      )}

      {mode === "password" && (
        <div className="flex items-center justify-between gap-4">
          <Checkbox
            label="Keep me signed in"
            checked={remember}
            onChange={(event) => setRemember(event.target.checked)}
          />

          <Link
            href="/forgot-password"
            className="text-sm font-medium text-[var(--color-primary)] hover:underline"
          >
            Forgot password?
          </Link>
        </div>
      )}

      <Button type="submit" size="lg" loading={pending}>
        {mode === "password" ? "Sign in" : "Email me a sign-in link"}
      </Button>

      <div className="flex items-center gap-3">
        <span className="h-px flex-1 bg-[var(--color-border)]" />
        <span className="text-xs uppercase tracking-wider text-[var(--color-foreground-subtle)]">
          or
        </span>
        <span className="h-px flex-1 bg-[var(--color-border)]" />
      </div>

      <Button
        type="button"
        variant="secondary"
        size="lg"
        onClick={() => {
          setMode(mode === "password" ? "link" : "password");
          setFailure(null);
        }}
      >
        {mode === "password" ? "Sign in with an email link" : "Sign in with a password"}
      </Button>
    </form>
  );
}
