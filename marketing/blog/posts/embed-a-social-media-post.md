---
{
 "id": "LK-05",
 "slug": "embed-a-social-media-post",
 "title": "How to Embed a Social Media Post on Your Website",
 "excerpt": "Every platform publishes embed code and most hide it. Here is where each one keeps it, when to use an iframe instead of a script, and what to do about consent.",
 "category": "seo",
 "categories": [],
 "tags": ["embeds", "short-links", "how-to"],
 "primary_keyword": "embed social media post",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "How to Embed a Social Media Post on Your Site",
  "description": "Embed a social media post from X, Instagram, TikTok, LinkedIn or Reddit: where each platform hides the code, and when an iframe beats a script.",
  "focus_keyword": "embed social media post",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Embed a post from any platform, without the script tag",
  "og_description": "Where each platform hides its embed code, and the iframe version most people never find.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
To embed a social media post, you paste a small block of HTML from the platform into
your page. Every major network publishes one — X, Instagram, TikTok, Facebook,
LinkedIn, Pinterest, Reddit, Threads, YouTube, Vimeo — and almost none of them put it
somewhere you can find without knowing where to look.

[[tool:social-media-embed-code-generator]]

## Where each platform hides its social media post embed code

| Platform | Where it lives |
| --- | --- |
| X | The post's "…" menu, visible only when signed in |
| Instagram | Removed from the web UI; the permalink form still works |
| TikTok | Three taps into the share sheet |
| Facebook | The post menu, or the Embed Post plugin page |
| LinkedIn | No button at all — the embed path takes an activity URN |
| Pinterest | The Pin's "…" menu, on desktop only |
| Reddit | The share menu, under "Embed" |
| YouTube | Share → Embed |

LinkedIn is the awkward one. Its embed URL is
`linkedin.com/embed/feed/update/urn:li:activity:<id>`, and the ID is buried in the
post's own URL after `activity-`. Nothing in the interface tells you this.

## Script or iframe

Most platforms publish two forms, and they fail in different places.

**Blockquote plus script** is what each platform documents. It renders the real post —
avatar, media, live like counts — and loads third-party JavaScript onto your page to do
it. It is the version that looks right and the version that costs you the most.

**Iframe**, where the platform publishes one, renders the same post in a sandbox with
no script on your page. Instagram, TikTok, Facebook, LinkedIn, YouTube and Vimeo all
have one. Reach for it when:

- your CMS strips `<script>` tags on save, which many rich-text editors do;
- the page is AMP, which forbids arbitrary script outright;
- a privacy review has to sign the page off.

One rule that catches almost everyone: **include each platform's script once per page,
not once per embed**. Every blockquote on the page is picked up by a single copy of
`widgets.js`. Ten copies of the script tag is ten downloads of the same file.

## The five-minute version

If you want the outcome rather than the reasoning:

1. Open the post in a browser and copy its URL from the address bar, not from the share
   sheet — the share sheet adds tracking parameters that will be baked into every page
   view of your article forever.
2. Get the embed code, either from the platform's own menu or from a generator.
3. Paste it into a **raw HTML** block, not a rich-text one.
4. Move the `<script>` tag to the bottom of the page and delete the duplicates.
5. Add a plain link to the post immediately after the embed.
6. Load the page with JavaScript disabled and check that step five is visible.

Step six is the one people skip, and it is the one that catches the failure everybody
else finds out about from a reader.

## Keep the plain link underneath

An embed is not a guaranteed render. It fails when the visitor blocks third-party
script, when the post is deleted, when the account goes private, and — for Meta's
embeds in particular — silently, leaving an empty box where your quotation was.

So write the embed as an enhancement over a link, not as a replacement for one. A
linked quotation degrades into a sentence and a link, which still reads. An empty
`<div>` degrades into nothing, and the reader cannot tell whether they missed something
or you did.

:::warning
Every embed loads content from the platform, which sets cookies and sees your visitor's
IP address before they have interacted with anything. In the EU and UK that generally
needs consent first, so on a site with a consent banner, load embeds behind a
click-to-load placeholder rather than on page load. The
[ICO's guidance on cookies](https://ico.org.uk/for-organisations/direct-marketing-and-privacy-and-electronic-communications/guide-to-pecr/cookies-and-similar-technologies/)
covers the reasoning.
:::

## What an embed costs the page

A single X or Instagram embed pulls several hundred kilobytes of JavaScript and makes
its own network requests, none of which you control. Three of them on one article is a
measurable hit to Largest Contentful Paint.

Click-to-load solves the performance problem and the consent problem at the same time:
you render a static placeholder — an image, the quoted text, a play button — and only
insert the real embed when the reader taps it. Most readers never do, which is exactly
the point.

For YouTube specifically, use the
[YouTube embed code generator](/tools/youtube-embed-code-generator): it has the full
parameter set, defaults to the privacy-enhanced `youtube-nocookie.com` domain, and
knows which parameters still do anything. Instagram has its own quirks, covered in
[how to get an Instagram embed code](/blog/instagram-embed-code).

If the post you want to embed is behind a shortened link, expand it first — the embed
generators need the canonical post URL, not a redirect. [How to expand a short
URL](/blog/expand-a-short-url) covers that.

:::faq
Q: Why does my X embed render as plain text?
A: Almost always the anchor. X's widget reads the URL from the `<a>` inside the blockquote, not from the blockquote itself, and many editors tidy an empty anchor out of existence on save. Paste the code into a raw HTML block rather than a rich-text one.
Q: Can I embed a private or deleted post?
A: No. Embeds only render public posts, and if a post is later deleted or the account goes private, the embed becomes an empty box on your page. This is why the plain-link fallback matters.
Q: Do embedded posts help my SEO?
A: Not directly — the content is rendered by the platform, not by your page, so it is not indexed as yours. What helps is the surrounding commentary, which is your content.
Q: Does embedding a post count as attribution?
A: It is the strongest form of attribution available, because the post stays under the author's control and disappears if they remove it. That is a better position than a screenshot, legally and ethically.
:::

Paste a post URL into the
[social media embed code generator](/tools/social-media-embed-code-generator) and it
will give you both forms plus the fallback link, or read
[social media links](/blog/social-media-links) for what happens to the URL itself.
