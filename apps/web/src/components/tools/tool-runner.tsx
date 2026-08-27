"use client";

import { ArrowRight, LockKeyhole, Sparkles, Zap } from "lucide-react";
import Link from "next/link";
import * as React from "react";

import { ResultRenderer } from "@/components/tools/results";
import { ToolForm } from "@/components/tools/tool-form";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { pollRun, runTool, type RunToolFailure } from "@/lib/client-api";
import type { ToolDetail, ToolRun } from "@/lib/types";
import { cn } from "@/lib/utils";

/**
 * The interactive part of a tool page: form, result, and every failure state.
 *
 * Access is decided by the server and arrives on `tool.access`. This component
 * renders that decision — it never computes one, because a client-side gate is a
 * suggestion, not a control.
 */
export function ToolRunner({ tool }: { tool: ToolDetail }) {
  const [run, setRun] = React.useState<ToolRun | null>(null);
  const [failure, setFailure] = React.useState<RunToolFailure | null>(null);
  const [pending, setPending] = React.useState(false);
  const resultRef = React.useRef<HTMLDivElement>(null);

  const locked = tool.access?.allowed === false;

  async function handleSubmit(values: Record<string, unknown>, source?: string) {
    setPending(true);
    setFailure(null);

    // `finally` guarantees the pending state is cleared on every path, including an
    // unexpected throw. Without it a single failure leaves the button spinning
    // forever, which looks exactly like the site being broken.
    try {
      const response = await runTool(tool.slug, values, source);

      if (!response.ok) {
        setFailure(response.error);
        setRun(null);
        return;
      }

      let result = response.run;

      // Slow tools come back queued; poll until they finish.
      if (result.status === "queued" || result.status === "running") {
        result = await pollRun(result.id);
      }

      setRun(result);

      // Move focus to the result so keyboard and screen-reader users are taken to
      // the thing they asked for, rather than left at the bottom of the form.
      requestAnimationFrame(() => resultRef.current?.focus());
    } catch (error) {
      setRun(null);
      setFailure({
        code: "tool.failed",
        message:
          error instanceof Error
            ? error.message
            : "Something went wrong running this tool. Please try again.",
        status: 500,
      });
    } finally {
      setPending(false);
    }
  }

  if (locked) {
    return <AccessGate tool={tool} />;
  }

  return (
    <div className="flex flex-col gap-6">
      <section
        aria-labelledby="tool-input-heading"
        className="panel relative overflow-hidden p-5 shadow-[var(--shadow-card)] sm:p-7"
      >
        {/* The brand rail along the top edge — the same two-colour signature the
            cards, the header and the footer all carry. */}
        <span
          aria-hidden="true"
          className="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-[var(--color-primary)] to-transparent opacity-70"
        />

        <h2 id="tool-input-heading" className="sr-only">
          Tool input
        </h2>

        <ToolForm
          schema={tool.input_schema}
          example={tool.example}
          fieldErrors={failure?.fieldErrors}
          pending={pending}
          toolName={tool.name}
          toolSlug={tool.slug}
          onSubmit={handleSubmit}
        />
      </section>

      <div
        ref={resultRef}
        tabIndex={-1}
        aria-live="polite"
        aria-atomic="false"
        className="scroll-mt-24 outline-none"
      >
        {pending && <ResultSkeleton />}
        {!pending && failure && !failure.fieldErrors && <RunFailure failure={failure} />}
        {!pending && run?.status === "failed" && run.error && (
          <RunFailure failure={{ ...run.error, status: 422 }} />
        )}
        {!pending && run?.status === "succeeded" && run.result && (
          <ResultPanel run={run} result={run.result} />
        )}
      </div>
    </div>
  );
}

function ResultPanel({ run, result }: { run: ToolRun; result: NonNullable<ToolRun["result"]> }) {
  return (
    <section
      aria-labelledby="tool-result-heading"
      className="panel overflow-hidden shadow-[var(--shadow-card)]"
    >
      <header className="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--color-border-subtle)] px-5 py-3.5 sm:px-6">
        <h2 id="tool-result-heading" className="text-heading-3">
          Result
        </h2>

        <div className="flex items-center gap-2 text-xs text-[var(--color-foreground-subtle)]">
          {run.meta.cache_hit ? (
            <Badge variant="neutral">
              <Zap className="size-3" /> Cached
            </Badge>
          ) : run.meta.duration_ms ? (
            <span className="tabular">{run.meta.duration_ms} ms</span>
          ) : null}
        </div>
      </header>

      <div className="p-5 sm:p-6">
        <ResultRenderer result={result} />
      </div>
    </section>
  );
}

/**
 * The paywall.
 *
 * Deliberately not an interstitial: it appears where the tool would be, states
 * exactly what is missing, and leads with the free option. Free visitors get a
 * "create a free account" ask; free accounts get the plan.
 */
function AccessGate({ tool }: { tool: ToolDetail }) {
  const needsSubscription = tool.access?.error_code === "tool.subscription_required";

  return (
    <section className="panel relative overflow-hidden p-8 text-center shadow-[var(--shadow-card)] sm:p-12">
      <div
        aria-hidden="true"
        className="pointer-events-none absolute inset-0 bg-aurora opacity-60"
      />

      <div className="relative mx-auto flex max-w-lg flex-col items-center gap-4">
        <span className="flex size-12 items-center justify-center rounded-full bg-[var(--color-primary-subtle)] text-[var(--color-primary)]">
          {needsSubscription ? <Sparkles className="size-5" /> : <LockKeyhole className="size-5" />}
        </span>

        <h2 className="text-heading-2">
          {needsSubscription ? "Included with Pro" : "Free account required"}
        </h2>

        <p className="text-[var(--color-foreground-muted)]">
          {tool.access?.message ??
            "Sign in to continue using this tool."}
        </p>

        <ul className="mt-2 flex flex-col gap-2 text-left text-sm text-[var(--color-foreground-muted)]">
          {(needsSubscription
            ? [
                "Every premium tool, including this one",
                "1,000 runs a day and unlimited history",
                "Exports, bulk operations and media kits",
              ]
            : [
                "Higher daily limits on every free tool",
                "Your results saved to your history",
                "No card needed — it takes about ten seconds",
              ]
          ).map((line) => (
            <li key={line} className="flex items-start gap-2">
              <span
                aria-hidden="true"
                className="mt-1.5 size-1.5 shrink-0 rounded-full bg-[var(--color-accent)]"
              />
              {line}
            </li>
          ))}
        </ul>

        <div className="mt-4 flex flex-wrap items-center justify-center gap-3">
          <Button asChild size="lg">
            <Link href={needsSubscription ? "/pricing" : `/register?next=/tools/${tool.slug}`}>
              {needsSubscription ? "See plans" : "Create a free account"}
              <ArrowRight />
            </Link>
          </Button>

          <Button asChild variant="ghost" size="lg">
            <Link href={needsSubscription ? "/tools?tier=free" : "/login"}>
              {needsSubscription ? "Browse free tools" : "I already have an account"}
            </Link>
          </Button>
        </div>

        {needsSubscription && (
          <p className="text-xs text-[var(--color-foreground-subtle)]">
            Not ready for a subscription? A 7-day pass is $9, one-off.
          </p>
        )}
      </div>
    </section>
  );
}

function RunFailure({ failure }: { failure: { code: string; message: string; status?: number } }) {
  const isQuota = failure.code === "tool.quota_exceeded";

  return (
    <div
      role="alert"
      className={cn(
        "flex flex-col gap-3 rounded-[var(--radius-lg)] border p-5 backdrop-blur-sm",
        isQuota
          ? "border-[var(--color-primary)]/30 bg-[var(--color-primary-subtle)]"
          : "border-[var(--color-danger)]/30 bg-[var(--color-danger)]/8",
      )}
    >
      <p className="text-sm font-medium text-[var(--color-foreground)]">{failure.message}</p>

      {isQuota && (
        <div className="flex flex-wrap gap-3">
          <Button asChild size="sm">
            <Link href="/pricing">Raise my limit</Link>
          </Button>
          <Button asChild variant="ghost" size="sm">
            <Link href="/tools">Browse other tools</Link>
          </Button>
        </div>
      )}
    </div>
  );
}

/** Matches the final layout so nothing shifts when the result arrives. */
function ResultSkeleton() {
  return (
    <div className="panel p-5 sm:p-6">
      <div className="flex flex-col gap-4">
        <div className="h-5 w-2/5 animate-pulse rounded bg-[var(--color-surface-sunken)]" />
        <div className="grid gap-3 sm:grid-cols-2">
          {[0, 1, 2, 3].map((index) => (
            <div
              key={index}
              className="h-20 animate-pulse rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)]"
              style={{ animationDelay: `${index * 80}ms` }}
            />
          ))}
        </div>
      </div>
      <span className="sr-only">Running the tool…</span>
    </div>
  );
}
