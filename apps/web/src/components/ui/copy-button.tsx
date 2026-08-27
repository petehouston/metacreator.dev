"use client";

import { Check, Copy } from "lucide-react";
import * as React from "react";

import { Button, type ButtonProps } from "@/components/ui/button";

/**
 * Copying a result is the most common action on this site, so the affordance gets
 * real feedback: the label changes, and the change is announced.
 */
export function CopyButton({
  value,
  label = "Copy",
  copiedLabel = "Copied",
  ...props
}: { value: string; label?: string; copiedLabel?: string } & Omit<ButtonProps, "onClick">) {
  const [copied, setCopied] = React.useState(false);

  React.useEffect(() => {
    if (!copied) return;
    const timer = setTimeout(() => setCopied(false), 1800);
    return () => clearTimeout(timer);
  }, [copied]);

  return (
    <Button
      variant="ghost"
      size="sm"
      onClick={async () => {
        try {
          await navigator.clipboard.writeText(value);
          setCopied(true);
        } catch {
          // Clipboard access can be denied (insecure context, permissions policy).
          // Falling back to selection keeps the action possible rather than silent.
          const area = document.createElement("textarea");
          area.value = value;
          document.body.append(area);
          area.select();
          document.execCommand("copy");
          area.remove();
          setCopied(true);
        }
      }}
      {...props}
    >
      {copied ? <Check className="text-[var(--color-success)]" /> : <Copy />}
      <span aria-live="polite">{copied ? copiedLabel : label}</span>
    </Button>
  );
}
