---
{
 "id": "SZ-04",
 "slug": "download-youtube-thumbnail",
 "title": "How to Download a YouTube Thumbnail",
 "excerpt": "Every YouTube video publishes its thumbnail at several resolutions. Here is how to download any of them, including the maximum-resolution version.",
 "category": "design",
 "categories": [],
 "tags": ["youtube", "thumbnails", "how-to"],
 "primary_keyword": "download youtube thumbnail",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "How to Download a YouTube Thumbnail",
  "description": "Download a YouTube thumbnail at full resolution: paste the video URL, or build the image address yourself. Every size a video publishes, explained.",
  "focus_keyword": "download youtube thumbnail",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Download any YouTube thumbnail, at every resolution",
  "og_description": "Paste a URL, or construct the image address yourself. Both routes explained.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
To download a YouTube thumbnail, paste the video URL into a thumbnail downloader and
pick a resolution — or build the image address yourself, which takes about ten
seconds once you know the pattern. Every public video publishes its thumbnail at several sizes, plus
automatically generated frames, and all of them are plain image files.

[[tool:youtube-thumbnail-downloader]]

## Which YouTube thumbnail resolutions a video publishes

| Name | Size | Notes |
| --- | --- | --- |
| `maxresdefault` | 1280×720 | The custom thumbnail. Absent if the channel never set one |
| `sddefault` | 640×480 | Padded to 4:3 |
| `hqdefault` | 480×360 | Always present |
| `mqdefault` | 320×180 | |
| `default` | 120×90 | |
| `hq1`, `hq2`, `hq3` | 480×360 | Auto-generated frames from the video itself |

The image URL is predictable: `img.youtube.com/vi/VIDEO_ID/maxresdefault.jpg`. Swap
the last part for any name in the table. If `maxresdefault` returns a placeholder, the
video has no custom thumbnail — fall back to `hqdefault`, which always exists.

## Doing it by hand

1. Copy the video URL.
2. Take the ID: the part after `watch?v=` or after `youtu.be/`.
3. Open `https://img.youtube.com/vi/THAT_ID/maxresdefault.jpg`.
4. Save the image.

That is the whole mechanism. A downloader is faster because it tries the resolutions
in order and shows you which exist, but nothing secret is happening.

## Why several resolutions exist at all

YouTube generates the set so that a phone on a slow connection is not made to download
a 1280-pixel image for a 120-pixel slot. That is also why `hqdefault` is the one that
always exists: it is the workhorse size used across most surfaces, and it is generated
whether or not a creator ever uploaded custom artwork.

Two consequences worth knowing. First, `maxresdefault` is the only one that is
genuinely the creator's uploaded file - the smaller sizes are re-encodes, and text that
was crisp in the original can be soft in them. If you are studying someone's
typography, use the max version or you are studying an artefact.

Second, the `hq1`, `hq2` and `hq3` frames are pulled from the video itself, at fixed
points. They are what YouTube offers a creator who has not uploaded a custom
thumbnail, and their existence is a quick way to tell whether a channel bothers with
thumbnails at all: if `maxresdefault` is missing across a channel's catalogue, nobody
there is making them. YouTube's own guidance on custom thumbnails, including the
formats and limits, is in
[YouTube Help](https://support.google.com/youtube/answer/72431).

## The channel's other images

Avatars and banners are separate assets and are not on `img.youtube.com`:

[[tool:youtube-image-downloader]]

The image downloader handles the channel avatar and banner as well as a video's
generated frames — useful when you are assembling a competitive research board rather
than grabbing one file.

## What you may do with the file

Downloading is trivially easy; that does not make the image yours. A thumbnail is
somebody's creative work, and the sensible uses are:

- **Research and analysis** — comparing what ranks in your niche.
- **Commentary and criticism**, where quotation is defensible.
- **Your own videos**, recovering a file you made and lost.
- **A facade embed** on your own site, where the thumbnail links to the video it
  belongs to.

Republishing someone else's thumbnail as your own artwork is not a grey area. If you
are building the facade case, the embed markup is in
[YouTube embed code](/blog/youtube-embed-code).

## If you are downloading it to study it

The useful comparison is not the thumbnail on its own but the thumbnail at display
size next to its title. Shrink it to about 170 pixels wide and ask whether you can
tell what the video is. Most cannot, which is exactly why the ones that can win.

Sizing and the covered corners are in
[YouTube thumbnail size](/blog/youtube-thumbnail-size), and the wider field-by-field
picture is in the [YouTube SEO guide](/blog/youtube-seo).

:::faq
Q: How do I download a YouTube thumbnail in full resolution?
A: Use `maxresdefault.jpg` for the 1280×720 version, either through a downloader or by
building the img.youtube.com URL yourself. If it is missing, the video has no custom
thumbnail.
Q: Why is maxresdefault not available for some videos?
A: Because no custom thumbnail was uploaded. YouTube generates lower-resolution frames
automatically, and hqdefault always exists.
Q: Can I download the thumbnail of a private video?
A: No. Private videos publish nothing. Unlisted videos do, if you have the link.
Q: Is it legal to download someone's YouTube thumbnail?
A: Downloading a public image is straightforward; republishing it as your own is not.
Research, commentary and linking back are the defensible uses.
:::

Grab every available resolution at once with the
[YouTube thumbnail downloader](/tools/youtube-thumbnail-downloader). For the picture
behind a post on any other platform, see the
[guide to downloading social media images](/blog/download-social-media-images).
