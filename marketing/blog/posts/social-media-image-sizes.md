---
{
 "id": "SZ-01",
 "slug": "social-media-image-sizes",
 "title": "Social Media Image Sizes: One Reference for Everything",
 "excerpt": "Every social media image size that matters, organised by aspect ratio rather than by platform - because the ratio is what stays true when the pixels change.",
 "category": "design",
 "categories": [],
 "tags": ["image-sizes", "safe-zones", "guide"],
 "primary_keyword": "social media image sizes",
 "status": "draft",
 "is_featured": true,
 "allow_comments": true,
 "seo": {
  "title": "Social Media Image Sizes: One Reference",
  "description": "Social media image sizes for every network, organised by aspect ratio: feed, story, thumbnail, Pin and link-card dimensions, with the safe zones that cover them.",
  "focus_keyword": "social media image sizes",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Social media image sizes, organised by ratio",
  "og_description": "Six ratios cover every placement on every network. Here they are, with the safe zones.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
Social media image sizes look like a list of forty numbers. They are really six
aspect ratios, and every placement on every network is one of them. Learn the six and
the pixel counts stop mattering — a 4:5 export at sufficient resolution is correct on
a feed whatever this season's recommended dimensions happen to be.

## Social media image sizes are really six ratios

| Ratio | Where it lives | Common export |
| --- | --- | --- |
| 16:9 | YouTube thumbnails, video, X and Facebook link cards | 1920×1080 |
| 9:16 | Stories, Reels, TikTok, Shorts | 1080×1920 |
| 1:1 | Square feed posts, profile grids, LinkedIn documents | 1080×1080 |
| 4:5 | The tallest allowed feed post on Instagram and Facebook | 1080×1350 |
| 2:3 | Pinterest Pins | 1000×1500 |
| ~1.91:1 | Link preview cards, Open Graph images | 1200×630 |

That is the whole subject. Everything below is detail on where each one applies and
what gets covered by interface furniture.

[[tool:social-image-resizer]]

Give the resizer one image and it exports the set. That is the practical answer to
this entire page: you do not need to remember the numbers if the export does.

## Feed images

**4:5 is the most valuable ratio on any feed** that allows it. It occupies the most
vertical space a single post can claim, which means more of the screen and more time
before a thumb moves on. Square is safer for grids; 16:9 wastes space in a vertical
feed and should be reserved for link cards and video.

Per-network detail:
[Instagram image size](/blog/instagram-image-sizes) ·
[Facebook image size](/blog/facebook-image-sizes) ·
[Twitter image sizes for X](/blog/twitter-image-size) ·
[LinkedIn image and document sizes](/blog/linkedin-image-sizes).

## Vertical video and stories

9:16 everywhere: Stories, Reels, TikTok, Shorts. The trap is not the ratio, it is that
the platform draws its own interface on top of your frame — captions, the username,
the action rail, the progress bar. Text placed in those regions is covered.

[[tool:safe-zone-guide]]

The safe-zone guide shows the covered regions per platform on your own image. Detail:
[safe zones on social media](/blog/social-media-safe-zones) and
[TikTok video size](/blog/tiktok-video-size).

## Thumbnails

YouTube documents 3840×2160 at 16:9, minimum width 640 pixels, JPG or PNG, with a
2 MB limit on mobile and 50 MB on desktop
([YouTube Help](https://support.google.com/youtube/answer/72431)). The number that
matters more is the display size: a sidebar suggestion is around 170 pixels wide, and
anything unreadable at that width is decoration.

See [YouTube thumbnail size](/blog/youtube-thumbnail-size).

## Pins

Pinterest recommends a 2:3 ratio, "or 1000 x 1500 pixels", with titles up to 100
characters of which roughly the first 40 show in feeds, and descriptions up to 800
characters
([Pinterest specs](https://help.pinterest.com/en/business/article/pinterest-product-specs)).
Pinterest converts uploads to 8-bit RGB JPEG, which is why a PNG in sRGB is the safest
thing to upload.

See [Pinterest Pin size](/blog/pinterest-pin-size).

## Link preview cards

The 1200×630 Open Graph image is the one asset that appears on every platform at once
— shared to X, Facebook, LinkedIn, Slack, WhatsApp and everywhere else that unfurls a
URL. It is also the one most often wrong, because it is set once in a template and
never looked at again.

[[tool:link-preview-debugger]]

The debugger renders the card as each network draws it, from one fetch. See
[Open Graph tags](/blog/open-graph-tags) and
[link preview not showing](/blog/link-preview-not-showing).

## Profile pictures and headers

The forgotten assets, and the ones seen most often.

| Asset | Ratio | Notes |
| --- | --- | --- |
| Profile picture, everywhere | 1:1 | Displayed as a circle on most networks — keep the subject centred and away from the corners |
| YouTube banner | 16:9 | 2048×1152 minimum, with a safe area of 1235×338 for text and logos, 6 MB limit ([YouTube Help](https://support.google.com/youtube/answer/12950272)) |
| Facebook / LinkedIn cover | Wide, network-specific | Cropped differently on mobile and desktop; keep text central |

The YouTube banner is the clearest illustration of why safe zones matter more than
dimensions. The image is 2048 pixels wide; the region visible on every device is
1235×338 in the middle of it. Everything outside that is decoration that some viewers
see and others never do, and a logo placed in a corner is a logo that is missing on
television.

## What happens to your file after you upload it

Every network recompresses. That is not negotiable, and it is why two people can
upload the same photograph and get visibly different results — the one who uploaded a
correctly sized, already-optimised file gets a better outcome than the one who
uploaded a 12 MB export and let the platform decide.

Three rules that hold across all of them:

1. **Match the dimensions, do not exceed them wildly.** An 8000-pixel-wide upload is
   downscaled by the platform's own algorithm, which is tuned for speed rather than
   quality. Downscale it yourself first and you control the result.
2. **Avoid double compression.** Export once at high quality from the source, not a
   JPEG saved from a JPEG saved from a screenshot.
3. **Flat colour and text suffer most.** Photographs survive recompression
   gracefully; screenshots, charts and text-heavy graphics show artefacts quickly.
   That is a format decision, covered in
   [WebP vs JPEG vs PNG](/blog/webp-vs-jpeg-vs-png).

## Video frames follow the same rules

A video thumbnail, a Reel cover and a Story background are all still images inside a
video placement, and they follow the ratio table above: 16:9 for horizontal, 9:16 for
vertical, with the platform's interface drawn on top.

Two specific cases catch people out. An Instagram Reel cover appears both as a 9:16
cover and as a cropped tile in the profile grid, so the subject has to survive both
crops — see [Instagram Reels cover size](/blog/instagram-reels-cover-size). And a Story
that will carry text needs its text inside the safe area, not merely inside the frame
— see [Instagram Story size](/blog/instagram-story-size).

## A workflow that produces every size once

The reason this subject feels endless is that most people approach it per post: make
a thing, discover it is the wrong shape, remake it. The alternative is to decide the
shapes once.

**Compose at 4:5 or 9:16, whichever is taller for your platform mix.** Tall frames
crop down to every other ratio; wide frames do not crop up. If your main placement is
vertical video, compose 9:16 and take everything else out of it.

**Leave a margin you are willing to lose.** Roughly 10% on each edge of a vertical
frame, more at the bottom where captions and action rails sit. That margin is what
makes one composition survive four crops.

**Export the set in one pass, not one at a time.** This is the part worth automating:

[[tool:social-image-resizer]]

**Check the crops before publishing, not after.** A cropped Reel cover looks fine in
your editor and wrong in the grid. The preview tools exist precisely because the
platform's crop is not obvious from the source file.

**Then compress.** Last step, always — compressing before cropping wastes the quality
you were protecting.

[[tool:image-compressor]]

## Which sizes are worth memorising

Almost none. If you retain three numbers, make them these:

- **1080** — the short edge for anything going in a feed or a story.
- **1200×630** — the link preview card, because it is the one asset shared everywhere
  and the one nobody checks.
- **1235×338** — the YouTube banner safe area, because it is the only asset where the
  visible region is dramatically smaller than the file.

Everything else is a ratio, and a ratio is easier to remember than a number. When a
platform changes its recommendation - and they do, quietly - a file exported at the
right ratio and a reasonable resolution keeps working. A file exported at last year's
exact pixel dimensions is the one that starts looking soft.

## Getting the file right, not just the frame

Correct dimensions with a 6 MB file still produces a bad post: platforms recompress
aggressively, and the visible damage is worse when the upload is oversized.

| Problem | Fix |
| --- | --- |
| File too large, visibly recompressed | [Compress it first](/blog/compress-image-for-instagram) |
| Wrong format for the content | [WebP vs JPEG vs PNG](/blog/webp-vs-jpeg-vs-png) |
| Ratio maths by hand | [Aspect ratio calculator](/blog/aspect-ratios-explained) |

[[tool:image-compressor]]

:::tip Export once, at the largest ratio you need
Shoot and export at 4:5 or 9:16, then crop down to square and 16:9. Cropping down
keeps quality; scaling up never does, and cropping a 16:9 export into a 4:5 feed post
loses the top and bottom of the frame you actually composed.
:::

:::faq
Q: What is the best image size for social media?
A: There is no single one. Export at 1080 pixels on the short edge and produce the
ratios you need: 4:5 for feeds, 9:16 for stories and vertical video, 16:9 for
thumbnails and link cards, 1:1 for grids, 2:3 for Pinterest.
Q: Do social media image sizes change often?
A: The pixel recommendations drift; the aspect ratios almost never do. That is why
this reference is organised by ratio - a 9:16 export has been correct for vertical
video across every platform that has ever had it.
Q: What size should an Open Graph image be?
A: 1200×630, roughly 1.91:1. It is used for link previews on nearly every platform,
so it is worth getting right once.
Q: Can one image work everywhere?
A: One source image can, if you compose with margin and export crops from it. One
exported file cannot - a 16:9 image in a 9:16 slot is letterboxed or cropped to
nothing.
:::

The rest of the free toolkit, organised by the job it does, is in
[the free creator tools you actually need](/blog/free-creator-tools).

Turn one image into every size you need with the
[social image resizer](/tools/social-image-resizer).
