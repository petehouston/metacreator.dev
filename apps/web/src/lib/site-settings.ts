import "server-only";

import { siteConfig } from "@/config/site";
import { api } from "@/lib/api";

/**
 * What the public blog puts on the page, as configured under Settings → Blog.
 *
 * A separate, typed read rather than passing the raw settings map around: a page
 * that does `settings["blog.show_author"]` has no idea whether that key exists,
 * and a typo silently hides the author on every article.
 */
export interface BlogDisplay {
  showAuthor: boolean;
  showPublishedDate: boolean;
  showReadingTime: boolean;
  showFeaturedImage: boolean;
  showCategories: boolean;
  showTags: boolean;
  showRelatedPosts: boolean;
  postsPerPage: number;
}

/**
 * The defaults are what the product looked like before any of this was
 * configurable, and they are also the fallback when the settings request fails.
 * That direction matters: a settings endpoint that is briefly unavailable must
 * not blank out the bylines on every article on the site.
 */
const DEFAULTS: BlogDisplay = {
  showAuthor: true,
  showPublishedDate: true,
  showReadingTime: true,
  showFeaturedImage: true,
  showCategories: true,
  showTags: true,
  showRelatedPosts: true,
  postsPerPage: 12,
};

export async function blogDisplay(): Promise<BlogDisplay> {
  const settings = await api.settings().catch(() => null);

  if (settings === null) return DEFAULTS;

  const flag = (key: string, fallback: boolean): boolean => {
    const value = settings[key];

    return value === undefined || value === null ? fallback : Boolean(value);
  };

  const count = Number(settings["blog.posts_per_page"]);

  return {
    showAuthor: flag("blog.show_author", DEFAULTS.showAuthor),
    showPublishedDate: flag("blog.show_published_date", DEFAULTS.showPublishedDate),
    showReadingTime: flag("blog.show_reading_time", DEFAULTS.showReadingTime),
    showFeaturedImage: flag("blog.show_featured_image", DEFAULTS.showFeaturedImage),
    showCategories: flag("blog.show_categories", DEFAULTS.showCategories),
    showTags: flag("blog.show_tags", DEFAULTS.showTags),
    showRelatedPosts: flag("blog.show_related_posts", DEFAULTS.showRelatedPosts),
    // Clamped to what the API will accept, so a nonsense value in the table is a
    // sane page rather than an unfiltered list or an empty one.
    postsPerPage:
      Number.isFinite(count) && count >= 1 ? Math.min(24, Math.round(count)) : DEFAULTS.postsPerPage,
  };
}

/**
 * Site-wide feature switches the whole app renders against.
 *
 * `billingEnabled` is the master switch for money (Settings → Features). Off, the
 * product has no paid plans at all: pricing and billing surfaces are absent rather
 * than disabled, and the API has already downgraded every Pro tool to "Account
 * Required", so a card never advertises a plan the site does not sell.
 */
export interface SiteFeatures {
  billingEnabled: boolean;
  /**
   * `features.changelog_enabled` (Settings → Changelog). Off, the public changelog
   * routes 404 and the footer link that points at them goes with it.
   */
  changelogEnabled: boolean;
  /**
   * `features.search_enabled` (Settings → Search). Off, the API 404s `/search`
   * and every affordance that leads there — the header search box, its dropdown,
   * the results page — is absent rather than disabled.
   */
  searchEnabled: boolean;
}

/**
 * On is the fallback, for the same reason the blog defaults hold: a settings
 * request that fails for ten seconds must not silently give the paid catalog away.
 * The API is the real gate — this only decides what gets drawn.
 */
const FEATURE_DEFAULTS: SiteFeatures = {
  billingEnabled: true,
  changelogEnabled: true,
  // The one flag whose fallback is *off*, matching the API. On is the safe default
  // for a feature whose failure mode is hiding something people paid for; search's
  // failure mode is offering a box that 404s, so absence is the safe default here.
  searchEnabled: false,
};

export async function siteFeatures(): Promise<SiteFeatures> {
  const settings = await api.settings().catch(() => null);

  if (settings === null) return FEATURE_DEFAULTS;

  const flag = (key: string, fallback: boolean): boolean => {
    const value = settings[key];

    return value === undefined || value === null ? fallback : Boolean(value);
  };

  return {
    billingEnabled: flag("features.billing_enabled", FEATURE_DEFAULTS.billingEnabled),
    changelogEnabled: flag("features.changelog_enabled", FEATURE_DEFAULTS.changelogEnabled),
    searchEnabled: flag("features.search_enabled", FEATURE_DEFAULTS.searchEnabled),
  };
}

/**
 * The one address the site hands out, from Settings → General → Support email.
 *
 * Contact, support, privacy questions and security reports all point here. It is a
 * single setting rather than one per purpose because a small team reading four
 * inboxes reads none of them, and because an address published on a legal page and
 * then changed in only three of the five places it appears is worse than no address.
 *
 * `siteConfig.supportEmail` is the fallback, on the same principle as the blog
 * defaults: a settings request that fails must leave a reachable address on the
 * page, not a `mailto:undefined`.
 */
export async function contactEmail(): Promise<string> {
  const settings = await api.settings().catch(() => null);
  const value = settings?.["site.support_email"];

  return typeof value === "string" && value.trim() !== ""
    ? value.trim()
    : siteConfig.supportEmail;
}

/**
 * The tracking configuration from Settings → Tracking & scripts.
 *
 * Two halves that behave differently: the measurement IDs, which we turn into the
 * provider's official snippet ourselves, and four raw-HTML slots an admin pastes
 * into verbatim. Both are public settings, so this is the same cached `/settings`
 * read every other surface makes — no extra request.
 */
export interface TrackingScripts {
  ga4Id: string;
  gtmId: string;
  metaPixelId: string;
  tiktokPixelId: string;
  headStart: string;
  headEnd: string;
  bodyStart: string;
  bodyEnd: string;
}

const NO_TRACKING: TrackingScripts = {
  ga4Id: "",
  gtmId: "",
  metaPixelId: "",
  tiktokPixelId: "",
  headStart: "",
  headEnd: "",
  bodyStart: "",
  bodyEnd: "",
};

export async function trackingScripts(): Promise<TrackingScripts> {
  const settings = await api.settings().catch(() => null);

  // Unlike the blog and feature defaults, the fallback here is *nothing*. A
  // settings read that fails should drop a page view, not inject a half-configured
  // tag or, worse, some stale default onto every page on the site.
  if (settings === null) return NO_TRACKING;

  const text = (key: string): string => {
    const value = settings[key];

    return typeof value === "string" ? value.trim() : "";
  };

  return {
    ga4Id: text("tracking.ga4_id"),
    gtmId: text("tracking.gtm_id"),
    metaPixelId: text("tracking.meta_pixel_id"),
    tiktokPixelId: text("tracking.tiktok_pixel_id"),
    headStart: text("scripts.head_start"),
    headEnd: text("scripts.head_end"),
    bodyStart: text("scripts.body_start"),
    bodyEnd: text("scripts.body_end"),
  };
}
