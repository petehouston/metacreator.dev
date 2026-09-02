---
{
 "id": "IG-09",
 "slug": "link-in-bio-tracking",
 "title": "Link in Bio Tracking: Which Post Sent the Click",
 "excerpt": "Platform analytics stop at the platform. Tagged links tell you which post, which platform and which campaign actually sent someone to your site.",
 "category": "analytics",
 "categories": ["growth"],
 "tags": ["instagram", "utm-tracking", "how-to"],
 "primary_keyword": "link in bio tracking",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "Link in Bio Tracking: Which Post Sent the Click",
  "description": "How to set up link in bio tracking with UTM parameters so you know which post, platform and campaign sent each visitor - and what the platforms never tell you.",
  "focus_keyword": "link in bio tracking",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Knowing which post sent the click",
  "og_description": "Platform analytics stop at the platform. Tagged links do not.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
Link in bio tracking means tagging the URL in your profile so your own analytics can tell
you which platform, which post and which campaign sent each visitor. Without it, every
click arrives as anonymous referral traffic and the most valuable question you can ask —
*which of my content actually sends people anywhere* — has no answer.

## Why platform analytics are not enough

Instagram tells you how many people tapped your link. It does not tell you what they did
afterwards, and it certainly does not tell you which post inspired the tap. The moment
someone leaves the app, the platform stops caring — but that is exactly where the outcome
you care about happens.

Your own analytics can see all of it, provided the link says who it is.

[[tool:utm-link-builder]]

## The scheme

Five parameters, of which three matter for creators:

| Parameter | What to put | Example |
| --- | --- | --- |
| `utm_source` | The platform | `instagram` |
| `utm_medium` | The placement | `bio`, `story`, `post` |
| `utm_campaign` | The specific thing | `carousel-espresso-guide` |
| `utm_content` | A variant, if testing two | `version-a` |
| `utm_term` | Paid keywords; ignore it | — |

```text
https://example.com/guide?utm_source=instagram&utm_medium=bio&utm_campaign=espresso-guide
```

The discipline that makes this useful later is consistency: always lowercase, always
hyphens, and always the same word for the same thing. `Instagram`, `instagram` and `IG`
become three separate rows in a report, and untangling them a year later is genuinely
unpleasant. The naming conventions are in
[UTM parameters for creators](/blog/utm-parameters-for-creators).

## What to tag, and what not to

**Tag** the bio link, every link in a Story, links in video descriptions, and each link
on a link-in-bio landing page.

**Do not tag** internal links between pages of your own site — it breaks session
attribution and makes your own analytics lie to you.

**Never put personal data in a URL.** Parameters are visible, logged and shared.

## The link-in-bio page itself

If you use a landing page with several links, tag both halves: the link that gets people
to the page, and each link on the page. Otherwise you learn that Instagram sent 400
people and nothing about which of your six links they wanted.

For print, screens or anywhere a URL cannot be tapped, the same tagged link works behind
a QR code:

[[tool:qr-code-generator]]

See [a QR code for a bio link that still scans](/blog/qr-code-generator-for-bio).

## Reading the result

The report answers three questions that platform analytics cannot:

1. **Which platform sends traffic that does anything?** Frequently not the one with the
   most followers.
2. **Which post sent it?** This is the one that changes what you make next.
3. **What happened afterwards?** Signups, purchases, time on page — the outcomes.

That third question is why this is worth the effort. A tracked link is one of the few
creator metrics that measures a result rather than an impression — the argument made at
length in [vanity metrics](/blog/vanity-metrics).

Google documents how campaign parameters are collected in its
[Analytics Help](https://support.google.com/analytics/answer/10917952), and the bio field
itself is covered in [Instagram bio ideas](/blog/instagram-bio-ideas).

[[tool:instagram-bio-preview]]

:::faq
Q: How do I track clicks from my Instagram bio?
A: Add UTM parameters to the URL so your own analytics can attribute the visit to
Instagram, to the bio placement and to a specific campaign.
Q: Do UTM parameters work on Instagram?
A: Yes. They are read by your analytics when the visitor arrives, not by Instagram.
Q: Will the parameters be visible to visitors?
A: Yes, in the address bar. Never put anything private in them.
Q: Do I need a link-in-bio service?
A: Only if you need several links. The tracking works the same either way, and you should
tag both the link to the page and the links on it.
:::

Build a consistently tagged link in seconds with the
[UTM link builder](/tools/utm-link-builder).
