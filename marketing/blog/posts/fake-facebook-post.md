---
{
 "id": "MU-02",
 "slug": "fake-facebook-post",
 "title": "How to Make a Fake Facebook Post for a Mock-Up",
 "excerpt": "Draw a Facebook post card for a slide or a client mock-up, in desktop or mobile width, without screenshotting a real feed and cropping four things out of it.",
 "category": "design",
 "categories": [],
 "tags": ["facebook", "mockups", "how-to"],
 "primary_keyword": "fake facebook post",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "How to Make a Fake Facebook Post for a Mock-Up",
  "description": "Make a fake Facebook post image for a slide, deck or teaching example: desktop and mobile widths, light and dark, with no feed to crop out.",
  "focus_keyword": "fake facebook post",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "A Facebook post card, drawn rather than screenshotted",
  "og_description": "No sidebar, no cookie banner, nobody else's content in the frame.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
To make a fake Facebook post for a mock-up, type the page name and the post text into a
generator and download the card as an image. The result is the post card on its own —
no browser chrome, no sidebar, no cookie banner and nobody else's content in the frame,
which is what a screenshot of a real feed always drags in.

[[tool:fake-facebook-post-generator]]

## What goes into a Facebook post card

Six fields do almost all the work, and getting two of them right is what makes a card
read as real rather than as approximately Facebook-shaped.

| Field | Notes |
| --- | --- |
| Page or person name | Drawn in semibold above the timestamp |
| Post text | Wrapped at the card width, with links and hashtags in Facebook's blue |
| Timestamp | Facebook writes "2h", "Yesterday at 14:20" or a bare date |
| Audience | Public, Friends or Only me — the icon line under the name |
| Reactions, comments, shares | Reactions sit left, comments and shares right |
| Avatar | Optional; left blank, the card draws the name's initials |

The two that give a mock-up away are the **timestamp format** and the **asymmetry of
the counts row**. Facebook puts reaction icons and a count on the left and
"31 comments · 12 shares" on the right; a centred row of three numbers is instantly
wrong even to somebody who could not tell you why.

## Desktop or mobile

Width is the only difference, and it is not cosmetic — it changes where the text wraps
and how much of the post is read before the eye stops.

Draw the **mobile** card when the image will be looked at on a phone, or when the point
you are making is about length. Draw the **desktop** card for a presentation, where the
wider line reads better at a distance. If the post is long enough that the two wrap
differently, that difference is usually the thing worth showing.

Facebook's own reference for how a post is laid out, and which elements sit where, is in
Meta's [design resources for
publishers](https://developers.facebook.com/docs/plugins/embedded-posts/).

For the real thing rather than a mock-up — checking how a link you are about to post
will render in the feed — use the [Facebook post
preview](/tools/facebook-post-preview) instead, which works from your actual copy.

## Exporting it

The card exports as PNG, JPG, WebP or AVIF at one, two or three times size. Use:

- **PNG at 2×** for a slide or a document. It is the safe default.
- **WebP or AVIF** for a web page, where the file size difference is worth having.
- **JPG** only when something downstream insists on it — a JPG cannot hold a
  transparent background, and the transparent option is switched off automatically when
  you pick it.

Everything is drawn on a canvas in your own browser, including any avatar you add.
Nothing is uploaded, so there is nothing on our side to store or leak.

## The part to be careful about

This draws whatever you type. That makes it a mock-up, not proof, and a card from it
proves nothing about who posted what.

The card deliberately carries **no verified badge**, because a badge is the single
element that makes a drawn post claim to be authentic. What the tool cannot decide for
you is whose name goes on it: putting a real business or a real person's name above
words they did not write is impersonation, whatever it was drawn with, and a disclaimer
in your caption does not travel with the image when somebody reposts it.

[Fake social media post generators: what they are for](/blog/fake-social-media-post-generator)
goes through the line in more detail, including the cases where an
[embed](/blog/embed-a-social-media-post) is the honest tool instead.

:::faq
Q: Can I add a photo to the fake Facebook post?
A: Not in the tool, deliberately. A generator that composited a real image into a real-looking post would be a forgery kit rather than a mock-up tool. Place your image behind or below the card in your slide or layout.
Q: Why is the image an SVG?
A: The API version draws vectors, so it is sharp at any size. The web version draws on a canvas and exports PNG, JPG, WebP or AVIF — use that one if you need a raster file.
Q: Can I mock up a Facebook comment rather than a post?
A: Not yet on Facebook. There are comment generators for YouTube and TikTok, and an X reply generator that draws a reply with the post it answers.
Q: Is this the same as a Facebook post preview?
A: No. A preview takes your real copy and shows how the feed will render it before you publish. A generator draws a card from any text at all, for use as an image somewhere else.
:::

Open the [fake Facebook post generator](/tools/fake-facebook-post-generator), or start
from [the pillar](/blog/fake-social-media-post-generator) if you are choosing between
platforms.
