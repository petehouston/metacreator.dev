import { ArrowUp } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";

import { ToolBrowser } from "@/components/tools/tool-browser";
import { siteConfig } from "@/config/site";
import { api } from "@/lib/api";

export const metadata: Metadata = {
  title: "All Creator Tools — YouTube, Instagram, TikTok, X & More",
  description:
    "Browse every MetaCreator tool: engagement calculators, hashtag generators, thumbnail downloaders, headline analyzers and more. Most are free with no account.",
  alternates: { canonical: "/tools" },
  openGraph: {
    title: `Creator Tools | ${siteConfig.name}`,
    description:
      "60+ tools for creators across YouTube, Instagram, TikTok, X, Facebook and LinkedIn.",
    url: `${siteConfig.url}/tools`,
  },
};

/**
 * The catalog, rendered whole.
 *
 * Every tool ships in the initial HTML — good for crawlers, and it lets the search
 * box and the filter chips work with no round trip (see {@link ToolBrowser}).
 */
export default async function ToolsPage({ searchParams }: PageProps<"/tools">) {
  const params = await searchParams;

  const [response, categories] = await Promise.all([
    api.tools.list({ per_page: 200 }).catch(() => null),
    api.tools.categories().catch(() => []),
  ]);

  return (
    <div id="top" className="mx-auto w-full max-w-[80rem] px-4 py-12 sm:px-6 lg:py-16">
      <header className="flex flex-col gap-3">
        <p className="eyebrow">The catalog</p>
        <h1 className="text-heading-1 text-balance sm:text-display-lg">Creator tools</h1>
        <p className="max-w-2xl text-body-lg text-[var(--color-foreground-muted)]">
          Every tool does one job properly. Free tools run instantly with no account —
          search or filter below to find what you need.
        </p>
      </header>

      {response ? (
        <ToolBrowser
          tools={response.data}
          categories={categories}
          initial={{
            q: single(params.q),
            tier: single(params.tier),
            platform: single(params.platform),
            category: single(params.category),
            sort: single(params.sort),
          }}
        />
      ) : (
        <p className="mt-12 rounded-[var(--radius-lg)] border border-[var(--color-danger)]/30 bg-[var(--color-danger)]/8 p-6 text-sm">
          We couldn&apos;t load the catalog just now. Please refresh in a moment, or{" "}
          <Link href="/contact" className="text-[var(--color-primary)] hover:underline">
            tell us
          </Link>{" "}
          if it keeps happening.
        </p>
      )}

      {/* A bare `<a>`, not a scroll handler and not `<Link>`: `html` already carries
          `scroll-behavior: smooth`, which the reduced-motion block in globals.css
          turns off for anyone who asked for that, and the router has no business
          in a same-page hash. Being a real link also means it works before
          hydration, which is the whole point at the foot of a page this long. */}
      <div className="mt-12 flex justify-center">
        <a
          href="#top"
          className="inline-flex items-center gap-1.5 text-sm text-[var(--color-foreground-muted)] underline-offset-4 transition-colors hover:text-[var(--color-foreground)] hover:underline"
        >
          <ArrowUp className="size-4" aria-hidden="true" />
          Back to top
        </a>
      </div>
    </div>
  );
}

function single(value: string | string[] | undefined): string | undefined {
  return Array.isArray(value) ? value[0] : value;
}
