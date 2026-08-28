import {
  Activity,
  BarChart3,
  FileText,
  Gauge,
  Image,
  Inbox,
  KeyRound,
  Layers,
  LineChart,
  Mail,
  Receipt,
  RefreshCcw,
  Settings,
  Sparkles,
  Tags,
  Ticket,
  Users,
  Wrench,
  type LucideIcon,
} from "lucide-react";

/**
 * The admin navigation, declared once.
 *
 * Each entry carries the permission that opens it. The sidebar, the ⌘K palette and
 * the "you have nowhere to go" fallback all read this list, which means a screen a
 * role cannot use never appears in its navigation — and, more importantly, that
 * hiding it is a consequence of the same fact the API enforces rather than a second
 * hand-maintained opinion about who sees what.
 *
 * This is presentation only. Every route re-checks server-side; a link that leaks
 * into the wrong sidebar is a cosmetic bug, not a security one.
 */
export interface AdminNavItem {
  href: string;
  label: string;
  icon: LucideIcon;
  description: string;
  /** Any one of these is enough to see the entry. */
  permissions: string[];
  /** Renders a count chip when the loaded badge map has a non-zero entry. */
  badgeKey?: "tickets" | "contact" | "posts_draft";
}

export interface AdminNavSection {
  title: string;
  items: AdminNavItem[];
}

export const adminNavSections: AdminNavSection[] = [
  {
    title: "Insight",
    items: [
      {
        href: "/admin",
        label: "Overview",
        icon: Gauge,
        description: "The health of the product in one screen",
        permissions: ["analytics.view"],
      },
      {
        href: "/admin/analytics",
        label: "Analytics",
        icon: BarChart3,
        description: "Tools, funnel and content performance",
        permissions: ["analytics.view", "tool_analytics.view"],
      },
    ],
  },
  {
    title: "Content",
    items: [
      {
        href: "/admin/posts",
        label: "Posts",
        icon: FileText,
        description: "Write, schedule and publish the blog",
        permissions: ["posts.view_any"],
        badgeKey: "posts_draft",
      },
      {
        href: "/admin/taxonomy",
        label: "Categories & tags",
        icon: Tags,
        description: "How the blog is organised",
        permissions: ["post_categories.view_any", "tags.view_any"],
      },
      {
        href: "/admin/media",
        label: "Media",
        icon: Image,
        description: "Every file, with its alt text",
        permissions: ["media.view_any"],
      },
    ],
  },
  {
    title: "Product",
    items: [
      {
        href: "/admin/tools",
        label: "Tools",
        icon: Wrench,
        description: "Tiering, visibility and the catalog",
        permissions: ["tools.view_any"],
      },
      {
        href: "/admin/grants",
        label: "Tool grants",
        icon: Sparkles,
        description: "Who has been comped what, and until when",
        permissions: ["tool_grants.view_any"],
      },
    ],
  },
  {
    title: "People",
    items: [
      {
        href: "/admin/users",
        label: "Users",
        icon: Users,
        description: "Find anyone, and what they are entitled to",
        permissions: ["users.view_any"],
      },
      {
        href: "/admin/roles",
        label: "Roles & permissions",
        icon: KeyRound,
        description: "Compose exactly the access a job needs",
        permissions: ["roles.view_any"],
      },
    ],
  },
  {
    // Four destinations rather than one screen with four tabs. Tabs made every
    // one of these unaddressable: "look at invoice 412" was not a link, a refresh
    // dropped you back on Plans, and the browser's back button undid a filter
    // instead of leaving the screen.
    title: "Billing",
    items: [
      {
        href: "/admin/billing/plans",
        label: "Plans",
        icon: Layers,
        description: "What is for sale, and at what price",
        permissions: ["plans.view_any"],
      },
      {
        href: "/admin/billing/subscriptions",
        label: "Subscriptions",
        icon: RefreshCcw,
        description: "Who is paying, and what renews when",
        permissions: ["subscriptions.view_any"],
      },
      {
        href: "/admin/billing/invoices",
        label: "Invoices",
        icon: Receipt,
        description: "Every charge, refund and outstanding balance",
        permissions: ["invoices.view_any"],
      },
      {
        href: "/admin/billing/report",
        label: "Report",
        icon: LineChart,
        description: "Revenue, churn and what is driving both",
        permissions: ["invoices.view_any"],
      },
    ],
  },
  {
    title: "Support",
    items: [
      {
        href: "/admin/tickets",
        label: "Tickets",
        icon: Ticket,
        description: "The queue, worst-first",
        permissions: ["tickets.view_any"],
        badgeKey: "tickets",
      },
      {
        href: "/admin/messages",
        label: "Contact inbox",
        icon: Inbox,
        description: "Messages from the public form",
        permissions: ["tickets.view_any"],
        badgeKey: "contact",
      },
    ],
  },
  {
    title: "Platform",
    items: [
      {
        href: "/admin/newsletter",
        label: "Newsletter",
        icon: Mail,
        description: "The list, and whether it is syncing",
        permissions: ["newsletter.view"],
      },
      {
        href: "/admin/settings",
        label: "Settings",
        icon: Settings,
        description: "Branding, flags, tracking and providers",
        permissions: ["settings.view"],
      },
      {
        href: "/admin/activity",
        label: "Audit log",
        icon: Activity,
        description: "Who changed what, and when",
        permissions: ["activity_log.view"],
      },
    ],
  },
];

export const adminNavItems: AdminNavItem[] = adminNavSections.flatMap((section) => section.items);

/** `/admin` is a prefix of everything, so it only ever matches exactly. */
export function isAdminActive(href: string, pathname: string): boolean {
  return href === "/admin"
    ? pathname === href
    : pathname === href || pathname.startsWith(`${href}/`);
}

/** The sections a given permission set can actually reach, empties dropped. */
export function visibleSections(can: (permission: string) => boolean): AdminNavSection[] {
  return adminNavSections
    .map((section) => ({
      ...section,
      items: section.items.filter((item) => item.permissions.some(can)),
    }))
    .filter((section) => section.items.length > 0);
}

/** Where to send someone who lands on `/admin` without `analytics.view`. */
export function firstReachable(can: (permission: string) => boolean): AdminNavItem | null {
  return adminNavItems.find((item) => item.permissions.some(can)) ?? null;
}
