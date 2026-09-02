---
{
 "id": "DL-02",
 "slug": "download-twitter-images",
 "title": "How to Download Images From X (Twitter) at Full Size",
 "excerpt": "X keeps one file per photo and serves it at six named sizes. The timeline shows you a middling one; the largest is a single word away in the URL.",
 "category": "design",
 "categories": [],
 "tags": ["x", "downloads", "how-to"],
 "primary_keyword": "download twitter images",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "How to Download Twitter (X) Images at Full Size",
  "description": "Download Twitter images at the size they were uploaded. The name=orig trick on pbs.twimg.com explained, plus the route that works when X will not answer.",
  "focus_keyword": "download twitter images",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Get the original photo, not the timeline copy",
  "og_description": "X serves six sizes of every photo. Only one of them is the upload.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
To download Twitter images at full size, take the photo's `pbs.twimg.com` address and
change `name=medium` to `name=orig`. X keeps one file per photo and serves it at six
named sizes; the timeline asks for a middling one, and `orig` is the file exactly as it
was uploaded.

[[tool:x-image-downloader]]

## The six sizes X serves

Every photo posted to X lives at one address on `pbs.twimg.com`, and the `name`
parameter picks which rendition comes back.

| `name` | Fits inside | Where X uses it |
| --- | --- | --- |
| `thumb` | 150 × 150 | The square crop in a multi-photo post |
| `small` | 680 px wide | Timelines and narrow layouts |
| `medium` | 1200 px wide | The desktop timeline — usually what a link card names |
| `large` | 2048 px wide | The photo viewer, when you click through |
| `4096x4096` | 4096 px wide | Nothing in the interface |
| `orig` | As uploaded | Nothing in the interface |

The last two are the interesting ones. Neither is reachable by clicking anything: they
exist because the CDN will serve them, not because the app asks for them.

```text
https://pbs.twimg.com/media/EXAMPLE123?format=jpg&name=medium
https://pbs.twimg.com/media/EXAMPLE123?format=jpg&name=orig
```

`orig` is the upload, so it is only larger than `large` when the upload was larger than
2048 pixels. A photo posted from a phone camera usually was, by some margin. A
screenshot posted from the same phone usually was not, and in that case the two files
are identical — which is the correct outcome rather than a failure.

## Download Twitter images by hand

1. Open the post and click the photo so it fills the viewer.
2. Right-click it and choose **Copy image address**.
3. Paste it somewhere you can edit. It will contain `name=` followed by a size.
4. Change that size to `orig`.
5. Open the edited address and save the file.

An older URL form is still in circulation and still served, ending in
`.jpg:large` rather than carrying a query string. The same idea applies: the suffix
after the colon is the rendition name.

## Right-clicking in the timeline gets you the small one

The picture rendered in a timeline is `small` or `medium` depending on the width of the
column it landed in. Saving it saves that, at a resolution chosen for a layout you are
not using and compressed a second time to get there.

This matters most in the two places people usually take a screenshot: a slide, and a
document. Both are printed or projected larger than a timeline column, and both make a
second round of JPEG compression obvious in exactly the places a photo cannot afford it —
skin, sky, and any text inside the image.

## When X will not answer at all

X increasingly serves an interstitial rather than the post to anything that is not
signed in. That affects every tool that reads a post link, ours included, and it is
worth saying plainly rather than dressing up as a bug.

The way around it does not involve signing anything in. The size ladder is a property of
the *image address*, not of the post — so if you can see the photo in your own browser,
you can copy its address and derive every size from that with no request to X at all.
The [X image downloader](/tools/x-image-downloader) accepts a `pbs.twimg.com` link for
that reason, and that route cannot be walled.

:::tip
If a post link comes back empty, open the photo in a tab, copy the address, and paste
that instead. It is two extra clicks and it always works.
:::

## What it will not do

- **Video and GIFs.** A video post publishes a poster frame on a different host, and
  that host has no size ladder. These are image tools.
- **Protected accounts.** A protected post is not public and publishes nothing.
  No account, no access — which is the correct behaviour, not a limitation to route
  around.
- **Whole timelines.** One post at a time. Walking an account's media tab is scraping,
  and [X's terms of service](https://x.com/en/tos) have a view on it.

## What you may do with the file

The photo belongs to whoever posted it. Research, reference, moodboards and commentary
are ordinary uses; re-uploading somebody's picture as your own is not, and a reply
crediting them afterwards is not a licence granted in advance.

When you are writing *about* a post rather than using its picture, quoting the post
itself is both better practice and better content — it carries the author's name, and it
stays correct if they edit or delete. The
[social media embed code generator](/tools/social-media-embed-code-generator) produces
X's own embed markup for that.

:::faq
Q: What is the difference between `large` and `orig`?
A: `large` fits the photo inside 2048 pixels. `orig` is the file as uploaded, at whatever size that was. When the upload was smaller than 2048, they are the same picture.
Q: Do old twitter.com links still work?
A: Yes, and so does the older `.jpg:large` image URL form. Both resolve to the same thing.
Q: Can I download the video from a post?
A: Not with this. What comes back for a video post is its cover frame.
Q: Nothing came back for my post link.
A: X answered with a sign-in interstitial. Open the photo in your browser, copy its `pbs.twimg.com` address, and paste that instead.
:::

Paste a post link — or the image address itself — into the
[X image downloader](/tools/x-image-downloader) to get every size at once, or read the
[full guide to downloading social media images](/blog/download-social-media-images) for
how the other platforms compare.
