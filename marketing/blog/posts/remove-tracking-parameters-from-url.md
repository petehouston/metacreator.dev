---
{
 "id": "LK-06",
 "slug": "remove-tracking-parameters-from-url",
 "title": "How to Remove Tracking Parameters From a Link",
 "excerpt": "Some of these identify a person, not a campaign. igshid names the account that shared, mc_eid names the subscriber — and YouTube's t must never be stripped.",
 "category": "growth",
 "categories": [],
 "tags": ["short-links", "utm-tracking", "how-to"],
 "primary_keyword": "remove tracking parameters from url",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "How to Remove Tracking Parameters From a URL (And Which to Keep)",
  "description": "Remove tracking parameters from a URL safely: what utm, fbclid, igshid and si each identify, which ones name a person, and the four you must never strip.",
  "focus_keyword": "remove tracking parameters from url",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Some of these identify a person, not a campaign",
  "og_description": "igshid names who shared it. mc_eid names the subscriber. Both travel with a forwarded link.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
To remove tracking parameters from a URL, delete everything after the `?` that the
destination does not need — `utm_*`, `fbclid`, `igshid`, `si` and the rest — while
keeping the handful that change what the link does. The reason to bother is not
tidiness: several of these identify a **person**, and they travel with the link when you
forward it.

[[tool:social-media-url-cleaner]]

## The tracking parameters in a URL that name somebody

Most tracking parameters describe a campaign. A few describe you.

**`igshid` and `igsh`** are Instagram share identifiers, minted when somebody taps Share.
Forward that link and it carries the fact that it came from your account.

**`mc_eid`** is a Mailchimp *subscriber* identifier. Paste a newsletter link into a group
chat with that attached and every click from every reader is recorded against your
subscription.

**`si`** does the same job on YouTube and Spotify shares: it names the session the link
was copied from.

**`share_id`, `sender_device`, `is_from_webapp`** are TikTok's share metadata, which
describe how and from what the link was produced.

None of these are sinister on their own, and all of them are attached without anybody
choosing to attach them. The point is simply that "clean the link" is a privacy action
before it is a cosmetic one, and it is the reason to do it to links you were *sent*
rather than only to links you are sending.

## The ones that are just campaign tags

`utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content` and `utm_id` are
the Google Analytics convention, and they describe the campaign rather than the reader.
Google documents the scheme in its
[campaign URL reference](https://support.google.com/analytics/answer/10917952).

Click identifiers — `fbclid`, `gclid`, `gbraid`, `wbraid`, `msclkid`, `twclid`, `ttclid`,
`rdt_cid` — are minted per click by an ad platform to tie the visit back to the ad that
produced it.

Removing either kind does not hide the visit from the destination site. It stops the
link naming which campaign, share or subscriber produced it, which is a different and
smaller claim than the one privacy tools sometimes make.

One consequence worth stating for marketers: strip your own `utm` tags and the visit
arrives in your analytics as direct traffic. Clean links you were sent; do not clean the
links you are publishing.

## The four you must never strip

This is where naive cleaners break things.

- **`t`** on YouTube is the timestamp the video starts at. Remove it and you have sent
  somebody to 0:00 of a two-hour stream. On `x.com` the same parameter name is a share
  token, which is exactly why a cleaner has to know which platform it is looking at.
- **`list`** is the playlist a video is being watched in.
- **`start` and `end`** set the range on an embed.
- **`v`** is the video itself. Anything that removes it has destroyed the link.

The [social media URL cleaner](/tools/social-media-url-cleaner) keeps those by default,
lists them in their own group with the reason, and warns you if you switch the behaviour
off. It also names every parameter it removed, so nothing disappears silently.

## Clean a link

1. Paste the link you were sent.
2. Copy the cleaned URL from the first row.
3. Read the **Removed** group if you want to know what was in there — each parameter has
   a line saying what it was for.
4. Read the **Kept** group to confirm nothing load-bearing was touched.

It works on any URL, not only a social one. Most of these parameters arrive from ads and
email, so a link from a newsletter is the most common thing worth running through it.

## Where the parameters come from in the first place

Knowing which button produced a parameter makes it much easier to recognise one you have
not seen before.

**The share sheet.** Every platform's Share button mints something: `igshid` on
Instagram, `si` on YouTube and Spotify, `share_id` and friends on TikTok, `s` and `t` on
X. If you copied a link out of an app rather than out of the address bar, it has one.

**An ad click.** `fbclid`, `gclid`, `msclkid`, `ttclid` and the rest are attached by the
ad platform at the moment of the click, so they appear on links you followed rather than
links you were handed.

**An email.** `mc_cid`, `mc_eid` and their equivalents in other senders are written into
every link in the campaign before it goes out.

**A marketer, deliberately.** The `utm_*` set is the only family somebody sat down and
typed on purpose, which is why it is the only one that is safe to assume means what it
says.

The practical shortcut: if you copied the link from an app or from an email, assume it
carries something and clean it before you forward it.

## What to do next with a clean link

**Shorten it**, if it is going somewhere character-limited — the
[social link shortener](/tools/social-media-link-shortener) derives a first-party short
form where one exists rather than routing you through a third party.

**Expand it first**, if it arrived already shortened and you want to see where it goes.
The [link expander](/tools/link-expander) follows the chain hop by hop and names the
destination, which is the sensible thing to do before clicking an unfamiliar short link
at all.

**Tag it deliberately**, if you are the one publishing. The
[UTM builder](/tools/utm-link-builder) writes a consistent scheme, which is what makes
campaign tags worth having in the first place.

And if the link is going somewhere you want opened in an app rather than a browser, that
is a different job with a different answer:
[social media deep links](/blog/social-media-deep-links).

:::faq
Q: What is igshid on an Instagram link?
A: A share identifier, generated when somebody taps Share. It identifies the account the link was copied from, so forwarding a link with it attached passes that along.
Q: Is it safe to remove utm parameters?
A: For the reader, yes — the link still goes exactly where it went. For a marketer, removing your own tags means the visit lands in analytics as direct traffic.
Q: Which parameters should I never remove?
A: Anything that changes what the link does: YouTube's v, t, list, start and end are the common ones. A cleaner that drops those has broken the link it was asked to fix.
Q: Does removing tracking stop the site knowing I visited?
A: No. It stops the link naming which campaign, share or subscriber produced the visit. The visit itself is still a visit.
:::

Clean a link and see what each parameter was for with the
[social media URL cleaner](/tools/social-media-url-cleaner), or follow an unfamiliar one
to its destination first with the [link expander](/tools/link-expander).
