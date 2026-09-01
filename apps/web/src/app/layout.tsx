import type { Metadata, Viewport } from "next";
import { DM_Sans, JetBrains_Mono } from "next/font/google";

import { SessionProvider } from "@/components/auth/session-provider";
import { FeaturesProvider } from "@/components/site/features-provider";
import { ThemeProvider } from "@/components/site/theme-provider";
import { FavoritesProvider } from "@/components/tools/favorites-provider";
import { siteConfig } from "@/config/site";
import { siteFeatures } from "@/lib/site-settings";

import "./globals.css";

/**
 * DM Sans and JetBrains Mono are both SIL Open Font Licence — free for commercial
 * use with no attribution requirement, which keeps the licensing question closed.
 * `next/font` self-hosts them, so there is no third-party font request at runtime.
 */
const dmSans = DM_Sans({
  variable: "--font-dm-sans",
  subsets: ["latin", "latin-ext"],
  display: "swap",
  weight: ["400", "500", "600", "700"],
});

const jetbrainsMono = JetBrains_Mono({
  variable: "--font-jetbrains-mono",
  subsets: ["latin"],
  display: "swap",
  weight: ["400", "500"],
});

export const metadata: Metadata = {
  metadataBase: new URL(siteConfig.url),
  title: {
    default: `${siteConfig.name} — ${siteConfig.tagline}`,
    // Every page supplies its own title; this template keeps branding consistent
    // without each page having to remember it.
    template: `%s | ${siteConfig.name}`,
  },
  description: siteConfig.description,
  applicationName: siteConfig.name,
  keywords: [
    "social media tools",
    "creator tools",
    "youtube tools",
    "instagram tools",
    "tiktok tools",
    "pinterest tools",
    "threads tools",
    "engagement rate calculator",
    "hashtag generator",
  ],
  openGraph: {
    type: "website",
    siteName: siteConfig.name,
    locale: "en_US",
    url: siteConfig.url,
    title: `${siteConfig.name} — ${siteConfig.tagline}`,
    description: siteConfig.description,
  },
  twitter: {
    card: "summary_large_image",
    site: "@metacreatordev",
  },
  robots: {
    index: true,
    follow: true,
    googleBot: {
      index: true,
      follow: true,
      "max-image-preview": "large",
      "max-snippet": -1,
    },
  },
  alternates: { canonical: "/" },
  manifest: "/manifest.webmanifest",
  /**
   * `favicon.ico` stays where Next serves it from `src/app` and is picked up
   * automatically; everything listed here is the modern half of the set — the SVG
   * that scales to any tab size, and the touch icons iOS uses when the site is
   * added to a Home Screen. Regenerate with `node scripts/generate-icons.mjs`.
   */
  icons: {
    icon: [
      { url: "/brand/favicon.svg", type: "image/svg+xml" },
      { url: "/brand/favicon-32x32.png", sizes: "32x32", type: "image/png" },
      { url: "/brand/favicon-16x16.png", sizes: "16x16", type: "image/png" },
      { url: "/brand/favicon-96x96.png", sizes: "96x96", type: "image/png" },
    ],
    apple: [
      { url: "/brand/apple-touch-icon.png", sizes: "180x180" },
      { url: "/brand/apple-touch-icon-167.png", sizes: "167x167" },
      { url: "/brand/apple-touch-icon-152.png", sizes: "152x152" },
    ],
    other: [{ rel: "mask-icon", url: "/brand/logo-mark-flat.svg", color: "#3d80f7" }],
  },
  other: {
    "msapplication-TileColor": "#0d1017",
    "msapplication-TileImage": "/brand/mstile-150.png",
  },
};

export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
  themeColor: [
    { media: "(prefers-color-scheme: light)", color: "#fbfbfe" },
    { media: "(prefers-color-scheme: dark)", color: "#0d1017" },
  ],
};

export default async function RootLayout({ children }: LayoutProps<"/">) {
  // Read once, at the root, and handed down. Every surface that could show a price
  // — the header, the sidebar, the plan meter, a quota wall — needs the same answer,
  // and reading it here means they all get it before the first paint.
  const features = await siteFeatures();

  return (
    <html
      lang="en"
      suppressHydrationWarning
      className={`${dmSans.variable} ${jetbrainsMono.variable} h-full`}
    >
      <body className="min-h-full">
        <ThemeProvider>
          <FeaturesProvider features={features}>
            <SessionProvider>
              {/* Inside the session, because the saved list is a property of who is
                  signed in; above everything else, because the catalog cards, the
                  tool page and the Favourites sort must all read the same set. */}
              <FavoritesProvider>{children}</FavoritesProvider>
            </SessionProvider>
          </FeaturesProvider>
        </ThemeProvider>
      </body>
    </html>
  );
}
