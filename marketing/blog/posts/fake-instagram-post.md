---
{
 "id": "MU-03",
 "slug": "fake-instagram-post",
 "title": "How to Make a Fake Instagram Post for a Mock-Up",
 "excerpt": "Draw an Instagram post card with the caption cut exactly where the feed cuts it, so you can see which sentence disappears behind \"more\" before you publish.",
 "category": "design",
 "categories": [],
 "tags": ["instagram", "mockups", "captions"],
 "primary_keyword": "fake instagram post",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "How to Make a Fake Instagram Post for a Mock-Up",
  "description": "Make a fake Instagram post image with username, caption, likes and comments — and see exactly where the feed cuts your caption behind the more link.",
  "focus_keyword": "fake instagram post",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "An Instagram post card, with the caption fold drawn in",
  "og_description": "See which sentence disappears behind \"more\" before you publish it.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
To make a fake Instagram post, type a username and a caption into a generator, pick the
post shape, and download the card. The useful part is not the mock-up itself: it is that
a good generator cuts the caption **exactly where the feed cuts it**, so you can see
which sentence disappears behind "… more".

[[tool:fake-instagram-post-generator]]

## The caption fold is the reason to use this

Instagram shows roughly the first 125 characters of a caption in the feed and hides the
rest. That is far less than most people picture — about a line and a half on a phone.

A caption whose hook lands at character 140 is a caption nobody reads. Nobody taps
"more" to find out whether the sentence was worth it; they tapped through to the next
post two seconds ago.

The card greys the hidden half **in place** rather than dropping it, which is the
difference between a number and an answer. "Your caption is 210 characters" tells you
nothing actionable. Seeing that the cut lands mid-way through your call to action tells
you exactly what to move. [Instagram caption
length](/blog/instagram-caption-length) covers the limits themselves, including the
2,200-character ceiling and where hashtags sit.

## Choosing the post shape

Three shapes, and the choice changes the card's whole proportion:

| Shape | Ratio | Where it is used |
| --- | --- | --- |
| Square | 1:1 | The classic feed post |
| Portrait | 4:5 | The tallest the feed will show without cropping |
| Landscape | 16:9 | Rarely worth it — takes the least vertical space in a feed |

Portrait occupies the most screen on a phone, which is why most accounts have moved to
it. If you are mocking up for a client, draw the shape they will actually post in — a
square mock-up of a portrait post understates how much room the caption has above the
fold. [Instagram image sizes](/blog/instagram-image-sizes) has the pixel dimensions.

## Why a fake Instagram post draws a placeholder photo

Deliberately. The card draws a marked frame at the platform's own aspect ratio rather
than accepting an image.

A generator that composited a real photograph into a real-looking post would be a
forgery kit rather than a mock-up tool, and the placeholder loses nothing for the job
people actually have: the questions are about the caption, the username and the shape.
Because the frame sits at Instagram's exact ratio, dropping your own image in behind the
card in Figma, Keynote or anything else lines up precisely.

An **avatar** is the exception — you can add one, it is read in your browser, and it is
never uploaded.

Instagram's own guidance on captions and formats is in its
[Help Centre](https://help.instagram.com/1631821640426723).

## Exporting

PNG, JPG, WebP or AVIF, at one, two or three times size. PNG at 2× is the safe default
for a slide; WebP or AVIF is worth the switch for a web page. JPG cannot hold
transparency, so the transparent-background option turns itself off when you choose it.

Everything is drawn on a canvas in your browser and nothing is sent anywhere.

:::warning
This draws whatever you type and carries no verified badge, on purpose. A card is a
mock-up, not proof — putting a real account's username above a caption they did not
write is impersonation, and the image travels without your disclaimer.
:::

:::faq
Q: Can I upload the actual photo?
A: No, by design. The placeholder sits at Instagram's own aspect ratio, so composing your image behind the card in whatever you are building lines up exactly.
Q: Does it do Stories or Reels?
A: It draws feed posts. For Story and Reels dimensions use the story templates sizer and the Reels cover cropper, which work at the real pixel sizes.
Q: Where exactly does Instagram cut the caption?
A: Around 125 characters, though it varies slightly by device and font size. Treat it as a strong guide rather than a hard boundary, and keep anything past it to detail rather than the hook.
Q: Can I use this to make a fake account look real?
A: Please do not. Impersonating a real person or brand is against Instagram's terms everywhere and illegal in most jurisdictions.
:::

Open the [fake Instagram post generator](/tools/fake-instagram-post-generator), or read
[what these generators are for](/blog/fake-social-media-post-generator) if you are
weighing up whether a mock-up is the right thing at all.
