---
{
 "id": "DL-03",
 "slug": "download-instagram-photos",
 "title": "How to Download Instagram Photos Without an App",
 "excerpt": "Instagram publishes a larger copy of every public post's picture so link cards can render it. Here is how to reach it, and why the link stops working.",
 "category": "design",
 "categories": [],
 "tags": ["instagram", "downloads", "how-to"],
 "primary_keyword": "download instagram photos",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "How to Download Instagram Photos (No App Needed)",
  "description": "Download Instagram photos from any public post in your browser. What Instagram publishes, why the URL expires, and why a carousel often gives you one slide.",
  "focus_keyword": "download instagram photos",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "The full-size photo behind a public Instagram post",
  "og_description": "No app, no extension, no account — and an honest answer when Instagram declines.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
You can download Instagram photos from any public post by reading the copy Instagram
publishes for link cards, which is larger than the one the feed renders. No app, no
extension and no account is involved — but the address you get back is signed and stops
working after a few hours, so save the file rather than the link.

[[tool:instagram-image-downloader]]

## What Instagram actually publishes

Every public post has a page, and that page carries `<meta>` tags describing the post so
that other sites can draw a link card for it. One of those tags names an image. That
copy exists to be read by other services, which is what makes reading it different in
kind from scraping a feed: no session, no private endpoint, nothing that requires being
signed in.

It is also bigger than the picture in the app, because a link card in a chat window is
bigger than a photo in a scrolling column.

## Download an Instagram photo in three steps

1. Open the post in a browser and copy the address from the bar, or use **Copy link**
   in the app's share sheet.
2. Paste it into the [Instagram image downloader](/tools/instagram-image-downloader).
3. Save the file — right-click, **Save image as**.

Step three is the one people skip. Do not skip it.

## Why the link expires

Instagram signs every image URL. The address carries a signature and an expiry, and once
the expiry passes the address answers `403 Forbidden` no matter who asks.

This is the single most common way a picture "disappears" from a document nobody edited:
somebody pasted the image URL into a shared doc, a deck or a CMS field, it rendered fine
that afternoon, and by the following week it was a broken-image icon. The fix is always
the same — download the file and upload it wherever it needs to live. The mechanism, if
you want it, is in
[why a saved Instagram image link expires](/blog/instagram-image-link-expired).

:::warning
Any advice that tells you to edit the size segment of an Instagram image URL — swapping
in `s1080x1080` — predates the signing. The signature covers the whole path, so an
edited address is an invalid address and answers 403 immediately.
:::

## Carousels usually come back as one slide

A carousel has up to ten pictures and publishes one of them to its link card. When
Instagram answers a signed-out request, that is generally all you get.

This is a limit of what Instagram chooses to publish rather than a failure of the read,
and there is a workaround that costs nothing: open the slide you want in the app, use
**Copy link** from that slide, and paste that. Where Instagram does publish the whole
set, every slide comes back.

## When Instagram answers with a sign-in page

Instagram increasingly serves a sign-in page to signed-out requests, including for posts
that are public when you are logged in. Every tool that reads a post link is affected,
and the honest report is "Instagram declined", not "no images found" — they are
different problems and only one of them is yours to fix.

The test that settles it: open the post in a private browser window. If you can see it
there, a tool can read it. If you cannot, nothing signed-out can — and a tool claiming
otherwise is using somebody's session to do it, which is the thing you were avoiding by
not installing an app.

Instagram sets out what it permits in its
[terms of use](https://help.instagram.com/581066165581870).

## Stories and profile pictures

Neither works, and both are worth explaining rather than silently failing:

- **Stories** are served to signed-in viewers and then they are gone. There is nothing
  public to read at any point.
- **Profile pictures** are published to link cards at 100 pixels square and signed, which
  is smaller than the profile page itself renders. A tool for it could not honour its own
  name, so we did not ship one.

## What you may do with the photo

The picture belongs to whoever posted it. Reference, moodboards, research and commentary
are ordinary uses of the web; reposting somebody's photograph as your own is not, and
tagging them afterwards is not a licence granted in advance.

If what you want is to show the post rather than to use the picture, Instagram's own
embed keeps the author's name and the link attached — the
[social media embed code generator](/tools/social-media-embed-code-generator) writes the
markup for it.

:::faq
Q: Do I need an account or an app?
A: No. This reads what a public post publishes for link cards, which is the same thing any site rendering a preview reads.
Q: Why did my saved link stop working?
A: Instagram signs its image URLs with an expiry. Save the file, not the address.
Q: Can I download a whole carousel?
A: Only when Instagram publishes every slide, which signed-out it usually does not. Copy the link from the specific slide instead.
Q: Can I download a story or a profile picture?
A: No. Stories are not public, and the avatar is published at 100 pixels square — smaller than the profile page shows.
:::

Paste a post link into the
[Instagram image downloader](/tools/instagram-image-downloader), or see
[how the other platforms compare](/blog/download-social-media-images) if the picture you
want is somewhere else.
