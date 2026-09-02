---
{
 "id": "XT-01",
 "slug": "how-to-post-on-x",
 "title": "How to Post on X and Threads: Limits, Threads, Cards",
 "excerpt": "How to post on X and Threads properly: the real character limits, how a thread should be structured, and why your link card shows the wrong image.",
 "category": "content",
 "categories": [],
 "tags": ["x", "threads", "guide"],
 "primary_keyword": "how to post on x",
 "status": "draft",
 "is_featured": true,
 "allow_comments": true,
 "seo": {
  "title": "How to Post on X and Threads: A Practical Guide",
  "description": "How to post on X and Threads: character limits counted properly, thread structure that gets read, link cards that render, and the image sizes that survive.",
  "focus_keyword": "how to post on x",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Posting on X and Threads, properly",
  "og_description": "Limits, threads, cards and crops - the mechanics that decide whether a post lands.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
Posting well on X and Threads is mostly mechanics: knowing where the character limit
actually bites, structuring a thread so people read past the first post, and making sure
a shared link renders the card you intended. None of it is difficult, and all of it is
routinely got wrong.

## How to post on X: the limits that matter

X counts characters in a weighted way rather than one-per-character. URLs are counted as
a fixed length regardless of how long they are, and characters in scripts such as
Japanese or Chinese count as two.

Threads has its own limit — 500 characters — and overflows into a chained post rather
than refusing the text. X documents its own counting rules, including the URL weighting,
in its [developer documentation](https://developer.x.com/en/docs/counting-characters).

[[tool:social-media-character-counter]]

The counter handles the weighting for both, alongside every other platform, which is
more reliable than counting in a text editor. Detail:
[the Twitter character limit](/blog/twitter-character-limit) and
[the Threads character limit](/blog/threads-character-limit).

## Threads that get read

A thread is a sequence, and the failure is nearly always structural rather than
literary:

- **The first post is the entire pitch.** It has to work alone, because most people will
  see nothing else. No "a thread 🧵" as the whole opening.
- **One idea per post.** A post crammed with three points is skimmed and abandoned.
- **The second post carries the cost of the first.** This is where the drop-off is.
- **End with the point, not with a plug.** A thread that ends by selling something
  teaches people not to read the next one.

[[tool:x-thread-splitter]]

The splitter breaks long writing at sentence boundaries rather than mid-clause, which is
the difference between a thread that reads naturally and one that is obviously an
article chopped up. See
[how to write a Twitter thread](/blog/how-to-write-a-twitter-thread).

## Link cards come from the page

Post a URL and the card is built from that page's Open Graph and card tags — not from
anything you attach. So a wrong card is a page problem, and the fix is on your site.

[[tool:link-preview-debugger]]

The debugger fetches once and draws the card as X, Facebook, LinkedIn and chat apps each
render it. See [Open Graph tags](/blog/open-graph-tags) and, when the card is stale or
missing, [link preview not showing](/blog/link-preview-not-showing).

## Images and the timeline crop

X shows an attached image cropped to a band in the timeline and expands it on tap, so a
tall image or a screenshot of text is reduced to something unreadable at the moment it
matters.

[[tool:social-image-resizer]]

Sizes and the crop behaviour are in
[Twitter image sizes for X](/blog/twitter-image-size). If you are sharing a post as an
image, do it honestly — a drawn card proves nothing about who wrote it:

[[tool:tweet-screenshot-generator]]

See [how to screenshot a tweet](/blog/screenshot-a-tweet).

## Threads is not X with a different logo

Two differences that change how you write:

**The limit is lower** — 500 characters, with overflow chaining. That makes Threads
closer to a short post format than a microblog.

**Replies are the currency.** Threads surfaces conversation, so a post that invites a
reply outperforms one that states a conclusion. The profile people see when they arrive
from a reply is worth more attention than on X —
[Threads bio ideas](/blog/threads-bio-ideas).

[[tool:threads-post-preview]]

## What travels on each platform

The mechanics above are necessary and not sufficient. What actually gets read differs
between the two in a way worth naming.

**On X**, the unit that travels is a claim - something specific enough to be agreed with
or argued against. Posts that state a position, share a number or make a comparison get
quoted; posts that describe a feeling get liked and forgotten. The quote-post mechanic is
the distribution engine, and you can only quote something that says something.

**On Threads**, the unit that travels is a prompt. The system surfaces conversation, so a
post that invites a reply outperforms one that closes the subject. That is not a
lower-quality bar, it is a different one: the question has to be worth answering, and
"thoughts?" is not.

The practical version: write the claim first, then decide whether to end it with a full
stop for X or a question for Threads. Same substance, different close.

## Crossposting, carefully

The same text rarely works on both. A 280-character post reads as clipped on Threads; a
500-character Threads post does not fit on X at all. If you are publishing to both, write
for the tighter limit and let the looser one carry it — and check both before publishing
rather than after.

:::faq
Q: What is the character limit on X?
A: The standard limit is 280 characters for most accounts, counted with weighting - URLs
count as a fixed length and some scripts count double.
Q: How long can a Threads post be?
A: 500 characters. Longer text chains into a second post rather than being rejected.
Q: Why is my link preview showing the wrong image?
A: The card is built from the linked page's Open Graph tags and cached. Fix the page,
then check what the platforms are actually reading.
Q: Should I post the same thing on X and Threads?
A: Rarely word for word. The limits and the conversational norms differ enough that a
post optimised for one reads badly on the other.
:::

Check the limits, the weighting and the fold before you post:
[character counter](/tools/social-media-character-counter).
