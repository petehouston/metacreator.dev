import { ChevronRight, Clock, Gauge, Layers } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";

import { BlockRenderer } from "@/components/blocks/block-renderer";
import { NewsletterForm } from "@/components/site/newsletter-form";
import { ToolCard, ToolCardSkeleton } from "@/components/tools/tool-card";
import { ToolRunner } from "@/components/tools/tool-runner";
import { Badge, TierBadge } from "@/components/ui/badge";
import { platforms, siteConfig } from "@/config/site";
import { api, ApiRequestError } from "@/lib/api";
import { discoverTools } from "@/lib/tool-discovery";
import { formatNumber } from "@/lib/utils";
import type { ToolDetail } from "@/lib/types";

/**
 * Tool pages are the money pages: they carry the organic traffic and they are where
 * the paywall converts. Everything here serves those two jobs — one API round trip,
 * complete metadata, structured data that matches what is on the page, and prose
 * substantial enough to rank.
 *
 * The form owns the full width of the page. It is the reason the visitor is here,
 * and a sidebar beside it buys nothing: the fields end up too narrow to read your
 * own input in, which is a real failure traded for a decorative one. Everything
 * that used to sit in that column — related tools, the newsletter — now appears
 * *after* the answer, which is also the only moment either is welcome.
 */
async function loadTool(slug: string): Promise<ToolDetail | null> {
  try {
    return await api.tools.get(slug);
  } catch (error) {
    if (error instanceof ApiRequestError && error.status === 404) return null;
    throw error;
  }
}

export async function generateMetadata({
  params,
}: PageProps<"/tools/[slug]">): Promise<Metadata> {
  const { slug } = await params;
  const tool = await loadTool(slug);

  if (!tool) return { title: "Tool not found" };

  const title = tool.seo?.title ?? `${tool.name} — Free Online Tool`;
  const description = tool.seo?.description ?? tool.tagline ?? siteConfig.description;
  const url = `${siteConfig.url}/tools/${tool.slug}`;

  return {
    title,
    description,
    alternates: { canonical: tool.seo?.canonical_url ?? `/tools/${tool.slug}` },
    robots: tool.seo?.robots?.includes("noindex") ? { index: false, follow: true } : undefined,
    openGraph: {
      type: "website",
      url,
      title: tool.seo?.og_title ?? title,
      description: tool.seo?.og_description ?? description,
      siteName: siteConfig.name,
    },
    twitter: {
      card: "summary_large_image",
      title: tool.seo?.og_title ?? title,
      description: tool.seo?.og_description ?? description,
    },
  };
}

export default async function ToolPage({ params }: PageProps<"/tools/[slug]">) {
  const { slug } = await params;
  const tool = await loadTool(slug);

  if (!tool) notFound();

  const platformLabels = tool.platforms
    .map((key) => platforms.find((platform) => platform.key === key)?.label ?? key)
    .filter(Boolean);

  const accent = tool.category?.accent_color ?? undefined;

  return (
    <article className="w-full">
      {/* ── Masthead ─────────────────────────────────────────────────────────── */}
      <header className="relative overflow-hidden border-b border-[var(--color-border-subtle)]">
        <div aria-hidden="true" className="pointer-events-none absolute inset-0 bg-aurora" />

        <div className="relative mx-auto w-full max-w-[80rem] px-4 pb-10 pt-8 sm:px-6">
          <Breadcrumbs tool={tool} />

          <div className="mt-6 flex flex-col gap-5">
            <div className="flex flex-wrap items-center gap-2">
              <TierBadge tier={tool.tier} />
              {tool.category && (
                <Link href={`/tools?category=${tool.category.slug}`}>
                  <Badge
                    variant="neutral"
                    className="cursor-pointer"
                    style={accent ? { color: accent, borderColor: `${accent}55` } : undefined}
                  >
                    {tool.category.name}
                  </Badge>
                </Link>
              )}
              {platformLabels.map((label) => (
                <Badge key={label} variant="neutral">
                  {label}
                </Badge>
              ))}
              {tool.is_deprecated && <Badge variant="warning">Deprecated</Badge>}
            </div>

            <h1 className="max-w-4xl text-heading-1 text-balance sm:text-display-lg">{tool.name}</h1>

            <p className="max-w-3xl text-body-lg text-[var(--color-foreground-muted)]">
              {tool.tagline}
            </p>

            <ToolFacts tool={tool} />
          </div>
        </div>
      </header>

      <div className="mx-auto w-full max-w-[80rem] px-4 sm:px-6">
        {tool.is_deprecated && tool.successor && (
          <p className="mt-8 rounded-[var(--radius-md)] border border-[var(--color-warning)]/30 bg-[var(--color-warning)]/8 p-4 text-sm backdrop-blur-sm">
            This tool has been replaced by{" "}
            <Link
              href={`/tools/${tool.successor.slug}`}
              className="font-medium text-[var(--color-primary)] hover:underline"
            >
              {tool.successor.name}
            </Link>
            . It still works, but it is no longer being improved.
          </p>
        )}

        {/* ── The tool. Full bleed within the measure, nothing beside it. ────── */}
        <section aria-label={`${tool.name} workspace`} className="mt-8 lg:mt-10">
          <ToolRunner tool={tool} />
        </section>

        {/* ── Reference material, at reading width, once the work is done.
              The sidebar column only exists when there is something to put in it —
              an empty 18rem track just pushes the prose off-centre for no reason. */}
        <div
          className={`mt-16 grid gap-12 lg:items-start ${
            tool.related.length > 0 ? "lg:grid-cols-[minmax(0,1fr)_18rem]" : "lg:grid-cols-1"
          }`}
        >
          <div className="flex min-w-0 flex-col gap-12">
            {tool.description && (
              <section className="prose-measure">
                <p className="eyebrow">Overview</p>
                <h2 className="mt-3 text-heading-2">About this tool</h2>
                <p className="mt-3 leading-relaxed text-[var(--color-foreground-muted)]">
                  {tool.description}
                </p>
              </section>
            )}

            {tool.instructions && (
              <section aria-labelledby="how-to-use" className="prose-measure">
                <p className="eyebrow">Instructions</p>
                <h2 id="how-to-use" className="mt-3 text-heading-2">
                  How to use it
                </h2>
                <BlockRenderer document={tool.instructions} className="mt-5" />
              </section>
            )}

            {tool.faq.length > 0 && (
              <section aria-labelledby="faq" className="prose-measure">
                <p className="eyebrow">Questions</p>
                <h2 id="faq" className="mt-3 text-heading-2">
                  Frequently asked
                </h2>

                <div className="mt-5 flex flex-col gap-3">
                  {tool.faq.map((item) => (
                    <details key={item.q} className="panel group p-5">
                      <summary className="flex cursor-pointer list-none items-center justify-between gap-3 font-medium marker:hidden">
                        {item.q}
                        <ChevronRight
                          aria-hidden="true"
                          className="size-4 shrink-0 text-[var(--color-foreground-subtle)] transition-transform group-open:rotate-90"
                        />
                      </summary>
                      <p className="mt-3 text-sm leading-relaxed text-[var(--color-foreground-muted)]">
                        {item.a}
                      </p>
                    </details>
                  ))}
                </div>
              </section>
            )}
          </div>

          {tool.related.length > 0 && (
            <aside className="lg:sticky lg:top-24">
              <section aria-labelledby="related-tools" className="panel p-5">
                <h2 id="related-tools" className="eyebrow">
                  Pairs well with
                </h2>

                <ul className="mt-4 flex flex-col">
                  {tool.related.slice(0, 6).map((related) => (
                    <li key={related.slug}>
                      <Link
                        href={`/tools/${related.slug}`}
                        className="group flex items-center justify-between gap-2 border-b border-[var(--color-border-subtle)] py-2.5 text-sm text-[var(--color-foreground-muted)] transition-colors last:border-0 hover:text-[var(--color-foreground)]"
                      >
                        <span>{related.name}</span>
                        <ChevronRight className="size-3.5 shrink-0 transition-transform group-hover:translate-x-0.5" />
                      </Link>
                    </li>
                  ))}
                </ul>
              </section>
            </aside>
          )}
        </div>
      </div>

      {/* ── Discovery. Two shelves: the neighbourhood, then the wider catalog. ─ */}
      <Suspense fallback={<DiscoveryFallback />}>
        <Discovery tool={tool} />
      </Suspense>

      {/* ── The ask, last, where it belongs. ───────────────────────────────────*/}
      <section className="mx-auto w-full max-w-[80rem] px-4 pb-20 sm:px-6">
        <div className="panel relative overflow-hidden p-6 sm:p-10">
          <div aria-hidden="true" className="pointer-events-none absolute inset-0 bg-aurora" />
          <div className="relative grid gap-6 lg:grid-cols-[1.1fr_1fr] lg:items-center">
            <div className="flex flex-col gap-2">
              <p className="eyebrow">Stay sharp</p>
              <h2 className="text-heading-2 text-balance">
                One email a month, when a tool worth your time ships.
              </h2>
              <p className="text-sm text-[var(--color-foreground-muted)]">
                No drip sequence, no launch countdowns. Unsubscribe in one click.
              </p>
            </div>

            <NewsletterForm source={`tool:${tool.slug}`} />
          </div>
        </div>
      </section>

      <ToolStructuredData tool={tool} />
    </article>
  );
}

/** The measurable facts about a tool, as a mono strip under the title. */
function ToolFacts({ tool }: { tool: ToolDetail }) {
  const facts = [
    tool.stats.runs > 0
      ? { icon: Layers, label: `${formatNumber(tool.stats.runs)} runs` }
      : null,
    tool.stats.avg_duration_ms
      ? { icon: Clock, label: `~${Math.round(tool.stats.avg_duration_ms)} ms` }
      : null,
    // `success_rate` is already a percentage (0–100) on the API side, not a ratio.
    tool.stats.success_rate
      ? { icon: Gauge, label: `${Math.round(tool.stats.success_rate)}% success` }
      : null,
  ].filter((fact) => fact !== null);

  if (facts.length === 0) return null;

  return (
    <ul className="flex flex-wrap items-center gap-x-5 gap-y-2 font-mono text-[0.6875rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
      {facts.map((fact) => {
        const Icon = fact.icon;

        return (
          <li key={fact.label} className="flex items-center gap-1.5">
            <Icon aria-hidden="true" className="size-3.5" />
            <span className="tabular">{fact.label}</span>
          </li>
        );
      })}
    </ul>
  );
}

async function Discovery({ tool }: { tool: ToolDetail }) {
  const { sameCategory, elsewhere } = await discoverTools(tool);

  if (sameCategory.length === 0 && elsewhere.length === 0) return null;

  return (
    <div className="mx-auto w-full max-w-[80rem] px-4 py-16 sm:px-6">
      <hr className="rule-fade" />

      {sameCategory.length > 0 && (
        <Shelf
          eyebrow={tool.category ? tool.category.name : "Same shelf"}
          title={
            tool.category
              ? `More in ${tool.category.name.toLowerCase()}`
              : "More tools like this"
          }
          description={tool.category?.tagline ?? undefined}
          action={
            tool.category
              ? { href: `/tools?category=${tool.category.slug}`, label: "See the whole category" }
              : undefined
          }
          tools={sameCategory}
        />
      )}

      {elsewhere.length > 0 && (
        <Shelf
          eyebrow="Elsewhere in the catalog"
          title="Popular with people who used this"
          description="Different job, same workspace — these are the tools the rest of the catalog is busiest with."
          action={{ href: "/tools", label: "Browse all tools" }}
          tools={elsewhere}
        />
      )}
    </div>
  );
}

function Shelf({
  eyebrow,
  title,
  description,
  action,
  tools,
}: {
  eyebrow: string;
  title: string;
  description?: string;
  action?: { href: string; label: string };
  tools: ToolDetail["related"];
}) {
  return (
    <section aria-label={title} className="pt-12 first:pt-10">
      <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div className="flex max-w-2xl flex-col gap-2">
          <p className="eyebrow">{eyebrow}</p>
          <h2 className="text-heading-2 text-balance">{title}</h2>
          {description && (
            <p className="text-sm text-[var(--color-foreground-muted)]">{description}</p>
          )}
        </div>

        {action && (
          <Link
            href={action.href}
            className="group flex items-center gap-1.5 text-sm font-medium text-[var(--color-primary)]"
          >
            {action.label}
            <ChevronRight className="size-4 transition-transform group-hover:translate-x-0.5" />
          </Link>
        )}
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {tools.map((item) => (
          <ToolCard key={item.slug} tool={item} />
        ))}
      </div>
    </section>
  );
}

function DiscoveryFallback() {
  return (
    <div className="mx-auto w-full max-w-[80rem] px-4 py-16 sm:px-6">
      <hr className="rule-fade" />
      <div className="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {Array.from({ length: 4 }, (_, index) => (
          <ToolCardSkeleton key={index} />
        ))}
      </div>
    </div>
  );
}

function Breadcrumbs({ tool }: { tool: ToolDetail }) {
  const trail = [
    { href: "/", label: "Home" },
    { href: "/tools", label: "Tools" },
    ...(tool.category
      ? [{ href: `/tools?category=${tool.category.slug}`, label: tool.category.name }]
      : []),
  ];

  return (
    <nav aria-label="Breadcrumb">
      <ol className="flex flex-wrap items-center gap-1 font-mono text-[0.6875rem] uppercase tracking-[0.1em] text-[var(--color-foreground-subtle)]">
        {trail.map((crumb) => (
          <li key={crumb.href} className="flex items-center gap-1">
            <Link href={crumb.href} className="hover:text-[var(--color-foreground)]">
              {crumb.label}
            </Link>
            <ChevronRight className="size-3" aria-hidden="true" />
          </li>
        ))}
        <li aria-current="page" className="text-[var(--color-foreground-muted)]">
          {tool.name}
        </li>
      </ol>
    </nav>
  );
}

/**
 * `SoftwareApplication` + `HowTo` + `BreadcrumbList`.
 *
 * The `Offer` price reflects the tool's *real* tier — a premium tool must never
 * claim to be free. Mis-marked pricing is a manual-action risk, and it is also just
 * dishonest (see docs/16).
 */
function ToolStructuredData({ tool }: { tool: ToolDetail }) {
  const url = `${siteConfig.url}/tools/${tool.slug}`;
  const isFree = tool.tier.value !== "premium";

  const graph: Record<string, unknown>[] = [
    {
      "@type": "SoftwareApplication",
      "@id": `${url}#app`,
      name: tool.name,
      description: tool.tagline ?? tool.description,
      url,
      applicationCategory: "UtilitiesApplication",
      operatingSystem: "Any (web-based)",
      offers: {
        "@type": "Offer",
        price: isFree ? "0" : "19.00",
        priceCurrency: "USD",
        availability: "https://schema.org/InStock",
      },
      publisher: { "@type": "Organization", name: siteConfig.name, url: siteConfig.url },
    },
    {
      "@type": "BreadcrumbList",
      itemListElement: [
        { "@type": "ListItem", position: 1, name: "Home", item: siteConfig.url },
        { "@type": "ListItem", position: 2, name: "Tools", item: `${siteConfig.url}/tools` },
        { "@type": "ListItem", position: 3, name: tool.name, item: url },
      ],
    },
  ];

  if (tool.faq.length > 0) {
    graph.push({
      "@type": "FAQPage",
      mainEntity: tool.faq.map((item) => ({
        "@type": "Question",
        name: item.q,
        acceptedAnswer: { "@type": "Answer", text: item.a },
      })),
    });
  }

  return (
    <script
      type="application/ld+json"
      dangerouslySetInnerHTML={{
        __html: JSON.stringify({ "@context": "https://schema.org", "@graph": graph }),
      }}
    />
  );
}
