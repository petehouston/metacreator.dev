import { Slot } from "@radix-ui/react-slot";
import { cva, type VariantProps } from "class-variance-authority";
import * as React from "react";

import { cn } from "@/lib/utils";

const buttonVariants = cva(
  // Base: everything every button shares. `disabled` styling lives here so no
  // variant can forget it.
  [
    "inline-flex cursor-pointer items-center justify-center gap-2 whitespace-nowrap font-medium",
    "transition-[background-color,border-color,color,box-shadow,transform] duration-150",
    "ease-[var(--ease-standard)] active:translate-y-px",
    // Tailwind v4 stopped setting `cursor: pointer` on buttons, so a button that
    // does not say so renders under an arrow and reads as inert. Spelled out here
    // rather than per call site: every button in the app is a thing you click.
    "disabled:pointer-events-none disabled:opacity-50",
    "[&_svg]:pointer-events-none [&_svg]:shrink-0",
  ],
  {
    variants: {
      variant: {
        primary:
          "bg-[var(--color-primary)] text-[var(--color-primary-foreground)] shadow-[var(--shadow-card)] hover:bg-[var(--color-primary-hover)]",
        secondary:
          "bg-[var(--color-surface-raised)] text-[var(--color-foreground)] border border-[var(--color-border)] hover:border-[var(--color-border-strong)] hover:bg-[var(--color-surface-sunken)]",
        ghost:
          "text-[var(--color-foreground-muted)] hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-foreground)]",
        outline:
          "border border-[var(--color-primary)] text-[var(--color-primary)] hover:bg-[var(--color-primary-subtle)]",
        danger:
          "bg-[var(--color-danger)] text-white hover:brightness-110",
        link: "text-[var(--color-primary)] underline-offset-4 hover:underline p-0 h-auto",
      },
      size: {
        sm: "h-8 rounded-[var(--radius-sm)] px-3 text-sm [&_svg]:size-4",
        md: "h-10 rounded-[var(--radius-md)] px-4 text-sm [&_svg]:size-4",
        lg: "h-12 rounded-[var(--radius-md)] px-6 text-base [&_svg]:size-5",
        xl: "h-14 rounded-[var(--radius-lg)] px-8 text-base [&_svg]:size-5",
        icon: "size-10 rounded-[var(--radius-md)] [&_svg]:size-4",
      },
    },
    defaultVariants: { variant: "primary", size: "md" },
  },
);

export interface ButtonProps
  extends React.ButtonHTMLAttributes<HTMLButtonElement>,
    VariantProps<typeof buttonVariants> {
  /** Render as the child element (e.g. a `<Link>`) while keeping button styling. */
  asChild?: boolean;
  loading?: boolean;
}

export function Button({
  className,
  variant,
  size,
  asChild = false,
  loading = false,
  disabled,
  children,
  ...props
}: ButtonProps) {
  const Comp = asChild ? Slot : "button";

  return (
    <Comp
      className={cn(buttonVariants({ variant, size }), className)}
      disabled={disabled || loading}
      // Announce the pending state rather than only showing a spinner.
      aria-busy={loading || undefined}
      {...props}
    >
      {loading ? (
        <>
          <Spinner />
          {children}
        </>
      ) : (
        children
      )}
    </Comp>
  );
}

function Spinner() {
  return (
    <svg className="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="3" />
      <path
        className="opacity-90"
        fill="currentColor"
        d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"
      />
    </svg>
  );
}

export { buttonVariants };
