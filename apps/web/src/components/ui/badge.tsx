import { cva, type VariantProps } from "class-variance-authority";
import type * as React from "react";

import { cn } from "@/lib/utils";
import type { ToolTier } from "@/lib/types";

const badgeVariants = cva(
  "inline-flex items-center gap-1 rounded-full border font-medium whitespace-nowrap",
  {
    variants: {
      variant: {
        neutral:
          "border-[var(--color-border)] bg-[var(--color-surface-sunken)] text-[var(--color-foreground-muted)]",
        brand:
          "border-[var(--color-brand-500)]/30 bg-[var(--color-primary-subtle)] text-[var(--color-primary)]",
        success:
          "border-[var(--color-success)]/30 bg-[var(--color-success)]/10 text-[var(--color-success)]",
        warning:
          "border-[var(--color-warning)]/30 bg-[var(--color-warning)]/10 text-[var(--color-warning)]",
        danger:
          "border-[var(--color-danger)]/30 bg-[var(--color-danger)]/10 text-[var(--color-danger)]",
        solid:
          "border-transparent bg-[var(--color-foreground)] text-[var(--color-background)]",
      },
      size: {
        sm: "px-2 py-0.5 text-[0.6875rem]",
        md: "px-2.5 py-1 text-xs",
      },
    },
    defaultVariants: { variant: "neutral", size: "sm" },
  },
);

export function Badge({
  className,
  variant,
  size,
  ...props
}: React.HTMLAttributes<HTMLSpanElement> & VariantProps<typeof badgeVariants>) {
  return <span className={cn(badgeVariants({ variant, size }), className)} {...props} />;
}

/**
 * The tier badge appears on every card and header, so its colour language has to be
 * learnable at a glance: neutral = free, brand = needs you, solid = paid.
 */
export function TierBadge({
  tier,
  className,
}: {
  tier: { value: ToolTier; label: string };
  className?: string;
}) {
  const variant = (
    { free: "success", account: "brand", premium: "solid" } as const
  )[tier.value];

  return (
    <Badge variant={variant} className={className}>
      {tier.value === "premium" && <span aria-hidden="true">✦</span>}
      {tier.label}
    </Badge>
  );
}
