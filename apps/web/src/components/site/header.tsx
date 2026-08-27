"use client";

import { Menu, X } from "lucide-react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import * as React from "react";

import { NotificationBell } from "@/components/account/notification-bell";
import { useSession } from "@/components/auth/session-provider";
import { UserMenu } from "@/components/account/user-menu";
import { Logo } from "@/components/site/logo";
import { ThemeToggle } from "@/components/site/theme-toggle";
import { primaryNav } from "@/config/site";
import { cn } from "@/lib/utils";

export function SiteHeader() {
  const pathname = usePathname();
  const { user } = useSession();
  // The mobile menu is stored as "the path it was opened on" rather than a bare
  // boolean, so navigating away closes it by derivation instead of by an effect that
  // resets state after the new page has already rendered with the menu open.
  const [openedOn, setOpenedOn] = React.useState<string | null>(null);
  const open = openedOn === pathname;
  const setOpen = React.useCallback(
    (next: boolean) => setOpenedOn(next ? pathname : null),
    [pathname],
  );

  const [scrolled, setScrolled] = React.useState(false);

  React.useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 8);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  return (
    <header
      className={cn(
        "sticky top-0 z-50 border-b transition-colors duration-200",
        scrolled
          ? "border-[var(--color-border)] bg-[var(--color-background)]/80 backdrop-blur-xl backdrop-saturate-150"
          : "border-transparent bg-transparent",
      )}
    >
      {/* The brand rail. Two pixels of the site's whole colour story, pinned to the
          very top of every page — the cheapest possible signature. */}
      <span
        aria-hidden="true"
        className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-[var(--color-primary)] via-[var(--color-accent)] to-[var(--color-ember)]"
      />

      <div className="mx-auto flex h-16 w-full max-w-[75rem] items-center justify-between gap-4 px-4 sm:px-6">
        <div className="flex items-center gap-8">
          <Logo />

          <nav aria-label="Main" className="hidden md:block">
            <ul className="flex items-center gap-1">
              {primaryNav.map((item) => {
                const active = pathname === item.href || pathname.startsWith(`${item.href}/`);

                return (
                  <li key={item.href}>
                    <Link
                      href={item.href}
                      aria-current={active ? "page" : undefined}
                      className={cn(
                        "relative rounded-[var(--radius-sm)] px-3 py-2 text-sm font-medium transition-colors",
                        // The active tab is marked with the accent tick rather than
                        // a filled pill, so the nav stays quiet next to the logo.
                        "after:absolute after:inset-x-3 after:bottom-1 after:h-px after:origin-left after:scale-x-0 after:bg-[var(--color-accent)] after:transition-transform hover:after:scale-x-100",
                        active
                          ? "text-[var(--color-foreground)] after:scale-x-100"
                          : "text-[var(--color-foreground-muted)] hover:text-[var(--color-foreground)]",
                      )}
                    >
                      {item.label}
                    </Link>
                  </li>
                );
              })}
            </ul>
          </nav>
        </div>

        <div className="flex items-center gap-2">
          <div className="hidden sm:block">
            <ThemeToggle />
          </div>

          <NotificationBell />
          <UserMenu />

          <button
            type="button"
            onClick={() => setOpen(!open)}
            aria-expanded={open}
            aria-controls="mobile-nav"
            aria-label={open ? "Close menu" : "Open menu"}
            className="flex size-9 items-center justify-center rounded-[var(--radius-md)] text-[var(--color-foreground-muted)] md:hidden"
          >
            {open ? <X className="size-5" /> : <Menu className="size-5" />}
          </button>
        </div>
      </div>

      {open && (
        <nav
          id="mobile-nav"
          aria-label="Main"
          className="border-t border-[var(--color-border)] bg-[var(--color-background)]/95 backdrop-blur-xl md:hidden"
        >
          <ul className="mx-auto flex w-full max-w-[75rem] flex-col px-4 py-2 sm:px-6">
            {primaryNav.map((item) => (
              <li key={item.href}>
                <Link
                  href={item.href}
                  className="block py-3 text-sm font-medium text-[var(--color-foreground)]"
                >
                  {item.label}
                </Link>
              </li>
            ))}
            <li className="flex items-center justify-between border-t border-[var(--color-border-subtle)] py-3">
              {/* Signed-in users reach their account from the avatar menu, which is
                  visible at every breakpoint — repeating it here would be a dead link
                  to a page they are already one tap from. */}
              {user ? (
                <Link href="/dashboard" className="text-sm font-medium">
                  Dashboard
                </Link>
              ) : (
                <Link href="/login" className="text-sm font-medium">
                  Sign in
                </Link>
              )}
              <ThemeToggle />
            </li>
          </ul>
        </nav>
      )}
    </header>
  );
}
