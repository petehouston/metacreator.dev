"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import * as React from "react";

import { cn } from "@/lib/utils";

const TABS = [
  { href: "/dashboard/settings", label: "Profile" },
  { href: "/dashboard/settings/security", label: "Security" },
  { href: "/dashboard/settings/notifications", label: "Notifications" },
];

/**
 * Settings split into three screens instead of one long scroll.
 *
 * Tabs rather than a nested sidebar: three destinations do not earn a second rail,
 * and a tab strip keeps the deep-linkable URL that a support reply can point at.
 */
export function SettingsTabs() {
  const pathname = usePathname();

  return (
    <div
      className="mb-6 flex gap-1 overflow-x-auto border-b border-[var(--color-border-subtle)]"
      role="navigation"
      aria-label="Settings"
    >
      {TABS.map((tab) => {
        const active = pathname === tab.href;

        return (
          <Link
            key={tab.href}
            href={tab.href}
            aria-current={active ? "page" : undefined}
            className={cn(
              "relative whitespace-nowrap px-3 py-2.5 text-sm font-medium transition-colors",
              active
                ? "text-[var(--color-foreground)]"
                : "text-[var(--color-foreground-muted)] hover:text-[var(--color-foreground)]",
            )}
          >
            {tab.label}

            {active && (
              <span
                aria-hidden="true"
                className="absolute inset-x-2 -bottom-px h-0.5 rounded-full bg-[var(--color-primary)]"
              />
            )}
          </Link>
        );
      })}
    </div>
  );
}
