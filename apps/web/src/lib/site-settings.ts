import "server-only";

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
