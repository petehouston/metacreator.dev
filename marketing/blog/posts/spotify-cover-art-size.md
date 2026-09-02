---
{
 "id": "DL-10",
 "slug": "spotify-cover-art-size",
 "title": "Spotify Cover Art Size: Why 640px Is the Ceiling",
 "excerpt": "Spotify publishes album and artist images at 640, 300 and 64 pixels. There is no original behind the 640 — every 'HD Spotify cover art' result is upscaling the same file.",
 "category": "design",
 "categories": [],
 "tags": ["audio", "downloads", "explainer"],
 "primary_keyword": "spotify cover art size",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "Spotify Cover Art Size: 640px Is the Largest You Can Get",
  "description": "Spotify cover art size tops out at 640×640 for album and artist images. What the three renditions are, how to get the largest, and why there is nothing bigger.",
  "focus_keyword": "spotify cover art size",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "640 is the ceiling, and that is the whole story",
  "og_description": "No original, no HD, no 4K. Anything larger you are offered was upscaled from this file.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
The largest Spotify cover art size you can get is **640×640 pixels**. Album and artist
images are published at 640, 300 and 64, and there is no original behind the 640 — so
every result promising HD or 4K Spotify artwork is upscaling this same file and charging
you attention for it.

[[tool:spotify-cover-art-downloader]]

## The three Spotify cover art sizes, and how they are addressed

Spotify's image URLs are a fixed prefix followed by the image's own identifier, and the
**prefix is the size**. For an album cover: one prefix is the 640, another the 300, a
third the 64. Swap the prefix and you move between renditions with no second request.

That is the same trick Pinterest's width directories allow, and it is why a downloader
can offer the ladder instantly. It also explains a common annoyance: Spotify's public
[oEmbed endpoint](https://developer.spotify.com/documentation/embeds/reference/oembed) —
which is what a link card reads, and what needs no token — hands out the **300** by
default. The useful one is always one substitution away and never the one you are given.

An artist portrait has its own ladder: 640, 320, 160. A playlist mosaic and a podcast
cover are served as a single rendition, which is why the
[Spotify cover art downloader](/tools/spotify-cover-art-downloader) fetches and measures
those rather than offering sizes that do not exist.

## Why there is no original

This is the part worth being blunt about, because it is where every other page on this
subject quietly misleads.

Some platforms keep the upload. Pinterest serves an `/originals/` copy of the file as
posted. Apple Podcasts keeps the 3000×3000 master a publisher is required to submit, as
covered in [downloading podcast cover art](/blog/download-podcast-cover-art). Twitch
keeps a 600 avatar the page never shows you.

Spotify keeps **none of those**. The label delivers artwork at a much higher resolution
than 640 when it delivers a release, and Spotify does not publish it. There is no
address to guess, no prefix to swap, no parameter to add. A tool that offers you a
1500-pixel Spotify cover has resampled the 640, and resampling adds pixels rather than
detail.

If you need genuinely high-resolution album artwork — for print, for a physical release,
for a large display — the source is the label's press kit or the artist directly, not a
streaming client.

## What 640 is enough for

More than people assume, which is the other half of the honest answer.

- **A link card or an embed.** Both are drawn far below 640.
- **A playlist post or a story graphic.** A 640 square holds up at typical phone widths.
- **A thumbnail, a slide, a moodboard.** Fine.
- **A full-width hero on a desktop site.** Not fine — this is the case where 640 shows.
- **Print.** Not fine at any size that matters.

If you are placing a 640 into a layout, convert rather than scale: the
[image format converter](/tools/image-format-converter) will give you a WebP or AVIF at
the same dimensions and a fraction of the weight, which is the useful optimisation when
you cannot get more pixels.

## Get the largest one

1. Copy any `open.spotify.com` link — track, album, artist, playlist, show or episode.
   The `spotify:album:…` URI the desktop app copies works too.
2. Paste it into the downloader.
3. Take the first row.

The `?si=` on a shared Spotify link is dropped before anything is fetched. That
parameter is a per-share identifier naming the session the link was copied from, and
there is no reason for it to travel with a request about an album cover — the same
reasoning as [removing tracking from links](/blog/remove-tracking-parameters-from-url).

## What you may do with it

Cover art is the copyright of the label, the artist or the podcast publisher. Describing
a playlist, reviewing a record, illustrating a recommendation, keeping a reference — all
ordinary. Releasing it as artwork of your own is not, and a 640 would be a poor way to
try.

:::faq
Q: What is the maximum Spotify cover art size?
A: 640×640 for album and artist images. Spotify publishes 300 and 64 below it, and nothing above it.
Q: How do I get HD or 4K Spotify album art?
A: You cannot, from Spotify. Anything larger you are offered has been upscaled from the 640. High-resolution artwork comes from the label or the artist.
Q: Why did my download come back at 300 pixels?
A: Because the oEmbed endpoint and the link card both publish the 300. The 640 is at the same address with a different size prefix.
Q: Does this work for podcasts on Spotify?
A: Yes, but podcast and playlist covers are served as one rendition rather than a ladder, so the tool measures that file and reports its real size.
:::

Get the 640 from the
[Spotify cover art downloader](/tools/spotify-cover-art-downloader) — and if the artwork
you need is a podcast's, the
[Apple Podcasts artwork downloader](/tools/apple-podcasts-artwork-downloader) will
usually have a 3000 of the same show.
