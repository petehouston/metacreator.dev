---
{
 "id": "DL-06",
 "slug": "download-bluesky-images",
 "title": "How to Download Images From a Bluesky Post",
 "excerpt": "Bluesky is the one platform that will simply tell you what is on a post. You get every image, the alt text, and the file exactly as it was uploaded.",
 "category": "design",
 "categories": [],
 "tags": ["bluesky", "downloads", "how-to"],
 "primary_keyword": "download bluesky images",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "How to Download Images From a Bluesky Post",
  "description": "Download Bluesky images at original size — every image on the post, not just the first, plus the alt text the author wrote. No account needed.",
  "focus_keyword": "download bluesky images",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Every image on the post, and the file as uploaded",
  "og_description": "Bluesky keeps a public read API open, so a post can be asked for rather than guessed at.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
You can download Bluesky images at their original size, and unlike every other platform
here you get all of them rather than the first. Bluesky keeps a public read API open, so
a post can be asked for directly instead of inferred from the link card it publishes —
and a link card names one picture no matter how many the post carries.

[[tool:bluesky-image-downloader]]

## Why Bluesky gives you more than the others

Every other platform in this family has to be read through the tags it publishes for
link previews. That works, and it is what the
[social media image downloader](/tools/social-media-image-downloader) does, but it is
reading a summary written for a different purpose.

Bluesky is built to be read. AT Protocol, the network underneath it, keeps an
unauthenticated read API open so that other clients — other apps, other front ends — can
work at all. Asking it about a post returns the post, and that changes three things:

- **Every image comes back**, up to the four a post can carry, instead of whichever one
  the card names.
- **The alt text comes with them.** It is the author's own writing and it is frequently a
  real caption rather than a bare description.
- **The file itself is reachable.** Not the app's re-encoded copy — the bytes as
  uploaded, from the server that holds them.

## Original upload versus full size

The result gives you two rows per image, and the difference is worth understanding
because it is not a difference of dimensions.

**Full size** is Bluesky's own copy, served from its CDN. It is the picture at its full
pixel dimensions, re-encoded by Bluesky for delivery.

**Original upload** is the blob: the file the author posted, fetched by its content hash
from their own server. Same picture, nothing re-compressed. On a photograph the
difference is routinely several times the file size, and it shows in exactly the places
a second JPEG pass always shows — gradients, skin, and text inside the image.

If you are archiving, printing, or editing the picture further, take the original. If
you need it for a slide at screen size, either is fine.

## How the file is found

The mechanism is unusually clean, and worth a paragraph because it explains what the
tool can and cannot do.

Bluesky is not one server. An account's posts and files live on its personal data
server, and which one that is varies — many are Bluesky's own, but the protocol does not
require it. The account's identity document says where, publicly. The image's content
hash is already in the CDN address. So: resolve the handle to an identifier, look up
where that identity keeps its data, and ask that server for the file by hash.

Nothing in that chain is authenticated, and nothing in it is scraped. It is the same
sequence any Bluesky client performs. The protocol's own documentation is at
[atproto.com](https://atproto.com/).

## Download a Bluesky image

1. Open the post and copy the address — it looks like
   `bsky.app/profile/<handle>/post/<id>`.
2. Paste it into the [Bluesky image downloader](/tools/bluesky-image-downloader).
3. Take the **original upload** row unless you have a reason not to.

A profile link will not do: a profile has no images of its own to return. Open the
specific post first.

## Carry the alt text with the picture

This is the part everybody drops, and Bluesky is the platform where dropping it is most
obviously a loss. Its users write alt text — the app prompts for it, and the culture
around it stuck — so the description attached to an image is often a sentence somebody
composed rather than a filename.

It is also the only part of an image post a screen reader can read. If you reuse the
picture somewhere else and leave the description behind, you have made the copy worse
for the people who needed it most, for no saving at all. The tool hands the alt text
back next to each image so that carrying it across is a copy and a paste. More on
writing your own: [alt text for social media images](/blog/social-media-alt-text).

## Where it stops

- **Public posts only.** An account that has asked to be excluded from logged-out views
  is not served by the public read API, and no tool here signs in to get around that.
- **Deleted posts and deactivated accounts** return nothing, because nothing is there.
- **Quote posts** return the images on the post you linked to, not the images on the post
  it quotes. Those have a different author and their own link.

## What you may do with the file

The images belong to whoever posted them — and so does the alt text. Reference, research,
moodboards and commentary are ordinary use; reposting the picture as your own is not.

:::faq
Q: What is the difference between full size and original upload?
A: Full size is Bluesky's re-encoded copy from its CDN. Original upload is the file the author posted, fetched from their own server by its content hash.
Q: Why does the alt text matter?
A: It is the author's writing, and it is the only part of an image a screen reader can read. Dropping it when you reuse the picture makes the copy worse for the people who need it most.
Q: It could not find my post.
A: Check the link points at a post rather than a profile, and that the post still exists. Handles change hands, so an old link may name an account that has moved on.
Q: Does it need an account?
A: No. The read API it uses is open by design — it is how other Bluesky clients work.
:::

Paste a post link into the
[Bluesky image downloader](/tools/bluesky-image-downloader), or read the
[guide to downloading social media images](/blog/download-social-media-images) to see how
much less the other platforms are willing to say.
