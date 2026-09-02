---
{
 "id": "DL-08",
 "slug": "social-media-alt-text",
 "title": "How to Write Alt Text for Social Media Images",
 "excerpt": "Alt text is read by people, not just crawlers. Where each platform hides the field, what a good description does, and why reused images lose theirs.",
 "category": "content",
 "categories": [],
 "tags": ["bluesky", "downloads", "how-to"],
 "primary_keyword": "social media alt text",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "How to Write Alt Text for Social Media Images",
  "description": "Social media alt text, done properly: where each platform puts the field, what to write, what to leave out, and how to keep it when you reuse an image.",
  "focus_keyword": "social media alt text",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Alt text people actually read",
  "og_description": "Where each platform puts the field, and what belongs in it.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
Social media alt text is a short description of a picture, read aloud by screen readers
and shown when an image fails to load. Every major platform has a field for it, most of
them hide it two taps deep, and the difference between a useful one and a useless one is
about thirty seconds of thought.

## Where each platform keeps the alt text field

| Platform | Where it is |
| --- | --- |
| Bluesky | An **ALT** badge on the image in the composer |
| Instagram | Advanced settings → Write alt text, before posting |
| X | An **ALT** button on the attached image |
| Threads | Tap the image in the composer |
| LinkedIn | **Add alt text** on the image preview |
| Facebook | Edit photo → Alternative text |

Instagram and Facebook also generate a description automatically when you leave the
field empty. It is a machine listing objects — the reason a screen reader user sometimes
hears "may be an image of one person and text". Writing your own replaces it.

Bluesky is the outlier in culture rather than mechanism: the field is one tap away, the
app nudges you toward it, and the norm stuck. It is the one platform where you can
usually assume an image *has* a description worth reading.

## What to write in social media alt text

Describe what the image is doing in the post, not everything in the frame.

- **Lead with the point.** "A line chart: signups double after the pricing change" beats
  "A chart with a blue line and axis labels".
- **Read the text out.** If there are words in the image — a quote card, a screenshot, a
  price — those words are the content. Put them in.
- **Keep it to a sentence or two.** A paragraph read aloud before the post itself is a
  worse experience than a short description.
- **Skip "image of" and "picture of".** The screen reader has already said it is an
  image.
- **Say who, when it matters.** Names, if the people are the point. Not a description of
  their appearance, unless that is what the post is about.
- **Decorative images need nothing.** A background texture with no information has no
  description to give.

## What not to do with the field

Do not stuff keywords into it. Alt text carries little search weight on social platforms,
and it is read aloud — a string of hashtags in the middle of a sentence is unpleasant for
the person it was written for and buys nothing from anybody else.

Do not repeat the caption. If the description says exactly what the post already says, a
screen reader user hears the same sentence twice and learns nothing about the picture.

Do not leave the platform's automatic description in place on anything that matters. It
lists objects; it does not tell anyone why the picture is in the post.

:::tip
Read it aloud before you post. If it sounds like a sentence somebody would say, it is
right. If it sounds like a filename, it is not.
:::

## Carry the alt text when you carry the image

This is where it usually goes wrong. Somebody finds a chart in a post, saves the picture,
drops it into a deck — and the description that came with it stays behind.

The picture had a description because its author wrote one. Reusing the image and
dropping it makes the copy worse for exactly the readers who needed it, at no saving
whatsoever, and it takes one paste to avoid.

The [Bluesky image downloader](/tools/bluesky-image-downloader) returns the alt text next
to each image for this reason — Bluesky's read API hands it over, so there is no excuse
for losing it.

[[tool:bluesky-image-downloader]]

Other platforms are less forthcoming. What a link card publishes is the picture, not the
description, so an image pulled from
[a Threads post](/blog/download-threads-images) or
[an Instagram post](/blog/download-instagram-photos) arrives bare. Where you can still
see the original post, open it and read the description off it before you reuse the
picture.

## Why this matters beyond compliance

The accessibility case is the real one, and the
[WCAG text alternatives guidance](https://www.w3.org/WAI/tutorials/images/) sets out the
standard. But there are two ordinary reasons as well.

Images fail. A slow connection, a blocked CDN, a
[link that has expired](/blog/instagram-image-link-expired) — when the picture does not
render, the description is what is left, and a post whose entire point was in an
unrendered image says nothing at all.

And writing one clarifies the post. If you cannot describe in a sentence what the image
is doing there, the image may not be doing anything there.

:::faq
Q: Does alt text help SEO on social platforms?
A: Barely, and that is not the reason to write it. Write it for the people reading it, and take the small benefit if it exists.
Q: How long should alt text be?
A: A sentence or two. Long enough to convey what the image contributes, short enough that hearing it read aloud before the post is not a chore.
Q: Do I need alt text on every image?
A: Not on purely decorative ones. On anything carrying information — charts, screenshots, quote cards, anything with words in it — yes.
Q: What happens if I leave it blank?
A: Instagram and Facebook generate a machine description that lists objects. It is better than silence and much worse than a sentence you wrote.
:::

If you are reusing somebody else's image, the
[guide to downloading social media images](/blog/download-social-media-images) covers what
each platform hands over — and what it does not.
