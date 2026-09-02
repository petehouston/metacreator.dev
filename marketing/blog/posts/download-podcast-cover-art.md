---
{
 "id": "DL-09",
 "slug": "download-podcast-cover-art",
 "title": "How to Download Podcast Cover Art at Full Size",
 "excerpt": "Apple's public directory hands out a 600-pixel copy. The 3000×3000 the publisher submitted is at the same address with one number changed.",
 "category": "design",
 "categories": [],
 "tags": ["audio", "downloads", "how-to"],
 "primary_keyword": "podcast cover art download",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "Podcast Cover Art Download: Get the Full 3000×3000 File",
  "description": "Download podcast cover art at 3000×3000 from any Apple Podcasts link. Why the page only shows a 600, and how the full-size file is reached.",
  "focus_keyword": "podcast cover art download",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "The 3000 is one number away from the 600",
  "og_description": "Apple requires publishers to submit 3000×3000 artwork, and it keeps it.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
A podcast cover art download should give you **3000×3000 pixels**, because that is the
size Apple requires publishers to submit and the size Apple keeps. What a show page or a
link preview hands you is a 600 — the same file, five times smaller in each dimension,
at an address one number away from the good one.

[[tool:apple-podcasts-artwork-downloader]]

## Where a full-size podcast cover art download comes from

Apple runs a public, unauthenticated directory API — the same one the Podcasts app asks
— and it answers with a record for any show or episode. That record carries an artwork
URL ending in a literal `600x600bb.jpg`.

The size is a **path segment**, not a query parameter and not part of a signature. So
the same file is served at any size by substitution: change `600x600` to `3000x3000` and
you get the master. Ask for 4000 and Apple hands back the 3000 anyway, which is a useful
confirmation that there is nothing larger behind it.

Why 3000 exists at all: Apple's
[podcast artwork requirements](https://podcasters.apple.com/support/896-artwork-requirements)
specify square artwork between 1400 and 3000 pixels, and effectively every show in the
directory submits at the top of that range. The master is not a lucky find; it is the
file the publisher uploaded.

## Show art and episode art are different pictures

A link copied from a single episode carries an `?i=` identifier. Shows that ship
per-episode covers have different artwork on that record, and asking Apple about the
episode id rather than the show id is the difference between the right picture and its
parent.

The [Apple Podcasts artwork downloader](/tools/apple-podcasts-artwork-downloader) reads
whichever identifier your link carries, prefers the episode when both are present — a
link copied from an episode has both — and tells you which one it looked up.

## Get the file

1. Open the show or episode in Apple Podcasts or on the web.
2. Copy the address. It looks like `podcasts.apple.com/…/id1200361736`, optionally with
   `?i=` and an episode id.
3. Paste it into the downloader. The bare numeric id works too.
4. Take the last row — the 3000. Everything above it is a resize of the same file.

The run also hands back the show's **RSS feed URL**, because Apple's record carries it.
That is the fastest way to find a podcast's real feed from an Apple link, and it is
usually what people are actually after when they go looking for a show's details.

## When it does not work

**The show is not in the directory.** Feeds go dead and Apple removes the listing while
the old link keeps working. If the lookup finds nothing, that is what happened.

**The artwork URL is not in the expected shape.** Rare, and the tool hands back the URL
Apple gave rather than inventing five that would 404 — the same rule every downloader
here follows.

**You wanted Spotify.** Different platform, different answer, and a much less generous
one: [Spotify cover art size](/blog/spotify-cover-art-size) explains why 640 is the
ceiling there and no tool can beat it.

## Why the small copy is what you usually end up with

Three routes hand you a 600 without ever mentioning that a 3000 exists, and between them
they account for almost every soft podcast cover in circulation.

**Right-clicking the web page.** The image element on a show page is the 600. Saving it
saves exactly what is on screen.

**A general image downloader.** Anything that reads a page's link-preview tags gets the
600, because that is what the tag names. It is reporting the page accurately; it simply
has no way to know the master exists.

**A screenshot.** Worse again, because a screenshot is the 600 resampled to whatever
your display was doing at the time.

None of those are wrong so much as incomplete, and the difference only becomes visible
when the artwork is placed somewhere large — a thumbnail, a slide, a printed flyer for a
live show — at which point re-finding the original is a chore.

## What you may do with it

The artwork belongs to the publisher. A directory listing, a review, a link card, a
recommendation post, your own reference — all ordinary use. Presenting it as the face of
something you made is not, and neither is re-uploading a show's cover as your own
podcast's.

If you are making artwork rather than collecting it, the same square master is what
every other platform wants too — the [social image resizer](/tools/social-image-resizer)
cuts one file into each platform's required size, and the
[image compressor](/tools/image-compressor) will get a 3000-pixel PNG under a submission
limit without visible loss. The general case for every other platform is covered in
[downloading social media images](/blog/download-social-media-images).

:::faq
Q: What size is podcast cover art?
A: Between 1400 and 3000 pixels square by Apple's requirements, and effectively every show submits at 3000×3000. That master is what a full-size download should return.
Q: Why is the artwork I saved only 600 pixels?
A: Because 600 is what the web page and the link card publish. The 3000 lives at the same address with the size segment changed.
Q: Does this need an Apple account?
A: No. The directory lookup it uses is public and unauthenticated — it is the same request the Podcasts app makes.
Q: Can I get episode artwork rather than the show's?
A: Yes. Paste the episode link, which carries an ?i= identifier, and the tool asks about the episode instead of its parent show.
:::

Paste a show or episode link into the
[Apple Podcasts artwork downloader](/tools/apple-podcasts-artwork-downloader), or see
what the same question looks like on
[Spotify](/tools/spotify-cover-art-downloader) — where the answer is a good deal
smaller.
