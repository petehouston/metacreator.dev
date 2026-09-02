---
{
 "id": "IG-02",
 "slug": "instagram-carousel-size",
 "title": "Instagram Carousel Size, and Cutting a Seamless One",
 "excerpt": "Instagram carousel size is 1080x1350 per slide, and every slide must share the first one's ratio. Here is how to split one wide image so the seams disappear.",
 "category": "design",
 "categories": ["growth"],
 "tags": ["instagram", "image-sizes", "how-to"],
 "primary_keyword": "instagram carousel size",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "Instagram Carousel Size and Seamless Splitting",
  "description": "The Instagram carousel size to use, why every slide must match the first slide's ratio, and how to split one wide image into panels with invisible seams.",
  "focus_keyword": "instagram carousel size",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Instagram carousel size, and seamless panels",
  "og_description": "One ratio for every slide, and the split that hides the seams.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
Instagram carousel size is 1080×1350 per slide for portrait, or 1080×1080 for square —
and the rule that catches people out is that **every slide takes the ratio of the first
one**. Mix ratios and Instagram crops or letterboxes the whole set.

## The Instagram carousel size rules

| Rule | Detail |
| --- | --- |
| Slides per post | Up to 20 |
| Ratio | Set by the first slide; all others are forced to match |
| Portrait | 1080×1350 (4:5) — the most feed space |
| Square | 1080×1080 |
| Grid preview | Only the first slide appears, cropped square |

The last two rows interact awkwardly: a 4:5 carousel takes the most space in the feed,
but its first slide is squared in your profile grid. Compose the first slide so it
survives both crops.

[[tool:social-image-resizer]]

## Splitting one image across slides

A panorama that reads as one image when you swipe is the most eye-catching thing you
can do with the format, and it fails visibly if the maths is off by a few pixels.

The arithmetic: for *n* slides at 1080 wide, the source needs to be 1080 × n pixels
wide at the correct height, cut at exact multiples. Doing that by hand in an editor is
where the seams come from — a two-pixel drift is invisible in the editor and obvious in
the feed.

[[tool:carousel-splitter]]

The splitter takes one wide image and produces the panels at exact boundaries, so the
seams line up when someone swipes.

## Designing for the swipe

A carousel is a sequence, and sequences have rules:

**Slide 1 is the whole post.** It decides whether anyone swipes at all. Put the promise
there — not a title card, and not your logo.

**Slide 2 has to justify slide 1.** The biggest drop-off in any carousel is between the
first and second slides.

**The last slide asks for something.** A save, a follow, a comment — one thing, and only
because the reader got value first.

**Design for the phone.** Text is read at about 400 pixels wide. If it needs zooming, it
does not exist.

## Why carousels are worth the effort

Two mechanisms. They hold attention longer than a single image, and time spent is what
Instagram distributes on. And they are the format most likely to be **saved**, because a
carousel is usually a reference — a list, a comparison, a set of steps — and saves are
weighted heavily.

That combination is why carousels punch above their weight for accounts without large
followings. The wider strategy is in
[Instagram growth](/blog/instagram-growth-guide), and the engagement effect is visible
in the numbers:

[[tool:engagement-rate-calculator]]

## Where the format actually earns its keep

A carousel is the only Instagram format that lets a reader control the pace. That
sounds minor and is not: a video plays at your speed, a single image is taken in at a
glance, but a carousel is read at the reader's own rate, which is why it suits anything
that needs a moment of thought per step.

The content types that consistently work in the format:

- **A comparison.** One option per slide, the verdict last.
- **A process.** One step per slide, with the finished result first so people know why
  they are swiping.
- **A mistake and its fix.** The mistake on one slide, the fix on the next, repeated.
- **A list with substance.** Not "5 tips" - five things each worth a slide.

What does not work is a blog post cut into slides. If a slide is a paragraph, the reader
is doing work the format was supposed to save them, and they stop after the second one.

## Common failures

- **A square slide in a portrait set.** The whole carousel is cropped. Check every
  slide's ratio before uploading.
- **Text near the edges.** Instagram's own interface and the swipe affordance sit
  there.
- **A seamless panorama with the subject on a seam.** The join is where the eye
  travels; put nothing important on it.
- **Twenty slides.** Nobody swipes twenty times. Five to eight is where attention runs
  out.

Meta documents the placement geometry it supports in its
[Business Help Centre](https://www.facebook.com/business/help/103816146375741), and the
full ratio reference is
[Instagram image size](/blog/instagram-image-sizes).

:::faq
Q: What size should an Instagram carousel be?
A: 1080×1350 for portrait or 1080×1080 for square, and every slide must use the same
ratio as the first.
Q: How many slides can an Instagram carousel have?
A: Up to 20, though attention usually runs out around five to eight.
Q: Why is my carousel cropped?
A: Because one slide has a different ratio from the first. Instagram forces the whole
set to the first slide's shape.
Q: How do I make a seamless Instagram carousel?
A: Build one wide image at 1080 × the number of slides, then cut it at exact
boundaries - a splitter avoids the pixel drift that produces visible seams.
:::

Cut one wide image into exact panels with the
[carousel splitter](/tools/carousel-splitter).
