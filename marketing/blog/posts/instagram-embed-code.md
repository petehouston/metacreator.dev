---
{
 "id": "LK-06",
 "slug": "instagram-embed-code",
 "title": "How to Get an Instagram Embed Code",
 "excerpt": "Instagram removed the embed button from the web interface, but the embed itself still works. Here is how to build the code from a post URL, in both forms.",
 "category": "seo",
 "categories": [],
 "tags": ["instagram", "embeds", "how-to"],
 "primary_keyword": "instagram embed code",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "How to Get an Instagram Embed Code From a Post URL",
  "description": "Build an Instagram embed code from any public post URL — blockquote or iframe, with or without the caption — now the web embed button is gone.",
  "focus_keyword": "instagram embed code",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "The Instagram embed button is gone. The embed is not.",
  "og_description": "Build the code from the post URL: blockquote, iframe, captioned or not.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
An Instagram embed code is a `blockquote` carrying the post's permalink plus one script
tag, or an iframe pointing at the post's `/embed/` path. The embed still works; the
button that used to generate it is no longer in Instagram's web interface, so the code
has to be built from the post URL.

[[tool:social-media-embed-code-generator]]

## What the Instagram embed code looks like

The scripted form is the one Instagram documents:

```html
<blockquote class="instagram-media"
  data-instgrm-permalink="https://www.instagram.com/p/SHORTCODE/"
  data-instgrm-version="14"></blockquote>
<script async src="https://www.instagram.com/embed.js"></script>
```

`SHORTCODE` is the segment after `/p/` in the post URL. Reels use `/reel/` in the
permalink and everything else is identical.

The iframe form is simpler and loads no script onto your page:

```html
<iframe src="https://www.instagram.com/p/SHORTCODE/embed/"
  width="540" height="760" frameborder="0" scrolling="no"
  loading="lazy" title="Instagram post"></iframe>
```

Both render the real post. The iframe is the one to use if your CMS strips script tags,
if the page is AMP, or if a privacy review has to approve it.

## Getting the shortcode out of a messy URL

A link copied from the app arrives looking like this:

```text
https://www.instagram.com/p/Cxyz1234567/?igshid=MzRlODBiNWFlZA%3D%3D
```

Everything from the `?` onwards is a per-share tracking identifier. Leave it in an
embed and it is baked into every page view of your article, telling Instagram who
forwarded the link to you. Take `Cxyz1234567` and discard the rest —
[social media links](/blog/social-media-links) covers what each platform appends and
why. If the link you were given is a `pin.it`-style redirect rather than a direct URL,
[expand it first](/blog/expand-a-short-url).

## Sizing the embed so it does not break your layout

Instagram's embed has no fixed height. The scripted version measures its own content
and resizes the container after the script runs, which means the block gets taller a
moment after the page paints — a layout shift, and one that Core Web Vitals counts
against you if it happens below an element the reader is already looking at.

Two ways to avoid it. Give the blockquote a `max-width` and let it size itself while it
sits at the bottom of an article, where a shift costs nothing. Or use the iframe, which
takes an explicit height and therefore never moves: 760 pixels at 540 wide fits a
square post with its caption, and a Reel wants closer to 1.4 times its width.

Either way, set `max-width: 100%` on the container. Instagram's own stylesheet assumes
a desktop column, and a 540-pixel embed inside a 390-pixel phone viewport is a
horizontal scrollbar on your article.

## Turning the caption off

`data-instgrm-captioned="false"` renders the media without the caption and comment
thread. It is worth knowing about for two reasons: the captioned version is
substantially taller, and on a long caption the embed can dominate an article it was
meant to illustrate.

Instagram's own documentation for the embed, including the parameters it honours, is in
the [oEmbed reference](https://developers.facebook.com/docs/instagram-platform/oembed).

## When an Instagram embed shows an empty box

This is the failure mode, and it is silent.

Meta's embeds render **public posts only**. If the account is private, or was public
when you embedded it and has since been switched, or the post has been deleted, the
embed renders nothing at all. No error, no placeholder — an empty rectangle where your
quotation used to be, which your readers will assume is your bug.

The fix is not technical. Put a plain link to the post immediately after the embed, so
that a failed render degrades into a sentence and a link rather than into a hole:

```html
<a href="https://www.instagram.com/p/SHORTCODE/" rel="noopener">
  View this post on Instagram
</a>
```

The same reasoning applies to every platform's embed, and it is covered more fully in
[how to embed a social media post](/blog/embed-a-social-media-post).

:::warning
An Instagram embed loads scripts and cookies from Meta before your visitor interacts
with anything. On a site with a consent banner, that generally has to sit behind a
click-to-load placeholder rather than firing on page load.
:::

:::faq
Q: Do I need permission to embed someone's Instagram post?
A: Embedding keeps the post under the author's control and disappears if they remove it, which is a stronger position than a screenshot — but courts have not treated embedding as automatically safe. For a commercial use, ask.
Q: Why did Instagram remove the embed button?
A: Meta has not published a reason. The embed endpoints and the oEmbed API remain documented and functional, which is why building the code by hand still works.
Q: Can I embed a Story?
A: No. Stories are ephemeral and have no permalink to embed. Reels and feed posts both work.
Q: Does the embed count as a view or an impression for the creator?
A: Instagram counts impressions on embedded posts, but they are not broken out separately in the creator's own insights.
:::

Paste any Instagram post URL into the
[social media embed code generator](/tools/social-media-embed-code-generator) and it
strips the tracking parameters and gives you all three forms at once.
