---
{
 "id": "PN-07",
 "slug": "link-preview-mobile-vs-desktop",
 "title": "Why Your Link Preview Looks Different on Mobile",
 "excerpt": "The same link renders two different cards. Facebook gives a title 88 characters on desktop and about 65 on a phone — and the phone is where most of the taps are.",
 "category": "seo",
 "categories": [],
 "tags": ["link-previews", "metadata", "troubleshooting"],
 "primary_keyword": "link preview mobile",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "Why Your Link Preview Looks Different on Mobile",
  "description": "A link preview on mobile crops harder than the desktop one. Here are the real limits per platform, and how to write a title that survives both.",
  "focus_keyword": "link preview mobile",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Your link preview is cut in half on a phone",
  "og_description": "Facebook: 88 characters on desktop, about 65 on mobile. LinkedIn: 100 and about 60.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
A link preview on mobile is drawn from the same Open Graph tags as the desktop one, but
the card is narrower, so the platform crops the title harder and shows less of the
description — or none of it. A title that reads perfectly at your desk can be cut
mid-word for most of the people who actually see it.

[[tool:link-preview-debugger]]

## What each platform shows in a mobile link preview

Approximate, because every platform varies its crop with device width, font size and
the reader's accessibility settings. Treat them as planning limits, not guarantees.

| Platform | Desktop title | Mobile title | Description on mobile |
| --- | --- | --- | --- |
| Facebook | ~88 characters | ~65 | One line |
| LinkedIn | ~100 | ~60 | None — LinkedIn ignores it entirely |
| X | ~70 | ~50 | None on the large image card |
| Pinterest | ~40 | ~40 | Shown on the closeup |
| WhatsApp, Telegram | ~65 | ~65 | ~100 characters |

Two of these deserve attention. **LinkedIn ignores `og:description` on every surface**,
so the title carries the entire card — which makes LinkedIn the platform where a vague
title costs the most. And **X drops the description on the large image card**, which is
what most links produce, so the description you carefully wrote is invisible to the
largest share of the audience.

## Why the crop differs at all

The card is a fixed proportion of the column, and the column on a phone is roughly a
third the width of a desktop feed. The platform does not reflow the card; it allocates
the same one or two lines and fits fewer characters into them.

That is why the failure is silent. Nothing errors, nothing is missing from the tags, and
the desktop preview — the one you checked — is correct. The phone is simply a different
render of the same data, and it is the render the majority of your audience gets.

## Writing a title that survives both

One rule does most of the work: **put the point in the first fifty characters**.

- Fifty characters or fewer survives every platform on every surface.
- Sixty-five is safe on Facebook and the chat apps.
- Anything past seventy is decoration on mobile — write it for the reader who taps
  through, not for the one deciding whether to.
- Never end the first clause on a preposition or a dangling name. "How to fix a link
  preview that…" reads as broken; "Fix a broken link preview" does not.

The description is a different calculation. It is worth writing well for the chat apps,
which keep 100 characters or more, and it is worth accepting that Facebook shows one
line on a phone and LinkedIn shows none. Front-load it exactly as you would the title.
[Open Graph tags](/blog/open-graph-tags) covers the tags themselves and which platform
reads which. If you want the count as you type, the
[social media character counter](/tools/social-media-character-counter) tracks every
surface at once.

Meta documents its own crop and image behaviour in the
[Sharing best practices](https://developers.facebook.com/docs/sharing/best-practices/)
guide.

## Check the mobile card, not the desktop one

Every platform's own debugger shows you a desktop render, requires you to be logged in
to that platform, and shows a different subset from the others. Checking a link properly
that way is four logins and four tabs, and none of them shows you the phone.

The [link preview debugger](/tools/link-preview-debugger) fetches the page once and
draws every platform's card **twice — desktop and mobile** — from the tags it finds,
including the fallbacks each platform applies when a tag is missing. If the card is
wrong on both, the tags are the problem and
[link preview not showing](/blog/link-preview-not-showing) is the post to read next; if
Facebook is showing an old version of a card you have already fixed,
[Facebook link preview not updating](/blog/facebook-link-preview-not-updating) covers
the cache.

:::tip
Chat apps fetch a preview **once, on the first send, and cache it hard.** A fix
published later will not reach a message that has already gone out. Get the card right
before the link is shared, not after.
:::

:::faq
Q: Can I set a different title for mobile?
A: No. There is one set of Open Graph tags and every surface reads the same ones. The only lever is writing a title short enough to survive the tightest crop you care about.
Q: My mobile preview shows no image at all.
A: Check the image is at least 1200×630 and under about 8 MB, and that it is reachable without a cookie or a login. Some platforms silently skip an image they cannot fetch quickly rather than reporting a failure.
Q: Does the crop change with the reader's font size?
A: Yes. A reader using large text sees fewer characters again, which is another argument for a short first clause rather than a title tuned to a specific limit.
Q: Why does LinkedIn show no description?
A: It does not read og:description at all, on any surface. The title and the image are the whole card.
:::

Run the URL through the [link preview debugger](/tools/link-preview-debugger) and read
the mobile rows first — they are the ones that decide whether the link gets tapped.
