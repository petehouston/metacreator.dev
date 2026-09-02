---
{
 "id": "YT-13",
 "slug": "youtube-subscribe-link",
 "title": "YouTube Subscribe Link: One Click, One URL",
 "excerpt": "A YouTube subscribe link opens the subscribe confirmation instead of the channel page. Here is the format, where to use it, and the rule about not begging.",
 "category": "growth",
 "categories": [],
 "tags": ["youtube", "bio", "how-to"],
 "primary_keyword": "youtube subscribe link",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "YouTube Subscribe Link: One Click, One URL",
  "description": "Build a YouTube subscribe link with sub_confirmation=1 so a click opens the subscribe dialog. The format, where to put it, and the HTML snippet.",
  "focus_keyword": "youtube subscribe link",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "The one-click YouTube subscribe link",
  "og_description": "sub_confirmation=1, and where it belongs.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
A YouTube subscribe link is a channel URL with `?sub_confirmation=1` on the end. Click
it and YouTube opens the subscribe confirmation dialogue directly, instead of dropping
someone on your channel page to find the button themselves.

## The YouTube subscribe link format

```text
https://www.youtube.com/channel/UCxxxxxxxxxxxxxxxxxxxxxx?sub_confirmation=1
https://www.youtube.com/@yourhandle?sub_confirmation=1
```

Both forms work. The channel ID version is the more durable one, because a handle can
be changed and an old link then points nowhere useful.

[[tool:youtube-subscribe-link-generator]]

The generator takes a handle or channel URL, resolves it, and returns the link with
ready-made HTML and Markdown snippets.

If you need the underlying ID for anything else — feeds, API calls, analytics —
that is [how to find a YouTube channel ID](/blog/find-youtube-channel-id):

[[tool:youtube-channel-id-finder]]

## Where it belongs

| Placement | Worth it? |
| --- | --- |
| Your website's header or footer | Yes — the highest-intent placement you own |
| Email signature and newsletter | Yes |
| Video description | Yes, once, below the visible lines |
| Other social bios | Yes, if the audience overlaps |
| Every comment you leave | No. This is spam and reads as spam |
| Inside the video as a full-screen card | No — viewers cannot click a burned-in URL |

The description placement has a rule attached: it goes below the fold, not in the
first three lines. Those lines are the only ones a viewer reads before deciding to
stay, and spending them on a subscribe link is the most common description mistake
there is. Structure is in
[a YouTube description template that earns its space](/blog/youtube-description-template).

## What the link does not do

It does not subscribe anyone. It opens a dialogue that a person still has to confirm,
which is the correct behaviour — YouTube's
[Community Guidelines](https://support.google.com/youtube/answer/9482361) prohibit
engagement gained through deception or automation, and anything that subscribed people
silently would be exactly that.

It also does not improve reach on its own. A subscriber who confirms a dialogue and
never watches again is a number, not an audience; watch time is what YouTube actually
counts. The link removes friction for people who already decided — that is its whole
value, and it is real but modest.

## The tracking version

If you publish the link in several places and want to know which one works, tag it:

[[tool:utm-link-builder]]

YouTube itself does not report UTM parameters back to you, but your own site
analytics will show which page and which button sent the click before it left. The
naming discipline that makes this readable later is in
[UTM parameters for creators](/blog/utm-parameters-for-creators).

## Putting it on your own site properly

A subscribe link in a page is a link, and it should behave like one: real anchor text,
a visible destination, and no interception. Two small details make it better.

**Open it in a new tab** if the page it sits on is something the reader is in the
middle of. Sending someone to YouTube mid-article usually means losing them, and the
subscribe dialogue is a detour rather than a destination.

**Do not disguise it as a button that does something else.** A link labelled
"Subscribe" that opens YouTube's confirmation is honest. A link labelled "Continue"
that does the same thing is not, and it produces subscribers who immediately
unsubscribe - which is worse than no subscriber, because it teaches YouTube that your
channel does not hold the people who join it.

The same reasoning applies to the QR-code version for print or on-screen use: point it
at the subscribe link, label it clearly, and accept that the people who scan it are
the ones who were already interested.

## The honest version of "smash subscribe"

The link is a convenience, not a growth strategy. The things that actually move
subscriptions are the ones covered in the
[YouTube SEO guide](/blog/youtube-seo): being found, being clicked, and being worth
the next video. A frictionless link matters at the margin, on the day someone has
already decided.

:::faq
Q: How do I make a YouTube subscribe link?
A: Add `?sub_confirmation=1` to your channel URL. It works with both the /channel/UC…
form and the @handle form.
Q: Does the subscribe link work on mobile?
A: Yes. On a device with the YouTube app installed it typically opens the app and
shows the confirmation there.
Q: Can a link subscribe someone automatically?
A: No, and anything claiming to is a violation of YouTube's policies. The link only
opens the confirmation dialogue.
Q: Should the subscribe link go at the top of my description?
A: No. The first lines are the only ones most viewers see - use them to restate what
the video delivers, and put the link further down.
:::

Generate the link and its HTML snippet with the
[subscribe link generator](/tools/youtube-subscribe-link-generator).
