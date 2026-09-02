---
{
 "id": "SZ-10",
 "slug": "compress-image-for-instagram",
 "title": "How to Compress an Image for Instagram Without Damage",
 "excerpt": "Every platform recompresses your upload. Compressing first, correctly, is how you control the result instead of letting their algorithm decide.",
 "category": "design",
 "categories": [],
 "tags": ["image-optimization", "instagram", "how-to"],
 "primary_keyword": "compress image for instagram",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "How to Compress an Image for Instagram Properly",
  "description": "How to compress an image for Instagram without visible damage: the right dimensions, the quality setting to use, and the order the steps have to happen in.",
  "focus_keyword": "compress image for instagram",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Compressing images for social without wrecking them",
  "og_description": "Why your photo looks soft after uploading, and the order of operations that fixes it.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
To compress an image for Instagram without visible damage: resize it to 1080 pixels on
the short edge first, then export as JPEG at around 80-85% quality, then upload.
Instagram recompresses everything regardless — the point is to hand it a file that
compresses well rather than one it has to fix.

## Why you should compress an image for Instagram yourself

Every platform re-encodes uploads to control bandwidth. What varies is how much damage
that does, and the damage is worst when the platform is doing two jobs at once:
**downscaling and recompressing**.

Upload a 6000-pixel-wide photo and the platform scales it to feed size using a fast,
quality-indifferent algorithm, then compresses the result. Upload a correctly sized
file and it only has to do the second half. That single difference accounts for most
"why does my image look soft on Instagram" complaints.

[[tool:image-compressor]]

The compressor shows a live comparison as you move the quality slider, so you can find
the point where the file gets small and the image does not visibly change — which is
different for every photograph.

## The order of operations

1. **Crop to the right ratio.** 4:5, 1:1 or 9:16 — see
   [Instagram image size](/blog/instagram-image-sizes).
2. **Resize** to 1080 pixels on the short edge.
3. **Sharpen slightly**, if your editor offers it, because downscaling softens edges.
4. **Compress** to JPEG at 80-85%, or PNG if the image is flat colour or text.
5. **Upload.**

Doing this in the wrong order is the common mistake. Compressing before cropping means
you compress pixels you then throw away, and the surviving pixels have already lost
information.

## How far you can push quality

There is no universal number, because compression artefacts depend on content:

| Content | Compresses well? | Setting |
| --- | --- | --- |
| Photographs with texture | Very well | JPEG 75-85% |
| Skin tones and gradients | Poorly — banding shows | JPEG 85-90% |
| Flat colour, logos, UI | Badly as JPEG | PNG, or WebP |
| Text overlays | Badly as JPEG — edges fringe | PNG, or JPEG at 90%+ |
| Screenshots | Badly as JPEG | PNG |

The pattern: JPEG is built for photographs and struggles with hard edges. If your
image is mostly type, JPEG is the wrong format at any quality setting —
[WebP vs JPEG vs PNG](/blog/webp-vs-jpeg-vs-png) covers the trade-offs.

[[tool:image-format-converter]]

## How small is small enough

There is no target file size, because the platform is going to re-encode anyway. What
you are optimising is the *ratio of quality to information the encoder has to throw
away*, and there are two useful stopping points.

**For a feed post**, stop when the file is a few hundred kilobytes and the image looks
identical at 100% zoom. Below that you are giving up quality for a saving nobody
experiences - the platform's own encoder is going to decide the delivered size.

**For an image on your own site**, keep going. Here the file size is the delivered
size, it counts against your page load, and it is measured by search engines as part
of Core Web Vitals. This is where format choice earns its keep: a WebP or AVIF version
of the same photograph is meaningfully smaller than the JPEG at equal quality.

The mistake in both cases is compressing repeatedly. Each save re-encodes what the
previous save already damaged, and the artefacts accumulate in a way that no later
step can reverse.

## What compressing does not fix

- **A file that was already compressed.** Every save loses information permanently.
  Work from the original, not from a JPEG somebody sent you.
- **A screenshot of a screenshot.** Same problem, more of it.
- **Wrong dimensions.** Compression makes a file smaller; it does not make a 16:9
  image fit a 4:5 slot.

[[tool:social-image-resizer]]

The resizer handles the dimension half from one source file — full reference in
[social media image sizes](/blog/social-media-image-sizes).

## Platform-specific notes

**Instagram** favours 1080 pixels on the short edge and recompresses hardest on
uploads from the app itself. Uploading from a desktop browser generally preserves more.

**Pinterest** converts everything to 8-bit RGB JPEG
([Pinterest specs](https://help.pinterest.com/en/business/article/pinterest-product-specs)),
so a PNG in sRGB is the safest input.

**X and LinkedIn** keep PNG as PNG for reasonably sized files, which makes them the
better places for screenshots and charts.

**Facebook** recompresses aggressively; the correctly sized upload advice applies
doubly — see [Facebook image sizes](/blog/facebook-image-sizes).

:::faq
Q: How do I compress an image for Instagram without losing quality?
A: Resize to 1080 pixels on the short edge, then export as JPEG at 80-85%. Doing the
resize yourself is what prevents Instagram's own downscaling from softening the image.
Q: What is the best image size for Instagram to avoid compression?
A: 1080 pixels on the short edge in the ratio you are posting - 1080×1350 for a
portrait feed post. Larger files are downscaled by Instagram rather than by you.
Q: Does Instagram compress every upload?
A: Yes. You cannot avoid it, only give it a file that survives it well.
Q: Should I use PNG or JPEG for social posts?
A: JPEG for photographs, PNG for flat colour, text or screenshots. JPEG produces
visible fringing around hard edges.
:::

Find the point where the file shrinks and the image does not with the
[image compressor](/tools/image-compressor).
