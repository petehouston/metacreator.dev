import { ChevronRight } from "lucide-react";
import type { Metadata } from "next";
import Image from "next/image";
import Link from "next/link";
import { notFound } from "next/navigation";

import { BlockRenderer, toolSlugsIn } from "@/components/blocks/block-renderer";
import { PostCard } from "@/components/blog/post-card";
import { NewsletterForm } from "@/components/site/newsletter-form";
import { ToolCard } from "@/components/tools/tool-card";
import { Badge } from "@/components/ui/badge";
import { siteConfig } from "@/config/site";
import { api, ApiRequestError } from "@/lib/api";
import { blogDisplay } from "@/lib/site-settings";
import { toolsForReader } from "@/lib/tool-discovery";
import { formatDate } from "@/lib/utils";
import type { PostDetail, ToolSummary } from "@/lib/types";

/**
 * The article page. Alongside tool pages this is where organic traffic lands, so it
 * carries complete metadata, `Article` structured data that matches what is on the
 * page, and internal links out to the tools the post talks about.
 */
async function loadPost(slug: string): Promise<PostDetail | null> {
  try {
    return await api.blog.get(slug);
  } catch (error) {
    // 410 is a post that was published and withdrawn; 404 is one that never was.
    // Both are "gone" for rendering purposes — the status code is the API's job.
    if (error instanceof ApiRequestError && (error.status === 404 || error.status === 410)) {
      return null;
    }
    throw error;
  }
}

export async function generateMetadata({ params }: PageProps<"/blog/[slug]">): Promise<Metadata> {
  const { slug } = await params;
  const post = await loadPost(slug);

  // notFound() here rather than a "Post not found" title: metadata resolves before
  // the response is committed, so this is the last point at which Next can still
  // send a real 404 status. Returning metadata instead produces a soft 404 — a page
  // that says 404 while the HTTP status says 200, which search engines will index.
  if (!post) notFound();

  const title = post.seo.title ?? post.title;
  const description = post.seo.description ?? post.excerpt ?? siteConfig.description;
  const url = `${siteConfig.url}/blog/${post.slug}`;
  const image = post.seo.og_image_url ?? post.featured_image?.url;

  return {
    title,
    description,
    alternates: { canonical: post.seo.canonical_url ?? `/blog/${post.slug}` },
    robots: post.seo.robots?.includes("noindex") ? { index: false, follow: true } : undefined,
    authors: post.author ? [{ name: post.author.name }] : undefined,
    openGraph: {
      type: "article",
      url,
      title: post.seo.og_title ?? title,
      description: post.seo.og_description ?? description,
      siteName: siteConfig.name,
      publishedTime: post.published_at ?? undefined,
      authors: post.author ? [post.author.name] : undefined,
      tags: post.tags?.map((tag) => tag.name),
      images: image ? [{ url: image, width: 1200, height: 630 }] : undefined,
    },
    twitter: {
      card: "summary_large_image",
      title: post.seo.og_title ?? title,
      description: post.seo.og_description ?? description,
      images: image ? [image] : undefined,
    },
  };
}

export default async function PostPage({ params }: PageProps<"/blog/[slug]">) {
  const { slug } = await params;
  const post = await loadPost(slug);

  if (!post) notFound();

  const document = { version: 1, blocks: post.blocks };
  const [tools, display] = await Promise.all([resolveTools(document), blogDisplay()]);

  // The tools the article itself reached for lead the shelf below it; the rest is
  // topped up from the catalog. Best-effort — an article still reads fine without.
  const toolPicks = await toolsForReader({ prefer: Object.values(tools), limit: 6 }).catch(
    () => [],
  );

  const showAuthor = display.showAuthor && post.author !== null && post.author !== undefined;
  const showDate = display.showPublishedDate && Boolean(post.published_at);

  return (
    <article className="mx-auto w-full max-w-[80rem] px-4 py-10 sm:px-6 lg:py-14">
      <Breadcrumbs post={post} />

      <header className="mx-auto mt-6 flex max-w-[46rem] flex-col gap-5">
        {(display.showCategories && post.category) || display.showReadingTime ? (
          <div className="flex flex-wrap items-center gap-2">
            {display.showCategories && post.category ? (
              <Link href={`/blog?category=${post.category.slug}`}>
                <Badge variant="neutral" size="md" className="cursor-pointer">
                  {post.category.name}
                </Badge>
              </Link>
            ) : null}
            {display.showReadingTime ? (
              <span className="tabular font-mono text-[0.6875rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
                {post.reading_minutes} min read
              </span>
            ) : null}
          </div>
        ) : null}

        <h1 className="text-heading-1 text-balance sm:text-display-lg">{post.title}</h1>

        {post.excerpt ? (
          <p className="text-body-lg text-[var(--color-foreground-muted)]">{post.excerpt}</p>
        ) : null}

        {/* The rule under the byline belongs to the byline: with both the author
            and the date hidden, an empty bordered strip is left behind. */}
        {showAuthor || showDate ? (
          <div className="flex items-center gap-3 border-t border-[var(--color-border-subtle)] pt-5">
            {showAuthor && post.author?.avatar_url ? (
              <Image
                src={post.author.avatar_url}
                alt=""
                width={36}
                height={36}
                className="size-9 rounded-full object-cover"
              />
            ) : null}
            <div className="flex flex-col text-sm">
              {showAuthor && post.author ? (
                <span className="font-medium text-[var(--color-foreground)]">
                  {post.author.name}
                </span>
              ) : null}
              {showDate && post.published_at ? (
                <time
                  dateTime={post.published_at}
                  className="text-[var(--color-foreground-subtle)]"
                >
                  {formatDate(post.published_at)}
                </time>
              ) : null}
            </div>
          </div>
        ) : null}
      </header>

      {post.featured_image && display.showFeaturedImage ? (
        <figure className="mx-auto mt-8 max-w-[60rem]">
          <Image
            src={post.featured_image.url}
            alt={post.featured_image.alt}
            width={post.featured_image.width ?? 1200}
            height={post.featured_image.height ?? 630}
            className="w-full rounded-[var(--radius-xl)] border border-[var(--color-border-subtle)] object-cover"
            sizes="(max-width: 1024px) 100vw, 60rem"
            priority
          />
        </figure>
      ) : null}

      {/* The article measure, matching what the editor canvas will show. */}
      <div className="mx-auto mt-10 max-w-[46rem]">
        <BlockRenderer document={document} className="gap-6" tools={tools} />
      </div>

      {display.showTags && post.tags && post.tags.length > 0 ? (
        <div className="mx-auto mt-10 flex max-w-[46rem] flex-wrap items-center gap-2 border-t border-[var(--color-border-subtle)] pt-6">
          <span className="eyebrow">Tagged</span>
          {post.tags.map((tag) => (
            <Link key={tag.slug} href={`/blog?tag=${tag.slug}`}>
              <Badge variant="neutral" className="cursor-pointer">
                {tag.name}
              </Badge>
            </Link>
          ))}
        </div>
      ) : null}

      {toolPicks.length > 0 ? (
        <section aria-labelledby="post-tools" className="mt-16">
          <hr className="rule-fade" />

          <div className="mt-10 flex flex-wrap items-end justify-between gap-4">
            <div className="flex max-w-2xl flex-col gap-2">
              <p className="eyebrow">Put it to work</p>
              <h2 id="post-tools" className="text-heading-2 text-balance">
                Tools for the job you just read about
              </h2>
              <p className="text-sm text-[var(--color-foreground-muted)]">
                Free to run, most of them without an account.
              </p>
            </div>

            <Link
              href="/tools"
              className="group flex items-center gap-1.5 text-sm font-medium text-[var(--color-primary)]"
            >
              Browse all tools
              <ChevronRight className="size-4 transition-transform group-hover:translate-x-0.5" />
            </Link>
          </div>

          <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {toolPicks.map((tool) => (
              <ToolCard key={tool.slug} tool={tool} />
            ))}
          </div>
        </section>
      ) : null}

      {display.showRelatedPosts && post.related.length > 0 ? (
        <section aria-labelledby="keep-reading" className="mt-16">
          <hr className="rule-fade" />

          <div className="mt-10 flex flex-col gap-2">
            <p className="eyebrow">Keep reading</p>
            <h2 id="keep-reading" className="text-heading-2 text-balance">
              More from the blog
            </h2>
          </div>

          <div className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {post.related.map((related) => (
              <PostCard key={related.slug} post={related} display={display} />
            ))}
          </div>
        </section>
      ) : null}

      <aside className="mt-16">
        <div className="panel relative overflow-hidden p-6 sm:p-10">
          <div aria-hidden="true" className="pointer-events-none absolute inset-0 bg-aurora" />
          <div className="relative grid gap-6 lg:grid-cols-[1.1fr_1fr] lg:items-center">
            <div className="flex flex-col gap-2">
              <p className="eyebrow">Stay sharp</p>
              <h2 className="text-heading-2 text-balance">
                Get the next one in your inbox.
              </h2>
              <p className="text-sm text-[var(--color-foreground-muted)]">
                One email a month. No drip sequence, unsubscribe in one click.
              </p>
            </div>

            <NewsletterForm source="blog-post" />
          </div>
        </div>
      </aside>

      <PostJsonLd post={post} />
    </article>
  );
}

/**
 * Resolves every `toolCard` block in one pass.
 *
 * The requests are cached and tagged, so a warm page pays nothing; a tool that has
 * since been withdrawn simply drops out and the block falls back to a plain link.
 */
async function resolveTools(
  document: { version: number; blocks: PostDetail["blocks"] },
): Promise<Record<string, ToolSummary>> {
  const slugs = toolSlugsIn(document);

  if (slugs.length === 0) return {};

  const resolved = await Promise.all(
    slugs.map((slug) => api.tools.get(slug).catch(() => null)),
  );

  return Object.fromEntries(
    resolved.filter((tool) => tool !== null).map((tool) => [tool.slug, tool]),
  );
}

function Breadcrumbs({ post }: { post: PostDetail }) {
  return (
    <nav aria-label="Breadcrumb">
      <ol className="flex flex-wrap items-center gap-1 font-mono text-[0.6875rem] uppercase tracking-[0.1em] text-[var(--color-foreground-subtle)]">
        <li>
          <Link href="/" className="hover:text-[var(--color-foreground)]">
            Home
          </Link>
        </li>
        <ChevronRight aria-hidden="true" className="size-3.5" />
        <li>
          <Link href="/blog" className="hover:text-[var(--color-foreground)]">
            Blog
          </Link>
        </li>
        {post.category ? (
          <>
            <ChevronRight aria-hidden="true" className="size-3.5" />
            <li>
              <Link
                href={`/blog?category=${post.category.slug}`}
                className="hover:text-[var(--color-foreground)]"
              >
                {post.category.name}
              </Link>
            </li>
          </>
        ) : null}
      </ol>
    </nav>
  );
}

function PostJsonLd({ post }: { post: PostDetail }) {
  const url = `${siteConfig.url}/blog/${post.slug}`;
  const image = post.seo.og_image_url ?? post.featured_image?.url;

  const graph: Record<string, unknown>[] = [
    {
      "@type": post.seo.schema_type ?? "BlogPosting",
      "@id": `${url}#article`,
      headline: post.title,
      description: post.seo.description ?? post.excerpt ?? undefined,
      url,
      mainEntityOfPage: url,
      datePublished: post.published_at ?? undefined,
      wordCount: post.word_count,
      image: image ? [image] : undefined,
      author: post.author
        ? { "@type": "Person", name: post.author.name }
        : { "@type": "Organization", name: siteConfig.name },
      publisher: { "@type": "Organization", name: siteConfig.name, url: siteConfig.url },
      articleSection: post.category?.name,
      keywords: post.tags?.map((tag) => tag.name).join(", ") || undefined,
    },
    {
      "@type": "BreadcrumbList",
      itemListElement: [
        { "@type": "ListItem", position: 1, name: "Home", item: siteConfig.url },
        { "@type": "ListItem", position: 2, name: "Blog", item: `${siteConfig.url}/blog` },
        { "@type": "ListItem", position: 3, name: post.title, item: url },
      ],
    },
  ];

  // An FAQ block on the page earns FAQPage markup — and only then, because
  // markup that is not reflected on the page is a manual-action risk.
  const faqBlock = post.blocks.find((block) => block.type === "faq");
  const faqItems = (faqBlock?.data?.items ?? []) as { question?: string; answer?: string }[];

  if (faqItems.length > 0) {
    graph.push({
      "@type": "FAQPage",
      mainEntity: faqItems.map((item) => ({
        "@type": "Question",
        name: item.question,
        acceptedAnswer: { "@type": "Answer", text: stripTags(item.answer ?? "") },
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

/** JSON-LD values are plain text; leaving markup in produces invalid rich results. */
function stripTags(html: string): string {
  return html.replace(/<[^>]*>/g, "").trim();
}
