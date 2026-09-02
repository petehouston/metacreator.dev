---
{
 "id": "DL-01",
 "slug": "download-social-media-images",
 "title": "How to Download Images From Any Social Platform",
 "excerpt": "Every platform publishes a larger copy of a post's picture than the one the feed renders. Here is where each one keeps it, and where the trail goes cold.",
 "category": "design",
 "categories": [],
 "tags": ["downloads", "image-optimization", "guide"],
 "primary_keyword": "download social media images",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "How to Download Social Media Images at Full Size",
  "description": "Download social media images at the size they were uploaded. What each platform publishes, which ones hide a bigger copy, and where a saved link expires.",
  "focus_keyword": "download social media images",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "The full-size picture, not the one the feed shrank",
  "og_description": "Where each platform keeps the larger copy of a post's image, and how to reach it.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
To download social media images at full size, do not right-click the picture in the
feed. Every platform publishes a second, larger copy of a post's image so that link
cards render on other sites, and on four of them there is a third copy larger still —
the file exactly as it was uploaded.

[[tool:social-media-image-downloader]]

## Why the picture in the feed is the wrong file

A feed is a performance problem before it is anything else. Somebody scrolling forty
posts on a phone cannot be made to download forty full-resolution photographs, so every
platform resizes and re-compresses each image for the column it is about to sit in.
Right-clicking saves that copy: sized for a layout you are not using, and compressed a
second time on the way there.

This is not the platform being obstructive. It is a CDN doing exactly its job. The
consequence is only that the version you can click on is never the version you want,
and that the difference is invisible until the picture is in a slide and the text in it
has gone soft.

## Where to download social media images from instead

There is a second copy, and it is public by design. When a post is shared into a chat
app, a forum or another network, the receiving site renders a link card — and to draw
one it reads a handful of `<meta>` tags on the post's own page, in the
[Open Graph](https://ogp.me/) format every network agreed on. One of those tags names
an image. It has to be a decent size, because a link card is a decent size, so it is
almost always larger than the feed rendering.

That is the copy these tools read. It matters that it is the *published* one:

- **No sign-in is involved.** The tags exist to be read by other sites.
- **Nothing private is touched.** A post that is not public does not publish them.
- **The platform decides what to hand over.** Where the answer is a sign-in page, the
  honest report is "the platform declined", not "no images found".

The [social media image downloader](/tools/social-media-image-downloader) reads those
tags for any public post on any platform — and for any web page at all, since Open
Graph is not a social-network invention.

## Seven platforms keep a larger copy still

On most platforms the published copy is the end of the road. On four of them it is not,
because their image CDNs have a naming convention that can be read.

| Platform | The convention | What it gets you |
| --- | --- | --- |
| Pinterest | `/originals/` in place of the width directory | The file as uploaded |
| X | `name=orig` in place of `name=medium` | The file as uploaded |
| YouTube | `=s0` on a channel asset URL | The asset at full size |
| Bluesky | A public read API, not a convention at all | Every image, alt text, and the blob |
| Twitch | The size is a path segment, `-300x300` to `-600x600` | The largest avatar Twitch stores |
| Apple Podcasts | `600x600bb` becomes `3000x3000bb` | The artwork as the publisher submitted it |
| Spotify | The image prefix *is* the size | The 640, which is the ceiling |

Those seven have their own pages here for exactly that reason. A general tool that
guessed at CDN conventions would hand back a list of URLs that 404; a per-platform tool
that knows one can hand back the upload.

### X

X serves each photo from a single file and picks a rendition with a `name` parameter:
`thumb`, `small`, `medium`, `large`, `4096x4096` and `orig`. The timeline asks for a
middling one. Swapping in `orig` gives you the file as posted, and nothing in the
interface links to it. Full walkthrough: [download images from X](/blog/download-twitter-images).

### Pinterest

Pinterest serves every Pin from a directory named after its width and keeps the upload
under `/originals/`. The grid shows you the 236-pixel version. See
[download a Pinterest image](/blog/download-pinterest-image).

### YouTube

Video thumbnails come in five fixed sizes, and channel art takes a `=s0` suffix meaning
"as uploaded". See [download a YouTube thumbnail](/blog/download-youtube-thumbnail).

### Bluesky

Bluesky is the outlier, and the most generous of the four. AT Protocol keeps an
unauthenticated read API open so that other clients can work, which means a post can be
asked for rather than inferred from a card. You get every image rather than the first,
the alt text the author wrote, and the blob — the file itself, from the server that
holds it. See [download images from Bluesky](/blog/download-bluesky-images).

### Twitch

Twitch names the dimensions in the path, and the link card always publishes the 300 while
Twitch stores a 600. The ladder is verifiable rather than assumed — 900 and 1200 answer
404 — which is why the tool fetches each candidate before offering it. See
[Twitch profile picture size](/blog/twitch-profile-picture-size).

### Apple Podcasts and Spotify

The two audio directories give opposite answers to the same question, and both are worth
knowing. Apple's public lookup API hands out a 600 and keeps the 3000×3000 master the
publisher was required to submit: [download podcast cover art](/blog/download-podcast-cover-art).
Spotify's prefix *is* the size, and its ceiling is 640 with no original behind it —
[Spotify cover art size](/blog/spotify-cover-art-size) is the honest version of a query
where almost every other result promises HD.

## Meta's three platforms sign their links

Instagram, Facebook and Threads run on one company's infrastructure, and it behaves
differently from the rest in a way worth knowing before you paste an image URL into a
document.

Every image URL Meta serves is **signed and carries an expiry**. The signature covers
the whole path, which kills two pieces of advice that still circulate: you cannot edit
the size segment to ask for a bigger copy, and you cannot keep the link. Once the
signature is stale the address answers 403, and there is no way to refresh it except
reading the post again.

:::warning
Save the file, not the link. A signed URL pasted into a shared doc, a deck or a CMS is
a picture that works today and is a broken image icon by next week. This is the single
most common way an image "disappears" from a document nobody edited.
:::

The remaining life of each link is readable from the link itself, which is why the
Meta-platform tools show it. If you want the mechanism,
[why a saved Instagram image link expires](/blog/instagram-image-link-expired) takes it
apart.

Per-platform walkthroughs: [Instagram](/blog/download-instagram-photos),
[Facebook](/blog/download-facebook-photos) and [Threads](/blog/download-threads-images).
Facebook is the one with a second route worth knowing about — its Page picture endpoint
is public and answers with the size it actually has, which is how you get a brand's logo
at the resolution it was uploaded rather than the small one the API hands out by default.

## Where the trail genuinely goes cold

Some of what people want here does not exist, and a tool that pretends otherwise is
lying to you:

- **Stories.** Served to signed-in viewers, then gone. There is nothing public to read.
- **Private accounts.** A post that is not public publishes no tags. No tool reaches it
  without an account, and one that claims to is using somebody's session.
- **Video.** These are image tools. What comes back for a video link is its poster
  frame.
- **Whole albums and boards.** One post at a time. Walking every post on a profile is
  scraping, which is a different activity with a different answer under most platforms'
  terms.
- **Instagram carousels, often.** Instagram publishes only the first slide to a link
  card when it answers a signed-out request.

## What you may do with the file

Downloading a publicly published image for reference is ordinary use of the web.
Republishing it is a copyright question, and the answer does not change because of how
the file was obtained.

Research, moodboards, competitive analysis, commentary and teaching are the uses these
tools are built for. Re-uploading somebody's photograph as your own is not one of them,
and a credit in the caption is not a licence. Where a platform gives you a way to share
the post itself — a re-pin, a repost, an embed — that route keeps the credit and the
link attached, which is usually what the person who made the picture actually wanted.

If what you need is the post rather than the picture, the
[embed code generator](/tools/social-media-embed-code-generator) produces the official markup for
twelve platforms, and that renders the real post with the author's name on it.

:::faq
Q: Is downloading an image from a public post legal?
A: Saving a publicly published file for personal reference is ordinary browsing. Republishing it, selling it, or presenting it as your own work is a copyright matter regardless of how you got the file.
Q: Why is the downloaded picture still smaller than I expected?
A: Because that is the size it was uploaded at. None of these tools can invent detail — they find the largest copy the platform holds, and on a post shot and posted from a phone that is frequently modest.
Q: Can I download a carousel or an album in one go?
A: Sometimes. Where a platform publishes every slide, every slide comes back. Instagram usually publishes only the first when answering without a session.
Q: The download link worked yesterday and 403s today.
A: It was a Meta link, and those are signed with an expiry. Fetch the post again and save the file this time.
:::

Paste any post link into the
[social media image downloader](/tools/social-media-image-downloader) to see what the
platform publishes, or go straight to the platform's own page above if it is one of the
four with something extra to give.
