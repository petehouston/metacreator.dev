"use client";

import { Check, Loader2 } from "lucide-react";
import * as React from "react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/field";
import { newsletterApi } from "@/lib/newsletter-api";
import { cn } from "@/lib/utils";

/**
 * Newsletter capture.
 *
 * `source` is carried through to the API so we can measure which placements
 * actually work and remove the ones that do not (see docs/14).
 */
export function NewsletterForm({
  source,
  compact = false,
  className,
}: {
  source: string;
  compact?: boolean;
  className?: string;
}) {
  const [state, setState] = React.useState<"idle" | "pending" | "done" | "error">("idle");
  const [message, setMessage] = React.useState<string | null>(null);

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);

    // Honeypot: a real person never fills a field they cannot see.
    if (form.get("company")) return;

    setState("pending");

    const result = await newsletterApi.subscribe({
      email: String(form.get("email") ?? ""),
      source,
      source_url: window.location.pathname,
    });

    if (!result.ok) {
      setState("error");
      setMessage(result.error.message);
      return;
    }

    setState("done");
    setMessage(
      result.data.requires_confirmation
        ? "Almost there — check your inbox to confirm."
        : "You're subscribed. Thanks!",
    );
  }

  if (state === "done") {
    return (
      <p
        className={cn(
          "flex items-center gap-2 text-sm text-[var(--color-success)]",
          className,
        )}
        role="status"
      >
        <Check className="size-4" />
        {message}
      </p>
    );
  }

  return (
    <form onSubmit={handleSubmit} className={cn("flex flex-col gap-2", className)}>
      {!compact && (
        <label htmlFor={`newsletter-${source}`} className="text-sm font-medium">
          New tools and creator tactics, once a week
        </label>
      )}

      <div className="flex gap-2">
        <Input
          id={`newsletter-${source}`}
          type="email"
          name="email"
          required
          autoComplete="email"
          placeholder="you@example.com"
          aria-label={compact ? "Email address" : undefined}
          aria-invalid={state === "error" || undefined}
          className="flex-1"
        />
        <Button type="submit" loading={state === "pending"} variant={compact ? "secondary" : "primary"}>
          {state === "pending" ? <Loader2 className="animate-spin" /> : null}
          Subscribe
        </Button>
      </div>

      {/* Honeypot — visually and programmatically hidden from real users. */}
      <input
        type="text"
        name="company"
        tabIndex={-1}
        autoComplete="off"
        aria-hidden="true"
        className="absolute size-0 opacity-0"
      />

      {state === "error" && message && (
        <p role="alert" className="text-xs text-[var(--color-danger)]">
          {message}
        </p>
      )}

      <p className="text-xs text-[var(--color-foreground-subtle)]">
        No spam. Unsubscribe in one click.
      </p>
    </form>
  );
}
