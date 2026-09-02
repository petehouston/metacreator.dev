---
{
 "id": "YM-07",
 "slug": "advertiser-friendly-guidelines",
 "title": "Advertiser-Friendly Guidelines: What Actually Limits Ads",
 "excerpt": "YouTube publishes the categories that limit ads. The opening of a video is weighted hardest, context decides everything, and no word list can see context.",
 "category": "monetization",
 "categories": [],
 "tags": ["youtube", "monetization", "explainer"],
 "primary_keyword": "advertiser friendly guidelines",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "Advertiser-Friendly Guidelines: What Actually Limits Ads on YouTube",
  "description": "The advertiser-friendly guidelines in plain terms: the published categories, why the opening matters most, and what a text checker can and cannot tell you.",
  "focus_keyword": "advertiser friendly guidelines",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "The yellow icon, explained by the published rules",
  "og_description": "The categories are public. What is not public is the context call, and that is the whole game.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
YouTube's advertiser-friendly guidelines are a published list of content categories that
can limit or remove ads on a video. The list is public, the opening of a video carries
more weight than the rest of it, and the part nobody can automate is the one that
decides most cases: context.

[[tool:youtube-advertiser-friendly-checker]]

## The advertiser-friendly guidelines are published

This is the first thing worth correcting, because the subject is surrounded by folklore.
YouTube publishes the list in its
[advertiser-friendly content guidelines](https://support.google.com/youtube/answer/6162278),
and the categories are broadly:

- Inappropriate language
- Violence
- Adult content
- Shocking content
- Harmful or dangerous acts
- Hateful and derogatory content
- Recreational drugs and drug-related content
- Firearms-related content
- Controversial issues and sensitive events
- Tobacco-related content
- Adult themes in family content

There is no hidden list of banned words. There is a set of subjects, and a judgement
about how your video treats each one.

## Why the opening is weighted

The guidelines call out **placement**, not just presence. Strong profanity in the first
several seconds, or used repeatedly throughout, is treated differently from the same word
once at 14:20.

There is a practical reason to care beyond the rule itself: the opening is the cheapest
part of a script to rewrite. Moving a word out of the first thirty seconds costs you
almost nothing, and it is one of the very few edits in this whole subject that is both
easy and effective.

The title is weighted hardest of all, and for the obvious reason — it is read on every
surface the video appears on, by systems and by people, before anyone watches anything.

## Context decides, and that is unautomatable

A documentary about addiction and a video glorifying it use the same vocabulary. A news
report on an attack and a video celebrating one use the same nouns. YouTube's classifier
watches the video: the audio, the frames, the thumbnail, the title, and the context
around every word.

Which puts a hard ceiling on what any checker can do. The
[advertiser-friendly script checker](/tools/youtube-advertiser-friendly-checker) reads
text and text alone. It finds the terms that put a video into a published category and
tells you which category and where — weighting the title and the opening hardest — and
it says outright that a clean score is not a promise of a green icon.

Two categories are deliberately left out of its word matching entirely: **hateful
content** and **controversial issues**. Both are decided by meaning rather than
vocabulary, and a trigger-word list for either would flag every news channel on the
platform while missing the videos that actually get demonetized. They appear as prompts
to review by hand, which is the honest thing a text tool can offer.

## What to do when a term is load-bearing

Keep it.

If your video is about addiction, you will say the names of drugs. If it is about
firearms law, you will say the word. Rewriting around a subject you are covering
produces worse content and does not change the classification, because the
classification is about the subject rather than about the spelling.

What to do instead:

- **Self-certify honestly.** The certification system exists so that a video with a
  genuine reason for its content is reviewed rather than assumed. Certifying
  optimistically and being corrected is what damages a channel's standing.
- **Front-load carefully.** Keep the flagged term out of the title and the first thirty
  seconds where you reasonably can.
- **Expect limited ads on some subjects** and price that in. A video that will carry the
  yellow icon whatever you do is a video to fund another way — a sponsor, a membership
  tier — rather than to soften into nothing.

## Limited ads is not a strike

Worth separating, because the two get conflated constantly and the consequences are
nothing alike.

**Limited or no ads** — the yellow icon — is a monetization state. It says advertisers
are unlikely to want to run against this video. It affects that video's revenue and
nothing else. It is appealable, and human review overturns a meaningful share of them.

**A Community Guidelines strike** is an enforcement action against the channel. It is a
different system, a different rulebook and a different set of consequences.

A video can be perfectly within Community Guidelines and carry the yellow icon
permanently, and that is a normal outcome rather than a punishment. News, true crime,
health and history channels live in that state and build their businesses on
memberships, sponsors and audience funding instead.

The practical read: if you get the icon, check whether the video is worth appealing, and
if it is not, do not soften the video. Fund it differently.

## Check the script, not the upload

The order matters more than people expect. A script is a text file: moving a word costs
nothing. A finished edit is an afternoon of re-recording, and a published video that
picks up the icon is a decision you now have to appeal rather than avoid.

So the useful moment to run a check is before you record. Read the flagged terms, move
the two or three sitting in the opening for no reason, leave the ones your subject
genuinely needs, and record. That is the whole workflow, and it takes a couple of
minutes against a cost measured in the hundreds of dollars on a video that does well.

## Where this sits in your revenue

Limited ads lower the share of your views that carry a full ad load, which is the first
term of the identity in [CPM vs RPM](/blog/cpm-vs-rpm) — and that term costs most
channels more than YouTube's revenue split does.

It is worth checking a script before recording rather than a video after uploading,
because a script is cheap to change and a finished edit is not. Run it, read the
flagged terms, and move the two or three that are in the opening for no reason. Then
plan the rest of the video's monetization: [ad breaks](/blog/youtube-ad-breaks) covers
where the mid-rolls go once the video is eligible for them.

:::faq
Q: What are YouTube's advertiser-friendly guidelines?
A: A published list of content categories — language, violence, adult content, drugs, firearms and others — that can limit or remove ads on a video depending on how the video treats them.
Q: Is there a list of demonetized words?
A: No. There are categories and there is context. A word list is a useful prompt for a self-review and is not what the classifier uses.
Q: Does swearing demonetize a video?
A: Strong profanity in the first several seconds or repeated throughout is one of the most common causes of limited ads. The same word once, later, is treated differently.
Q: Can a checker tell me if my video will be monetized?
A: No, and any tool claiming to is overstating. A text checker reads words; YouTube watches the video. It can tell you which words are worth a second look.
:::

Check a script before you record it with the
[advertiser-friendly script checker](/tools/youtube-advertiser-friendly-checker), and
tighten the title while you are there with the
[headline analyzer](/tools/headline-analyzer).
