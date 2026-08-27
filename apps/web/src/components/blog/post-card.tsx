import Image from "next/image";
import Link from "next/link";

import { cn, formatDate } from "@/lib/utils";
import type { PostSummary } from "@/lib/types";

/**
 * The blog grid card. Two shapes from one component: `featured` gives the lead
 * post a wider image and larger type, everything else uses the compact form.
 */
export function PostCard({
  post,
  featured = false,
  className,
}: {
  post: PostSummary;
  featured?: boolean;
  className?: string;
}) {
  return (
    <article
      className={cn("panel panel-lift group relative flex flex-col overflow-hidden", className)}
    >
      <div className={cn("relative overflow-hidden bg-[var(--color-surface-sunken)]", featured ? "aspect-[2/1]" : "aspect-[16/9]")}>
        {post.featured_image ? (
          <Image
            src={post.featured_image.url}
            alt={post.featured_image.alt}
            fill
            className="object-cover transition-transform duration-500 ease-[var(--ease-standard)] group-hover:scale-[1.03]"
            sizes={featured ? "(max-width: 1024px) 100vw, 66vw" : "(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"}
            // The lead card is almost always the largest contentful paint.
            priority={featured}
          />
        ) : (
          /* No image is a normal state — an unillustrated post, or a related card
             whose media never loaded. A tinted monogram is a deliberate-looking
             placeholder; an empty grey rectangle just reads as broken. */
          <div
            aria-hidden="true"
            className="absolute inset-0 flex items-center justify-center bg-aurora"
          >
            <span className="font-mono text-4xl font-semibold text-[var(--color-foreground-subtle)]/50">
              {post.title.trim().charAt(0).toUpperCase()}
            </span>
          </div>
        )}
      </div>

      <div className="flex flex-1 flex-col gap-2.5 p-5">
        <div className="flex flex-wrap items-center gap-x-2 gap-y-1 font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
          {post.category ? (
            <span
              className="font-medium"
              style={post.category.accent_color ? { color: post.category.accent_color } : undefined}
            >
              {post.category.name}
            </span>
          ) : null}
          <span aria-hidden="true">·</span>
          <span className="tabular">{post.reading_minutes} min read</span>
        </div>

        <h3
          className={cn(
            "font-semibold leading-snug text-balance text-[var(--color-foreground)]",
            featured ? "text-heading-2" : "text-base",
          )}
        >
          {/* The whole card is the target, but only the title is the link — so the
              accessible name is the title and not a wall of card text. */}
          <Link href={`/blog/${post.slug}`} className="after:absolute after:inset-0">
            {post.title}
          </Link>
        </h3>

        {post.excerpt ? (
          <p
            className={cn(
              "text-sm leading-relaxed text-[var(--color-foreground-muted)]",
              featured ? "line-clamp-3" : "line-clamp-2",
            )}
          >
            {post.excerpt}
          </p>
        ) : null}

        <footer className="mt-auto flex items-center gap-2 pt-2 text-xs text-[var(--color-foreground-subtle)]">
          {post.author?.avatar_url ? (
            <Image
              src={post.author.avatar_url}
              alt=""
              width={20}
              height={20}
              className="size-5 rounded-full object-cover"
            />
          ) : null}
          {post.author ? <span>{post.author.name}</span> : null}
          {post.published_at ? (
            <>
              <span aria-hidden="true">·</span>
              <time dateTime={post.published_at}>{formatDate(post.published_at)}</time>
            </>
          ) : null}
        </footer>
      </div>
    </article>
  );
}

export function PostCardSkeleton({ featured = false }: { featured?: boolean }) {
  return (
    <div className="panel overflow-hidden">
      <div className={cn("animate-pulse bg-[var(--color-surface-sunken)]", featured ? "aspect-[2/1]" : "aspect-[16/9]")} />
      <div className="flex flex-col gap-3 p-5">
        <div className="h-3 w-24 animate-pulse rounded bg-[var(--color-surface-sunken)]" />
        <div className="h-4 w-4/5 animate-pulse rounded bg-[var(--color-surface-sunken)]" />
        <div className="h-3 w-full animate-pulse rounded bg-[var(--color-surface-sunken)]" />
      </div>
    </div>
  );
}
