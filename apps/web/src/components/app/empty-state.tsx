import type * as React from "react";

/**
 * What an empty list says, and the one thing to do about it.
 *
 * Every empty state in the app carries an action — a screen that only says "nothing
 * here" makes the dead end the user's problem to solve.
 */
export function EmptyState({
  icon: Icon,
  title,
  description,
  action,
}: {
  icon?: React.ComponentType<{ className?: string }>;
  title: string;
  description?: React.ReactNode;
  action?: React.ReactNode;
}) {
  return (
    <div className="flex flex-col items-center gap-3 rounded-[var(--radius-lg)] border border-dashed border-[var(--color-border)] px-6 py-12 text-center">
      {Icon && (
        <span className="flex size-11 items-center justify-center rounded-full bg-[var(--color-surface-sunken)] text-[var(--color-foreground-subtle)]">
          <Icon className="size-5" aria-hidden="true" />
        </span>
      )}

      <p className="text-sm font-semibold text-[var(--color-foreground)]">{title}</p>

      {description && (
        <p className="max-w-sm text-sm leading-relaxed text-[var(--color-foreground-muted)]">
          {description}
        </p>
      )}

      {action && <div className="mt-1">{action}</div>}
    </div>
  );
}
