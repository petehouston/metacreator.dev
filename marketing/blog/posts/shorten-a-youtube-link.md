---
{
 "id": "LK-02",
 "slug": "shorten-a-youtube-link",
 "title": "How to Shorten a YouTube Link",
 "excerpt": "YouTube has its own short domain, youtu.be. Here is how to build one from any link shape, and why copying the timestamp across usually breaks it.",
 "category": "seo",
 "categories": [],
 "tags": ["youtube", "short-links", "how-to"],
 "primary_keyword": "shorten youtube link",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "How to Shorten a YouTube Link (youtu.be)",
  "description": "Shorten a YouTube link to youtu.be from any URL shape, keep the timestamp working, and strip the tracking id the share button adds.",
  "focus_keyword": "shorten youtube link",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Shorten a YouTube link without breaking the timestamp",
  "og_description": "youtu.be wants bare seconds. A watch page writes 90s. Copy it across and the video starts from the beginning.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
To shorten a YouTube link, replace everything before the video ID with `youtu.be/`. A
watch URL of `youtube.com/watch?v=dQw4w9WgXcQ` becomes `youtu.be/dQw4w9WgXcQ`. That is
YouTube's own domain, so the short link never expires and needs no shortening service
at all.

[[tool:youtube-link-shortener]]

## Shorten a YouTube link from any URL shape

Every YouTube URL contains the same eleven-character ID. Only its position changes.

| Link shape | Where the ID sits |
| --- | --- |
| `youtube.com/watch?v=ID` | After `v=` |
| `youtu.be/ID` | The whole path |
| `youtube.com/shorts/ID` | After `/shorts/` |
| `youtube.com/embed/ID` | After `/embed/` |
| `youtube.com/live/ID` | After `/live/` |
| `m.youtube.com`, `music.youtube.com` | Same as the desktop shape |

An ID is always exactly eleven characters of letters, digits, hyphens and underscores.
If what you have extracted is longer or shorter, you have caught a parameter as well.

## The timestamp is where this goes wrong

This is the part worth reading even if you were going to do it by hand.

A watch page writes a start time as `t=90s`. **A `youtu.be` link wants bare seconds:
`t=90`.** The `s` suffix is not an error — it is silently ignored, so the link works,
loads the right video, and starts from the beginning. Nobody notices until the person
you sent it to asks what they were supposed to be looking at.

```text
Watch page:  https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=90s
Short link:  https://youtu.be/dQw4w9WgXcQ?t=90
```

If you are building a set of deep links into one video rather than sharing a single
moment, the [YouTube timestamp link builder](/tools/youtube-timestamp-link-builder)
handles the chapter block as well.

## Strip the tracking id before you send it

Copy a link from YouTube's own Share button and it arrives with `?si=` on the end. That
is a **per-share identifier**, not analytics you can read. Forwarding a link that still
carries it tells YouTube who forwarded it to whom. Delete it.

Two more parameters worth knowing about:

- `list` carries the playlist you were watching from. Usually you meant to share the
  video, not the queue — and a `list` beginning `RD` or `UL` is an auto-generated mix
  that is personal to your session, so it opens as a dead link for everybody else.
- `index` positions a video inside a playlist page and does nothing at all on a
  `youtu.be` link.

The same reasoning applies across every platform, and most of them do not have a short
domain you can build at all — [which platforms have their own short link
domain](/blog/social-media-short-links) has the full split.

## When to shorten, and when not to bother

A `youtu.be` link is worth building in three places: anywhere character count is
scarce, anywhere the URL will be read aloud or typed, and anywhere the link has to
outlive the campaign it was made for. It buys you nothing in a blog post, where the
anchor text is what people read anyway.

What it does not buy you is analytics. A first-party short link gives you no click
data at all — that is the trade for its permanence. If you need the numbers, tag the
destination rather than routing through a service: the
[UTM link builder](/tools/utm-link-builder) produces parameters YouTube passes through
untouched, and [UTM parameters for creators](/blog/utm-parameters-for-creators) covers
which ones are worth setting.

There is also no reason to run a YouTube link through bit.ly. It adds a hop, a tracking
service, and a dependency on somebody else's business model, in exchange for shortening
a link that was already twenty-eight characters.

## What you cannot shorten

`youtu.be` only serves videos. A channel, a playlist or a Shorts feed has no short form:
for a channel, the `youtube.com/@handle` URL is already the shortest official link, and
if you do not know the handle, the
[YouTube channel ID finder](/tools/youtube-channel-id-finder) will resolve it.

YouTube documents the share behaviour and the parameters it honours in
[YouTube Help](https://support.google.com/youtube/answer/57741).

:::faq
Q: Is youtu.be an official YouTube domain?
A: Yes. It is owned and served by YouTube, and it is what the platform's own Share button produces. It is not a third-party shortener, and there is no service in the middle that can go away.
Q: Can I shorten a YouTube Shorts link?
A: Yes — a Shorts URL and a watch URL point at the same video, so the same short link works for both. On desktop it opens in the normal player, which is usually what you want when sharing outside the app.
Q: Why did my timestamp stop working?
A: Almost certainly the `s` suffix. `t=90s` works on a watch page and is ignored on youtu.be; use `t=90`.
Q: Does a short link hurt the video's reach?
A: No. It is a redirect to the same video, and the view is counted identically.
:::

Paste any YouTube URL into the [YouTube link shortener](/tools/youtube-link-shortener)
and it will do all of this at once — including converting the timestamp — or read
[social media links](/blog/social-media-links) for how the other platforms compare.
