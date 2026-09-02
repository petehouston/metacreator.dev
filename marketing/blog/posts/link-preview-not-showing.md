---
{
 "id": "XT-04",
 "slug": "link-preview-not-showing",
 "title": "Link Preview Not Showing: The Ordered List of Causes",
 "excerpt": "A link preview not showing is almost always one of six things. Here they are in order of likelihood, each with the check that confirms it.",
 "category": "seo",
 "categories": ["design"],
 "tags": ["x", "facebook", "linkedin", "link-previews", "troubleshooting"],
 "primary_keyword": "link preview not showing",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "Link Preview Not Showing: The Causes, in Order",
  "description": "Why a link preview is not showing: six causes in order of likelihood, from missing Open Graph tags to platform caching, each with a check that confirms it.",
  "focus_keyword": "link preview not showing",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Link preview not showing? Six causes, in order",
  "og_description": "Missing tags, blocked crawlers, caching, redirects, image rules, and the one nobody checks.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
A link preview not showing is almost never mysterious. It is one of six causes, and they
are worth checking in this order because that is roughly how often each turns out to be
the answer.

[[tool:link-preview-debugger]]

The debugger fetches the URL once and renders the card as X, Facebook, LinkedIn and chat
apps each draw it — which usually identifies the cause before you read any further.

## 1. A link preview not showing usually means no Open Graph tags

The most common cause by a distance. Platforms read `og:title`, `og:description`,
`og:image` and `og:url` from the page's `<head>`. With none of them, some platforms guess
from the page content and others show nothing.

The minimum set:

```html
<meta property="og:title" content="Page title">
<meta property="og:description" content="One sentence.">
<meta property="og:image" content="https://example.com/card.png">
<meta property="og:url" content="https://example.com/page">
<meta name="twitter:card" content="summary_large_image">
```

Full detail in [Open Graph tags](/blog/open-graph-tags).

## 2. The image is unreachable or the wrong shape

The image URL must be absolute, publicly reachable, and not behind authentication or a
hotlink protection rule. Platforms also enforce their own minimums — an image that is too
small is ignored rather than scaled up.

1200×630 is the safe size everywhere. Sizes per platform are in
[social media image sizes](/blog/social-media-image-sizes).

[[tool:social-image-resizer]]

## 3. The platform cached an older version

Platforms fetch a page once and cache what they find, sometimes for a long time. If you
have just fixed the tags, the card you are looking at may predate the fix.

The remedy is platform-specific, and Facebook is the usual offender —
[Facebook link preview not updating](/blog/facebook-link-preview-not-updating) covers
forcing a re-scrape.

[[tool:facebook-post-preview]]

## 4. The crawler is blocked

Preview crawlers are bots, and anything that blocks bots blocks them: a `robots.txt`
rule, a firewall, a bot-protection service, or a server that only responds to browsers.

A page that renders perfectly for you and returns nothing to a crawler is the classic
version of this, and it is invisible from a browser. Fetching the URL through a preview
tool, which requests it the way a platform would, is the check.

## 5. The content is rendered by JavaScript

Some preview crawlers do not execute JavaScript. If your tags are injected client-side,
the crawler sees an empty head. Open Graph tags belong in the server-rendered HTML,
always.

## 6. Redirects and canonical mismatches

A URL that redirects can lose its tags along the way, and an `og:url` pointing somewhere
other than the page itself confuses platforms that use it for deduplication. Share the
final URL, and make `og:url` self-referencing.

## Why previews matter more than they look

A link card is the entire visual footprint of a shared URL. On every platform in this
list, a post containing a link is competing against posts containing images and video,
and the card is the only picture it gets.

Which makes the failure modes expensive in a way that is easy to underestimate:

- **No card at all** turns your post into a bare URL, which is the least clickable object
  in any feed.
- **A wrong image** - the site's logo, a stock header, someone else's photograph - is
  worse than no image, because it actively misrepresents the page.
- **A stale card** persists. Platforms cache, and a bad card can outlive several redesigns
  of the page it describes.

The fix is cheap and permanent: get the tags right once, at the template level, so every
page on the site produces a correct card without anyone thinking about it. That is a
half-hour of work that improves every link anyone ever shares.

## The check that settles it

Rather than guessing, fetch the page the way a platform does and compare what comes back
with what you expect:

[[tool:link-preview-debugger]]

If the debugger shows correct tags and a platform still shows a wrong card, the cause is
caching — number three — and the fix is on that platform's side. Meta documents its
sharing requirements in its
[developer documentation](https://developers.facebook.com/docs/sharing/webmasters/), and
X documents its card formats in
[its own](https://developer.x.com/en/docs/x-for-websites/cards/overview/abouts-cards).

Related: [Twitter image sizes for X](/blog/twitter-image-size) for what the card actually
displays, and [LinkedIn image and document sizes](/blog/linkedin-image-sizes) for the
LinkedIn variant.

:::faq
Q: Why is my link preview not showing?
A: Most often missing Open Graph tags, an unreachable image, or a cached older version of
the page. Check in that order.
Q: How do I refresh a link preview?
A: Each platform has its own re-scrape mechanism. Editing the page alone does not clear a
cache that has already been populated.
Q: Do I need Twitter card tags as well as Open Graph?
A: X falls back to Open Graph for most fields, but `twitter:card` is worth setting
explicitly so you get the large card rather than the small one.
Q: Why does the preview work on one platform but not another?
A: Different crawlers, different caches and different image requirements. A tool that
renders all of them from one fetch shows you which is which.
:::

See what every platform actually reads from your URL:
[link preview debugger](/tools/link-preview-debugger).
