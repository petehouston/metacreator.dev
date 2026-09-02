---
{
 "id": "SZ-09",
 "slug": "pinterest-pin-size",
 "title": "Pinterest Pin Size: 2:3, and When to Break It",
 "excerpt": "Pinterest recommends a 2:3 Pin at 1000x1500. Here is why that ratio wins, what happens to taller Pins, and the title and description limits alongside it.",
 "category": "design",
 "categories": ["seo"],
 "tags": ["pinterest", "image-sizes", "explainer"],
 "primary_keyword": "pinterest pin size",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "Pinterest Pin Size: 2:3, and When to Break It",
  "description": "The Pinterest Pin size to use is 1000x1500 at 2:3. Why that ratio wins in the feed, what happens to longer Pins, and the title and description limits.",
  "focus_keyword": "pinterest pin size",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Pinterest Pin size, and the limits around it",
  "og_description": "2:3 at 1000x1500, plus the title and description caps that decide what shows.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
The Pinterest Pin size to use is 1000×1500 pixels — a 2:3 aspect ratio, which is what
Pinterest itself recommends
([Pinterest specs](https://help.pinterest.com/en/business/article/pinterest-product-specs)).
Everything else about a Pin, including the title and description limits, follows from
the fact that the feed is a column of tall tiles competing for one glance.

## The Pinterest Pin size specification

| Field | Value |
| --- | --- |
| Aspect ratio | 2:3 |
| Resolution | 1000×1500 |
| File size | Up to 20 MB on desktop, 32 MB in-app |
| Formats | PNG or JPEG — Pinterest converts everything to 8-bit RGB JPEG |
| Title | Up to 100 characters; roughly the first 40 show in feeds |
| Description | Up to 800 characters; used for relevance, not shown in feeds |

Two details in that table are worth pausing on. Pinterest converts every upload to
JPEG, so uploading a PNG in sRGB is the safest way to control what that conversion
produces. And **the description does not appear in the home or search feed at all** —
it is read by Pinterest for matching, not by the person scrolling.

[[tool:pin-image-sizer]]

The Pin sizer exports 2:3, 1:1 and 9:16 from one image at Pinterest's own dimensions,
which covers standard Pins, square placements and the vertical video slot.

## Why 2:3 and not taller

Longer Pins were once a growth trick. Pinterest now truncates Pins taller than 2:3 in
the feed, so the bottom of a tall Pin — usually where people put the call to action —
is simply not shown until someone taps through.

The result is that 2:3 is not a recommendation to consider, it is the shape that gets
seen. Square Pins work and take less vertical space; landscape Pins are a waste of a
vertical feed.

[[tool:pinterest-pin-preview]]

The preview draws the Pin as both the feed tile and the closeup, which show different
things — the feed tile crops, the closeup does not, and a design that only works in one
of them is a design that fails half the time.

## Text on a Pin

Pinterest is a visual search engine where most Pins carry a text overlay, and the
overlay is usually the reason a Pin is clicked. Rules that survive the small feed tile:

- **Six words or fewer.** The Pin is competing at thumbnail size.
- **Top third or bottom third**, not centred over the busiest part of the image.
- **High contrast.** Assume it will be recompressed.
- **Say the outcome**, not the topic. "Sourdough that rises in 4 hours" beats
  "Sourdough tips".

## The fields that decide whether it is found

The image gets the click; the text fields get the impression. Titles are capped at 100
characters with roughly the first 40 showing in feeds, and descriptions run to 800
characters used for relevance matching.

[[tool:pinterest-pin-seo-checker]]

The SEO checker scores keyword placement across the title, description, board and
destination URL together — which is the right unit, because Pinterest reads all four.
See [Pinterest SEO](/blog/pinterest-seo) and
[writing a Pin description Pinterest can read](/blog/pinterest-pin-description).

## Rich Pins pull data from your page

A Rich Pin adds metadata from the linked page — article, product or recipe details —
and it depends on markup on your site being correct and validated.

[[tool:rich-pin-validator]]

When they stop working, the cause is nearly always the markup:
[Rich Pins not working](/blog/rich-pins-not-working).

The cross-network ratio reference is
[social media image sizes](/blog/social-media-image-sizes).

:::faq
Q: What is the best Pinterest Pin size?
A: 1000×1500 pixels at a 2:3 aspect ratio, which is Pinterest's own recommendation.
Q: What happens if a Pin is taller than 2:3?
A: It is truncated in the feed, so anything at the bottom - typically the call to
action - is not seen until someone opens the Pin.
Q: How long can a Pin title be?
A: Up to 100 characters, with roughly the first 40 visible in feeds. Front-load
anything that has to be read.
Q: Does the Pin description show in the feed?
A: No. It is used by Pinterest for relevance matching. It still matters, but not as
copy the scroller reads.
:::

Export every Pin ratio at Pinterest's own dimensions with the
[Pin image sizer](/tools/pin-image-sizer).
