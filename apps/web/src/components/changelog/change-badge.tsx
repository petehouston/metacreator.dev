import { Badge } from "@/components/ui/badge";
import type { ChangeTone } from "@/lib/types";
import { cn } from "@/lib/utils";

/**
 * The one place a change tone becomes a colour.
 *
 * The API decides the tone (`ChangeType::tone()`) and the UI decides what that tone
 * looks like. Splitting it this way means adding a change type server-side needs no
 * frontend change, while restyling the badge needs no API change.
 */
const VARIANT: Record<ChangeTone, "success" | "brand" | "warning" | "danger" | "neutral"> = {
  success: "success",
  info: "brand",
  warning: "warning",
  danger: "danger",
  muted: "neutral",
};

export function ChangeBadge({
  label,
  tone,
  className,
}: {
  label: string;
  tone: ChangeTone;
  className?: string;
}) {
  return (
    <Badge
      variant={VARIANT[tone] ?? "neutral"}
      className={cn(
        // Fixed width so the badges form a column down the left of a release rather
        // than a ragged edge — the single thing that makes a long release scannable.
        "w-[5.5rem] shrink-0 justify-center font-mono text-[0.625rem] uppercase tracking-[0.08em]",
        className,
      )}
    >
      {label}
    </Badge>
  );
}
