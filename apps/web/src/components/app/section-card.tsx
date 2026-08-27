import type * as React from "react";

import { cn } from "@/lib/utils";

/** A titled block of app content: header row, optional action, body. */
export function SectionCard({
  title,
  description,
  action,
  children,
  className,
  bodyClassName,
}: {
  title: string;
  description?: React.ReactNode;
  action?: React.ReactNode;
  children: React.ReactNode;
  className?: string;
  bodyClassName?: string;
}) {
  return (
    <section className={cn("app-card overflow-hidden", className)}>
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--color-border-subtle)] px-4 py-3">
        <div className="min-w-0">
          <h2 className="text-sm font-semibold text-[var(--color-foreground)]">{title}</h2>
          {description && (
            <p className="mt-0.5 text-xs text-[var(--color-foreground-subtle)]">{description}</p>
          )}
        </div>

        {action}
      </div>

      <div className={cn("p-4", bodyClassName)}>{children}</div>
    </section>
  );
}
