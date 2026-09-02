---
{
 "id": "SZ-12",
 "slug": "aspect-ratios-explained",
 "title": "Aspect Ratio for Creators: 16:9, 9:16, 4:5 and 1:1",
 "excerpt": "An aspect ratio is a shape, not a size. Here is what each ratio is for, how to convert between them, and why cropping down beats scaling up.",
 "category": "design",
 "categories": [],
 "tags": ["image-sizes", "explainer"],
 "primary_keyword": "aspect ratio",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "Aspect Ratio for Creators: 16:9, 9:16, 4:5, 1:1",
  "description": "What each aspect ratio is for, how to convert between them without distortion, and the one rule that makes a single shoot work on every platform.",
  "focus_keyword": "aspect ratio",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Aspect ratios, and the one rule that matters",
  "og_description": "Compose tall, crop down. Everything else is arithmetic.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
An aspect ratio is the shape of a frame — width to height — independent of how many
pixels it contains. 1920×1080 and 1280×720 are the same 16:9 shape at different sizes.
Once that distinction is clear, most sizing confusion disappears, because platforms
care about the shape and only set a minimum on the size.

## The aspect ratios you need

| Ratio | Shape | Used for |
| --- | --- | --- |
| 16:9 | Wide | Video, YouTube thumbnails, desktop headers |
| 9:16 | Tall, full screen | Stories, Reels, TikTok, Shorts |
| 4:5 | Tall, feed-friendly | The tallest allowed feed post on Instagram and Facebook |
| 1:1 | Square | Profile grids, LinkedIn documents, avatars |
| 2:3 | Tall | Pinterest Pins |
| 1.91:1 | Wide | Link preview cards and Open Graph images |

[[tool:aspect-ratio-calculator]]

The calculator does the conversion arithmetic — give it a ratio and one dimension and
it returns the other, which is the operation you actually perform when resizing.

## Compose tall, crop down

The single rule that saves the most work: **shoot and compose in the tallest ratio you
need, then crop down to the others**.

Cropping down discards pixels you already have. Scaling up invents pixels that were
never there, and it looks like it. A 9:16 frame contains a 4:5, a 1:1 and a 16:9 crop;
a 16:9 frame contains none of them without either letterboxing or losing the sides of
your composition.

The corollary is that you need margin. Compose with roughly 10% of the frame on each
edge that you are willing to lose, and one shoot serves every platform.

[[tool:social-image-resizer]]

Full per-platform reference:
[social media image sizes](/blog/social-media-image-sizes).

## Ratio versus resolution versus file size

Three separate things that get conflated:

- **Aspect ratio** — the shape. 4:5.
- **Resolution** — the pixel count. 1080×1350.
- **File size** — the bytes. 480 KB.

A platform's requirement is usually a ratio plus a minimum resolution plus a maximum
file size, and each is satisfied independently. An image can be the right shape and too
small, or the right size and too heavy. The file-size half is
[how to compress an image](/blog/compress-image-for-instagram).

## Where a wrong ratio actually hurts

**Vertical video in a horizontal frame.** Letterboxed with black bars, on a full-screen
vertical feed. It is the most visible mistake on TikTok and Reels, and it reads as
repurposed content because it is —
[TikTok video size](/blog/tiktok-video-size).

**A 16:9 image in a 4:5 slot.** Either cropped to its middle band or shown small. On a
vertical feed, small means scrolled past.

**A carousel with mixed ratios.** Instagram applies the first slide's ratio to all of
them, so one square slide in a portrait set crops the whole thing —
[Instagram carousel size](/blog/instagram-carousel-size).

## The maths, if you want it

Aspect ratio is width ÷ height. 16 ÷ 9 = 1.78; 9 ÷ 16 = 0.5625; 4 ÷ 5 = 0.8. To find a
missing dimension, multiply or divide by that number: a 1080-pixel-wide 4:5 image is
1080 ÷ 0.8 = 1350 pixels tall.

The 56.25% you see in responsive video CSS is the same figure — 9 ÷ 16 expressed as a
percentage, used to reserve the correct height before an embed loads. That technique is
in [YouTube embed code](/blog/youtube-embed-code), and it is documented in the
[MDN guide to aspect-ratio](https://developer.mozilla.org/en-US/docs/Web/CSS/aspect-ratio).

:::faq
Q: What aspect ratio should I use for social media?
A: 9:16 for vertical video and stories, 4:5 for feed posts, 1:1 for grids and profile
images, 16:9 for video and thumbnails, 2:3 for Pinterest.
Q: What is the difference between aspect ratio and resolution?
A: The ratio is the shape; the resolution is how many pixels fill it. 1920×1080 and
1280×720 are both 16:9.
Q: Can I change an aspect ratio without cropping?
A: Only by adding bars or distorting the image. Cropping is the honest option, which is
why composing with margin matters.
Q: Why does my video have black bars?
A: The video's ratio does not match the player's. A 16:9 video in a 9:16 feed is
letterboxed; the fix is to export vertically rather than to stretch it.
:::

Convert between any ratio and resolution with the
[aspect ratio calculator](/tools/aspect-ratio-calculator).
