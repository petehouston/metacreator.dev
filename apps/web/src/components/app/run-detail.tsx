"use client";

import { ArrowLeft } from "lucide-react";
import Link from "next/link";
import * as React from "react";

import { AppPageHeader } from "@/components/app/page-header";
import { formatDuration } from "@/components/app/overview";
import { RunStatusBadge } from "@/components/app/run-status-badge";
import { SectionCard } from "@/components/app/section-card";
import { FormAlert } from "@/components/auth/form-alert";
import { ResultRenderer } from "@/components/tools/results";
import { Button } from "@/components/ui/button";
import { CopyButton } from "@/components/ui/copy-button";
import { apiData } from "@/lib/http";
import type { ToolRun } from "@/lib/types";

/**
 * One stored run, on its own page.
 *
 * The list endpoint returns runs without their full payload, so this fetches the
 * single run it is showing. Only asynchronous runs keep their result (docs/08) — a
 * tool that answered instantly leaves a record of the run and not the output, and
 * saying so is better than an empty panel.
 */
export function RunDetail({ id }: { id: string }) {
  const [run, setRun] = React.useState<ToolRun | null>(null);
  const [error, setError] = React.useState<string | null>(null);
  const [loading, setLoading] = React.useState(true);

  React.useEffect(() => {
    let cancelled = false;

    void (async () => {
      const result = await apiData<ToolRun>(`/tools/runs/${id}`);
      if (cancelled) return;

      if (result.ok) {
        setRun(result.data);
        setError(null);
      } else {
        setError(result.error.message);
      }

      setLoading(false);
    })();

    return () => {
      cancelled = true;
    };
  }, [id]);

  const back = (
    <Button variant="secondary" size="sm" asChild>
      <Link href="/dashboard/runs">
        <ArrowLeft className="size-4" aria-hidden="true" />
        Back to history
      </Link>
    </Button>
  );

  if (loading) {
    return (
      <>
        <AppPageHeader eyebrow="Workspace · Run history" title="Run" actions={back} />
        <div className="app-card h-64 animate-pulse" aria-hidden="true" />
      </>
    );
  }

  if (!run) {
    return (
      <>
        <AppPageHeader
          eyebrow="Workspace · Run history"
          title="Run not found"
          description="It may have fallen outside your plan's history window."
          actions={back}
        />
        {error && <FormAlert>{error}</FormAlert>}
      </>
    );
  }

  return (
    <>
      <AppPageHeader
        eyebrow="Workspace · Run history"
        title={run.tool?.name ?? "Tool run"}
        description={
          <span className="flex flex-wrap items-center gap-2">
            <span>{formatDateTime(run.created_at)}</span>
            {run.meta.duration_ms !== null && (
              <span className="tabular">· {formatDuration(run.meta.duration_ms)}</span>
            )}
            {run.meta.cache_hit && <span>· cached</span>}
            <RunStatusBadge status={run.status} />
          </span>
        }
        actions={
          <>
            {back}
            {run.tool && (
              <Button asChild size="sm">
                <Link href={`/tools/${run.tool.slug}`}>Run again</Link>
              </Button>
            )}
          </>
        }
      />

      <div className="flex flex-col gap-4">
        {run.error && (
          <FormAlert>
            <span className="block font-medium">{run.error.message}</span>
            <span className="mt-1 block font-mono text-[0.6875rem] opacity-80">
              {run.error.code}
            </span>
          </FormAlert>
        )}

        {run.result && (
          <SectionCard
            title="Result"
            action={<CopyButton value={JSON.stringify(run.result.data, null, 2)} label="Copy JSON" />}
          >
            <div className="flex flex-col gap-4">
              {run.result.summary && (
                <p className="text-sm leading-relaxed text-[var(--color-foreground-muted)]">
                  {run.result.summary}
                </p>
              )}

              <ResultRenderer result={run.result} />

              {run.result.warnings.length > 0 && (
                <ul className="flex flex-col gap-1">
                  {run.result.warnings.map((warning) => (
                    <li key={warning} className="text-xs text-[var(--color-warning)]">
                      {warning}
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </SectionCard>
        )}

        {!run.result && !run.error && (
          <SectionCard title="Result">
            <div className="py-10 text-center">
              <p className="text-sm text-[var(--color-foreground-muted)]">
                No stored output for this run.
              </p>
              <p className="mx-auto mt-1.5 max-w-sm text-xs leading-relaxed text-[var(--color-foreground-subtle)]">
                Tools that answer instantly are recorded but their results are not kept.
                Running it again takes the same input and costs one run.
              </p>
            </div>
          </SectionCard>
        )}

        <p className="truncate font-mono text-[0.625rem] text-[var(--color-foreground-subtle)]">
          {run.id}
        </p>
      </div>
    </>
  );
}

function formatDateTime(iso: string): string {
  return new Date(iso).toLocaleString(undefined, {
    month: "short",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
}
