---
{
 "id": "YT-08",
 "slug": "find-youtube-channel-id",
 "title": "How to Find a YouTube Channel ID",
 "excerpt": "A YouTube channel ID is the UC... string behind a handle. Here are four ways to find one, including for channels that only show an @handle.",
 "category": "seo",
 "categories": [],
 "tags": ["youtube", "metadata", "how-to"],
 "primary_keyword": "youtube channel id",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "How to Find a YouTube Channel ID",
  "description": "Find any YouTube channel ID - the UC... string - from a handle, a custom URL or a video. Four methods, including the one that works when the URL hides it.",
  "focus_keyword": "youtube channel id",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Finding the UC... behind any YouTube handle",
  "og_description": "Four ways to get a channel ID, and what you need it for.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
A YouTube channel ID is the permanent identifier behind every channel: a 24-character
string starting `UC`. Handles change and custom URLs move, but the channel ID never
does — which is why RSS feeds, API calls and subscribe links all want it rather than
the `@name` you see.

## Four ways to find a YouTube channel ID

**1. Read it from the URL.** If the channel URL looks like
`youtube.com/channel/UCxxxxxxxxxxxxxxxxxxxxxx`, that is the ID. Most channels now
show a handle instead, so this only works some of the time.

**2. Paste the handle into a finder.** The reliable route when the URL hides it:

[[tool:youtube-channel-id-finder]]

Give it a handle, a custom URL, a video URL or a full channel URL, and it resolves
the underlying `UC…`.

**3. Read the page source.** Open the channel, view source, and search for
`channelId`. The value next to it is what you want. Slower, but it is the same ground
truth the tool reads.

**4. Your own channel, from Studio.** YouTube Studio → Settings → Channel → Advanced
settings shows your channel ID directly
([YouTube Help](https://support.google.com/youtube/answer/3250431)).

## What you actually need it for

| Use | Why the ID and not the handle |
| --- | --- |
| RSS feed | The feed URL is built from the channel ID |
| Subscribe links | The `?sub_confirmation=1` link takes the ID |
| API requests | Every endpoint identifies channels by ID |
| Analytics and tracking | A handle can be changed; the ID cannot |

The RSS case is the one most people arrive for — it is how you follow a channel
without an algorithm in the middle:

[[tool:youtube-rss-feed-generator]]

See [how to get the RSS feed for a YouTube channel](/blog/youtube-rss-feed).

## Handles, IDs and custom URLs are three different things

Worth separating, because the words get used interchangeably:

- **Channel ID** — `UCxxxx…`, permanent, machine-facing.
- **Handle** — `@yourname`, unique, changeable, what people see and @-mention.
- **Custom URL** — the legacy `youtube.com/c/name` form, largely superseded by
  handles.

If you are choosing or changing a handle, the rules and availability checks are in
[YouTube handles](/blog/youtube-handles).

[[tool:youtube-handle-availability-checker]]

## When the finder disagrees with the URL

Occasionally a channel URL and a video's uploader resolve to different IDs. That is
usually one of three things, none of them a bug:

- **A brand account moved.** A channel migrated between a personal Google account and
  a brand account keeps its ID, but old bookmarks may point at a redirect.
- **You are looking at a topic channel.** YouTube auto-generates channels for music
  artists, and those carry their own IDs distinct from the artist's real channel.
- **The handle was released and reclaimed.** Handles can change hands; the ID under a
  handle today is not necessarily the ID that was under it a year ago. This is the
  clearest argument for storing IDs rather than handles in anything you build.

If you are keeping a list of channels for research or monitoring, store the ID and
treat the handle as a display label. Everything else - names, avatars, URLs, even
the subject of the channel - is allowed to change underneath you.

## A note on links you build with it

A channel ID makes a one-click subscribe link possible — the kind that opens the
confirmation dialogue rather than just the channel page. That format, with the caveats
about where it is allowed to appear, is in
[the YouTube subscribe link](/blog/youtube-subscribe-link).

:::faq
Q: What does a YouTube channel ID look like?
A: 24 characters beginning with UC, for example UCxxxxxxxxxxxxxxxxxxxxxx. It is
visible in `/channel/` URLs and in the page source of any channel.
Q: How do I find my own channel ID?
A: YouTube Studio, then Settings, Channel, Advanced settings. It is shown there
directly and can be copied.
Q: Can a channel ID change?
A: No. Handles, names and custom URLs can all change; the channel ID is fixed for the
life of the channel, which is exactly why systems use it.
Q: How do I find the channel ID from a video?
A: Paste the video URL into the channel ID finder - it resolves the uploading
channel. The page source of the video also contains a channelId field.
:::

Resolve any handle or URL to its `UC…` with the
[channel ID finder](/tools/youtube-channel-id-finder).
