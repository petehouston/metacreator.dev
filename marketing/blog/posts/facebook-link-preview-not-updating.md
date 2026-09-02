---
{
 "id": "PN-05",
 "slug": "facebook-link-preview-not-updating",
 "title": "Facebook Link Preview Not Updating: The Cache",
 "excerpt": "Facebook caches what it read the first time. Here is why a fixed page still shows the old image, and the ways to force a re-scrape.",
 "category": "seo",
 "categories": [],
 "tags": ["facebook", "link-previews", "troubleshooting"],
 "primary_keyword": "facebook link preview not updating",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "Facebook Link Preview Not Updating: Clear the Cache",
  "description": "Why a Facebook link preview is not updating after you fixed the page, how the scrape cache works, and the ways to force Facebook to read the page again.",
  "focus_keyword": "facebook link preview not updating",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Facebook still showing the old image?",
  "og_description": "It cached the page. Here is how the cache works and how to clear it.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
A Facebook link preview not updating after you fixed the page is a caching problem, not a
markup one. Facebook scrapes a URL the first time it is shared and stores what it found;
later shares reuse that stored copy, so a corrected page can keep producing the old card
for a long time.

## Why a Facebook link preview is not updating: the cache

1. The first time a URL is shared, Facebook fetches it and reads the Open Graph tags.
2. It stores the title, description and image against that exact URL.
3. Subsequent shares of the same URL use the stored copy.
4. It re-scrapes on its own schedule, which is not something you can rely on.

The important word is *exact*. `example.com/page` and `example.com/page?x=1` are different
URLs with different cache entries, which is both the cause of some confusion and the basis
of the crudest workaround.

## Fixing it, in order

**1. Confirm the page is actually correct now.** Before blaming the cache, check what a
crawler currently reads:

[[tool:link-preview-debugger]]

If the tags are still wrong, this is not a caching problem —
[link preview not showing](/blog/link-preview-not-showing) covers the other causes, and
[Open Graph tags](/blog/open-graph-tags) covers what should be there.

**2. Force a re-scrape.** Facebook provides a sharing debugger in its
[developer tools](https://developers.facebook.com/tools/debug/) which re-fetches a URL on
demand and shows what it reads. This is the intended route and it works immediately.

**3. Change the URL.** If a re-scrape is not available to you, appending a query parameter
creates a new cache entry — `?v=2`. Crude, and it fragments any share counts attached to
the original, so it is a last resort rather than a first move.

**4. Wait.** Caches do expire. This is the least satisfying answer and sometimes the only
one.

## Preventing it next time

The cache is only a problem because the tags were wrong when the page was first shared.
Two habits eliminate it:

- **Set Open Graph tags in the template**, not per page, so every new page is correct by
  default.
- **Check before sharing.** Run a new URL through a preview tool before the first post
  rather than after — the first scrape is the one that sticks.

[[tool:facebook-post-preview]]

The post preview shows how the whole post renders in feed and mobile layouts, and the
image half of it is in
[Facebook image sizes](/blog/facebook-image-sizes).

[[tool:social-image-resizer]]

## The same cache elsewhere

LinkedIn caches aggressively too and has historically been slower to refresh than
Facebook. X re-scrapes more readily. The tags are shared between all of them, which is
why fixing the page once fixes every platform —
[LinkedIn image and document sizes](/blog/linkedin-image-sizes) covers the LinkedIn
specifics.

:::faq
Q: Why is my Facebook link preview not updating?
A: Facebook cached what it read the first time the URL was shared. Correcting the page
does not clear that cache on its own.
Q: How do I force Facebook to refresh a link preview?
A: Use Facebook's own sharing debugger to re-scrape the URL. It fetches the page again and
updates the stored copy immediately.
Q: Does adding a query parameter fix it?
A: It creates a new cache entry, so the new share is correct - but it fragments anything
attached to the original URL. Treat it as a last resort.
Q: How long does the Facebook preview cache last?
A: There is no published duration. Re-scraping is the reliable route rather than waiting.
:::

Check what platforms currently read from your URL:
[link preview debugger](/tools/link-preview-debugger).
