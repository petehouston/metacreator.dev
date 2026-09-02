---
{
 "id": "PI-05",
 "slug": "download-pinterest-image",
 "title": "How to Download a Pinterest Image in Full Size",
 "excerpt": "Pinterest serves every Pin at four fixed widths and keeps the original under a path the interface never links to. Here is how to get the file as uploaded.",
 "category": "design",
 "categories": [],
 "tags": ["pinterest", "image-optimization", "how-to"],
 "primary_keyword": "download pinterest image",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "How to Download a Pinterest Image in Full Size",
  "description": "Download a Pinterest image at the size it was uploaded, not the 236px grid thumbnail. The originals path explained, plus what you may do with the file.",
  "focus_keyword": "download pinterest image",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Get the original Pin, not the grid thumbnail",
  "og_description": "Pinterest keeps the upload under /originals/ and never links to it.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
To download a Pinterest image at full size, take the Pin's image URL and swap the width
directory in it for `originals`. Pinterest serves every Pin from a path named after its
width — `/236x/`, `/474x/`, `/564x/`, `/736x/` — and keeps the file as uploaded under
`/originals/`, which nothing in the interface ever links to.

[[tool:pinterest-image-downloader]]

## The five sizes a Pin is served at

| Path | Width | Where Pinterest uses it |
| --- | --- | --- |
| `/236x/` | 236 px | The grid thumbnail |
| `/474x/` | 474 px | The feed |
| `/564x/` | 564 px | Related Pins |
| `/736x/` | 736 px | The Pin closeup — the largest fixed width |
| `/originals/` | As uploaded | Nowhere in the UI |

Because all five are the same path under a different prefix, moving between them needs
no guesswork and no request:

```text
https://i.pinimg.com/236x/ab/cd/ef/abcdef.jpg
https://i.pinimg.com/originals/ab/cd/ef/abcdef.jpg
```

The original is frequently 1000×1500 or larger, since
[the recommended Pin size](/blog/pinterest-pin-size) is 1000×1500 at 2:3. Every resized
version has been through a second round of compression to produce it, so text and fine
detail are softer in all four.

## Right-clicking gets you the wrong file

Right-clicking a Pin in the grid saves the 236-pixel thumbnail. Right-clicking in the
closeup saves the 736. Neither is the upload, and neither is obviously wrong until you
put it in a document and the text goes fuzzy.

This is not Pinterest being obstructive — it is a sensible CDN doing its job. A phone
loading a grid of forty Pins should not be made to download forty full-resolution
images. The consequence is just that the version you can click on is never the version
you want.

## Download a Pinterest image by hand

1. Open the Pin.
2. Right-click the image and choose **Copy image address**.
3. Paste it somewhere you can edit it. It will look like
   `https://i.pinimg.com/736x/…`.
4. Replace `736x` with `originals`.
5. Open the edited URL and save the image.

If the result is a 404, the Pin is a video Pin or an Idea Pin — those have no still to
download and what you get is a cover frame at best.

## What you may actually do with it

Most Pins point at somebody's product photo or blog image, and downloading one is not a
licence to republish it.

Reference, moodboards, research and competitive analysis are ordinary uses of the web.
Re-uploading somebody's photograph as your own Pin is not, and it is worth noting that
**re-pinning through Pinterest itself is the version that keeps the credit and the
outbound link attached** — a downloaded file carries neither, which is exactly why the
original creator would rather you re-pinned.

Pinterest's own position on this is in its
[copyright policy](https://policy.pinterest.com/en/copyright).

:::warning
A Pin's image and its destination link are separate things. Downloading the image
detaches it from the site it was pinned from, which is the whole reason Pinterest exists
for the person who made it.
:::

## Downloading images from other platforms

The same trick does not generalise — Pinterest's width-named paths are its own
convention. What does generalise is the metadata: every platform publishes a post's
largest image in its Open Graph tags so that link cards render elsewhere, and that copy
is usually much larger than the one the feed shows.

The [social media image downloader](/tools/social-media-image-downloader) reads that tag
for any public post, on any platform, including every slide of a carousel where the
platform publishes them. Three other platforms keep a larger copy still, each behind its
own convention — the
[guide to downloading social media images](/blog/download-social-media-images) has the
whole map.

:::faq
Q: Does this work with video Pins and Idea Pins?
A: You get the cover frame, not the video. These are image tools.
Q: The original is smaller than 736 pixels.
A: Then the Pin was uploaded at that size. `/originals/` is the file as uploaded, so it is only larger than the renditions when the upload was.
Q: Can I download a whole board at once?
A: Not with this. A board downloader is a scraper, which is a different thing from reading one Pin's public metadata, and it is against Pinterest's terms.
Q: Nothing came back for my Pin.
A: Pins on secret boards are not public, and Pinterest answers with a sign-in page for them. Check the Pin opens in a private browser window first.
:::

Paste the Pin URL — or the `pin.it` short link — into the
[Pinterest image downloader](/tools/pinterest-image-downloader), which lists all five
sizes at once.
