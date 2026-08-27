import type * as React from "react";

import { SiteFooter } from "@/components/site/footer";
import { SiteHeader } from "@/components/site/header";

/**
 * The public, indexable surface: header, content, footer, and the painted canvas
 * the root stylesheet puts behind every page.
 *
 * It is a sibling of `(app)` rather than the root layout so that the signed-in
 * workspace can be a genuinely different thing — an app shell — instead of the
 * marketing site with a sidebar bolted on.
 */
export default function SiteLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex min-h-dvh flex-col">
      {/* Skip link: the first thing a keyboard user meets, and the cheapest
          accessibility win on any site. */}
      <a
        href="#main"
        className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[100] focus:rounded-[var(--radius-md)] focus:bg-[var(--color-primary)] focus:px-4 focus:py-2 focus:text-[var(--color-primary-foreground)]"
      >
        Skip to content
      </a>

      <SiteHeader />

      <main id="main" className="flex-1">
        {children}
      </main>

      <SiteFooter />
    </div>
  );
}
