---
{
 "id": "XT-02",
 "slug": "twitter-character-limit",
 "title": "The Twitter Character Limit on X, Counted Properly",
 "excerpt": "The Twitter character limit is 280 for most accounts, but URLs, emoji and non-Latin scripts are not counted one-for-one. Here is how the weighting works.",
 "category": "content",
 "categories": [],
 "tags": ["x", "captions", "explainer"],
 "primary_keyword": "twitter character limit",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "The Twitter Character Limit on X, Counted Properly",
  "description": "The Twitter character limit explained: 280 characters, how URLs and emoji are weighted, why some scripts count double, and where the practical limit sits.",
  "focus_keyword": "twitter character limit",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "The Twitter character limit, counted properly",
  "og_description": "280, except when a character is not one character.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
The Twitter character limit on X is 280 characters for standard accounts, but the count
is weighted rather than literal: a URL counts as a fixed length however long it is, and
characters in scripts such as Chinese, Japanese and Korean count as two. That is why a
post can be rejected at what looks like 250 characters.

## How the Twitter character limit is counted

| Content | Counted as |
| --- | --- |
| Latin letters, digits, punctuation | 1 each |
| CJK and some other scripts | 2 each |
| A URL of any length | A fixed length, set by X's link wrapper |
| Emoji | Often 2, sometimes more for composed emoji |
| An attached image or video | 0 — attachments do not consume characters |

The URL rule is the useful one. Shortening a link before posting saves you nothing,
because X substitutes its own wrapper and charges the same either way. Use the real URL,
which is also more trustworthy to a reader.

[[tool:social-media-character-counter]]

The counter applies the weighting rather than counting keystrokes, and shows the same
text against every other platform's limit at once.

## The practical limit is lower than the real one

Two reasons to aim well under 280.

**Quoting.** A post that someone can quote and add a line to travels further than one
that fills the space. Leaving room is a distribution decision.

**Reading.** Short posts are read; long ones are skimmed. Empirically, the posts that
travel furthest on X are considerably shorter than the limit.

If your point genuinely needs more space, that is a thread rather than a longer post —
[how to write a Twitter thread](/blog/how-to-write-a-twitter-thread).

[[tool:x-thread-splitter]]

## Longer posts, and why they change the advice less than you think

Premium accounts on X can post far longer text, which sounds like it removes the
constraint. In practice it moves it: long posts are collapsed behind a "Show more" link
in the timeline, so the first couple of lines are still doing the entire job.

That is the same structural problem as
[Instagram caption length](/blog/instagram-caption-length) and
[where LinkedIn puts See more](/blog/linkedin-see-more) — a fold rather than a cap, and
folds are unforgiving in a scrolling feed.

## What the limit does not include

Attachments are free. An image, a video, a poll or a quoted post costs no characters,
which makes them the cheapest way to add substance to a post that is already full.

Images have their own constraint — the timeline crop, which is more aggressive than
people expect:

[[tool:social-image-resizer]]

See [Twitter image sizes for X](/blog/twitter-image-size).

Links attached to a post also generate a card, built from the linked page rather than the
post — [Open Graph tags](/blog/open-graph-tags). X documents its card formats in its
[developer documentation](https://developer.x.com/en/docs/x-for-websites/cards/overview/abouts-cards).

[[tool:link-preview-debugger]]

## Threads counts differently again

Threads allows 500 characters and chains overflow into a second post rather than
refusing it — [the Threads character limit](/blog/threads-character-limit). Anyone
crossposting should write to the tighter limit, which is X's; the full comparison is in
[how to post on X and Threads](/blog/how-to-post-on-x).

:::faq
Q: How many characters is a tweet?
A: 280 for standard accounts, counted with weighting. Premium accounts can post
considerably longer text, which is collapsed behind a "Show more" link.
Q: Do links count toward the character limit?
A: Yes, but as a fixed length regardless of the URL's actual length - so shortening a
link saves nothing.
Q: Do emoji count as one character?
A: Usually two, and sometimes more for composed emoji such as those with skin-tone
modifiers.
Q: Do images count toward the limit?
A: No. Images, videos, polls and quoted posts are free.
:::

Count it the way X does, across every platform at once:
[character counter](/tools/social-media-character-counter).
