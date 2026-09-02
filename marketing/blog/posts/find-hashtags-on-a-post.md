---
{
 "id": "HT-06",
 "slug": "find-hashtags-on-a-post",
 "title": "How to Find the Hashtags on a Post",
 "excerpt": "Copying hashtags out of a screenshot by hand is how most people do this. Here is how to pull them off a public post from its URL, and what the limits are.",
 "category": "seo",
 "categories": [],
 "tags": ["hashtags", "metadata", "how-to"],
 "primary_keyword": "find hashtags on a post",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "How to Find the Hashtags on a Post",
  "description": "Find the hashtags on any public post from its link or its caption. Covers every platform, the login-wall limit, and why YouTube tags are a different thing.",
  "focus_keyword": "find hashtags on a post",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Pull every hashtag off a post, from its link",
  "og_description": "No screenshots, no retyping. Plus why YouTube tags are not hashtags.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
To find the hashtags on a post, paste its link into a hashtag extractor — or paste the
caption text directly, which works every time and needs no fetch at all. Both give you
the full set, de-duplicated and ready to copy, instead of retyping them out of a
screenshot.

[[tool:hashtag-extractor]]

## How a tool finds hashtags on a post

Every platform publishes a post's caption in its **Open Graph tags**, because that is
what renders when the post is shared somewhere else. That public metadata is what an
extractor reads. No login, no session, nothing that requires being signed in.

The honest consequence: a platform that answers an unauthenticated request with a
sign-in page cannot be read. Instagram and Facebook do this intermittently, even for
public posts, depending on where the request comes from. When it happens the answer is
not to find a cleverer tool — it is to copy the caption and paste that instead, which
takes two seconds and always works.

| Platform | Reads from a URL |
| --- | --- |
| YouTube | Reliably — the description is published as metadata |
| Pinterest | Reliably |
| Threads, Tumblr, Mastodon | Reliably |
| X | Usually |
| Instagram, Facebook | Intermittently — paste the caption instead |
| TikTok | Intermittently |

## Hashtags are not the same as YouTube tags

This is the most common confusion in this query family, and it matters because the two
live in different places.

**Tags** on YouTube are invisible keywords set in the upload options. Viewers never see
them; they exist for YouTube's own matching. **Hashtags** are part of the description
and appear above the video title, clickable.

Different questions, different tools. For the invisible keywords use the
[YouTube tag extractor](/tools/youtube-tag-extractor); for the ones in the description,
the hashtag extractor. [How to see the tags on a YouTube
video](/blog/how-to-see-tags-on-a-youtube-video) covers the first in full.

One quirk worth knowing: YouTube only publishes about the first 160 characters of a
description in its metadata, so hashtags at the end of a long description may not be
readable from the URL. The three that show above the title always are.

## Doing it by hand

There is no secret to it, and for a single post the manual route is perfectly
reasonable.

1. Open the post and expand the caption fully — on Instagram that means tapping "more",
   and on YouTube expanding the description.
2. Select the caption text and copy it.
3. Paste it somewhere you can read it, and pull out anything beginning with `#`.

Two things make this worse than it sounds at scale. Hashtags are frequently separated
by line breaks, invisible characters or a wall of dots, so a naïve copy brings all of
that with it. And accounts often repeat the same tag in different cases across posts,
which means a set you assemble by hand from five posts will be longer than the set
actually in use.

An extractor solves both — it de-duplicates case-insensitively, keeps the spelling it
first saw, and tells you where in the post each tag was found. On more than about three
posts, that is the difference between an afternoon and a coffee.

## What to do with a competitor's hashtag set

Not copy it wholesale. Their tags were chosen — or, more often, accumulated — for
their audience size, not yours. A set built around tags with ten million posts will bury
an account with two thousand followers, because the post falls out of the recent feed in
seconds.

Read the set for its **shape** instead:

- **How many.** A disciplined account uses a consistent number. Wild variation between
  posts usually means nobody is measuring.
- **How specific.** Count how many tags are niche rather than broad. That ratio is the
  strategy.
- **How many are branded.** A campaign or community tag that only they use tells you
  what they are trying to build.
- **Which repeat.** Tags appearing on every post are the ones they believe in; the rest
  are decoration.

Then build your own set at your own size. The [hashtag
generator](/tools/hashtag-generator) mixes broad, mid and niche tags for a topic, and
[how to use hashtags](/blog/how-to-use-hashtags) covers the reasoning per platform.
Before you commit to a set, check none of them are restricted —
[banned hashtags](/blog/banned-hashtags) explains what happens when one is.

Instagram documents its own hashtag rules, including the thirty-tag cap, in its
[Help Centre](https://help.instagram.com/351460621611310).

:::faq
Q: Does it work with Instagram links?
A: When Instagram serves the post's metadata, yes. It increasingly answers automated requests with a sign-in page instead, and no tool without a logged-in session gets past that. Pasting the caption text works every time.
Q: Are hashtags case-sensitive?
A: No. #TravelTips and #traveltips reach the same feed on every major platform. Case only affects readability, which is a real consideration in a long multi-word tag.
Q: Does it find hashtags in the comments?
A: No, only in the post itself. Hashtags placed in the first comment — a common Instagram habit — are not part of the post's published metadata.
Q: Will it read non-English hashtags?
A: Yes. Japanese, Arabic, Cyrillic and every other script are matched as written.
:::

Paste a link or a caption into the [hashtag extractor](/tools/hashtag-extractor), then
build your own set with the [hashtag generator](/tools/hashtag-generator).
