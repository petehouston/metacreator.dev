---
{
 "id": "YT-01",
 "slug": "youtube-seo",
 "title": "YouTube SEO: How Videos Actually Get Found",
 "excerpt": "YouTube SEO is not keyword stuffing. It is matching a real query, earning the click, and holding the view. Here is what each field does, in order of weight.",
 "category": "seo",
 "categories": ["growth"],
 "tags": ["youtube", "youtube-seo", "metadata", "guide"],
 "primary_keyword": "youtube seo",
 "status": "draft",
 "is_featured": true,
 "allow_comments": true,
 "seo": {
  "title": "YouTube SEO: How Videos Actually Get Found",
  "description": "A field-by-field guide to YouTube SEO: which metadata moves discovery, which barely matters, and how to check every field on your own video for free.",
  "focus_keyword": "youtube seo",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "YouTube SEO, field by field, in order of weight",
  "og_description": "Title, thumbnail, description, tags, hashtags, chapters - what each one actually does for discovery, and how to check yours.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
YouTube SEO is the work of matching a real search query, earning the click, and
holding the view long enough that YouTube keeps showing the video. Metadata gets you
considered; the thumbnail and the first thirty seconds decide everything after that.
This guide covers every field, in the order it actually matters.

## What YouTube SEO is actually optimising for

YouTube's job is to keep someone watching. Every surface — search, suggested, home,
Shorts — is a prediction: *of the videos we could show this person now, which one
holds them longest?* That single sentence explains most of what follows.

It explains why a keyword-perfect title with a weak thumbnail goes nowhere: the video
was shown and nobody clicked, which is evidence against it. It explains why a video
can rank for a phrase it never uses: viewers who searched that phrase watched it to
the end. And it explains why "SEO tricks" that fooled a text index in 2010 do nothing
here — the ranking signal is behaviour, and metadata is only the thing that gets you
into the running.

So the practical model is three gates:

1. **Retrieval.** Does YouTube understand what this video is? (metadata, transcript)
2. **The click.** Shown to the right person, do they choose it? (title, thumbnail)
3. **The hold.** Having clicked, do they stay? (the video itself)

You can only control the first two with metadata. The third is craft, and no field
in Studio substitutes for it.

## The title carries the query

The title is the only field that is both a ranking input and the thing a human reads
before deciding. It has a hard limit of 100 characters
([YouTube Help](https://support.google.com/youtube/answer/57407)), but the limit is
irrelevant compared with where it gets cut off — search results, suggested rails and
mobile each truncate at a different point, and a title whose promise lives in the
last four words is a title that fails on a phone.

Two rules that survive every algorithm change:

- **Front-load the phrase someone would type.** Not a slogan. If the query is
  "how to fix a link preview", the title starts near those words.
- **Make the last word earn something.** Curiosity, specificity, a number — but the
  keyword goes first and the hook goes second, not the other way round.

[[tool:social-media-character-counter]]

The counter shows the truncation point for each surface side by side, so you can see
which half of your title survives before you publish rather than after.

Full detail: [YouTube title length and where it gets cut off](/blog/youtube-title-length).

## The thumbnail is the other half of the title

Treat them as one unit, because the viewer does. A thumbnail that repeats the title
in text wastes half the space; a thumbnail that contradicts it destroys trust.

YouTube accepts custom thumbnails at 1280×720 or larger — the documented target is
3840×2160 at 16:9, minimum width 640 pixels, JPG or PNG
([YouTube Help](https://support.google.com/youtube/answer/72431)). What matters more
than resolution is legibility at the size it is actually seen: a sidebar suggestion
is roughly 168 pixels wide. Text that is unreadable there is decoration.

[[tool:safe-zone-guide]]

Sizing and the parts covered by the duration badge and progress bar are covered in
[YouTube thumbnail size and the part nobody sees](/blog/youtube-thumbnail-size).

## The description does two jobs, badly if you make it do one

The first two or three lines are shown above the fold and are read by humans deciding
whether to stay. The rest is context for YouTube and a place for links, chapters and
attribution. Descriptions allow up to 5,000 characters
([YouTube Help](https://support.google.com/youtube/answer/12948449)), which is an
invitation most channels answer badly — with a wall of boilerplate.

A description that works has a shape: the promise restated in one sentence, the
timestamps, the links that matter, then the boilerplate nobody reads. Put the
boilerplate first and you have spent your only visible lines on a Patreon URL.

[[tool:youtube-channel-description-generator]]

The full structure, with a fill-in template, is in
[a YouTube description template that earns its space](/blog/youtube-description-template).

## Tags: real, and nearly irrelevant

This is where most YouTube SEO advice is a decade out of date. YouTube's own
documentation is direct about it:

> Tags can be useful if the content of your video is commonly misspelled. Otherwise,
> tags play a minimal role in your video's discovery.
> -- [YouTube Help](https://support.google.com/youtube/answer/146402)

Which means: fill in a handful covering misspellings and genuine synonyms, then stop.
Tags remain useful as *research* — reading a competitor's tags tells you the
vocabulary they think describes their video — but copying them onto your own upload
achieves nothing, because they were doing nothing for the other channel either.

[[tool:youtube-tag-extractor]]

How to read any video's tags, and why the page source is the ground truth:
[how to see the tags on any YouTube video](/blog/how-to-see-tags-on-a-youtube-video).

## Hashtags are a different mechanism entirely

Hashtags live in the description, render as clickable links above the title, and have
rules worth knowing:

| Rule | Detail |
| --- | --- |
| Shown above the title | Up to three, chosen by YouTube as the most engaging |
| Hard ceiling | More than 60 on a video and every hashtag is ignored |
| No spaces | Join words instead: `#twowords` |
| Over-tagging | May remove the video from search or from your uploads |

Source: [YouTube Help on hashtags](https://support.google.com/youtube/answer/6390658).

Three to five, relevant, is the whole strategy. The mechanics are in
[how many hashtags to use on YouTube](/blog/youtube-hashtags).

## Chapters make one video answer several queries

Timestamps in the description become chapters, and chapters get their own treatment
in search results — a viewer can be sent to the 4:12 mark for the thing they asked
about. For any video that covers more than one question, this is the highest-return
five minutes of metadata work available.

[[tool:youtube-timestamp-link-builder]]

See [how to make a YouTube timestamp link](/blog/youtube-timestamp-links) for the
format and the rules chapters have to follow to appear at all.

## Keyword research without a keyword tool

You do not need a paid tool to find real demand. YouTube's own autocomplete is a live
record of what people are typing, and it is free to read.

[[tool:youtube-search-suggestions]]

Type a seed, read the completions, and note that these are queries with demand
attached — unlike a made-up "search volume" number. The method, including how to
build a topic tree from one seed, is in
[free YouTube keyword research using autocomplete](/blog/youtube-keyword-research).

## When a video simply does not appear

Before assuming a ranking problem, eliminate the settings that suppress a video
outright: visibility, made-for-kids status, age restriction, licence and category can
each remove it from surfaces you assumed it was on. That is a checklist, not a
mystery, and it is here:
[YouTube video not showing in search](/blog/youtube-video-not-showing-in-search).

## What to do first, in order

If you have one hour and an existing channel:

1. **Fix the titles of your five best-performing videos** so the searchable phrase is
   in the first half. Nothing else in this guide has a better return.
2. **Check the fold on those titles** with the character counter.
3. **Rewrite the first three lines of each description** to restate the promise.
4. **Add chapters** to anything longer than four minutes that covers multiple points.
5. **Add three hashtags.** Not thirty.
6. **Leave the tags alone.** Genuinely.

Then stop optimising and make the next video better, because that is the gate the
metadata cannot open for you.

:::faq
Q: Does changing a title hurt an existing video?
A: No. Titles can be edited freely, and a better title on a video that already has
watch-time history is one of the fastest wins available. What you should not do is
change it repeatedly in a short window and then judge the result.
Q: How long does YouTube SEO take to work?
A: Search results move within days once a video has enough impressions to be judged.
Suggested placement takes longer because it depends on accumulated behaviour. If a
video has impressions and a low click rate, that is a title and thumbnail problem and
it is fixable today.
Q: Do tags still matter at all on YouTube?
A: Barely, and YouTube says so directly - they help with commonly misspelled terms
and little else. They remain useful for research into how other channels describe
their videos.
Q: Is there a free way to do YouTube keyword research?
A: Yes. YouTube's autocomplete returns real queries, and our search suggestions tool
reads it directly. That is demand data rather than an estimate.
:::

Start with the field that carries the most weight: paste a title into the
[character counter](/tools/social-media-character-counter) and see what actually
survives.
