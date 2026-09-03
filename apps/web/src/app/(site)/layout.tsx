import type * as React from "react";

import { SiteFooter } from "@/components/site/footer";
import { SiteHeader } from "@/components/site/header";
import { RankingNavProvider } from "@/components/site/ranking-nav-provider";
import { TrackingScripts, TrackingScriptsBodyEnd } from "@/components/site/tracking-scripts";
import { trackingScripts } from "@/lib/site-settings";
import { rankingNav } from "@/lib/top-ranking-nav";

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
  // Both are cached reads, and they are independent, so they overlap rather than
  // queueing. The rankings list is fetched here rather than in the root layout
  // because only this branch of the tree has a header that draws it — `/c0ns0le`
  // and the customer dashboard would be paying for a menu they do not have.
  const [scripts, rankings] = await Promise.all([trackingScripts(), rankingNav()]);

  return (
    <RankingNavProvider items={rankings}>
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
    </RankingNavProvider>
  );
}
