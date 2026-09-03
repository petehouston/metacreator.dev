import Image from "next/image";
import Link from "next/link";

import { ResultTypeIcon, resultTypeMeta } from "@/components/search/result-type";
import { Badge } from "@/components/ui/badge";
import type { SearchResult } from "@/lib/types";

/**
 * One row of the results page.
 *
 * A list, not a grid. A grid asks the reader to compare cards; a result list asks
 * them to scan for the one thing they came for, and scanning is faster down a
 * single column of consistent rows than across a mosaic of them.
 *
 * The whole row is one link — the title, the image and the summary all lead to the
 * same place, so making only the heading clickable would leave most of the row an
 * inert target that looks like it should work.
 */
export function SearchResultCard({ result }: { result: SearchResult }) {
  const { accent } = resultTypeMeta(result.type);

  return (
    <li>
      <Link
        href={result.url}
        className="panel panel-lift group flex gap-4 overflow-hidden p-3 sm:gap-5 sm:p-4"
      >
        <div className="relative aspect-[4/3] w-24 shrink-0 overflow-hidden rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)] sm:w-36">
          {result.image ? (
            <Image
              src={result.image}
              alt=""
              fill
              className="object-cover transition-transform duration-500 ease-[var(--ease-standard)] group-hover:scale-[1.03]"
              sizes="(max-width: 640px) 6rem, 9rem"
            />
          ) : (
            /* No image is the *normal* case here, not a failure — a static page
               has none and most tools have none either. The type's own icon on a
               tinted ground is a deliberate-looking placeholder and doubles as a
               second read of what kind of result this is; an empty grey rectangle
               would just look broken twelve times down the page. */
            <span
              aria-hidden="true"
              className="absolute inset-0 flex items-center justify-center"
              style={{ backgroundColor: `color-mix(in oklab, ${accent} 8%, transparent)` }}
            >
              <ResultTypeIcon type={result.type} className="size-10" />
            </span>
          )}
        </div>

        <div className="flex min-w-0 flex-1 flex-col gap-1.5 py-0.5">
          <div className="flex flex-wrap items-center gap-2">
            <Badge variant="neutral">{result.type_label}</Badge>
            {result.context && (
              <span className="truncate text-xs text-[var(--color-foreground-subtle)]">
                {result.context}
              </span>
            )}
          </div>

          {/* Wraps freely: the title is what the reader is scanning for, and a
              truncated one is the single most likely thing to make them miss the
              result they wanted. */}
          <h2 className="text-balance text-base font-semibold text-[var(--color-foreground)] transition-colors group-hover:text-[var(--color-primary)] sm:text-lg">
            {result.title}
          </h2>

          {result.summary && (
            /* Clamped, not wrapped: a post excerpt can run for a paragraph, and a
               list whose rows are different heights is much harder to scan. */
            <p className="line-clamp-2 text-sm text-[var(--color-foreground-muted)]">
              {result.summary}
            </p>
          )}
        </div>
      </Link>
    </li>
  );
}
