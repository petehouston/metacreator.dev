---
{
 "id": "IG-04",
 "slug": "instagram-story-size",
 "title": "Instagram Story Size and the Zones You Cannot Use",
 "excerpt": "Instagram Story size is 1080x1920, but the top and bottom bands belong to the interface. Here is the usable area and how to design a template once.",
 "category": "design",
 "categories": [],
 "tags": ["instagram", "image-sizes", "safe-zones", "explainer"],
 "primary_keyword": "instagram story size",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "Instagram Story Size and the Unusable Zones",
  "description": "Instagram Story size is 1080x1920 at 9:16 - but the profile row, progress bars and reply field take back a third. The usable area, and a template to reuse.",
  "focus_keyword": "instagram story size",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Instagram Story size, minus the interface",
  "og_description": "The frame is 1080x1920. The part you can use is smaller.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
Instagram Story size is 1080×1920 pixels at 9:16. What matters more is the usable part:
the top carries progress bars and your profile row, the bottom carries the reply field
and any stickers, and text placed in either band is covered on some devices and clear on
others.

## Instagram Story size and its safe area

| Region | Roughly | What sits there |
| --- | --- | --- |
| Top | ~250 px | Progress bars, profile row, close button |
| Bottom | ~250-300 px | Reply field, share and react controls |
| Usable centre | ~1400 px tall | Everything you actually control |

Those bands move between devices and app versions — a phone with a large home indicator
takes more at the bottom than one without. Design to a generous margin rather than to
exact numbers.

[[tool:story-templates-sizer]]

The sizer exports a safe-zone overlay at the correct dimensions, which turns this from
something you remember into something your template enforces.

## Build a template, not a habit

The reliable fix is a reusable file with guide layers at the safe-zone boundaries,
kept switched on. Everything important goes inside the guides; the areas outside are
background.

That one artefact solves the problem permanently, including on the days you are posting
quickly from a phone — which is when the mistake usually happens.

[[tool:safe-zone-guide]]

The cross-platform version of the same problem, including TikTok's more aggressive
bands, is in [safe zones on social media](/blog/social-media-safe-zones) and
[TikTok video size](/blog/tiktok-video-size).

## Stickers, polls and links

Interactive stickers occupy real space and are tappable, so they need room around them —
a poll crammed against the bottom edge is hard to tap and partly covered by the reply
field.

Two placement habits worth having:

- **One interactive element per Story.** Two polls in one frame gets neither answered.
- **Put it in the lower-middle**, above the reply field but below the centre, where a
  thumb naturally sits.

## Stories are retention, not growth

Worth stating because it changes how much effort they deserve. Stories are shown mostly
to people who already follow you, which makes them a tool for keeping an audience warm
rather than finding a new one. The formats that reach non-followers are Reels and
carousels — see [Instagram growth](/blog/instagram-growth-guide).

That is not an argument for ignoring Stories. It is an argument for spending ten minutes
on them rather than an hour, and for spending the hour on
[a carousel that gets saved](/blog/instagram-carousel-size).

## What to actually put in a Story

The safe area answers where. What goes there is a different question, and the honest
answer is that most Stories fail for reasons of substance rather than layout.

Three things reliably earn a response:

**Something in progress.** A Story is the one format where unfinished work is
appropriate, and the audience that follows you wants exactly that.

**A question with an obvious answer.** Polls work when the answer is instant. A poll
requiring thought gets skipped.

**Continuity.** A sequence of three or four frames that builds is watched to the end far
more often than four unrelated frames.

What consistently does not work is a reposted feed post with "new post!" over it. The
audience has already seen the feed, and the Story adds nothing but a tap to skip.

## Exporting

1080×1920, JPEG for photographs, PNG for anything with flat colour or text. Instagram
recompresses either way, so export at the right size rather than uploading something
enormous — [how to compress an image for Instagram](/blog/compress-image-for-instagram).

[[tool:social-image-resizer]]

Meta's placement specifications, which cover Stories across its apps, are in the
[Business Help Centre](https://www.facebook.com/business/help/103816146375741), and the
full per-placement list is [Instagram image size](/blog/instagram-image-sizes).

:::faq
Q: What size is an Instagram Story?
A: 1080×1920 pixels, 9:16. The usable area is smaller because the interface covers the
top and bottom bands.
Q: What is the Instagram Story safe zone?
A: Roughly the middle 1,400 pixels of the 1,920-pixel frame - clear of the progress bars
and profile row at the top and the reply field at the bottom.
Q: Why is my Story text cut off?
A: It is in a band the interface covers. Those bands differ by device, which is why the
same Story can look fine on your phone and clipped on someone else's.
Q: Do Stories help me gain followers?
A: Rarely. They are shown mainly to existing followers. Reels and carousels are the
formats that reach people who do not follow you.
:::

Export a safe-zone overlay for your own template with the
[Story template sizer](/tools/story-templates-sizer).
