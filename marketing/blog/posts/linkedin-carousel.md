---
{
 "id": "PN-03",
 "slug": "linkedin-carousel",
 "title": "LinkedIn Carousel: A Document Post That Reads",
 "excerpt": "A LinkedIn carousel is a PDF rendered as swipeable pages. Here is the page size, the type size, and the structure that keeps people swiping.",
 "category": "design",
 "categories": ["content"],
 "tags": ["linkedin", "image-sizes", "how-to"],
 "primary_keyword": "linkedin carousel",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "LinkedIn Carousel: A Document Post That Reads",
  "description": "How to build a LinkedIn carousel: the page size, type size for phone reading, the first-page hook, and the structure that keeps people swiping to the end.",
  "focus_keyword": "linkedin carousel",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "LinkedIn carousels that get read on a phone",
  "og_description": "Page size, type size, and the first page that decides everything.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
A LinkedIn carousel is a PDF uploaded as a document post, which LinkedIn renders as
swipeable pages in the feed. It takes more feed space than any other format and holds
attention longer — provided the pages are built for a phone rather than for a slide
projector.

## The LinkedIn carousel specification

| Thing | Value |
| --- | --- |
| Format | PDF |
| Page ratio | 1:1 or 4:5 — square is safest |
| Page size | 1080×1080 or 1080×1350 |
| Pages | 5-10 works; more loses people |
| Type size | Large. Assume the page is read at about 400 pixels wide |

Landscape pages are the common mistake. A 16:9 slide deck uploaded as a document renders
as a small strip on a phone, and the type is unreadable.

[[tool:social-image-resizer]]

Full sizing reference:
[LinkedIn image and document sizes](/blog/linkedin-image-sizes).

## The structure that keeps people swiping

**Page 1 is the entire post.** It appears in the feed as the cover, and it decides
whether anyone swipes at all. It is a hook, not a title page — no logo-and-title layout,
no "Introduction".

**Page 2 delivers immediately.** The largest drop-off in any carousel is between the
first and second pages, and the cause is almost always that page two was setup rather
than substance.

**One idea per page.** A page is read in about two seconds. If it needs a paragraph, it
needs to be two pages.

**The last page asks for one thing.** A follow, a comment, or a link — one, and only
after the reader got something.

## Building it without design software

You need square pages with large type, which almost anything can produce. If you are
starting from one wide image or a set of images rather than a document, the cutting is
the fiddly part:

[[tool:carousel-splitter]]

The splitter produces exact panels, which matters when pages are meant to connect
visually — a few pixels of drift is invisible while you work and obvious when swiped.

## What to put in one

The format suits anything that is a sequence:

- **A process**, one step per page, with the result on page one.
- **A comparison**, one option per page, verdict last.
- **A teardown** — the thing, then what is wrong with it, then the fix.
- **A checklist** somebody would screenshot. Screenshots are the LinkedIn equivalent of
  a save.

What it does not suit is an essay. If the content is prose, write a text post — the
structure for that is in
[LinkedIn post length, formats and reach](/blog/linkedin-post-guide).

## The post text still matters

A document post has a text field above it, and that text is subject to the same fold as
any other post: two or three lines before "see more".

[[tool:linkedin-post-preview]]

See [where LinkedIn puts See more](/blog/linkedin-see-more). Use it to say what the
document contains and why it is worth swiping — not to summarise every page.

LinkedIn documents its supported post formats in its
[help centre](https://www.linkedin.com/help/linkedin).

:::faq
Q: What size should a LinkedIn carousel be?
A: Square pages at 1080×1080, or 4:5 at 1080×1350, exported as a PDF. Landscape pages are
unreadable on a phone.
Q: How many pages should a LinkedIn carousel have?
A: Five to ten. Attention runs out after that, and a long document reads as work rather
than as a post.
Q: How do I post a carousel on LinkedIn?
A: Upload a PDF as a document post. LinkedIn renders the pages as a swipeable carousel in
the feed.
Q: Do carousels perform better than text posts on LinkedIn?
A: They take more feed space and hold attention longer, which usually helps. A weak
carousel still loses to a strong text post.
:::

Cut exact pages from one source image with the
[carousel splitter](/tools/carousel-splitter).
