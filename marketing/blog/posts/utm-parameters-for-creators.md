---
{
 "id": "ID-02",
 "slug": "utm-parameters-for-creators",
 "title": "UTM Parameters for Creators, Without the Mess",
 "excerpt": "UTM parameters tell your analytics where a visitor came from. Here is a naming scheme that stays readable after a year and the mistakes that make reports useless.",
 "category": "analytics",
 "categories": [],
 "tags": ["utm-tracking", "how-to"],
 "primary_keyword": "utm parameters",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "UTM Parameters for Creators, Without the Mess",
  "description": "What each UTM parameter is for, a naming scheme that stays readable after a year, and the mistakes that quietly ruin an analytics report.",
  "focus_keyword": "utm parameters",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "UTM parameters that still make sense in a year",
  "og_description": "Five parameters, one convention, and the errors that fragment a report.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
UTM parameters are labels appended to a URL that tell your analytics where a visitor came
from. They cost nothing, they work everywhere, and the only hard part is naming them
consistently enough that the report still makes sense a year later.

## The five UTM parameters

| Parameter | Answers | Example |
| --- | --- | --- |
| `utm_source` | Which platform | `instagram`, `youtube`, `newsletter` |
| `utm_medium` | Which placement | `bio`, `story`, `description`, `email` |
| `utm_campaign` | Which specific thing | `espresso-guide` |
| `utm_content` | Which variant | `version-a` |
| `utm_term` | Paid keywords | Rarely relevant to creators |

```text
https://example.com/guide?utm_source=instagram&utm_medium=bio&utm_campaign=espresso-guide
```

[[tool:utm-link-builder]]

The builder assembles the URL and keeps the scheme consistent, which is the part humans are
bad at.

## The naming convention

Decide these once and never deviate:

- **Always lowercase.** `Instagram` and `instagram` are two separate rows in every report.
- **Hyphens, never spaces or underscores.** Spaces get encoded into `%20` and become
  unreadable.
- **One word per concept.** `youtube`, not `yt` in some links and `youtube` in others.
- **Campaign names that describe the content**, not the date. `espresso-guide` is findable;
  `march-push` is not.

Write the convention down somewhere you will actually look. Three months in, the temptation
to type `IG` instead of `instagram` is what fragments a report beyond repair.

## What to tag, and what never to

**Tag** every link you publish anywhere someone else controls: bios, video descriptions,
Stories, newsletter links, QR codes, partner placements.

**Never tag internal links** between pages of your own site. It restarts session
attribution and makes your own analytics misreport where visitors came from.

**Never put personal data in a parameter.** URLs are visible, logged, shared and cached.

## Where creators actually use them

**Bio links**, to find out which platform sends people who do something —
[link-in-bio tracking](/blog/link-in-bio-tracking).

**Video descriptions**, tagged per video, which is how you find out that one older video
quietly drives most of your signups —
[a YouTube description template](/blog/youtube-description-template).

**QR codes**, where a scan is otherwise entirely anonymous —
[a QR code for a bio link that still scans](/blog/qr-code-generator-for-bio).

[[tool:qr-code-generator]]

## A scheme that survives a year

Here is a complete convention worth copying, because the value is entirely in never having
to make these decisions again.

**Source** is the platform, always one word, always the platform's plain name:
`instagram`, `youtube`, `tiktok`, `linkedin`, `newsletter`, `podcast`.

**Medium** is where on that platform: `bio`, `story`, `post`, `description`, `comment`,
`email`. Keep this list short - six or seven values covers everything most creators do, and
a long medium list is a sign you are putting campaign information in the wrong field.

**Campaign** is the piece of content, named after the content:
`espresso-guide`, `thumbnail-teardown`, `pricing-post`. Never a date, never a quarter,
never "launch". You will be reading this in twelve months and the question you will ask is
"which thing was that", not "which week was that".

**Content** is only for genuine A/B variants. If you are not comparing two versions, leave
it out - an empty parameter is cleaner than a placeholder.

The test of a scheme is whether a stranger could read a row of your report and say what it
refers to. If they can, it will still make sense to you next year.

## Reading the result

The report answers a question no platform's own analytics can: **which content sends people
who do something**. Not who clicked — who arrived and then signed up, bought, or read three
more pages.

That distinction is the whole argument for bothering, and it is the same one made in
[vanity metrics](/blog/vanity-metrics): a tracked link measures a result rather than an
impression.

[[tool:engagement-rate-calculator]]

Google documents how campaign parameters are collected and reported in its
[Analytics Help](https://support.google.com/analytics/answer/10917952), which is the
reference when a parameter is not showing up where you expect.

:::faq
Q: What are UTM parameters?
A: Labels appended to a URL - source, medium, campaign and two optional ones - that tell
your analytics where a visitor came from.
Q: Do UTM parameters affect SEO?
A: Not meaningfully for external links. Do not use them on internal links, where they break
session attribution.
Q: Are UTM parameters visible to visitors?
A: Yes, in the address bar. Never put anything private in them.
Q: What is the difference between source and medium?
A: Source is the platform - instagram, youtube. Medium is the placement within it - bio,
story, description.
:::

Build a consistently named link in seconds:
[UTM link builder](/tools/utm-link-builder).
