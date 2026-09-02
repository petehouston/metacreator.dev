---
{
 "id": "SZ-09",
 "slug": "youtube-banner-size",
 "title": "YouTube Banner Size: The 1546×423 That Decides Everything",
 "excerpt": "Upload 2560×1440. Design for 1546×423 — the window every phone shows, which is 17% of the file. Four devices crop the same image four different ways.",
 "category": "design",
 "categories": [],
 "tags": ["youtube", "image-sizes", "safe-zones"],
 "primary_keyword": "youtube banner size",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "YouTube Banner Size: 2560×1440 Upload, 1546×423 Safe Area",
  "description": "The YouTube banner size to upload is 2560×1440, but a phone shows only 1546×423 of it. All four device crops, drawn to scale over your own artwork.",
  "focus_keyword": "youtube banner size",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "17% of your banner is what most people see",
  "og_description": "One file, four crops. Design outward from the 1546×423 centre, not inward from 2560.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
The YouTube banner size to upload is **2560×1440 pixels**, under 6 MB. But the number
that decides your design is **1546×423** — the window every phone shows, which is 17% of
the file you uploaded. Everything outside it is décor for a television.

[[tool:youtube-banner-safe-area]]

## The YouTube banner size every device shows

You upload one image. YouTube crops it four ways, all centred:

| Surface | Shows | Share of the file |
| --- | --- | --- |
| TV | 2560 × 1440 | 100% |
| Desktop | 2560 × 423 | 29% |
| Tablet | 1855 × 423 | 21% |
| Mobile | 1546 × 423 | 18% |

Read that table twice. The only surface that shows the whole image is the one almost
nobody browses YouTube on, and the surface most of your audience uses shows less than a
fifth of it.

Those four figures are YouTube's own published channel-art dimensions, listed in its
[channel art guidance](https://support.google.com/youtube/answer/2972003). That is why
so many channels have a banner whose wordmark is perfect on the designer's monitor and
cut in half on a phone. Nothing went wrong in the export. The design was
made for the canvas rather than for the window.

## Design outward from the smallest window

Reverse the usual order and the problem disappears.

1. **Start with a 1546×423 rectangle.** Put the channel name, the face, the tagline —
   everything that has to be read — inside it.
2. **Extend to 1855×423** for tablets. Anything here is a bonus.
3. **Extend to 2560×423** for desktop. Texture, colour, a repeated pattern.
4. **Fill the rest of the 2560×1440 canvas** with background that can be lost entirely.

The 423-pixel height is the constraint people miss. Every browser surface is a *strip*.
Vertical composition — a stacked logo, a two-line tagline, anything tall — is fighting a
crop it cannot win.

Paste your artwork URL into the
[banner safe area preview](/tools/youtube-banner-safe-area) and it draws each crop over
your own image, to scale, so you can see what a phone keeps rather than reading a
measurement and hoping.

## The 6 MB limit is on the file, not the canvas

A 2560×1440 PNG of a photograph routinely exceeds 6 MB. A JPEG of the same image at high
quality does not.

So: export photographic banners as JPEG, and keep PNG for flat colour, hard edges and
text — which is what PNG is good at anyway. If a PNG is genuinely the right format and
still too heavy, run it through the [image compressor](/tools/image-compressor) rather
than dropping the dimensions. Losing pixels to save bytes is the wrong trade on an asset
YouTube already upscales for televisions.

Upload smaller than 2560 wide and YouTube scales it up, which is what makes a banner
look soft on a TV while looking acceptable on a phone.

## The other assets, in the same session

While you are in the brand folder, the sizes the rest of a channel needs are worth doing
at once rather than one at a time when each is missed:

- **Channel avatar** — square, and drawn small almost everywhere.
- **Video thumbnails** — 1280×720, with the duration badge covering the bottom-right
  corner on every surface.
- **Every other platform's header**, which is a different aspect ratio again.

The [social image resizer](/tools/social-image-resizer) cuts one master into each
platform's required size in a single pass, and the
[safe zone guide](/tools/safe-zone-guide) does for vertical video what this post does for
the banner: shows where the app's own chrome sits over your frame. The broader picture is
in [social media image sizes](/blog/social-media-image-sizes).

## Why the strip is only 423 pixels tall

It is worth understanding rather than memorising, because it explains what survives.

A browser draws the banner as a band across the top of the channel page, above the
avatar, the name and the tab row. That band is a fixed height regardless of window
width — so as the window gets wider, the banner gets *wider*, never taller. The crop is
horizontal on a desktop and horizontal-plus-vertical on a phone.

Two design rules fall out of that:

- **Type sits on the horizontal axis.** A stacked lockup loses its lower half on every
  browser surface. Set the name and the tagline side by side, not above and below.
- **Vertical padding is free.** Anything in the top or bottom third of the canvas is
  television-only, so that is where a gradient, a texture or a repeated pattern goes —
  never a word.

The other thing the strip does is crop faces badly. A portrait composed for a square
avatar, dropped into a 423-pixel band, loses the forehead and the chin. Shoot or crop
for the band separately rather than reusing the avatar.

## Check a banner before you upload it

1. Export at 2560×1440 and confirm the file is under 6 MB.
2. Put the URL into the [preview](/tools/youtube-banner-safe-area).
3. Look only at the Mobile frame first. Is your name fully inside the clear rectangle?
4. Then check Desktop for anything important sitting above or below the strip.
5. Fix by moving elements inward, not by shrinking the whole design — a banner scaled
   down to fit the safe area wastes the space it does have.

:::faq
Q: What size should a YouTube banner be?
A: 2560×1440 pixels and under 6 MB. That is the upload canvas; the design has to work inside the 1546×423 window a phone shows.
Q: What is the YouTube banner safe area?
A: 1546×423 pixels, centred. It is the region every device displays, so it is where text and logos belong.
Q: Why is my YouTube banner cut off on mobile?
A: Because a phone shows a 1546×423 window from the centre of a 2560×1440 file. Anything you placed outside that window was never going to appear.
Q: Can I upload a smaller banner?
A: YouTube will accept it and scale it up, which softens the image on large displays. Export at 2560×1440 and the problem does not arise.
:::

Draw the four crops over your own artwork with the
[YouTube banner safe area preview](/tools/youtube-banner-safe-area), then cut the rest
of your brand assets in one pass with the
[social image resizer](/tools/social-image-resizer).
