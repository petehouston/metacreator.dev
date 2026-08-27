"use client";

import { useRouter, useSearchParams } from "next/navigation";
import * as React from "react";

import { FormAlert } from "@/components/auth/form-alert";
import { useSession } from "@/components/auth/session-provider";
import { Button } from "@/components/ui/button";
import { authApi } from "@/lib/auth-api";

/**
 * Lands from the emailed link, exchanges the token for a session, and moves on.
 *
 * The exchange runs exactly once. React 18's development StrictMode double-invokes
 * effects, and a second call against a single-use token would consume it and then
 * report the link as invalid — so the guard is a ref, not a state flag.
 */
export function MagicLinkConsumer() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { setUser } = useSession();

  const token = searchParams.get("token");
  const attempted = React.useRef(false);
  const [error, setError] = React.useState<string | null>(null);

  // A missing token is knowable at render time, so it is derived rather than pushed
  // into state from an effect.
  const message = token ? error : "That sign-in link is incomplete. Request a new one.";

  React.useEffect(() => {
    if (!token || attempted.current) return;
    attempted.current = true;

    void (async () => {
      const result = await authApi.consumeMagicLink(token);

      if (!result.ok) {
        setError(result.error.message);
        return;
      }

      setUser(result.data.data);
      router.refresh();

      const redirectTo = result.data.meta.redirect_to;
      router.replace(redirectTo && redirectTo.startsWith("/") ? redirectTo : "/dashboard");
    })();
  }, [token, router, setUser]);

  if (message) {
    return (
      <div className="flex flex-col gap-4">
        <FormAlert>{message}</FormAlert>
        <Button asChild size="lg">
          <a href="/login">Request a new link</a>
        </Button>
      </div>
    );
  }

  return (
    <p role="status" className="text-sm text-[var(--color-foreground-muted)]">
      Signing you in…
    </p>
  );
}
