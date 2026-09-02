---
{
 "id": "LK-03",
 "slug": "social-media-short-links",
 "title": "Which Social Platforms Have Their Own Short Link Domain",
 "excerpt": "Seven platforms let you build a short link from a URL. Five only issue one from their own share sheet. Everything else calling itself a shortener is a redirector.",
 "category": "seo",
 "categories": [],
 "tags": ["short-links", "explainer"],
 "primary_keyword": "social media short links",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "Social Media Short Links: Which Platforms Have One",
  "description": "A straight answer on social media short links: which platform domains you can build from a URL, which are issued only by the app, and which are fakes.",
  "focus_keyword": "social media short links",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Which platforms actually have a short link domain",
  "og_description": "youtu.be, redd.it and instagr.am you can build. t.co, pin.it and vm.tiktok.com you cannot. Here is why.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
Social media short links come in two kinds, and the difference matters more than the
length. Seven platform domains can be **constructed from a URL you already have**. Five
more exist but are **minted by the platform's own share sheet**, with no public way to
build one. Everything else advertising itself as a shortener for those five is a
third-party redirector.

## Social media short links you can build yourself

These are deterministic: the short form is derivable from the long one, so no request
to the platform is needed and no service sits in the middle.

| Platform | Domain | Rule |
| --- | --- | --- |
| YouTube | `youtu.be` | `youtu.be/` + the eleven-character video ID |
| Instagram | `instagr.am` | Same path, four fewer characters of domain |
| Reddit | `redd.it` | `redd.it/` + the post ID from `/comments/` |
| Dailymotion | `dai.ly` | `dai.ly/` + the video ID |
| Flickr | `flic.kr` | The photo ID encoded in base58 |
| Telegram | `t.me` | Already the canonical domain |
| WhatsApp | `wa.me` | `wa.me/` + a phone number in international format |

Two of these deserve a footnote. **`instagr.am`** is Instagram's own legacy domain and
still redirects, but it is not what the app's share sheet produces — use it where
character count matters and the full `instagram.com` link where the domain being
instantly recognisable matters more. **`flic.kr`** uses a base58 alphabet with no `0`,
`O`, `I` or `l`, which is the point of it: those are the characters people mistype when
a link is read aloud.

[[tool:social-media-link-shortener]]

## The ones only the platform can issue

| Platform | Domain | How you get one |
| --- | --- | --- |
| X | `t.co` | Applied automatically to every link when the post publishes |
| LinkedIn | `lnkd.in` | The share button and the mobile app |
| Pinterest | `pin.it` | Open the Pin, tap Share, Copy Link |
| Facebook | `fb.me`, `fb.watch` | Generated when you share from the app |
| TikTok | `vm.tiktok.com` | Tap Share, then Copy Link |

None of these can be constructed. `t.co` is the clearest case: X wraps links at
**publish** time, so a `t.co` address does not exist until the post does. There is
nothing to generate for a link you have not posted yet, and X documents the wrapping
behaviour in its own
[developer documentation](https://developer.x.com/en/docs/x-api/v1/data-dictionary/object-model/entities).

This is why "TikTok link shortener" results are worth treating with suspicion. A site
promising one is putting **its own** domain in front of the TikTok URL and calling the
result a short link. It works, in the sense that clicking it lands on TikTok. It also
means your link now depends on that service continuing to exist, continuing to be free,
and continuing not to interstitial your audience with an ad.

## Threads, Bluesky, Twitch and Mastodon

None of these has a short domain at all, which is a reasonable design decision rather
than an oversight — their post URLs are already short. A Threads post URL is a handle
and a post ID; there is nothing meaningful left to remove.

The practical consequence is the same as for the second table: if a shortener is
offering you one, it is theirs, not the platform's.

## What to do instead when a platform has no short link

Three options, in the order they are usually right:

1. **Post the canonical URL.** On most of these platforms it is already under sixty
   characters, and a recognisable domain outperforms a shorter unrecognisable one on
   click-through.
2. **Use the app's share sheet** when you need the short form and the platform has one.
   It takes two taps and produces a link the platform will honour permanently.
3. **Use a branded shortener** — your own domain, not a public one — if you genuinely
   need click data across channels. That is a business decision with real trade-offs,
   and it is not the same thing as a first-party short link.

What ties all three together is that the tracking parameters the platform attached to
the link you copied should come off first. `igshid`, `si` and `share_id` are per-share
identifiers: forwarding a link that carries one tells the platform who forwarded it.
[Social media links](/blog/social-media-links) goes through what each platform appends
and why.

If you have been handed a short link and want to know where it goes before you trust
it, [expand it first](/blog/expand-a-short-url).

:::warning
A short link's destination can usually be edited after it has been shared — by whoever
owns the shortener. That is a feature for marketers and a problem for everyone else,
and it is the reason an expanded link is only true at the moment you expand it.
:::

:::faq
Q: Which social media short link is the safest to rely on?
A: A first-party one. It is served by the platform, cannot expire, and cannot be repointed at something else, because it identifies one object rather than mapping to a row in somebody's database.
Q: Can I make a t.co link myself?
A: No. X applies t.co wrapping at publish time, so the address does not exist until the post does. Any site offering to make one is offering you its own redirect.
Q: Is instagr.am safe?
A: It is Instagram's own legacy domain and it still resolves. It is less recognisable than instagram.com, so use it only where the character saving is worth that.
Q: Do short links affect reach or the algorithm?
A: There is no credible evidence that a first-party short link changes distribution. Third-party shorteners are a different question — several platforms have historically down-weighted links to domains associated with spam.
:::

Paste any social link into the
[social media link shortener](/tools/social-media-link-shortener): it builds the
first-party short form where one exists, cleans the URL where one does not, and tells
you which case you are in.
