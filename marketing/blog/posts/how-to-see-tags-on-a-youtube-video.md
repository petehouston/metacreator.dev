---
{
 "id": "YT-02",
 "slug": "how-to-see-tags-on-a-youtube-video",
 "title": "How to See the Tags on Any YouTube Video",
 "excerpt": "YouTube video tags are hidden in the page source, not the interface. Here are three ways to read them, and what they are actually worth.",
 "category": "seo",
 "categories": [],
 "tags": ["youtube", "metadata", "how-to"],
 "primary_keyword": "youtube video tags",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "How to See the Tags on Any YouTube Video",
  "description": "YouTube video tags are in the page source, not the interface. Three ways to read any video's tags in seconds, and what those tags are actually worth.",
  "focus_keyword": "youtube video tags",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "The tags on any YouTube video, in about ten seconds",
  "og_description": "Tags are not shown in the interface, but they are in the page source. Three ways to read them - and the honest answer about how much they matter.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
YouTube video tags are still there — they are just not on the page you are looking
at. Every public video carries its uploader's tags in the HTML source, in a
`keywords` meta tag, and you can read them with a URL, a browser shortcut or the
page source itself. Here is each method, then the part most guides skip: what those
tags are actually worth.

## Where the tags went

YouTube stopped displaying tags under videos more than a decade ago. Creators still
enter them in [YouTube Studio](https://support.google.com/youtube/answer/146402) — under **Details → Show more** for an existing video —
and YouTube still serves them to the browser. They just never get rendered.

That is why the tags are readable at all. They are not hidden data; they are
published data that the interface chooses not to draw.

## Method 1: paste the URL into a tag extractor

The fastest route, and the one that works on a phone: give the video URL to a tool
that fetches the page and reads the field for you.

[[tool:youtube-tag-extractor]]

Paste any watch URL — `youtube.com/watch?v=…`, `youtu.be/…`, or a Shorts link — and
it returns the tag list as the uploader typed it, in order. No extension, no
account, no request to YouTube's API quota.

The order matters more than it looks. Tags come back in the sequence the creator
entered them, which is usually most-important-first, so the first three tags are a
reasonable guess at how the uploader thinks their video should be found.

## Method 2: read the page source yourself

If you would rather not trust a tool with something you can verify:

1. Open the video on desktop.
2. Press `Ctrl+U` (Windows) or `Cmd+Option+U` (Mac) to view the page source.
3. Press `Ctrl+F` / `Cmd+F` and search for `keywords`.
4. The tags are the comma-separated list in the `content` attribute.

```html
<meta name="keywords" content="video editing, premiere pro tutorial, color grading">
```

This is the ground truth every tag tool is reading, including ours. If a tool ever
disagrees with this, believe the page source.

:::tip One thing that trips people up
An empty `keywords` field is a real answer, not a failure. Plenty of large channels
use no tags at all — which tells you something in itself.
:::

## Method 3: check the rest of the metadata at the same time

Tags are one field out of a dozen the page declares about itself: publish date,
duration, category, whether it is family-safe, the live-stream flags, the thumbnail
resolutions. If you are researching a video rather than a keyword, read the lot at
once instead of pulling one field.

[[tool:youtube-metadata-viewer]]

The [YouTube metadata viewer](/tools/youtube-metadata-viewer) puts every declared
field in one table, and the [thumbnail downloader](/tools/youtube-thumbnail-downloader)
pulls the image assets from the same page if you want the creative as well.

## What YouTube video tags are actually worth

This is where most articles on this subject oversell. YouTube's own documentation is
unambiguous:

> Tags can be useful if the content of your video is commonly misspelled. Otherwise,
> tags play a minimal role in your video's discovery.
> -- [YouTube Help, "Add tags to your video"](https://support.google.com/youtube/answer/146402)

Two honest conclusions follow.

**For your own videos:** tags are a spelling-insurance policy, not a ranking lever.
Add the handful that cover misspellings and genuine synonyms of your subject, then
spend the saved time on the title, the thumbnail and the first two lines of the
description — the three fields YouTube names as the ones that matter.

**For someone else's videos:** tags are still useful, but as *research*, not as a
list to copy. They tell you which words a creator in your niche thinks describe
their video. That is a vocabulary sample, and vocabulary is worth having. Copying a
competitor's tags onto your own video does nothing, because the tags were never
doing much for them either.

| Field | How much it moves discovery | Where to check yours |
| --- | --- | --- |
| Title | High | [Character counter](/tools/social-media-character-counter) |
| Thumbnail | High | [Safe-zone guide](/tools/safe-zone-guide) |
| Description (first two lines) | Medium | [Channel description generator](/tools/youtube-channel-description-generator) |
| Tags | Minimal, per YouTube | [Tag extractor](/tools/youtube-tag-extractor) |
| Hashtags in the description | Low, but visible above the title | [Hashtag generator](/tools/youtube-hashtag-generator) |

## Tags are not hashtags

A common mix-up, and they behave differently. Tags are private metadata in the
`keywords` field. Hashtags are written into the description, they render as blue
links above the video title, and viewers can click them. They are the ones people
see; tags are the ones people do not.

If you came here looking for the clickable ones, that is a different job — see
[how many hashtags to use on YouTube](/blog/youtube-hashtags).

## A better use of the same ten minutes

If the reason you wanted a competitor's tags was "how do I get found for this
subject", the tags are the weakest available answer. Real demand data is one step
away: YouTube's own autocomplete is what people are typing right now, and it costs
nothing to read.

[[tool:youtube-search-suggestions]]

Start there, put the phrase people actually type in the title, and treat the tag
field as the small piece of housekeeping it is. The full picture of which fields
carry weight is in our [YouTube SEO guide](/blog/youtube-seo).

:::faq
Q: Can you see the tags on a YouTube Short?
A: Yes. Shorts are served from the same watch page, so the `keywords` field is
present and every method above works on a Shorts URL.
Q: Why do some videos show no tags at all?
A: Because the uploader left the field empty. It is optional, and given YouTube's
own guidance on how little tags matter, plenty of channels skip it.
Q: Can I see the tags on a private or unlisted video?
A: Only on unlisted videos you have the link for — the page still serves its
metadata. Private videos return nothing, because there is no public page to read.
Q: Does copying a competitor's tags help my video rank?
A: No. YouTube says tags play a minimal role except for misspellings, so copying
them moves nothing. Use them as a vocabulary sample for your title and description
instead.
:::

Ready to look one up? Paste a URL into the
[YouTube tag extractor](/tools/youtube-tag-extractor).
