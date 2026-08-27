import Link from "next/link";
import * as React from "react";

import { Logo } from "@/components/site/logo";

/**
 * The frame every auth screen shares.
 *
 * Two columns on desktop: the form on the left where the eye lands, and a quiet
 * proof panel on the right. The panel is `hidden lg:flex` rather than reordered on
 * mobile — on a phone the only thing that matters is the form.
 */
export function AuthShell({
  title,
  subtitle,
  children,
  footer,
  aside,
}: {
  title: string;
  subtitle?: React.ReactNode;
  children: React.ReactNode;
  footer?: React.ReactNode;
  aside?: React.ReactNode;
}) {
  return (
    <div className="mx-auto grid w-full max-w-[75rem] gap-16 px-4 py-12 sm:px-6 lg:grid-cols-[minmax(0,26rem)_minmax(0,1fr)] lg:py-20">
      <div className="w-full">
        <div className="mb-8 lg:hidden">
          <Logo />
        </div>

        <h1 className="text-[1.75rem] font-bold leading-tight tracking-[-0.02em] text-[var(--color-foreground)]">
          {title}
        </h1>

        {subtitle && (
          <p className="mt-2 text-sm leading-relaxed text-[var(--color-foreground-muted)]">
            {subtitle}
          </p>
        )}

        <div className="mt-8">{children}</div>

        {footer && (
          <div className="mt-8 text-sm text-[var(--color-foreground-muted)]">{footer}</div>
        )}
      </div>

      <aside className="hidden lg:flex lg:flex-col lg:justify-center">
        {aside ?? <DefaultAside />}
      </aside>
    </div>
  );
}

function DefaultAside() {
  const points = [
    ["Free forever tier", "Dozens of tools with no account at all, and higher limits once you have one."],
    ["Built for every platform", "YouTube, Instagram, TikTok, X, Facebook and LinkedIn in one place."],
    ["No posting access, ever", "We never ask to connect your accounts or post on your behalf."],
  ];

  return (
    <div className="panel p-10">
      <p className="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--color-primary)]">
        MetaCreator.dev
      </p>

      <p className="mt-4 text-xl font-semibold leading-snug tracking-[-0.01em] text-[var(--color-foreground)]">
        The toolkit creators reach for before they hit publish.
      </p>

      <ul className="mt-8 flex flex-col gap-6">
        {points.map(([heading, copy]) => (
          <li key={heading}>
            <p className="text-sm font-semibold text-[var(--color-foreground)]">{heading}</p>
            <p className="mt-1 text-sm leading-relaxed text-[var(--color-foreground-muted)]">
              {copy}
            </p>
          </li>
        ))}
      </ul>

      <p className="mt-8 text-xs text-[var(--color-foreground-subtle)]">
        By continuing you agree to our{" "}
        <Link href="/terms" className="underline underline-offset-2">
          Terms
        </Link>{" "}
        and{" "}
        <Link href="/privacy" className="underline underline-offset-2">
          Privacy Policy
        </Link>
        .
      </p>
    </div>
  );
}
