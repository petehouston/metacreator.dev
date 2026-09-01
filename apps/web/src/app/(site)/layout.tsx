import type * as React from "react";

import { SiteFooter } from "@/components/site/footer";
import { SiteHeader } from "@/components/site/header";
import { TrackingScripts, TrackingScriptsBodyEnd } from "@/components/site/tracking-scripts";
import { trackingScripts } from "@/lib/site-settings";

/**
 * The public, indexable surface: header, content, footer, and the painted canvas
 * the root stylesheet puts behind every page.
 *
 * It is a sibling of `(app)` rather than the root layout so that the signed-in
 * workspace can be a genuinely different thing — an app shell — instead of the
 * marketing site with a sidebar bolted on.
 */
export default async function SiteLayout({ children }: { children: React.ReactNode }) {
  // The tags configured under Settings → Tracking & scripts. Read here rather than
  // in the root layout because that root also wraps `/c0ns0le` and the customer
  // dashboard, and neither is ever allowed to carry a third-party tag (docs/15).
  const scripts = await trackingScripts();

  return (
    <div className="flex min-h-dvh flex-col">
      <TrackingScripts scripts={scripts} />

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

      <TrackingScriptsBodyEnd scripts={scripts} />
    </div>
  );
}
