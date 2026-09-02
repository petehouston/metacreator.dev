---
{
 "id": "SZ-08",
 "slug": "facebook-image-sizes",
 "title": "Facebook Image Sizes That Survive the Feed",
 "excerpt": "Facebook image size by placement: feed posts, link cards, Stories, cover photos and ads - plus the crops that differ between desktop and mobile.",
 "category": "design",
 "categories": [],
 "tags": ["facebook", "image-sizes", "explainer"],
 "primary_keyword": "facebook image size",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "Facebook Image Sizes That Survive the Feed",
  "description": "The Facebook image size for each placement - feed, link card, Stories, cover photo and ads - with the desktop and mobile crops that catch people out.",
  "focus_keyword": "facebook image size",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Facebook image sizes, by placement",
  "og_description": "Feed, cards, Stories, covers - and the crops that differ between desktop and phone.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
The Facebook image size to use for a feed post is 1080×1350 (4:5) or 1080×1080
(square); a link preview card is 1200×630. Facebook crops differently on desktop and
mobile, and the cover photo is the worst offender — its visible region changes shape
entirely between the two.

## Facebook image size, by placement

| Placement | Ratio | Export |
| --- | --- | --- |
| Feed, portrait | 4:5 | 1080×1350 |
| Feed, square | 1:1 | 1080×1080 |
| Link preview card | ~1.91:1 | 1200×630 |
| Stories and Reels | 9:16 | 1080×1920 |
| Page cover photo | Varies desktop vs mobile | 1640×856, subject centred |
| Profile picture | 1:1 | 320×320 or larger |
| Event cover | ~1.91:1 | 1200×628 |

[[tool:social-image-resizer]]

Meta publishes creative specifications for its advertising placements
([Meta Business Help](https://www.facebook.com/business/help/103816146375741)), and
organic posts use the same geometry — which makes the ads specs a useful reference
even if you never buy an ad.

## The cover photo problem

A Page cover is displayed wide on desktop and considerably narrower on a phone, with
different amounts cropped from the top and bottom. There is no single export that
fills both perfectly.

The only reliable approach is to treat the centre as the design and the edges as
padding: put everything meaningful in the middle 1000 pixels or so, horizontally and
vertically, and let the platform take whatever it wants from the outside. Text near
an edge will be missing for half your audience.

## Link cards are built from the linked page

Share a URL and Facebook builds the card from that page's Open Graph tags. Two
consequences worth internalising:

1. **Attaching an image to the post does not change the card.** The card comes from
   the page.
2. **Facebook caches the card hard.** Fix the page and the old image can persist,
   sometimes for a long time.

[[tool:link-preview-debugger]]

[[tool:facebook-post-preview]]

The debugger reads the page once and renders the card as Facebook draws it; the post
preview shows the whole post in feed and mobile layouts. If a card is stuck showing
the old image, that is a cache problem with a specific fix:
[Facebook link preview not updating](/blog/facebook-link-preview-not-updating). The
tags themselves are covered in [Open Graph tags](/blog/open-graph-tags).

## Text in images, and the ads inheritance

Facebook historically penalised ad images with more than 20% text coverage. The hard
rule has gone, but the underlying behaviour has not: text-heavy images perform worse
in a feed, because a feed is a scanning environment and a wall of small text is
something to scroll past.

The practical version of the old rule is still good advice — if the image needs a
paragraph to work, the paragraph belongs in the post text, where it is selectable,
translatable and readable by a screen reader.

Ad copy has its own hard limits, and they truncate earlier than most people expect:

[[tool:facebook-ad-text-counter]]

See [Facebook ad character limits](/blog/facebook-ad-character-limits).

## Why Facebook's crops differ from everyone else's

Facebook is older than the vertical-feed era and carries the consequences. It runs a
desktop layout and a mobile layout that were designed years apart, with different
column widths, and it renders the same asset in both. Instagram, built mobile-first,
never had to reconcile that.

The practical result is that Facebook is the one network where "design for the centre"
is not a stylistic preference but a requirement. A cover photo, an event header and
even a shared link card are each cropped differently depending on where they are seen,
and no single export satisfies every case.

So the working method for Facebook assets is:

1. **Compose in the middle.** Assume the outer 15% of every edge may be missing.
2. **Never put text at an edge.** Not a caption, not a logo, not a URL.
3. **Check the mobile crop, not the desktop one.** Most impressions are on a phone,
   and the mobile crop is the tighter of the two.
4. **Use the post text for anything that must be read.** Text in an image is not
   selectable, not translatable and not accessible.

## Stories and Reels follow the vertical rules

9:16, with the interface covering the top and bottom bands — the same geometry as
every other vertical feed.

[[tool:safe-zone-guide]]

Detail: [safe zones on social media](/blog/social-media-safe-zones). The full
cross-network ratio table is
[social media image sizes](/blog/social-media-image-sizes).

:::faq
Q: What is the best image size for a Facebook post?
A: 1080×1350 at 4:5 for the most feed space, or 1080×1080 square if you want a
predictable crop everywhere.
Q: What size should a Facebook link preview image be?
A: 1200×630. It is read from the linked page's Open Graph tags rather than from
anything you attach to the post.
Q: What size is a Facebook cover photo?
A: Around 1640×856, but the visible region differs between desktop and mobile. Keep
everything important in the centre.
Q: Does Facebook still have a 20% text rule for images?
A: Not as a hard rejection. Text-heavy images still perform poorly in the feed, so the
guidance survives even though the rule does not.
:::

Produce every Facebook placement from one image with the
[social image resizer](/tools/social-image-resizer).
