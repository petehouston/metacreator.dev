---
{
 "id": "YT-12",
 "slug": "youtube-rss-feed",
 "title": "How to Get the RSS Feed for a YouTube Channel",
 "excerpt": "Every YouTube channel and playlist publishes an RSS feed. Here is the URL format, how to find the ID it needs, and what the feed does and does not include.",
 "category": "seo",
 "categories": [],
 "tags": ["youtube", "embeds", "how-to"],
 "primary_keyword": "youtube rss feed",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "How to Get the RSS Feed for a YouTube Channel",
  "description": "Every YouTube channel has an RSS feed. The URL format, how to get the channel ID it needs, and how to follow uploads without an algorithm in the way.",
  "focus_keyword": "youtube rss feed",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Every YouTube channel has an RSS feed",
  "og_description": "The URL format, the ID you need, and what the feed contains.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
Every YouTube channel publishes an RSS feed, undocumented in the interface but
entirely public. The URL is
`youtube.com/feeds/videos.xml?channel_id=CHANNEL_ID`, and it gives you a plain list of
recent uploads with no recommendation system in between.

## Building the YouTube RSS feed URL

| Feed | URL |
| --- | --- |
| Channel | `https://www.youtube.com/feeds/videos.xml?channel_id=UCxxxx` |
| Playlist | `https://www.youtube.com/feeds/videos.xml?playlist_id=PLxxxx` |

The channel feed needs the channel ID — the `UC…` string — not the `@handle`. A handle
in that URL returns nothing.

[[tool:youtube-rss-feed-generator]]

Paste a handle, a video URL or a channel URL and the generator resolves the ID and
builds both feed URLs, verified against the live feed rather than assembled blindly.

If you only need the ID itself, that is
[how to find a YouTube channel ID](/blog/find-youtube-channel-id):

[[tool:youtube-channel-id-finder]]

## What the feed contains

Each entry carries the video title, its URL, the channel, a description snippet and a
thumbnail reference. Feeds return the most recent uploads only — typically the last
fifteen — and they are not a complete archive. There is no way to page backwards
through a channel's history with RSS.

Two other limits worth knowing before you build anything on it: Shorts and regular
uploads both appear with nothing distinguishing them beyond the video itself, and
scheduled premieres can appear before they are watchable.

## Why anyone still wants this

**Following without an algorithm.** A subscription feed shows what a channel actually
published, in order, with nothing inserted and nothing suppressed. For research,
competitor monitoring or simply following ten channels properly, that is a different
product from the YouTube home page.

**Automation.** An RSS feed is the simplest possible trigger for "when this channel
uploads, do something" — post to a Slack channel, add to a reading list, notify a
team. No API key, no quota.

**Archiving.** A feed reader keeps a record of what was published, which is useful
when a video is later edited, retitled or removed.

## Reading the feed without a feed reader

The XML is plain enough to consume directly, which matters if you are wiring it into
something rather than reading it yourself. Each `<entry>` carries a `<title>`, a
`<link>` to the watch page, a `<yt:videoId>`, the channel, and a media group with the
thumbnail. There is no authentication, no key and no quota, which is the reason to
prefer it over the Data API for simple "what is new" cases.

The usual gotcha is polling frequency. The feed is cached, so hammering it every
minute returns the same document and achieves nothing except looking like abuse. Every
fifteen minutes is more than enough for a channel that uploads weekly, and hourly is
plenty for most monitoring.

If you need anything the feed does not carry - view counts, full descriptions, older
uploads, comments - that is where the Data API starts, and where quota begins to
matter.

## The related trick: subscribe links

If the goal is the opposite — getting other people to subscribe to you — the channel
ID also builds a one-click subscribe URL:

[[tool:youtube-subscribe-link-generator]]

See [the YouTube subscribe link](/blog/youtube-subscribe-link).

## For embedding on your own site

An RSS feed is a reasonable way to render a "latest videos" list without calling the
Data API and spending quota. If you are also embedding the player, do it responsively
and privately:

[[tool:youtube-embed-code-generator]]

See [YouTube embed code](/blog/youtube-embed-code). YouTube's own API documentation,
if you outgrow the feed, is at
[Google Developers](https://developers.google.com/youtube/v3/docs).

:::faq
Q: Does YouTube still support RSS?
A: Yes. The feed endpoint is public and works for any channel or playlist, even though
nothing in the interface advertises it.
Q: Can I get an RSS feed from a YouTube handle?
A: Not directly - the feed URL takes the channel ID. Resolve the handle to its UC…
first, then build the URL.
Q: How many videos does a YouTube RSS feed include?
A: The most recent uploads only, typically fifteen. It is not an archive and cannot be
paged backwards.
Q: Do Shorts appear in the RSS feed?
A: Yes, alongside regular uploads, with nothing in the entry marking them as Shorts.
:::

Build and verify the feed URL for any channel with the
[RSS feed generator](/tools/youtube-rss-feed-generator).
