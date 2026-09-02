---
{
 "id": "SZ-03",
 "slug": "youtube-thumbnail-size",
 "title": "YouTube Thumbnail Size, and the Part Nobody Sees",
 "excerpt": "YouTube thumbnail size is 1280x720 at 16:9 - but the number that matters is the 170 pixels wide it is actually viewed at.",
 "category": "design",
 "categories": ["seo"],
 "tags": ["youtube", "thumbnails", "image-sizes", "explainer"],
 "primary_keyword": "youtube thumbnail size",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "YouTube Thumbnail Size and the Part Nobody Sees",
  "description": "YouTube thumbnail size, file limits and format - plus the display sizes that decide whether your text is readable, and the corner the duration badge covers.",
  "focus_keyword": "youtube thumbnail size",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "YouTube thumbnail size: the spec and the reality",
  "og_description": "1280x720 is the spec. 170 pixels wide is where it is judged.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
The YouTube thumbnail size to export is 1280×720 at 16:9 — YouTube documents a target
of 3840×2160, a minimum width of 640 pixels, JPG or PNG, with a 2 MB limit on mobile
and 50 MB on desktop
([YouTube Help](https://support.google.com/youtube/answer/72431)). The number that
decides whether it works is none of those: it is the roughly 170 pixels wide at which
most people see it.

## The YouTube thumbnail size spec, and the display sizes

| Thing | Value |
| --- | --- |
| Aspect ratio | 16:9 (9:16 for Shorts, 1:1 for podcasts) |
| Recommended resolution | 3840×2160; 1280×720 is ample in practice |
| Minimum width | 640 pixels |
| Formats | JPG or PNG |
| File size | 2 MB mobile, 50 MB desktop |
| Typical display width | ~360px on a phone feed, ~170px in a sidebar |

Design at 1280×720, then judge at 170. If the text is unreadable when you shrink the
tab, the text is not doing anything — it is texture.

[[tool:social-image-resizer]]

## What covers your thumbnail

Three pieces of interface sit on top, and they are always in the same places:

- **The duration badge**, bottom right. Anything you put there is covered.
- **The progress bar**, along the bottom edge on watched videos — a red line across
  the lower few pixels.
- **The "watched" overlay** on the whole tile for videos the viewer has already seen.

So the bottom-right corner is unusable and the bottom edge is unreliable. Compose with
the subject centred or to the left, and keep text out of the lower band.

[[tool:safe-zone-guide]]

The cross-platform version of this problem is
[safe zones on social media](/blog/social-media-safe-zones).

## Why the display size is the real constraint

A thumbnail is never judged at the size you designed it. It is judged in a row of
competitors, at a size set by the device, in a fraction of a second, usually against
a title that is also fighting for the same attention.

That has a practical consequence most thumbnail advice skips: the design brief is not
"make an attractive image", it is "make an image that is identifiable at a glance,
among others, small". Those are different jobs. A carefully composed scene with four
elements loses to a single high-contrast subject every time, not because subtlety is
bad but because subtlety does not survive downscaling.

The cheapest way to test this is to zoom your browser out until the thumbnail is
about the width of a thumbnail in a sidebar, and then look away and back. Whatever
you can still identify in that second is what the thumbnail communicates. Everything
else is for you, not for the viewer.

## Rules that survive at 170 pixels

- **Three or four words maximum.** Not a sentence. The title is right next to it
  carrying the sentence.
- **One subject.** A face, an object, a contrast — not a collage.
- **High contrast between subject and background**, because both will be scaled down
  and recompressed.
- **Do not repeat the title.** The thumbnail and title are read together; duplicating
  one in the other wastes half the space you have.

The title half of that pairing is covered in
[YouTube title length](/blog/youtube-title-length).

## Shorts thumbnails are a different shape

A Short is 9:16, and YouTube documents 2160×3840 with a minimum height of 640 pixels
for its thumbnail. The Shorts feed also crops differently from the Shorts shelf on a
channel page, so the same caution applies as with a
[Reels cover](/blog/instagram-reels-cover-size): the subject has to survive more than one
crop.

## Getting someone else's thumbnail

For research, for a facade embed, or because you lost your own source file, every
published thumbnail resolution is available from the video page.

[[tool:youtube-thumbnail-downloader]]

See [how to download a YouTube thumbnail](/blog/download-youtube-thumbnail).

## Where thumbnails sit in discovery

The thumbnail does not get you retrieved — the title, description and transcript do
that. It decides whether an impression becomes a click, which is the step most videos
lose. That is why "impressions but no clicks" is a thumbnail and title diagnosis, not
an algorithm one, as covered in the
[YouTube SEO guide](/blog/youtube-seo).

:::faq
Q: What size should a YouTube thumbnail be?
A: 1280×720 at 16:9 is the practical export. YouTube's documented target is
3840×2160 with a minimum width of 640 pixels, in JPG or PNG.
Q: What is the YouTube thumbnail file size limit?
A: 2 MB when uploading from mobile and 50 MB from desktop.
Q: Why does my thumbnail look blurry?
A: Usually an upload below 1280 pixels wide, or heavy compression before upload.
Export at 1280×720 or larger and compress once, at high quality.
Q: What part of a YouTube thumbnail gets covered?
A: The bottom-right corner by the duration badge, and the bottom edge by the progress
bar on watched videos. Keep text and faces out of that band.
:::

Check what survives at real display sizes with the
[safe-zone guide](/tools/safe-zone-guide).
