import { ArrowLeft } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";

import { ReleaseEntry } from "@/components/changelog/release-entry";
import { siteConfig } from "@/config/site";
import { api, ApiRequestError } from "@/lib/api";

/**
 * One release on its own page.
 *
 * Worth a route rather than only an anchor on the timeline: a release is the thing
 * a support reply, a release email or a social post links to, and those links must
 * not depend on the entry still being on page one of the list.
 */
async function load(slug: string) {
  return api.changelog.get(slug).catch((error: unknown) => {
    if (error instanceof ApiRequestError && error.status === 404) notFound();
    throw error;
  });
}

export async function generateMetadata({
  params,
}: PageProps<"/changelog/[slug]">): Promise<Metadata> {
  const { slug } = await params;
  const release = await load(slug).catch(() => null);

  if (!release) return { title: "Changelog" };

  const name = release.version ? `${release.version} — ${release.title}` : release.title;

  // The summary if there is one, otherwise the first few entries — which is a
  // truer description of a release than its title alone.
  const description =
    release.summary ??
    release.items
      .slice(0, 3)
      .map((item) => `${item.type_label}: ${item.title}`)
      .join(" · ");

  return {
    title: `${name} — Changelog`,
    description,
    alternates: { canonical: `/changelog/${release.slug}` },
    openGraph: {
      title: `${name} | ${siteConfig.name}`,
      description,
      url: `${siteConfig.url}/changelog/${release.slug}`,
      type: "article",
      publishedTime: release.released_at ?? undefined,
    },
  };
}

export default async function ReleasePage({ params }: PageProps<"/changelog/[slug]">) {
  const { slug } = await params;
  const release = await load(slug);

  return (
    <div className="mx-auto w-full max-w-[52rem] px-4 py-12 sm:px-6 lg:py-16">
      <Link
        href="/changelog"
        className="inline-flex items-center gap-1.5 text-sm text-[var(--color-foreground-muted)] transition-colors hover:text-[var(--color-foreground)]"
      >
        <ArrowLeft className="size-4" aria-hidden="true" />
        All releases
      </Link>

      {/* The same entry component the timeline uses. A release rendered one way on
          the list and another on its own page is how the two drift. */}
      <div className="mt-6">
        <ReleaseEntry release={release} standalone />
      </div>
    </div>
  );
}
