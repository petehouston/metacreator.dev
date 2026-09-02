---
{
 "id": "TW-03",
 "slug": "twitch-profile-picture-size",
 "title": "Twitch Profile Picture Size (and How to Get the Full-Size One)",
 "excerpt": "Upload a square image of at least 600×600. That is also the largest Twitch stores — and the page only ever publishes the 300, which is why avatars look soft when reused.",
 "category": "design",
 "categories": [],
 "tags": ["twitch", "brand-assets", "downloads"],
 "primary_keyword": "twitch profile picture size",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "Twitch Profile Picture Size: 600×600, and Where to Find It",
  "description": "The right Twitch profile picture size is 600×600 — which is also the largest Twitch keeps. How the size lives in the URL, and how to get the full-size file.",
  "focus_keyword": "twitch profile picture size",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "600×600 is the ceiling, 300×300 is what the page gives you",
  "og_description": "Twitch names the dimensions in the path, so the bigger file is one edit away.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
The Twitch profile picture size to upload is **600×600 pixels**, square, and that is
also the largest rendition Twitch keeps. The page itself only ever publishes the 300, so
an avatar grabbed from a screenshot or a link card is half the resolution of the file
sitting one URL away.

[[tool:twitch-image-downloader]]

## The Twitch profile picture size ladder

Twitch stores one avatar and serves it at six sizes: **600, 300, 150, 70, 50 and 28
pixels square**. Each is a real file, and each is used somewhere — 300 on the channel
page, 70 in the chat badge row, 28 in chat itself.

Ask for anything larger than 600 and you get a 404. There is no hidden original, which
is worth saying plainly because the equivalent claim is false on several other
platforms: Pinterest keeps an untouched upload, Apple Podcasts keeps a 3000-pixel
master, and Twitch keeps 600.

So the upload rule follows directly. Give it a square image of at least 600×600 and
Twitch has the best version it can hold. Give it less and every rendition below is
generated from something already soft. Twitch's own
[brand and channel guidance](https://www.twitch.tv/p/en/brand/) covers the surrounding
assets — the offline banner and the panels — which are different sizes again.

## Why the page hands you the 300

The link card. When a Twitch channel is shared anywhere — a chat app, a timeline, a
Discord embed — the preview image comes from the `og:image` tag on the channel page, and
that tag names the 300.

Which means every general-purpose image downloader that reads link previews will hand
you a 300 and call it the profile picture. It is not wrong, exactly; it is reporting
what the page published. It simply has no way to know that a 600 exists at an address
nobody advertises.

## The size is in the path

Here is the whole mechanism, because it is unusually clean:

`https://static-cdn.jtvnw.net/jtv_user_pictures/<id>-profile_image-300x300.png`

The `300x300` is a path segment, not a query parameter and not part of a signature.
Change it to `600x600` and you get the 600. That is why the
[Twitch image downloader](/tools/twitch-image-downloader) can offer the whole ladder
from a channel name: it reads the URL the page publishes and rewrites the size.

It does one more thing, and it matters: **every candidate is fetched and measured before
it appears in the results**. A tool that assumes the ladder would offer you a 1200×1200
link that 404s. A tool that checks tells you the ladder stops at 600 — which is the
actual answer to the question.

Clip stills and VOD thumbnails follow the same convention with different numbers, and
Twitch keeps different sizes for different items, so the same verification applies
there.

## Get the full-size file

1. Copy the channel address, or just type the channel name.
2. Paste it into the [Twitch image downloader](/tools/twitch-image-downloader).
3. Take the first row — it is the largest size that came back as a real image.

For a clip or a VOD, paste that link instead and you get its still ladder. If Twitch has
no thumbnail for the item — a deleted VOD, a sub-only VOD, a channel that does not exist
— it serves its own logo in place of one, and the tool says so rather than handing you
the Twitch logo as your answer.

## Prepare an avatar that survives every size

An avatar is drawn at 28 pixels in chat. That is the constraint nobody designs for, and
it is where most channel art falls apart.

- **One idea, large.** A face, a letter, a shape. Not a wordmark and not a scene.
- **Test at 28 pixels**, not at 600. If it is a grey blob in chat, it is a grey blob
  where it appears most often.
- **Square from the start.** Twitch crops to a circle for display, so anything in the
  corners is decoration you will not see.
- **Consistent across platforms.** Use the
  [social image resizer](/tools/social-image-resizer) to cut the same master into every
  platform's required size at once, rather than re-cropping by hand each time.

While you are doing brand housekeeping, the
[username availability checker](/tools/username-availability-checker) is the fastest way
to find out whether the name matches everywhere else — and
[Twitch monetization](/blog/twitch-monetization) covers what the channel does once it
looks the part.

:::faq
Q: What size should a Twitch profile picture be?
A: 600×600 pixels, square. That is the largest Twitch stores, and every smaller rendition is generated from it.
Q: What is the maximum Twitch avatar resolution?
A: 600×600. URLs asking for larger sizes answer 404 — there is no bigger file behind them.
Q: Why does the avatar I saved look blurry?
A: You almost certainly saved the 300, which is the only size a channel page or a link card publishes. The 600 is at a different address.
Q: Can I download somebody else's Twitch avatar?
A: The file is publicly served, so yes — for reference, a raid graphic you have permission for, or a credit. It is still their image and their brand.
:::

Get the full-size file from the
[Twitch image downloader](/tools/twitch-image-downloader), then cut it for every other
platform at once with the [social image resizer](/tools/social-image-resizer).
