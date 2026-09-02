---
{
 "id": "DL-07",
 "slug": "instagram-image-link-expired",
 "title": "Why Your Saved Instagram Image Link Expired",
 "excerpt": "The picture rendered fine when you pasted it and is a broken icon now. The expiry was in the URL the whole time — here is how to read it.",
 "category": "design",
 "categories": [],
 "tags": ["instagram", "downloads", "troubleshooting"],
 "primary_keyword": "instagram image link expired",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "Why Your Instagram Image Link Expired (and the Fix)",
  "description": "An Instagram image link expired and now returns 403. Here is what the oe parameter means, why no size rewrite works, and what to do instead of hotlinking.",
  "focus_keyword": "instagram image link expired",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "The expiry was in the URL the whole time",
  "og_description": "Why Meta's image links go 403, and why re-copying it will not help for long.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
Your Instagram image link expired because Meta signs every image URL it serves and
stamps an expiry into the address itself. The signature stops matching, the CDN answers
`403 Forbidden`, and the picture in your document turns into a broken icon — usually
within hours or days of pasting it.

[[tool:instagram-image-downloader]]

## Why an Instagram image link expires, in one parameter

A Meta image address is not a plain path to a file. It carries a set of parameters, and
two of them are the interesting ones:

| Parameter | What it is |
| --- | --- |
| `oh` | The signature — a hash over the path and the expiry |
| `oe` | The expiry, as a hexadecimal timestamp |

`oe` is a moment in time written in base 16. Nothing about it is hidden or obfuscated:
it is a public, checkable statement that this address stops working then. That is why
our Meta-platform tools show a **link life** column — the number was always there, and
showing it changes what you do next.

The same applies on `fbcdn.net`, which serves Facebook and Threads images, because all
three platforms are one company's infrastructure.

## Why re-copying the link only postpones it

Copying a fresh address gets you a fresh expiry, not a permanent one. Every read hands
back a link with a new countdown on it, and every one of those countdowns runs out.

If the picture needs to still be there next month, the address is the wrong thing to
keep. There is no version of hotlinking a signed CDN URL that ends well.

## No size rewrite will work either

There is long-standing advice about editing the size segment of an Instagram image URL —
substituting `s1080x1080` or similar to ask for a larger copy. It does not work, and it
has not for a long time.

The signature covers the whole path, size segment included. Change one character and the
hash no longer matches, so the CDN rejects the request immediately. What you get back is
not a bigger picture; it is a 403 that arrives faster than the expired one.

:::warning
Any tool or tutorial promising a "full resolution" Instagram image via a URL edit is
describing something that stopped working. The largest copy available is the one the
post publishes.
:::

## What to do instead

**Download the file and host it yourself.** In a CMS, upload it to the media library. In
a document or a deck, insert the picture rather than the address. In a repository, commit
it. The file does not expire; only the link does.

That is the entire fix, and it is why every downloader here says *save the file* rather
than *copy the link*:

- [Instagram image downloader](/tools/instagram-image-downloader)
- [Facebook image downloader](/tools/facebook-image-downloader)
- [Threads image downloader](/tools/threads-image-downloader)

**Or embed the post instead of the picture.** If what you want is to show the post, the
official embed renders the live post with the author's name and a working link, and it
keeps working because Meta maintains it. The
[social media embed code generator](/tools/social-media-embed-code-generator) writes the
markup.

## This is not a bug, and it is not aimed at you

Signed, expiring URLs are ordinary CDN practice. They let a platform revoke access to a
file when a post is deleted or an account goes private — which is a promise the platform
made to the person who posted, and one that would be worthless if every image URL it
ever issued kept working forever.

The design has a cost, and the cost lands on anybody who treated the address as
permanent. Knowing that up front is the whole of the fix. Meta describes its terms in the
[Instagram terms of use](https://help.instagram.com/581066165581870).

:::faq
Q: How long does an Instagram image link last?
A: It varies, and the address tells you: the `oe` parameter is the expiry. Our downloaders decode it and show the remaining life.
Q: Why does the picture still work in my browser but not in the doc?
A: Your browser cached it. The cached copy will go too, and anyone else opening the document never had one.
Q: Can I make a permanent Instagram image link?
A: No. Download the file and host it, or embed the post.
Q: Does the same thing happen with Facebook and Threads?
A: Yes — same infrastructure, same signed URLs, same expiry parameter.
:::

If you have the post link, paste it into the
[Instagram image downloader](/tools/instagram-image-downloader) and save the file this
time. For the wider picture, see the
[guide to downloading social media images](/blog/download-social-media-images).
