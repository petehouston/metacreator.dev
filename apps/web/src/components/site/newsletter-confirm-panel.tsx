"use client";

import { useSearchParams } from "next/navigation";
import * as React from "react";

import { FormAlert } from "@/components/auth/form-alert";
import { Button } from "@/components/ui/button";
import { newsletterApi } from "@/lib/newsletter-api";

/**
 * Where the double opt-in email lands.
 *
 * The token is confirmed once and only once — a re-render, or a client that
 * prefetches the link, must not spend it twice — hence the ref guard rather than
 * relying on the effect running a single time.
 */
export function NewsletterConfirmPanel() {
  const token = useSearchParams().get("token");
  const attempted = React.useRef(false);

  const [state, setState] = React.useState<"confirming" | "confirmed" | "failed">(
    token ? "confirming" : "failed",
  );
  const [message, setMessage] = React.useState<string | null>(
    token ? null : "That link is missing its confirmation code.",
  );

  React.useEffect(() => {
    if (!token || attempted.current) return;
    attempted.current = true;

    void (async () => {
      const result = await newsletterApi.confirm(token);

      if (result.ok) {
        setState("confirmed");
      } else {
        setState("failed");
        setMessage(result.error.message);
      }
    })();
  }, [token]);

  if (state === "confirming") {
    return (
      <p role="status" className="text-sm text-[var(--color-foreground-muted)]">
        Confirming…
      </p>
    );
  }

  return (
    <div className="flex flex-col gap-4">
      {state === "confirmed" ? (
        <FormAlert tone="success">
          You&rsquo;re on the list. The next issue is on its way.
        </FormAlert>
      ) : (
        <FormAlert>
          {message ?? "That confirmation link is no longer valid."}
        </FormAlert>
      )}

      <Button asChild size="lg">
        <a href={state === "confirmed" ? "/tools" : "/"}>
          {state === "confirmed" ? "Browse the tools" : "Back to the site"}
        </a>
      </Button>
    </div>
  );
}
