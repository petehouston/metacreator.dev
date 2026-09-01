import {
  Bell,
  CreditCard,
  Gauge,
  History,
  LifeBuoy,
  Settings,
  Wrench,
  type LucideIcon,
} from "lucide-react";

/**
 * The shell's navigation, in one place.
 *
 * The sidebar, the mobile drawer and the ⌘K palette all read this list, so a new
 * screen appears in three places by being added once — and cannot appear in two of
 * them and be forgotten in the third.
 */
export interface NavItem {
  href: string;
  label: string;
  icon: LucideIcon;
  /** Shown in the palette and as the sidebar tooltip when the rail is collapsed. */
  description: string;
}

export interface NavSection {
  title: string;
  items: NavItem[];
}

export const navSections: NavSection[] = [
  {
    title: "Workspace",
    items: [
      {
        href: "/dashboard",
        label: "Overview",
        icon: Gauge,
        description: "Plan, usage and where you left off",
      },
      {
        // The public catalog, not an in-app copy of it: `/tools` already shows
        // each card's access state for whoever is signed in, so a second browser
        // behind the dashboard was a surface to keep in step for no gain.
        href: "/tools",
        label: "Tools",
        icon: Wrench,
        description: "Search the catalog and run anything",
      },
      {
        href: "/dashboard/runs",
        label: "Run history",
        icon: History,
        description: "Every result you have produced",
      },
    ],
  },
  {
    title: "Account",
    items: [
      {
        href: "/dashboard/notifications",
        label: "Notifications",
        icon: Bell,
        description: "Everything we have told you about",
      },
      {
        href: "/dashboard/billing",
        label: "Plan & billing",
        icon: CreditCard,
        description: "Your plan, limits and invoices",
      },
      {
        href: "/dashboard/settings",
        label: "Settings",
        icon: Settings,
        description: "Profile, security and preferences",
      },
    ],
  },
  {
    title: "Help",
    items: [
      {
        href: "/dashboard/support",
        label: "Help & support",
        icon: LifeBuoy,
        description: "Answers, and how to reach a human",
      },
    ],
  },
];

export const navItems: NavItem[] = navSections.flatMap((section) => section.items);

/**
 * Routes that only exist while the site sells something.
 *
 * Filtered out rather than disabled: with billing off there is no plan, no invoice
 * and no card on file, so a "Plan & billing" screen would be a page about nothing.
 * Keeping the list here means the sidebar, the topbar's breadcrumb and the ⌘K
 * palette drop it together — the failure mode this file exists to prevent.
 */
const BILLING_HREFS: readonly string[] = ["/dashboard/billing"];

export function navSectionsFor(billingEnabled: boolean): NavSection[] {
  if (billingEnabled) return navSections;

  return navSections
    .map((section) => ({
      ...section,
      items: section.items.filter((item) => !BILLING_HREFS.includes(item.href)),
    }))
    // A section whose only entry was billing should not leave a heading behind.
    .filter((section) => section.items.length > 0);
}

export function navItemsFor(billingEnabled: boolean): NavItem[] {
  return navSectionsFor(billingEnabled).flatMap((section) => section.items);
}

/**
 * `/dashboard` is a prefix of every child route, so it only ever matches exactly;
 * everything else matches its own subtree.
 */
export function isActive(href: string, pathname: string): boolean {
  return href === "/dashboard"
    ? pathname === href
    : pathname === href || pathname.startsWith(`${href}/`);
}
