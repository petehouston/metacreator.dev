---
{
 "id": "YT-11",
 "slug": "youtube-embed-code",
 "title": "YouTube Embed Code: Responsive and Privacy-Friendly",
 "excerpt": "The default YouTube embed code is fixed-width and sets cookies before anyone presses play. Here is the version that is responsive, lazy and privacy-enhanced.",
 "category": "seo",
 "categories": ["design"],
 "tags": ["youtube", "embeds", "how-to"],
 "primary_keyword": "youtube embed code",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "YouTube Embed Code: Responsive and Private",
  "description": "A YouTube embed code that scales on mobile, loads lazily and uses the no-cookie domain - plus the parameters worth setting and the ones that no longer work.",
  "focus_keyword": "youtube embed code",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "The YouTube embed code you actually want",
  "og_description": "Responsive, lazy-loaded, privacy-enhanced - and the parameters that still do something.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
The YouTube embed code you get from the Share button is fixed at 560×315, loads the
full player immediately, and sets cookies before anyone presses play. Three changes
fix all of that: a wrapper that scales, `loading="lazy"`, and the `youtube-nocookie`
domain.

## The embed code worth pasting

```html
<div style="position:relative;padding-top:56.25%">
  <iframe
    src="https://www.youtube-nocookie.com/embed/VIDEO_ID"
    title="Descriptive title of the video"
    loading="lazy"
    style="position:absolute;inset:0;width:100%;height:100%;border:0"
    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
    allowfullscreen></iframe>
</div>
```

Four things are doing work there:

- **`padding-top:56.25%`** — that is 9 ÷ 16, which reserves the correct height before
  the iframe loads. It makes the embed responsive and stops the page jumping as it
  loads, which is a Core Web Vitals problem as much as an aesthetic one.
- **`youtube-nocookie.com`** — YouTube's privacy-enhanced domain. It defers
  cookie-based tracking until playback begins
  ([YouTube Help](https://support.google.com/youtube/answer/171780)).
- **`loading="lazy"`** — the player is not fetched until it approaches the viewport.
  On a page with three embeds this is the single biggest performance win available.
- **`title`** — required for screen readers. An iframe with no title is announced as
  nothing useful.

[[tool:youtube-embed-code-generator]]

The generator builds this from a URL, with the parameters set, so you are not
hand-editing an iframe every time.

## Parameters that still do something

| Parameter | Effect |
| --- | --- |
| `start=90` | Begin at 90 seconds — note: `start`, not `t` |
| `end=180` | Stop at 180 seconds |
| `rel=0` | Restricts related videos to the same channel |
| `modestbranding=1` | Deprecated; it no longer has an effect |
| `autoplay=1` | Requires `mute=1` in most browsers to work at all |
| `cc_load_policy=1` | Captions on by default |
| `playsinline=1` | Plays inline on iOS rather than going fullscreen |

The `start` distinction catches people out: a
[YouTube timestamp link](/blog/youtube-timestamp-links) uses `t=`, but the embedded
player wants `start=`. Paste a `t=` value into an iframe and the video quietly starts
at zero.

`rel=0` no longer removes related videos entirely — it limits them to the same
channel, which is still worth setting if you would rather not send a reader to a
competitor at the end of a clip.

## Why the default embed is a performance problem

An embedded player is not a picture of a video. It is an application: a document, its
own scripts, its own network requests, and a measurable amount of main-thread work
before anything is visible. Put three of them on an article and the page's load
budget is gone before your own content renders.

That has two consequences worth caring about. The first is Core Web Vitals - largest
contentful paint and layout stability both suffer from an unreserved, eagerly loaded
iframe, and both are things search engines measure. The second is simpler: on a
mid-range phone on a slow connection, a page with several eager embeds feels broken.

The wrapper above solves the layout half by reserving the space, and `loading="lazy"`
solves the fetch half for embeds below the fold. What neither solves is the cost of
the player itself once it does load, which is what the next section is for.

## The facade pattern, if performance really matters

Even lazily loaded, an embed pulls a substantial player. The lighter approach is to
render the thumbnail with a play button and only insert the iframe when someone
clicks it. The whole page then costs one image instead of a player.

[[tool:youtube-thumbnail-downloader]]

The thumbnail downloader gives you every resolution the video publishes, which is what
the facade needs. Sizing detail is in
[YouTube thumbnail size](/blog/youtube-thumbnail-size).

## Embedding a channel or a playlist

Playlists use `/embed/videoseries?list=PLAYLIST_ID`. There is no supported embed for
a whole channel — the usual substitute is embedding the channel's uploads playlist,
or building a feed:

[[tool:youtube-rss-feed-generator]]

See [how to get the RSS feed for a YouTube channel](/blog/youtube-rss-feed).

:::faq
Q: How do I make a YouTube embed responsive?
A: Wrap the iframe in a container with `padding-top:56.25%` and position the iframe
absolutely inside it. That reserves 16:9 space at any width without JavaScript.
Q: What is youtube-nocookie.com?
A: YouTube's privacy-enhanced embed domain. It avoids setting cookies for tracking
until the viewer actually starts playback.
Q: Why does my timestamp not work in an embed?
A: Embeds use `start=` in seconds; `t=` only works on watch and share links.
Q: Does modestbranding still work?
A: No. It was deprecated and no longer changes the player. Any guide still
recommending it is out of date.
:::

Generate the whole thing, parameters included, with the
[embed code generator](/tools/youtube-embed-code-generator).
