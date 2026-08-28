export const siteConfig = {
  name: "MetaCreator.Dev",
  shortName: "MetaCreator",
  tagline: "Tools that help creators grow",
  description:
    "A professional toolkit for creators and influencers — analyze, optimize and grow your accounts across YouTube, Instagram, TikTok, X, Facebook, LinkedIn, Threads and Pinterest. Free to start, no account needed.",
  url: process.env.NEXT_PUBLIC_APP_URL ?? "https://metacreator.dev",
  supportEmail: "support@metacreator.dev",
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
      { href: "/guides", label: "Guides" },
      { href: "/contact", label: "Contact" },
      { href: "/support", label: "Help centre" },
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
