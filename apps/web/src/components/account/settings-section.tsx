import * as React from "react";

/**
 * A titled block on a settings page.
 *
 * Label and description sit beside the controls on wide screens and above them on
 * narrow ones — the two-column form pattern people already know from every account
 * screen they have used.
 */
export function SettingsSection({
  title,
  description,
  children,
}: {
  title: string;
  description?: React.ReactNode;
  children: React.ReactNode;
}) {
  return (
    <section className="grid gap-6 border-t border-[var(--color-border-subtle)] py-8 first:border-t-0 first:pt-0 lg:grid-cols-[15rem_minmax(0,1fr)] lg:gap-10">
      <div>
        <h2 className="text-base font-semibold text-[var(--color-foreground)]">{title}</h2>
        {description && (
          <p className="mt-1 text-sm leading-relaxed text-[var(--color-foreground-muted)]">
            {description}
          </p>
        )}
      </div>

      <div className="min-w-0">{children}</div>
    </section>
  );
}
