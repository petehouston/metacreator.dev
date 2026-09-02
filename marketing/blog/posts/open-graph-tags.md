---
{
 "id": "PN-06",
 "slug": "open-graph-tags",
 "title": "Open Graph Tags, in the Order Platforms Read Them",
 "excerpt": "Open Graph tags decide what every platform shows when your link is shared. Here is the minimum set, the optional ones, and the mistakes that break cards.",
 "category": "seo",
 "categories": ["design"],
 "tags": ["link-previews", "explainer"],
 "primary_keyword": "open graph tags",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "Open Graph Tags, in the Order Platforms Read Them",
  "description": "The Open Graph tags that matter: the minimum set every page needs, the Twitter card additions, image requirements, and the mistakes that break link previews.",
  "focus_keyword": "open graph tags",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Open Graph tags, minimum viable set",
  "og_description": "Five tags, one image size, and the errors that break cards everywhere.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
Open Graph tags are the metadata every platform reads to build a link preview. Get five of
them right and your links render correctly on X, Facebook, LinkedIn, WhatsApp, Slack,
Discord and everywhere else that unfurls a URL. Get them wrong once and the wrong card is
cached for a long time.

## The minimum set of Open Graph tags

```html
<meta property="og:title"       content="The page title, under about 60 characters">
<meta property="og:description" content="One sentence, 110-160 characters.">
<meta property="og:image"       content="https://example.com/card.png">
<meta property="og:url"         content="https://example.com/page">
<meta property="og:type"        content="article">
<meta name="twitter:card"       content="summary_large_image">
```

Five Open Graph properties and one Twitter card declaration. That last line matters more
than its length suggests: without it X often renders the small square card instead of the
large one, which is a substantial difference in how much space a link takes in a feed.

[[tool:link-preview-debugger]]

The debugger fetches a URL once and draws the card as each platform renders it, which is
faster than testing by posting.

## The image

The one that goes wrong most often:

| Requirement | Value |
| --- | --- |
| Size | 1200×630 (about 1.91:1) |
| URL | Absolute, and publicly reachable |
| Format | JPEG or PNG |
| Weight | Keep it well under a megabyte |
| Access | Not behind auth, hotlink protection or a bot rule |

A relative image path is the classic failure — it works in your browser and returns
nothing to a crawler that has no page context. Sizes for every other placement are in
[social media image sizes](/blog/social-media-image-sizes).

[[tool:social-image-resizer]]

## What each tag is actually doing

Knowing the job of each one makes the failures obvious rather than mysterious.

**`og:title`** is the card's headline. It is not the page's `<title>` - it can and often
should differ, because a SERP title and a social card serve different moments. A SERP
title carries the query; a card title carries the reason to tap.

**`og:description`** is one sentence of context under the title. Some platforms show it,
some truncate it hard, and a couple ignore it entirely. Write it to work at about 110
characters and let the rest be a bonus.

**`og:image`** is the entire visual footprint of your link. On every platform, a post
containing a link is competing with posts containing images, and this is the only picture
it gets.

**`og:url`** is the canonical address of the content. Platforms use it to deduplicate:
three people sharing the same article via three tracking URLs should all resolve to one
piece of content, and this tag is how that happens.

**`og:type`** tells the platform what kind of thing it is - `article`, `website`,
`product`. It changes which additional properties are read and, for Pinterest, whether a
Rich Pin is possible at all.

## Optional tags worth having

```html
<meta property="og:site_name"   content="Your site">
<meta property="og:locale"      content="en_GB">
<meta property="article:author" content="https://example.com/about">
<meta name="twitter:site"       content="@yourhandle">
<meta property="og:image:alt"   content="What the card image shows">
```

`og:image:alt` is the accessibility one, and it is almost universally omitted.

## The mistakes that break cards

**Relative image URLs.** Must be absolute, with the protocol.

**Tags injected by JavaScript.** Many crawlers do not execute it. Open Graph tags belong
in server-rendered HTML.

**Duplicate or conflicting tags.** A theme and a plugin both emitting `og:image` produces
unpredictable results.

**`og:url` pointing elsewhere.** It should be self-referencing; platforms use it for
deduplication.

**Assuming a fix is live.** Platforms cache the first scrape —
[Facebook link preview not updating](/blog/facebook-link-preview-not-updating) and
[link preview not showing](/blog/link-preview-not-showing) cover the recovery.

## The specifications

The Open Graph protocol itself is documented at [ogp.me](https://ogp.me/), Meta documents
its sharing requirements in its
[developer documentation](https://developers.facebook.com/docs/sharing/webmasters/), and X
documents its card types in
[its own](https://developer.x.com/en/docs/x-for-websites/cards/overview/abouts-cards).
Pinterest reads the same tags for Rich Pins —
[Rich Pins not working](/blog/rich-pins-not-working).

[[tool:rich-pin-validator]]

## Where it pays off

Every share of every page, forever, by anyone. Which makes this one of the highest-leverage
half-hours available on a website: set the tags in the template, check one page, and every
link anyone posts renders correctly from then on.

:::faq
Q: What are Open Graph tags?
A: Metadata in a page's head that tells platforms what title, description and image to
show when the URL is shared.
Q: Which Open Graph tags are required?
A: og:title, og:description, og:image, og:url and og:type - plus twitter:card to get the
large card on X.
Q: What size should an og:image be?
A: 1200×630, at an absolute publicly reachable URL, as JPEG or PNG.
Q: Why do my Open Graph tags not work?
A: Most often a relative image URL, tags added by JavaScript, conflicting duplicates, or a
platform serving a cached earlier scrape.
:::

See what every platform reads from your page:
[link preview debugger](/tools/link-preview-debugger).
