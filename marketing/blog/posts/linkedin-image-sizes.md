---
{
 "id": "SZ-07",
 "slug": "linkedin-image-sizes",
 "title": "LinkedIn Image and Document Sizes",
 "excerpt": "LinkedIn image size by placement: feed posts, link cards, document posts, profile and company banners - and the mobile crop that decides what is seen.",
 "category": "design",
 "categories": [],
 "tags": ["linkedin", "image-sizes", "explainer"],
 "primary_keyword": "linkedin image size",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "LinkedIn Image and Document Sizes",
  "description": "Every LinkedIn image size that matters: feed images, link preview cards, document post pages, profile photo and banners, with the crops to design around.",
  "focus_keyword": "linkedin image size",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "LinkedIn image sizes, by placement",
  "og_description": "Feed, cards, documents, banners - and the one ratio that beats the rest on mobile.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
The LinkedIn image size to use for a feed post is 1200×1200 (square) or 1080×1350
(4:5); for a link preview card it is 1200×627. LinkedIn's feed is read overwhelmingly
on phones, and taller images take more of that screen — which is the whole reason to
care.

## LinkedIn image size, by placement

| Placement | Ratio | Export |
| --- | --- | --- |
| Feed image, square | 1:1 | 1200×1200 |
| Feed image, portrait | 4:5 | 1080×1350 |
| Link preview card | ~1.91:1 | 1200×627 |
| Document post page | 1:1 or 4:5 | 1080×1080 or 1080×1350 |
| Profile photo | 1:1 | 400×400 or larger |
| Profile background | 4:1 | 1584×396 |
| Company page logo | 1:1 | 300×300 |
| Company page cover | ~4:1 | 1128×191 |

[[tool:social-image-resizer]]

## Document posts are the format worth learning

A document post — a PDF that LinkedIn renders as a swipeable carousel — occupies more
feed space than any other format and holds attention longer, because swiping is a
deliberate act.

The constraints are simple and unforgiving:

- **Square or 4:5 pages.** Landscape pages become small and unreadable on a phone.
- **One idea per page.** A page is read in about two seconds.
- **Large type.** Assume the page is viewed at around 400 pixels wide.
- **The first page is the entire hook.** Nobody swipes into a document whose cover
  says "Introduction".

[[tool:carousel-splitter]]

The build process and the phone-first sizing are in
[LinkedIn carousel: a document post that reads on a phone](/blog/linkedin-carousel).

## Why portrait beats landscape here

LinkedIn's feed is mostly consumed on phones, in a column, between other things. The
practical effect is the same as on any vertical feed: a taller image occupies more
screen and stays in view longer.

That is why 4:5 outperforms 16:9 on LinkedIn even though the platform is nominally a
desktop-era professional network. The exception is the link card, which is fixed at
its own ratio and cannot be made taller - one more reason a native image post usually
outperforms a link post, quite apart from any question of how the feed treats
outbound links.

If a post genuinely needs to send people to a URL, the common workaround is to put
the image in the post and the link in the first comment. Whether that helps
distribution is contested and undocumented; what is certain is that a native image
takes more space than a link card, and space is attention.

## Link cards come from the page, not the post

Post a URL and LinkedIn builds the card from that page's Open Graph tags. If the card
is wrong, the page is wrong — and LinkedIn caches aggressively, so a fix is not
visible until the cache clears.

[[tool:link-preview-debugger]]

LinkedIn documents the tags it reads in its
[developer documentation](https://learn.microsoft.com/en-us/linkedin/consumer/integrations/self-serve/share-on-linkedin).
See [Open Graph tags](/blog/open-graph-tags) and
[link preview not showing](/blog/link-preview-not-showing).

## Where the text gets cut

Images are only half of a LinkedIn post's geometry. The text is truncated behind a
"see more" link after a few lines, and everything that matters has to be above it.

[[tool:linkedin-post-preview]]

The preview draws the post as LinkedIn renders it, with the hidden half greyed out
rather than reported as a number. Detail:
[where LinkedIn puts See more](/blog/linkedin-see-more).

## The wider reference

The ratios here are the same six that cover every network — see
[social media image sizes](/blog/social-media-image-sizes) for the whole map, and
[LinkedIn post length, formats and reach](/blog/linkedin-post-guide) for how images
fit into what actually gets distributed.

:::faq
Q: What is the best LinkedIn image size?
A: 1200×1200 square, or 1080×1350 if you want the extra vertical space on mobile.
Both are safe across desktop and phone.
Q: What size should a LinkedIn document post be?
A: Square or 4:5 pages, exported as a PDF. 1080×1080 or 1080×1350 per page, with type
large enough to read at about 400 pixels wide.
Q: What size is the LinkedIn profile banner?
A: 1584×396, roughly 4:1. The lower left is covered by your profile photo on most
layouts, so keep text out of that corner.
Q: Why is my LinkedIn link preview showing the wrong image?
A: LinkedIn reads and caches the page's Open Graph tags. Fix the tags on the page,
then wait for the cache - re-posting the same URL will not refresh it immediately.
:::

Export every LinkedIn placement from one file with the
[social image resizer](/tools/social-image-resizer).
