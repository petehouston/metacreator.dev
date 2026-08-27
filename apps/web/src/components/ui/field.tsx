"use client";

import * as React from "react";

import { cn } from "@/lib/utils";

/**
 * Label + control + hint + error, wired together with the right ARIA attributes.
 *
 * Doing this once means no form in the product can ship an input whose error is
 * invisible to a screen reader — which is the usual way accessibility rots.
 */
export function Field({
  id,
  label,
  hint,
  error,
  required,
  counter,
  children,
  className,
}: {
  id: string;
  label: string;
  hint?: string;
  error?: string;
  required?: boolean;
  /** Live "42/300" style count, shown on the label row once typing starts. */
  counter?: string;
  children: (props: {
    id: string;
    "aria-describedby"?: string;
    "aria-invalid"?: boolean;
    "aria-required"?: boolean;
  }) => React.ReactNode;
  className?: string;
}) {
  const hintId = hint ? `${id}-hint` : undefined;
  const errorId = error ? `${id}-error` : undefined;
  const describedBy = [hintId, errorId].filter(Boolean).join(" ") || undefined;

  return (
    <div className={cn("flex flex-col gap-1.5", className)}>
      <div className="flex items-baseline justify-between gap-3">
        <label htmlFor={id} className="text-sm font-medium text-[var(--color-foreground)]">
          {label}
          {required && (
            <span className="ml-1 text-[var(--color-danger)]" aria-hidden="true">
              *
            </span>
          )}
        </label>

        {/* Decorative: the same number is already on the input as `maxLength`, and
            announcing it on every keystroke would be unbearable. */}
        {counter && (
          <span aria-hidden="true" className="tabular font-mono text-[0.625rem] text-[var(--color-foreground-subtle)]">
            {counter}
          </span>
        )}
      </div>

      {children({
        id,
        "aria-describedby": describedBy,
        "aria-invalid": error ? true : undefined,
        "aria-required": required || undefined,
      })}

      {hint && !error && (
        <p id={hintId} className="text-xs text-[var(--color-foreground-subtle)]">
          {hint}
        </p>
      )}

      {error && (
        <p id={errorId} role="alert" className="text-xs font-medium text-[var(--color-danger)]">
          {error}
        </p>
      )}
    </div>
  );
}

const controlClasses = [
  // `min-w-0` matters as much as `w-full`: inside a grid or flex row an input
  // without it refuses to shrink and pushes the layout wider than the viewport.
  "w-full min-w-0 rounded-[var(--radius-md)] border border-[var(--color-border)]",
  "bg-[var(--color-surface-raised)] px-3.5 py-2 text-sm text-[var(--color-foreground)]",
  "placeholder:text-[var(--color-foreground-subtle)]",
  "transition-[border-color,box-shadow,background-color] duration-150",
  "hover:border-[var(--color-border-strong)]",
  "focus:border-[var(--color-ring)] focus:outline-none focus:ring-2 focus:ring-[var(--color-ring)]/25",
  "aria-[invalid=true]:border-[var(--color-danger)] aria-[invalid=true]:ring-[var(--color-danger)]/20",
  "disabled:cursor-not-allowed disabled:opacity-60",
].join(" ");

export function Input({ className, ...props }: React.InputHTMLAttributes<HTMLInputElement>) {
  return <input className={cn(controlClasses, "h-11", className)} {...props} />;
}

export function Textarea({
  className,
  ...props
}: React.TextareaHTMLAttributes<HTMLTextAreaElement>) {
  return <textarea className={cn(controlClasses, "min-h-32 resize-y leading-relaxed", className)} {...props} />;
}

export function Select({
  className,
  children,
  ...props
}: React.SelectHTMLAttributes<HTMLSelectElement>) {
  return (
    <select className={cn(controlClasses, "h-11 pr-8", className)} {...props}>
      {/*
        `children` is pulled out of the spread and passed through `Children.toArray`.

        Children written inline compile to `jsxs`, which tells React the list is
        static and needs no keys. Forwarding them inside `{...props}` loses that:
        React then sees a plain array and warns "each child in a list should have a
        unique key" for every `<option>` — on every Select in the app, from a wrapper
        the call site cannot see or fix. `toArray` assigns the keys itself, which is
        the API that exists for exactly this.
      */}
      {React.Children.toArray(children)}
    </select>
  );
}

export function Checkbox({
  label,
  hint,
  className,
  ...props
}: React.InputHTMLAttributes<HTMLInputElement> & { label: string; hint?: string }) {
  return (
    <label
      className={cn(
        "flex cursor-pointer items-start gap-2.5 rounded-[var(--radius-md)] py-1",
        className,
      )}
    >
      <input
        type="checkbox"
        className="mt-0.5 size-4 shrink-0 rounded border-[var(--color-border-strong)] accent-[var(--color-primary)]"
        {...props}
      />
      <span className="flex flex-col gap-0.5">
        <span className="text-sm text-[var(--color-foreground)]">{label}</span>
        {hint && (
          <span className="text-xs text-[var(--color-foreground-subtle)]">{hint}</span>
        )}
      </span>
    </label>
  );
}
