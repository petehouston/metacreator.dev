---
{
 "id": "YT-10",
 "slug": "youtube-video-metadata",
 "title": "YouTube Video Metadata: Every Field a Video Declares",
 "excerpt": "A YouTube page publishes far more about a video than the interface draws. Here is the full list of fields, what each one controls, and how to read them.",
 "category": "seo",
 "categories": [],
 "tags": ["youtube", "metadata", "explainer"],
 "primary_keyword": "youtube video metadata",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "YouTube Video Metadata: Every Field, Explained",
  "description": "Every piece of YouTube video metadata a public page declares - tags, category, language, licence, family-safe status, thumbnails - and what each one controls.",
  "focus_keyword": "youtube video metadata",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Everything a YouTube video says about itself",
  "og_description": "The fields the interface never draws, what they control, and how to read them on any video.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
YouTube video metadata is everything the page declares about a video that is not the
video: title, description, tags, category, language, licence, family-safe status,
thumbnail set, duration, upload state. Most of it never renders, and all of it is
readable from the public page.

## The YouTube video metadata fields, and what each does

| Field | Visible? | What it controls |
| --- | --- | --- |
| Title | Yes | The strongest text signal, and the click |
| Description | Partly | Context, chapters, links; first lines shown |
| Tags | No | Very little — YouTube says "a minimal role" |
| Category | No | Which broad pool a video is grouped with |
| Language / audio language | No | Who it is served to, caption defaults |
| Licence | No | Whether others may reuse it |
| Family-safe / made for kids | No | A large set of features and surfaces |
| Duration | Yes | Format classification, including Shorts |
| Thumbnail set | Yes | Every generated and custom image, at each size |
| Live / premiere flags | Sometimes | How the video is surfaced while live |

The pattern worth noticing: the fields with the largest effect on distribution are
mostly invisible ones. A wrong audio language or an accidental made-for-kids flag
does more damage than a mediocre tag list ever could — and neither is apparent from
looking at the watch page.

[[tool:youtube-metadata-viewer]]

The metadata viewer puts every declared field in one table for any public video, which
is faster than opening five panels in Studio and works on channels you do not own.

## Reading it yourself

All of it is in the page HTML. View source on a watch page and search for
`"keywords"` (the tags), `"category"`, `"isFamilySafe"` or `"lengthSeconds"`. That is
the same ground truth every metadata tool reads, ours included — if a tool ever
disagrees with the page source, believe the source.

## The three fields worth auditing on your own videos

**Audio language.** Set wrongly, YouTube serves your video to an audience that cannot
understand it and generates captions in the wrong language. It is one dropdown and it
is frequently wrong on channels that upload from a phone.

**Category.** A broad grouping signal. Not decisive, but a cooking video filed under
People & Blogs is competing in the wrong pool.

**Made for kids.** The one with real consequences: it disables comments,
notifications, personalised ads and some discovery surfaces
([YouTube Help](https://support.google.com/youtube/answer/9528076)). Set at channel
level, it applies to everything you upload afterwards.

If a video is missing from places you expect it, these are part of the checklist in
[YouTube video not showing in search](/blog/youtube-video-not-showing-in-search).

[[tool:youtube-shadowban-detector]]

The same public fields are what a citation is built from, which is why citing a video
accurately is a metadata exercise rather than a formatting one —
[how to cite a YouTube video](/blog/cite-a-youtube-video).

## Metadata for research, not imitation

Reading another channel's metadata is legitimate research: what language and category
they file under, how long their videos run, what vocabulary they use in tags. Copying
it wholesale is not a strategy — tags in particular do almost nothing, per
[YouTube's own guidance](https://support.google.com/youtube/answer/146402), which is
covered in [how to see the tags on any YouTube video](/blog/how-to-see-tags-on-a-youtube-video).

What is genuinely useful is the pattern across a competitor's whole catalogue: video
length, upload cadence, whether they use chapters, which thumbnail sizes they supply.
Those are decisions you can learn from.

## The thumbnail set is metadata too

Every video publishes several thumbnail images at different resolutions — the custom
one if there is one, plus automatically generated frames. That set is useful for
competitive research and for reclaiming your own artwork when the original file is
lost.

[[tool:youtube-thumbnail-downloader]]

See [how to download a YouTube thumbnail](/blog/download-youtube-thumbnail).

:::faq
Q: Where is YouTube video metadata stored?
A: On the video, set in YouTube Studio, and published in the watch page's HTML. Much
of it never renders in the interface but is present in the page source.
Q: Can I see the metadata of someone else's video?
A: Yes - everything above is public. Paste the URL into the metadata viewer, or read
the page source directly.
Q: Which metadata field matters most?
A: The title, by a distance, followed by the thumbnail. Among the hidden fields, made
for kids and audio language have the largest effect on where a video is shown.
Q: Do tags count as metadata?
A: Yes, and they are the least consequential kind. YouTube states they play a minimal
role in discovery apart from commonly misspelled terms.
:::

Read every field on any video with the
[metadata viewer](/tools/youtube-metadata-viewer).
