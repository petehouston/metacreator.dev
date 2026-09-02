---
{
 "id": "YT-07",
 "slug": "youtube-timestamp-links",
 "title": "How to Make a YouTube Timestamp Link (and Add Chapters)",
 "excerpt": "A YouTube timestamp link starts the video at an exact second. Here is the URL format, the chapter rules, and how to build both without counting seconds by hand.",
 "category": "seo",
 "categories": ["content"],
 "tags": ["youtube", "metadata", "how-to"],
 "primary_keyword": "youtube timestamp link",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "How to Make a YouTube Timestamp Link and Chapters",
  "description": "Build a YouTube timestamp link that opens at an exact moment, and turn a list of timestamps into chapters. The URL format, the rules, and a builder.",
  "focus_keyword": "youtube timestamp link",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "YouTube timestamp links and chapters, done properly",
  "og_description": "The ?t= format, the chapter rules that decide whether they appear at all, and a builder that does the arithmetic.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
A YouTube timestamp link is a normal video URL with `?t=` and a number of seconds on
the end — `youtube.com/watch?v=ID&t=252` opens the video at 4:12. Put a list of those
times in your description instead, and YouTube turns them into chapters. Both take
about a minute and both are underused.

## The YouTube timestamp link format

| Where | Format | Example |
| --- | --- | --- |
| Watch URL | `&t=` seconds | `youtube.com/watch?v=ID&t=252` |
| Short URL | `?t=` seconds | `youtu.be/ID?t=252` |
| With units | `1h2m30s` | `youtu.be/ID?t=1h2m30s` |
| Embed | `?start=` seconds | `youtube.com/embed/ID?start=252` |

Two things trip people up. On a `watch?v=` URL the parameter is joined with `&`
because `?v=` came first; on a `youtu.be` short link it is `?` because it is the
first parameter. And the embed player uses `start=`, not `t=` — a timestamp link
pasted into an iframe silently starts at zero.

[[tool:youtube-timestamp-link-builder]]

Paste the URL, type the moment as `4:12`, and the builder emits every form of the
link, including the embed version. It converts to seconds for you, which is where
most hand-written timestamps go wrong.

## Chapters: the same times, doing more work

Put timestamps in the description and YouTube builds chapters from them — the
segmented progress bar, the chapter list, and titled sections that can surface in
search on their own. For any video over a few minutes covering more than one point,
this is the best-value metadata you can add.

The rules are strict, and a list that breaks one of them produces no chapters at all
rather than an error message:

- The list starts with `00:00`.
- There are at least three timestamps.
- They are in ascending order.
- Each chapter runs at least ten seconds.

Reference: [YouTube Help on chapters](https://support.google.com/youtube/answer/9884579).

```text
00:00 What this covers
01:12 The setup
04:12 Where it usually goes wrong
09:40 The fix
```

## Why chapters matter more than they look

A chaptered video answers several questions instead of one. A viewer searching for
the narrow thing your video covers at 4:12 can be delivered to 4:12, which turns a
"this is mostly not what I wanted" bounce into a satisfied view.

There is a retention benefit too, and it is the unglamorous kind: people who can skip
the part they do not need stay for the part they do, instead of leaving to find a
shorter video.

Chapters sit inside a description that has its own structure — see
[a YouTube description template that earns its space](/blog/youtube-description-template)
for where the chapter block goes relative to the visible lines.

## Timestamp links in comments and elsewhere

Typing `4:12` in a comment on the video turns it into a clickable link automatically —
useful for answering a question by pointing at the moment that answers it. Outside
YouTube, in a blog post or a message, you need the full URL form above, because
nothing else knows what `4:12` refers to.

If you are embedding the video on your own site and want it to start at a specific
point, generate the embed rather than editing the URL by hand:

[[tool:youtube-embed-code-generator]]

More on responsive and privacy-friendly embeds:
[YouTube embed code](/blog/youtube-embed-code).

## Where this fits

Chapters are one field among several, and they are not the one that rescues a video
nobody clicks. The order of weight — title, thumbnail, the first thirty seconds, then
everything else — is in the [YouTube SEO guide](/blog/youtube-seo).

:::faq
Q: How do I link to a specific time in a YouTube video?
A: Add `?t=` (or `&t=` on a watch URL) followed by the number of seconds. 4:12 is
252 seconds, so `youtu.be/ID?t=252`.
Q: Why are my YouTube chapters not showing?
A: Almost always one of four rules: the list must start at 00:00, contain at least
three timestamps, run in ascending order, and give each chapter at least ten seconds.
Break one and no chapters appear.
Q: Do timestamps work in the embedded player?
A: Yes, but the parameter is `start=` rather than `t=`. A `t=` value on an embed URL
is ignored.
Q: Do chapters help a video get found?
A: They give YouTube labelled segments, and a chapter can surface in search on its
own. They will not fix a video with a weak title or thumbnail.
:::

Build the link and the chapter list in one go with the
[timestamp link builder](/tools/youtube-timestamp-link-builder).
