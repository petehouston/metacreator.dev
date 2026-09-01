export const siteConfig = {
  name: "MetaCreator.Dev",
  shortName: "MetaCreator",
  tagline: "Tools that help creators grow",
  description:
    "A professional toolkit for creators and influencers — analyze, optimize and grow your accounts across YouTube, Instagram, TikTok, X, Facebook, LinkedIn, Threads and Pinterest. Free to start, no account needed.",
  url: process.env.NEXT_PUBLIC_APP_URL ?? "https://metacreator.dev",
  // The build-time fallback only. The live address is Settings → General →
  // Support email; read it with `contactEmail()` rather than reaching for this.
  supportEmail: "metacreator.dev@gmail.com",
  social: {
    x: "https://x.com/metacreatordev",
    youtube: "https://youtube.com/@metacreatordev",
  },
} as const;

export const platforms = [
  { key: "youtube", label: "YouTube" },
  { key: "instagram", label: "Instagram" },
  { key: "tiktok", label: "TikTok" },
  { key: "x", label: "X" },
  { key: "facebook", label: "Facebook" },
  { key: "linkedin", label: "LinkedIn" },
  { key: "threads", label: "Threads" },
  { key: "pinterest", label: "Pinterest" },
] as const;

/**
 * Links that only make sense while the site sells something.
 *
 * The header and the footer both filter against this, so turning billing off cannot
 * leave a Pricing link in one of them and not the other.
 */
export const billingHrefs: readonly string[] = ["/pricing"];

/**
 * Links that only make sense while the changelog is published.
 *
 * Same contract as `billingHrefs`: one list, so the switch cannot leave a link
 * pointing at a route the API now 404s.
 */
export const changelogHrefs: readonly string[] = ["/changelog"];

export const primaryNav = [
  { href: "/tools", label: "Tools" },
  { href: "/pricing", label: "Pricing" },
  { href: "/blog", label: "Blog" },
  { href: "/about", label: "About" },
] as const;

export const footerNav = [
  {
    title: "Product",
    links: [
      { href: "/tools", label: "All tools" },
      { href: "/pricing", label: "Pricing" },
      { href: "/tools?tier=free", label: "Free tools" },
      { href: "/changelog", label: "Changelog" },
    ],
  },
  {
    title: "By platform",
    links: [
      { href: "/tools?platform=youtube", label: "YouTube tools" },
      { href: "/tools?platform=instagram", label: "Instagram tools" },
      { href: "/tools?platform=tiktok", label: "TikTok tools" },
      { href: "/tools?platform=x", label: "X tools" },
      { href: "/tools?platform=threads", label: "Threads tools" },
      { href: "/tools?platform=pinterest", label: "Pinterest tools" },
    ],
  },
  {
    title: "Resources",
    links: [
      { href: "/blog", label: "Blog" },
      { href: "/contact", label: "Contact" },
    ],
  },
  {
    title: "Legal",
    links: [
      { href: "/terms", label: "Terms of service" },
      { href: "/privacy", label: "Privacy policy" },
      { href: "/cookies", label: "Cookie policy" },
      { href: "/security", label: "Security" },
    ],
  },
] as const;

/** The header's links for the current feature set. */
export function primaryNavFor(billingEnabled: boolean) {
  return billingEnabled ? primaryNav : primaryNav.filter((item) => !billingHrefs.includes(item.href));
}

/** The footer's link groups for the current feature set, with emptied groups dropped. */
export function footerNavFor(billingEnabled: boolean, changelogEnabled = true) {
  if (billingEnabled && changelogEnabled) return footerNav;

  const hidden = [
    ...(billingEnabled ? [] : billingHrefs),
    ...(changelogEnabled ? [] : changelogHrefs),
  ];

  return footerNav
    .map((group) => ({
      ...group,
      links: group.links.filter((link) => !hidden.includes(link.href)),
    }))
    .filter((group) => group.links.length > 0);
}
