---
{
 "id": "PI-03",
 "slug": "rich-pins-not-working",
 "title": "Rich Pins Not Working: What Is Missing From Markup",
 "excerpt": "Rich Pins fail for a short list of reasons, nearly all of them markup on your own page. Here is the order to check them in.",
 "category": "seo",
 "categories": [],
 "tags": ["pinterest", "pinterest-seo", "troubleshooting"],
 "primary_keyword": "rich pins not working",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "Rich Pins Not Working: Check the Markup First",
  "description": "Why Rich Pins are not working: missing or invalid markup, an unvalidated domain, caching, or the wrong Pin type. The causes in order, with the checks.",
  "focus_keyword": "rich pins not working",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Rich Pins not working? Check the markup",
  "og_description": "Five causes, in the order they usually turn out to be the answer.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
Rich Pins not working is almost always a markup problem on your own page rather than
something wrong at Pinterest's end. A Rich Pin pulls structured data from the destination
URL, so if the data is absent, invalid or unreachable, the Pin falls back to an ordinary
one.

[[tool:rich-pin-validator]]

The validator checks the markup a Rich Pin needs on any URL and reports which pieces are
missing, which is usually the whole diagnosis.

## 1. Rich Pins not working usually means missing markup

Rich Pins read structured data from the page — Open Graph properties and, for products
and recipes, schema.org markup. A page with none of it cannot produce a Rich Pin.

The minimum for an article Pin:

```html
<meta property="og:type" content="article">
<meta property="og:title" content="The article title">
<meta property="og:description" content="One sentence.">
<meta property="og:url" content="https://example.com/page">
```

Products and recipes need more, and the requirements are documented by
[Pinterest](https://help.pinterest.com/en/business/article/rich-pins).

## 2. The markup is present but invalid

A typo in a property name, a `content` attribute left empty, or two conflicting
`og:type` declarations all produce the same result as no markup at all. The common
version is a plugin adding one set of tags while a theme adds another.

[[tool:link-preview-debugger]]

The preview debugger shows exactly what a crawler reads from the page, which settles the
question of whether the tags you think are there actually are.

## 3. The domain was never validated

Rich Pins require applying once per domain. Until that application is approved, correct
markup produces nothing. This is the single most common cause among people who "set
everything up" and saw no change — the markup was right and the domain was not enrolled.

## 4. Pinterest cached an older version

Pinterest caches what it reads. A Pin created before the markup was fixed keeps the old
data, and the fix appears on new Pins first.

## 5. The crawler cannot reach the page

Anything that blocks bots blocks Pinterest's: a `robots.txt` rule, bot protection, a
login wall, or markup injected by JavaScript that the crawler does not execute. Structured
data belongs in the server-rendered HTML.

This is the same failure that produces
[a link preview not showing](/blog/link-preview-not-showing) on other platforms, and the
underlying tags are the same ones — [Open Graph tags](/blog/open-graph-tags).

## What a working Rich Pin actually changes

Worth knowing before you spend an afternoon on this, because the benefit is real and
modest.

A Rich Pin pulls live detail from your page onto the Pin: for an article, the headline and
author; for a product, price and availability; for a recipe, ingredients and cooking time.
That information updates when your page updates, which is the genuinely useful part - a
price change propagates without re-pinning anything.

What it does not do is rank the Pin higher. Ranking comes from the title, the description,
the board and whether people save the Pin. A Rich Pin makes a Pin more informative and
slightly more clickable; it does not rescue one nobody sees.

So the honest priority order is: get the image and the title right first, get the
description right second, and set up Rich Pins once - after which they work for every Pin
to that domain forever, which is what makes the one-off effort worth it.

## While you are in there

Rich Pins improve a Pin; they do not rank it. The fields that decide whether a Pin
surfaces at all are the title, description, board and destination:

[[tool:pinterest-pin-seo-checker]]

See [Pinterest SEO](/blog/pinterest-seo) and
[writing a Pin description Pinterest can read](/blog/pinterest-pin-description). And the
image still has to work at feed-tile size —
[Pinterest Pin size](/blog/pinterest-pin-size).

[[tool:pin-image-sizer]]

:::faq
Q: Why are my Rich Pins not working?
A: Usually missing or invalid markup on the destination page, or a domain that was never
validated. Check the markup first, then the enrolment.
Q: Do I need to apply for Rich Pins?
A: Yes, once per domain. Correct markup on an unenrolled domain produces nothing.
Q: How long does it take for Rich Pins to update?
A: New Pins pick up corrected markup quickly; existing Pins can keep cached data for
some time.
Q: Do Rich Pins improve Pinterest ranking?
A: They make a Pin more informative rather than higher-ranked. Titles, descriptions and
boards are what drive ranking.
:::

Check what a Rich Pin needs on your own URL with the
[Rich Pin validator](/tools/rich-pin-validator).
