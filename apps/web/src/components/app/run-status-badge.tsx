import { cn } from "@/lib/utils";
import type { ToolRun } from "@/lib/types";

/**
 * One colour language for run state, used by the overview, the history table and
 * the run drawer — so "succeeded" is never green in one place and blue in another.
 */
export function RunStatusBadge({
  status,
  className,
}: {
  status: ToolRun["status"];
  className?: string;
}) {
  const tone = {
    succeeded: "bg-[var(--color-accent-surface)] text-[var(--color-accent)]",
    failed: "bg-[var(--color-danger)]/12 text-[var(--color-danger)]",
    running: "bg-[var(--color-primary-subtle)] text-[var(--color-primary)]",
    queued: "bg-[var(--color-surface-sunken)] text-[var(--color-foreground-muted)]",
    cancelled: "bg-[var(--color-surface-sunken)] text-[var(--color-foreground-muted)]",
  }[status];

  return (
    <span
      className={cn(
        "inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium capitalize",
        tone,
        className,
      )}
    >
      {(status === "running" || status === "queued") && (
        <span
          aria-hidden="true"
          className="size-1.5 animate-pulse rounded-full bg-current"
        />
      )}
      {status}
    </span>
  );
}
