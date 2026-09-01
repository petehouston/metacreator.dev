import Script from "next/script";

import type { TrackingScripts } from "@/lib/site-settings";

/**
 * Everything Settings → Tracking & scripts puts on a public page.
 *
 * Rendered from the `(site)` layout and nowhere else, which is what enforces the
 * rule in docs/15: tags never reach `/admin` or the signed-in dashboard. Putting it
 * in the root layout would be one line shorter and would leak the pixel into both.
 *
 * Two mechanisms, on purpose:
 *
 *  - The measurement IDs become the provider's official snippet, built here. An
 *    admin pasting `G-XXXX` into a field cannot get the loader subtly wrong, and we
 *    get `next/script`'s `afterInteractive` scheduling for free — the tag loads
 *    after hydration instead of competing with first paint.
 *  - The four raw slots are injected verbatim, because the whole point of a raw box
 *    is that we do not know what is in it. That means it is arbitrary code on every
 *    public page, which is why editing it needs `settings.scripts.update`.
 */
export function TrackingScripts({ scripts }: { scripts: TrackingScripts }) {
  return (
    <>
      {/* The head slots. They are emitted here, at the top of the site tree, rather
          than inside <head>: the App Router builds the document head from the
          metadata API and has no seam for an arbitrary HTML string. For a script —
          which is what these boxes hold in practice — position is irrelevant, since
          the browser parses and executes it either way. A verification <meta> does
          belong in the head, and has its own field under Settings → SEO. */}
      <RawHtml html={scripts.headStart} />
      <RawHtml html={scripts.headEnd} />
      <RawHtml html={scripts.bodyStart} />

      {scripts.gtmId !== "" ? <GoogleTagManager id={scripts.gtmId} /> : null}
      {/* GTM is expected to own GA4 when both are set (docs/15), so loading gtag.js
          as well would double every event. GTM wins; the GA4 field is then the
          fallback for sites not running a container. */}
      {scripts.ga4Id !== "" && scripts.gtmId === "" ? <GoogleAnalytics id={scripts.ga4Id} /> : null}
      {scripts.metaPixelId !== "" ? <MetaPixel id={scripts.metaPixelId} /> : null}
      {scripts.tiktokPixelId !== "" ? <TikTokPixel id={scripts.tiktokPixelId} /> : null}
    </>
  );
}

/**
 * The end-of-body slot, rendered as the last thing in the site layout.
 *
 * Separate from the component above so that "body end" means what it says — the
 * caller places it after the footer, not alongside everything else.
 */
export function TrackingScriptsBodyEnd({ scripts }: { scripts: TrackingScripts }) {
  return <RawHtml html={scripts.bodyEnd} />;
}

/**
 * A pasted snippet, verbatim.
 *
 * `display: contents` so the wrapper cannot disturb the layout it sits in: the
 * element is required — React has no way to emit a raw string without one — but it
 * is not allowed to become a box. Server-rendered, so the markup arrives as part of
 * the document and the browser's parser runs any <script> in it normally.
 */
function RawHtml({ html }: { html: string }) {
  if (html.trim() === "") return null;

  return <div style={{ display: "contents" }} dangerouslySetInnerHTML={{ __html: html }} />;
}

function GoogleTagManager({ id }: { id: string }) {
  return (
    <>
      <Script id="gtm" strategy="afterInteractive">
        {`(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','${id}');`}
      </Script>
      {/* The container's own fallback for a browser with JavaScript off. It buys
          nothing on its own, but GTM tags that are configured to fire from it stop
          working if it is missing. */}
      <noscript>
        <iframe
          src={`https://www.googletagmanager.com/ns.html?id=${encodeURIComponent(id)}`}
          height="0"
          width="0"
          style={{ display: "none", visibility: "hidden" }}
          title="Google Tag Manager"
        />
      </noscript>
    </>
  );
}

function GoogleAnalytics({ id }: { id: string }) {
  return (
    <>
      <Script
        src={`https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(id)}`}
        strategy="afterInteractive"
      />
      <Script id="ga4" strategy="afterInteractive">
        {`window.dataLayer = window.dataLayer || [];function gtag(){dataLayer.push(arguments);}gtag('js', new Date());gtag('config', '${id}');`}
      </Script>
    </>
  );
}

function MetaPixel({ id }: { id: string }) {
  return (
    <Script id="meta-pixel" strategy="afterInteractive">
      {`!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','${id}');fbq('track','PageView');`}
    </Script>
  );
}

function TikTokPixel({ id }: { id: string }) {
  return (
    <Script id="tiktok-pixel" strategy="afterInteractive">
      {`!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"];ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e};ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{};ttq._i[e]=[];ttq._i[e]._u=i;ttq._t=ttq._t||{};ttq._t[e]=+new Date;ttq._o=ttq._o||{};ttq._o[e]=n||{};var o=d.createElement("script");o.type="text/javascript";o.async=!0;o.src=i+"?sdkid="+e+"&lib="+t;var a=d.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};ttq.load('${id}');ttq.page();}(window,document,'ttq');`}
    </Script>
  );
}
