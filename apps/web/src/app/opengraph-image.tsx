import { ImageResponse } from "next/og";

import { siteConfig } from "@/config/site";
import { MARK_DATA_URI } from "@/lib/brand-mark.generated";

/**
 * The site-wide social card.
 *
 * File-based, so Next serves it at `/opengraph-image` and applies it to every page
 * that does not name its own image. A share with no image is a grey box in every
 * timeline it lands in, and pages carrying the organic traffic are exactly the ones
 * people paste into Slack and X.
 *
 * Drawn rather than uploaded: one file that always matches the brand beats a PNG
 * someone has to remember to re-export.
 */
export const size = { width: 1200, height: 630 };
export const contentType = "image/png";
export const alt = `${siteConfig.name} — ${siteConfig.tagline}`;

export default function OpengraphImage() {
  return new ImageResponse(
    (
      <div
        style={{
          width: "100%",
          height: "100%",
          display: "flex",
          flexDirection: "column",
          justifyContent: "space-between",
          padding: 80,
          background: "linear-gradient(135deg, #0d1017 0%, #151a26 55%, #1d2440 100%)",
          color: "#f4f6fb",
          fontFamily: "sans-serif",
        }}
      >
        <div style={{ display: "flex", alignItems: "center", gap: 14 }}>
          {/* Satori has no CSS variables and only a subset of SVG, so the mark
              arrives as a data URI with its palette already baked in — the same
              bytes the favicons are cut from. */}
          <img src={MARK_DATA_URI} width={52} height={52} alt="" />
          <div style={{ fontSize: 28, fontWeight: 600, letterSpacing: -0.5 }}>
            {siteConfig.name}
          </div>
        </div>

        <div style={{ display: "flex", flexDirection: "column", gap: 24 }}>
          <div style={{ fontSize: 68, fontWeight: 700, lineHeight: 1.1, letterSpacing: -2 }}>
            {siteConfig.tagline}
          </div>
          <div style={{ fontSize: 30, color: "#98a2b8", lineHeight: 1.4 }}>
            Free tools for YouTube, Instagram, TikTok, X and LinkedIn.
          </div>
        </div>

        <div style={{ fontSize: 24, color: "#6f7a92", letterSpacing: 2, textTransform: "uppercase" }}>
          {siteConfig.url.replace(/^https?:\/\//, "")}
        </div>
      </div>
    ),
    size,
  );
}
