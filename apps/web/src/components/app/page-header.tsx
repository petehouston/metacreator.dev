import type * as React from "react";

import { cn } from "@/lib/utils";

/**
 * The one H1 on an app screen, its explanation, and the actions that belong to the
 * whole page. Actions sit on the same row on wide screens and wrap under the title
 * on narrow ones — never in a sticky bar, which would steal height from the data.
 */
export function AppPageHeader({
  eyebrow,
  title,
  description,
  actions,
  className,
}: {
  eyebrow?: string;
  title: string;
  description?: React.ReactNode;
  actions?: React.ReactNode;
  className?: string;
}) {
  return (
    <div className={cn("mb-6 flex flex-wrap items-end justify-between gap-4", className)}>
      <div className="min-w-0">
        {eyebrow && <p className="eyebrow mb-2">{eyebrow}</p>}

        <h1 className="text-[1.625rem] font-bold leading-tight tracking-[-0.02em] text-[var(--color-foreground)]">
          {title}
        </h1>

        {description && (
          <p className="mt-1.5 max-w-2xl text-sm leading-relaxed text-[var(--color-foreground-muted)]">
            {description}
          </p>
        )}
      </div>

      {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
    </div>
  );
}
