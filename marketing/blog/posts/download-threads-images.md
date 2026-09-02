---
{
 "id": "DL-05",
 "slug": "download-threads-images",
 "title": "How to Download Images From a Threads Post",
 "excerpt": "Threads answers a signed-out request honestly, which makes it one of the easier platforms to get a picture out of. Two details are worth knowing first.",
 "category": "design",
 "categories": [],
 "tags": ["threads", "downloads", "how-to"],
 "primary_keyword": "download threads images",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "How to Download Images From a Threads Post",
  "description": "Download Threads images from any public post at full size. Works with threads.com and threads.net links, with no account, app or extension involved.",
  "focus_keyword": "download threads images",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "The picture on a Threads post, at full size",
  "og_description": "Both threads.com and threads.net links work. The tracking parameter is dropped first.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
To download Threads images, paste a public post's link and take the copy Threads
publishes for link cards, which is larger than the one the app renders. Threads is the
most cooperative of Meta's three platforms about answering a signed-out request, so this
usually works on the first try.

[[tool:threads-image-downloader]]

## Two things about the address

Threads links have two quirks, and both are handled before anything is fetched.

**The domain moved.** Threads started on `threads.net` and now uses `threads.com`. Both
are in circulation — old links, old bookmarks, old screenshots — and both resolve. Paste
whichever you have.

**The share sheet adds a tracker.** A link copied from the app carries an `igshid`
parameter. That is a per-share identifier: it does not describe the post, it describes
the act of sharing it, which means fetching a link with it attached tells Meta who
forwarded the post to you. It is stripped before the request goes out.

## Why the app's copy is the wrong one

The picture rendered in the app is sized for the column it is sitting in, on the device
it is being read on, and re-compressed to get there. That is the right trade for a feed —
nobody scrolling on a phone should be made to download full-resolution photographs — and
the wrong file for anything you are going to put in a slide, a document or a post of your
own.

The copy Threads publishes for link cards exists for a different job. A card rendered in
a chat window or on somebody else's site has to look right at a decent size, so the
picture behind it is a decent size. Reading that tag is what these tools do, and it is
public by design: no session, no private endpoint, nothing that requires being signed in.

## Download a Threads image

1. Open the post, tap the share icon and choose **Copy link** — or copy the address from
   the browser bar.
2. Paste it into the [Threads image downloader](/tools/threads-image-downloader).
3. Save the file.

A post carrying a set of pictures publishes the first one to its card, so a set can come
back as a single image. That is the card doing what cards do, not a failed read — open
the picture you want in the app, share that, and paste the link you get.

There is no app to install and no extension to grant permissions to, which is worth
stating plainly: most of what is offered under this search term is a browser extension
that wants read access to every page you open, in exchange for doing what one `<meta>`
tag already does in public.

## The link has a countdown on it

Threads runs on the same image infrastructure as Instagram, which means every address it
serves is signed and expires.

Two consequences follow, and both catch people out:

- **A saved link is not a saved picture.** Paste the address into a doc and it renders
  today and breaks later. Download the file.
- **The size cannot be rewritten.** The signature covers the whole path, so editing the
  size segment produces an invalid address rather than a bigger picture. What comes back
  is the largest copy Threads publishes.

The tool reads the remaining life out of the address and shows it, because "save it now"
is advice that only lands with a number attached. The mechanism is the same one behind
[expiring Instagram image links](/blog/instagram-image-link-expired).

## When nothing comes back

Threads posts are text by default, so the most common reason a post yields no picture is
that it has none. Beyond that:

- A **private profile** is not public, and no signed-out read reaches it.
- A **deleted post** publishes nothing.
- A post that **quotes another post** shows that post's picture in the app, but the
  quoted post is a different post with a different author — paste its link if that is
  what you want.

Meta's terms for Threads are in its
[supplemental terms](https://help.instagram.com/769983657850450).

## What you may do with the picture

The picture belongs to whoever posted it. Research, moodboards, reference and commentary
are ordinary use of a public post; reposting the picture as your own is not.

Where you are writing about the post rather than using its picture, the embed keeps the
author's name and the link attached — the
[social media embed code generator](/tools/social-media-embed-code-generator) produces
Threads' own markup.

:::faq
Q: Do threads.net links still work?
A: Yes. Threads moved to threads.com and both are accepted.
Q: A post with four pictures gave me one.
A: A post publishes one picture to its link card. That is the card, not a failed read.
Q: Can I ask for a bigger copy?
A: No, and no tool can. The signature on the address covers the size segment, so an edited link is an invalid link.
Q: Why is the tracking parameter removed?
A: The `igshid` on a shared link identifies whoever shared it with you. There is no reason to send that to Meta on your behalf.
:::

Paste a post link into the
[Threads image downloader](/tools/threads-image-downloader), or read the
[guide to downloading social media images](/blog/download-social-media-images) for the
platforms that keep a larger copy still.
