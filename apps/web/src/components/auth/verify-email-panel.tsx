"use client";

import { useSearchParams } from "next/navigation";
import * as React from "react";

import { FormAlert } from "@/components/auth/form-alert";
import { useSession } from "@/components/auth/session-provider";
import { Button } from "@/components/ui/button";
import { authApi } from "@/lib/auth-api";

/**
 * Two jobs on one route: confirm a link that was just clicked, or offer to resend
 * one to a signed-in user who has not confirmed yet.
 */
export function VerifyEmailPanel() {
  const searchParams = useSearchParams();
  const { user, refresh } = useSession();

  const link = searchParams.get("link");
  const attempted = React.useRef(false);

  const [state, setState] = React.useState<"idle" | "verifying" | "verified" | "failed">(
    link ? "verifying" : "idle",
  );
  const [message, setMessage] = React.useState<string | null>(null);
  const [resending, setResending] = React.useState(false);
  const [resent, setResent] = React.useState(false);

  React.useEffect(() => {
    if (!link || attempted.current) return;
    attempted.current = true;

    void (async () => {
      const result = await authApi.verifyEmail(link);

      if (result.ok) {
        setState("verified");
        // The session's `email_verified` flag drives banners elsewhere in the app.
        await refresh();
      } else {
        setState("failed");
        setMessage(result.error.message);
      }
    })();
  }, [link, refresh]);

  async function resend() {
    setResending(true);
    const result = await authApi.resendVerification();
    setResending(false);

    if (result.ok) {
      setResent(true);
    } else {
      setMessage(result.error.message);
    }
  }

  if (state === "verifying") {
    return <p role="status" className="text-sm text-[var(--color-foreground-muted)]">Confirming…</p>;
  }

  if (state === "verified") {
    return (
      <div className="flex flex-col gap-4">
        <FormAlert tone="success">Your email is confirmed. Thanks.</FormAlert>
        <Button asChild size="lg">
          <a href="/dashboard">Go to your dashboard</a>
        </Button>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-4">
      {state === "failed" && message && <FormAlert>{message}</FormAlert>}
      {resent && <FormAlert tone="success">Sent. Check your inbox.</FormAlert>}

      {user ? (
        <>
          <p className="text-sm text-[var(--color-foreground-muted)]">
            We&apos;ll send a fresh confirmation link to{" "}
            <span className="font-medium text-[var(--color-foreground)]">{user.email}</span>.
          </p>

          <Button size="lg" loading={resending} onClick={resend} disabled={resent}>
            {resent ? "Link sent" : "Send a new link"}
          </Button>
        </>
      ) : (
        <>
          <p className="text-sm text-[var(--color-foreground-muted)]">
            Sign in first and we&apos;ll send you a new confirmation link.
          </p>
          <Button asChild size="lg">
            <a href="/login">Sign in</a>
          </Button>
        </>
      )}
    </div>
  );
}
