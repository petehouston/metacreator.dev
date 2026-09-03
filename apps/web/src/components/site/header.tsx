"use client";

import { Menu, X } from "lucide-react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import * as React from "react";

import { NotificationBell } from "@/components/account/notification-bell";
import { useSession } from "@/components/auth/session-provider";
import { UserMenu } from "@/components/account/user-menu";
import { SiteSearch } from "@/components/search/site-search";
import { Logo } from "@/components/site/logo";
import { useBillingEnabled } from "@/components/site/features-provider";
import { NavDropdown } from "@/components/site/nav-dropdown";
import { useRankingNav } from "@/components/site/ranking-nav-provider";
import { ThemeToggle } from "@/components/site/theme-toggle";
import { primaryNavFor, rankingNavHref } from "@/config/site";
import { cn } from "@/lib/utils";

export function SiteHeader() {
  const pathname = usePathname();
  const { user } = useSession();
  const billingEnabled = useBillingEnabled();
  const nav = React.useMemo(() => primaryNavFor(billingEnabled), [billingEnabled]);
  const rankings = useRankingNav();
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
              {nav.map((item) => {
                const active = pathname === item.href || pathname.startsWith(`${item.href}/`);

                // The rankings entry carries a menu of pages the admin manages, so
                // it renders as a dropdown — and falls back to a plain link when
                // there are none, rather than a chevron that opens nothing.
                if (item.href === rankingNavHref) {
                  return (
                    <li key={item.href}>
                      <NavDropdown
                        label={item.label}
                        href={item.href}
                        active={active}
                        items={rankings.map((ranking) => ({
                          href: ranking.href,
                          label: ranking.label,
                          hint: `${ranking.platformLabel} · ${ranking.count} ${ranking.count === 1 ? "entry" : "entries"}`,
                          accent: ranking.accent,
                        }))}
                      />
                    </li>
                  );
                }

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
          {/* Before the theme toggle and the bell: search is a task, those are
              settings, and the eye reads the row left to right. Hides itself below
              `md` — the phone header has no room, so the mobile menu carries a
              full-width field instead — and renders nothing at all while the
              feature is switched off. */}
          <SiteSearch />

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
          <div className="mx-auto w-full max-w-[75rem] px-4 pt-3 sm:px-6">
            {/* First in the menu, not last: on a phone this is the fastest route to
                any of the ninety tools, and it beats scrolling a category list. */}
            <SiteSearch variant="block" />
          </div>

          <ul className="mx-auto flex w-full max-w-[75rem] flex-col px-4 py-2 sm:px-6">
            {nav.map((item) => (
              <li key={item.href}>
                <Link
                  href={item.href}
                  className="block py-3 text-sm font-medium text-[var(--color-foreground)]"
                >
                  {item.label}
                </Link>

                {/* Listed flat rather than behind a second tap. A hover menu has no
                    equivalent on a phone, and burying nine pages under an accordion
                    inside an already-open menu is one interaction too many for a
                    list this short. */}
                {item.href === rankingNavHref && rankings.length > 0 && (
                  <ul className="mb-2 ml-1 flex flex-col gap-0.5 border-l border-[var(--color-border-subtle)] pl-3">
                    {rankings.map((ranking) => (
                      <li key={ranking.href}>
                        <Link
                          href={ranking.href}
                          className="flex items-center gap-2 py-1.5 text-sm text-[var(--color-foreground-muted)]"
                        >
                          <span
                            aria-hidden="true"
                            className="size-1.5 shrink-0 rounded-full"
                            style={{ backgroundColor: `oklch(${ranking.accent})` }}
                          />
                          <span className="truncate">{ranking.label}</span>
                        </Link>
                      </li>
                    ))}
                  </ul>
                )}
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
