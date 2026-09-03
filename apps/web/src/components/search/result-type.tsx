import { FileText, Newspaper, Trophy, Wrench } from "lucide-react";
import type { LucideIcon } from "lucide-react";

import type { SearchResultType } from "@/lib/types";

/**
 * How each kind of result presents itself.
 *
 * One table, read by both surfaces — the dropdown's icon column and the results
 * page's badge. Two tables would be two chances for a tool to be a wrench in the
 * dropdown and something else on the page it leads to, which is exactly the
 * inconsistency that makes an icon language unlearnable.
 *
 * The colours are the tokens already carrying those meanings elsewhere on the site:
 * brand for tools (the product), accent for articles, ember for rankings, and
 * plain foreground for the site's own pages — which are navigation, not content,
 * and should not compete for attention.
 */
interface ResultTypeMeta {
  Icon: LucideIcon;
  /** The tinted disc behind the icon. An `oklch` triple or a CSS variable. */
  accent: string;
}

const META: Record<SearchResultType, ResultTypeMeta> = {
  tool: { Icon: Wrench, accent: "var(--color-primary)" },
  post: { Icon: Newspaper, accent: "var(--color-accent)" },
  top_ranking: { Icon: Trophy, accent: "var(--color-ember-500)" },
  page: { Icon: FileText, accent: "var(--color-foreground-subtle)" },
};

export function resultTypeMeta(type: SearchResultType): ResultTypeMeta {
  // A type the API added and this build has not heard of still renders as
  // *something*, rather than throwing inside a dropdown that is open on screen.
  return META[type] ?? META.page;
}

/**
 * The leading icon: a line icon on a tinted disc, sized to sit level with the
 * first line of a title beside it.
 */
export function ResultTypeIcon({
  type,
  className,
}: {
  type: SearchResultType;
  className?: string;
}) {
  const { Icon, accent } = resultTypeMeta(type);

  return (
    <span
      aria-hidden="true"
      className={
        "flex size-8 shrink-0 items-center justify-center rounded-[var(--radius-md)] " +
        (className ?? "")
      }
      style={{ backgroundColor: `color-mix(in oklab, ${accent} 12%, transparent)`, color: accent }}
    >
      <Icon className="size-4" strokeWidth={1.75} />
    </span>
  );
}
