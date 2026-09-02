---
{
 "id": "LK-04",
 "slug": "expand-a-short-url",
 "title": "How to Expand a Short URL and See Where It Goes",
 "excerpt": "A shortened link tells you nothing about its destination. Here is how to expand a short URL safely, read the redirect chain, and spot the hop that breaks tracking.",
 "category": "seo",
 "categories": [],
 "tags": ["short-links", "troubleshooting"],
 "primary_keyword": "expand short url",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "How to Expand a Short URL Before You Click It",
  "description": "Expand a short URL to see its real destination: read every redirect hop, check the final domain, and find where your tracking parameters get dropped.",
  "focus_keyword": "expand short url",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "See where a short link goes before you click it",
  "og_description": "Every hop, every status code, and the point where your UTM parameters disappear.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
To expand a short URL, follow its redirects without loading the destination page. A link
expander requests the short link, reads the `Location` header the server sends back,
and repeats until nothing redirects any more — showing you the final URL and every hop
on the way to it, which is what a browser hides.

[[tool:link-expander]]

## Two reasons to expand a short URL

They look like the same task and they are not.

**Before you click.** A shortened link is opaque by design, which is exactly why
phishing uses them. Expanding one first is the cheapest safety check available: you see
the destination domain without ever loading its page, running its scripts or accepting
its cookies. A chain that starts on a shortener you recognise and ends on a domain you
do not is the shape worth stopping at.

**When your own link misbehaves.** A campaign URL that passes through a shortener, a
marketing redirect and a CMS canonical often arrives at the landing page with its
tracking parameters gone. Hop by hop is the only way to find out which redirect dropped
them, because the browser shows you the start and the end and nothing in between.

## Reading the chain

Each row is one request. The status code tells you what kind of redirect it was, and
the difference matters more than it looks.

| Status | Meaning | Passes ranking signals |
| --- | --- | --- |
| 301 | Moved permanently | Yes |
| 302 / 307 | Temporary | Treated as permanent if it persists |
| 303 | See other | Yes |
| 308 | Permanent, method preserved | Yes |
| 4xx / 5xx | The chain ends in an error | No — the link is broken |

Two things to look at besides the codes. First, the **query string across hops**: if
`?utm_campaign=` is present at step one and gone at step three, step three is where you
have work to do. Second, the **final domain**: a link that starts on `bit.ly` and ends
somewhere you have never heard of deserves a second look before you click or publish it.

Google's own guidance on which redirect to use where is in the
[Search Central redirects documentation](https://developers.google.com/search/docs/crawling-indexing/301-redirects).

## What a link expander cannot see

This is the limitation worth knowing, because it is the one that catches people out.

An expander follows the redirects a **server** declares — the `Location` header on a
3xx response. A page that redirects with JavaScript, or with a `<meta http-equiv=
"refresh">` tag, is indistinguishable from a final destination to any HTTP client. The
chain will appear to end there.

That is not a gap so much as a signal. A short link that resolves to a plain-looking
page which then bounces the visitor somewhere else in the browser is doing something it
does not need to do, and it is worth treating as a red flag rather than a curiosity.

Two smaller limits: some shorteners block automated requests outright, and a few only
redirect for browsers they recognise. In both cases the honest answer is that the
destination cannot be determined — which is more useful than a guess.

## Chains on links you control

Three or more hops is worth collapsing. Each one is a full round trip before anything
renders, and on a mobile connection that is visible latency on every single click.

The usual causes, in the order you will find them:

1. A shortener pointing at a redirect that was set up for a previous campaign.
2. `http` → `https`, then `example.com` → `www.example.com`, as two separate hops
   rather than one rule.
3. A trailing-slash or lowercase canonical redirect at the end.

Collapse the first two into a single rule and most chains drop to one hop. If the link
is going onto a social profile or into a bio, check what the card looks like afterwards
as well — the destination's Open Graph tags are what the feed reads, and
[the link preview debugger](/tools/link-preview-debugger) will draw it.

For where short links come from in the first place, and which platforms have their own,
see [which platforms have their own short link
domain](/blog/social-media-short-links).

:::faq
Q: Does expanding a link count as a click?
A: It registers as a request on the shortener, so a click counter will usually tick over. It does not load the destination page, run its scripts, or accept any cookies from it.
Q: Can I expand a link without any tool?
A: Yes, with curl: `curl -sIL https://short.example/x` prints the headers for each hop, including the Location of every redirect. A tool is faster because it also strips tracking parameters and flags a loop.
Q: The expander says the host is unreachable.
A: Some services block automated requests, and a few redirect only for recognised browsers. That is the shortener's choice rather than a fault in the chain, and it means the destination genuinely cannot be determined from outside.
Q: Is a long redirect chain always suspicious?
A: On a link somebody sent you, often. On a link you own, usually just accumulated configuration — an old campaign redirect plus an http-to-https rule plus a canonical fix.
:::

Paste the link into the [link expander](/tools/link-expander) and read the chain, or
start from [social media links](/blog/social-media-links) if you are deciding what to
post in the first place.
