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
        href: "/dashboard/tools",
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
 * `/dashboard` is a prefix of every child route, so it only ever matches exactly;
 * everything else matches its own subtree.
 */
export function isActive(href: string, pathname: string): boolean {
  return href === "/dashboard"
    ? pathname === href
    : pathname === href || pathname.startsWith(`${href}/`);
}
