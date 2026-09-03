"use client";

import { ChevronDown } from "lucide-react";
import Link from "next/link";
import * as React from "react";

import { cn } from "@/lib/utils";

export interface NavDropdownItem {
  href: string;
  label: string;
  /** The small grey line under the label. */
  hint?: string;
  /** An oklch triple (`L C H`) for the leading dot. */
  accent?: string;
}

/**
 * A header nav item that opens a panel of links.
 *
 * **Hover opens it, but hover is not the only way in.** The trigger is a real
 * button: it opens on click, on Enter and on Space, and closes on Escape — which
 * matters because a menu that only exists on hover is unreachable by keyboard and
 * invisible on a touchscreen, where the first tap on a hover-only trigger does
 * nothing at all.
 *
 * **The close is delayed by a beat.** The panel sits below the trigger with a gap
 * the pointer has to cross, and closing on the first `mouseleave` means the menu
 * vanishes mid-reach. A short timer, cancelled the moment the pointer arrives
 * anywhere inside, is the difference between a menu that feels solid and one that
 * feels like it is running away.
 */
export function NavDropdown({
  label,
  href,
  items,
  active,
  footer,
}: {
  label: string;
  /** The trigger is also a link — the menu is a shortcut, not the only route in. */
  href: string;
  items: NavDropdownItem[];
  active: boolean;
  footer?: React.ReactNode;
}) {
  const [open, setOpen] = React.useState(false);
  const closeTimer = React.useRef<ReturnType<typeof setTimeout> | null>(null);
  const containerRef = React.useRef<HTMLDivElement>(null);

  const cancelClose = React.useCallback(() => {
    if (closeTimer.current) clearTimeout(closeTimer.current);
    closeTimer.current = null;
  }, []);

  const scheduleClose = React.useCallback(() => {
    cancelClose();
    closeTimer.current = setTimeout(() => setOpen(false), 140);
  }, [cancelClose]);

  React.useEffect(() => cancelClose, [cancelClose]);

  React.useEffect(() => {
    if (!open) return;

    function onKeyDown(event: KeyboardEvent) {
      if (event.key === "Escape") setOpen(false);
    }

    function onPointerDown(event: MouseEvent) {
      if (!containerRef.current?.contains(event.target as Node)) setOpen(false);
    }

    document.addEventListener("keydown", onKeyDown);
    document.addEventListener("mousedown", onPointerDown);

    return () => {
      document.removeEventListener("keydown", onKeyDown);
      document.removeEventListener("mousedown", onPointerDown);
    };
  }, [open]);

  if (items.length === 0) return null;

  return (
    <div
      ref={containerRef}
      className="relative"
      onMouseEnter={() => {
        cancelClose();
        setOpen(true);
      }}
      onMouseLeave={scheduleClose}
      // A tab into any link inside the panel keeps it open; a tab out of the last
      // one closes it. Without this the panel disappears the moment focus lands on
      // its first link, which makes it impossible to reach the second.
      onFocus={() => setOpen(true)}
      onBlur={(event) => {
        if (!event.currentTarget.contains(event.relatedTarget as Node | null))
          setOpen(false);
      }}
    >
      <button
        type="button"
        aria-expanded={open}
        aria-haspopup="true"
        onClick={() => setOpen((value) => !value)}
        className={cn(
          "relative flex items-center gap-1 rounded-[var(--radius-sm)] px-3 py-2 text-sm font-medium transition-colors",
          "after:absolute after:inset-x-3 after:bottom-1 after:h-px after:origin-left after:scale-x-0 after:bg-[var(--color-accent)] after:transition-transform hover:after:scale-x-100",
          active || open
            ? "text-[var(--color-foreground)] after:scale-x-100"
            : "text-[var(--color-foreground-muted)] hover:text-[var(--color-foreground)]",
        )}
      >
        {label}
        <ChevronDown
          className={cn(
            "size-3.5 transition-transform duration-200",
            open && "rotate-180",
          )}
          aria-hidden="true"
        />
      </button>

      {open && (
        <div
          // `pt-2` rather than `mt-2`: the gap between trigger and panel is padding
          // *inside* the hover target, so the pointer never crosses dead space on
          // its way down and the menu never flickers shut mid-travel.
          //
          // Anchored to the trigger's left edge rather than centred, because a wide
          // panel centred on a nav item near the logo hangs off the viewport.
          className="absolute left-0 top-full z-50 w-[min(46rem,calc(100vw-2rem))] pt-2"
        >
          {/* `app-card`, not `panel`. The frosted panel is a *content* surface —
              translucent by design, so a section of the page can show the canvas
              behind it. An overlay drawn on top of live text needs the opposite:
              anything less than opaque and the paragraph underneath reads straight
              through the menu. */}
          <div className="app-card overflow-hidden shadow-[var(--shadow-raised)]">
            {/* A grid, not a scrolling list. Every ranking is one item and there are
                nine of them; a column tall enough to need scrolling hides most of
                the menu behind a gesture, and a reader cannot compare what they
                cannot see at once. Two columns fit the whole set above the fold at
                every breakpoint this panel appears at. */}
            <ul className="grid gap-0.5 p-2 sm:grid-cols-2">
              {items.map((item) => (
                <li key={item.href}>
                  <Link
                    href={item.href}
                    onClick={() => setOpen(false)}
                    className="group flex h-full items-start gap-2.5 rounded-[var(--radius-md)] px-3 py-2.5 transition-colors hover:bg-[var(--color-surface-sunken)]"
                  >
                    <span
                      aria-hidden="true"
                      className="mt-[0.3rem] size-2 shrink-0 rounded-full transition-transform group-hover:scale-125"
                      style={
                        item.accent
                          ? { backgroundColor: `oklch(${item.accent})` }
                          : undefined
                      }
                    />

                    <span className="min-w-0 flex-1">
                      <span className="block text-sm font-medium leading-snug text-[var(--color-foreground)] transition-colors group-hover:text-[var(--color-primary)]">
                        {item.label}
                      </span>

                      {item.hint && (
                        <span className="mt-0.5 block truncate font-mono text-[0.6875rem] text-[var(--color-foreground-subtle)]">
                          {item.hint}
                        </span>
                      )}
                    </span>
                  </Link>
                </li>
              ))}
            </ul>

            {/* Last, not first. The panel's job is the nine links; "see all" is the
                fallback for someone who did not find what they wanted in them, and
                a footer is where a reader looks after reading rather than before. */}
            <Link
              href={href}
              onClick={() => setOpen(false)}
              className="flex items-center justify-between gap-2 border-t border-[var(--color-border-subtle)] bg-[var(--color-surface-sunken)]/60 px-4 py-2.5 text-xs font-medium text-[var(--color-foreground-muted)] transition-colors hover:text-[var(--color-foreground)]"
            >
              <span className="eyebrow">All rankings</span>
              <span
                aria-hidden="true"
                className="transition-transform hover:translate-x-0.5"
              >
                →
              </span>
            </Link>

            {footer}
          </div>
        </div>
      )}
    </div>
  );
}
