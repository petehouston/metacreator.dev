---
{
 "id": "YT-16",
 "slug": "search-youtube-comments",
 "title": "How to Search the Comments on a YouTube Video",
 "excerpt": "YouTube has no comment search. Here are three ways to search YouTube comments anyway, and what each one can and cannot reach.",
 "category": "growth",
 "categories": [],
 "tags": ["youtube", "analytics-basics", "how-to"],
 "primary_keyword": "search youtube comments",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "How to Search the Comments on a YouTube Video",
  "description": "You cannot search YouTube comments in the app. Three methods that work - browser find, the Data API, and a comment finder - with the limits of each.",
  "focus_keyword": "search youtube comments",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Searching YouTube comments, three ways",
  "og_description": "The app has no search. Here is what does work.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
YouTube gives you no way to search the comments on a video. To search YouTube comments
you need one of three workarounds: load them all and use your browser's find, query
the Data API, or use a tool that does the second thing for you. Each reaches a
different amount of the thread.

## Why you cannot search YouTube comments directly

Comments load lazily — a few dozen at a time as you scroll — and replies are collapsed
under their parents. There is no index to search, on your side or theirs, until the
comments are actually loaded. That single fact explains why every method below is
really a method for *loading* comments rather than searching them.

## Method 1: scroll and find

Open the video, scroll until comments stop loading, expand the reply threads you care
about, then press `Ctrl+F` (or `Cmd+F`) and search the page.

Works for: a video with a few hundred comments and a specific phrase you remember.

Fails for: anything popular. On a video with fifty thousand comments you will be
scrolling for a very long time, and collapsed replies are not searched at all.

## Method 2: the Data API

YouTube's [Data API](https://developers.google.com/youtube/v3/docs/commentThreads)
exposes comment threads properly, with paging, ordering and reply expansion. It needs
an API key, and every request spends quota.

This is the correct tool for anything systematic — a researcher reading a thousand
comments, a creator auditing sentiment across a catalogue.

[[tool:youtube-comment-finder]]

Our comment finder uses that official API, which is why it needs an operator-supplied
key rather than pretending to be free. It searches a video's comments for a phrase and
returns the matches with their authors and links.

## Method 3: search the video instead

Often the real question is not "where is that comment" but "what are people saying".
For that, the comments are a poor instrument anyway — they are self-selected, weighted
towards the first hours after publication, and dominated by whoever comments most.

If you want a signal rather than a quote, the better inputs are what people search for
and what the video's own metadata says about its audience:

[[tool:youtube-search-suggestions]]

See [free YouTube keyword research using autocomplete](/blog/youtube-keyword-research).

## What none of these can do

**Search comments across a whole channel.** There is no endpoint for it; you would
query video by video.

**Search your own comment history.** Google Takeout will export it, which is a
different and slower answer, but nothing searches it live.

**Find deleted or held comments.** Comments removed by the creator, caught by a filter
or held for review are not in the public thread and are not retrievable.

## A note on comment screenshots

If you are collecting comments to quote, remember that an image of a comment proves
nothing about who wrote it — anyone can draw one.

[[tool:fake-youtube-comment-generator]]

That tool exists to make mock-ups for design work and thumbnails, draws entirely in
the browser, and carries a warning saying so. Screenshot the real thread and link to
it when you are quoting someone; use a mock-up only where it is obviously a mock-up.
The general principle is the same one behind
[how to screenshot a tweet, honestly](/blog/screenshot-a-tweet).

:::faq
Q: Can you search comments on YouTube?
A: Not in the app or on the website. You either load the comments and use your
browser's find, or query the Data API.
Q: How do I find my own comment on a video?
A: Load the comment section, sort by newest, and use Ctrl+F. Your own comments are
also exportable through Google Takeout.
Q: Why does a comment search tool need an API key?
A: Because the only reliable route is YouTube's Data API, which is authenticated and
quota-limited. Anything claiming to search comments at scale without one is scraping.
Q: Can I search comments across an entire channel?
A: Not directly. You would have to query each video's comment threads separately.
:::

Search a video's comments through the official API with the
[YouTube comment finder](/tools/youtube-comment-finder).
