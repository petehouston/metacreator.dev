"use client";

import { AlertCircle, CheckCircle2 } from "lucide-react";
import * as React from "react";

import { cn } from "@/lib/utils";

/**
 * Form-level feedback.
 *
 * `role="alert"` on errors so a screen reader announces them without the user having
 * to go looking; successes use `status`, which does not interrupt.
 */
export function FormAlert({
  tone = "error",
  children,
  className,
}: {
  tone?: "error" | "success";
  children: React.ReactNode;
  className?: string;
}) {
  const Icon = tone === "error" ? AlertCircle : CheckCircle2;

  return (
    <div
      role={tone === "error" ? "alert" : "status"}
      className={cn(
        "flex items-start gap-2.5 rounded-[var(--radius-md)] border px-3.5 py-3 text-sm",
        tone === "error"
          ? "border-[var(--color-danger)]/30 bg-[var(--color-danger)]/8 text-[var(--color-danger)]"
          : "border-[var(--color-success,var(--color-primary))]/30 bg-[var(--color-primary)]/8 text-[var(--color-foreground)]",
        className,
      )}
    >
      <Icon className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
      <div className="leading-relaxed">{children}</div>
    </div>
  );
}
