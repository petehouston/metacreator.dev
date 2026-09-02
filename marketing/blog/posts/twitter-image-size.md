---
{
 "id": "SZ-06",
 "slug": "twitter-image-size",
 "title": "Twitter Image Sizes for X, and How the Card Crops Them",
 "excerpt": "Twitter image sizes on X: 16:9 for single images, 1200x628 for link cards, and the timeline crop that decides what people actually see.",
 "category": "design",
 "categories": ["seo"],
 "tags": ["x", "image-sizes", "link-previews", "explainer"],
 "primary_keyword": "twitter image size",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "Twitter Image Size on X, and How Cards Crop",
  "description": "The Twitter image size that works on X: 16:9 for single images, 1200x628 for link cards, plus the timeline crop and how to check yours before posting.",
  "focus_keyword": "twitter image size",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Twitter image sizes, and the crop nobody plans for",
  "og_description": "What X shows in the timeline versus what you uploaded.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
The Twitter image size that works on X is 1600×900 — 16:9 — for a single attached
image, and 1200×628 for the link preview card. Both get cropped in the timeline, and
the crop is the thing worth planning for: an image that reads perfectly when opened
can be meaningless in the feed.

## Twitter image sizes on X, by placement

| Placement | Ratio | Export |
| --- | --- | --- |
| Single image in a post | 16:9 | 1600×900 |
| Two to four images | Varies by grid position | 1200×1200, composed for a square crop |
| Link preview card (large) | ~1.91:1 | 1200×628 |
| Profile picture | 1:1 | 400×400 |
| Header image | 3:1 | 1500×500 |

[[tool:social-image-resizer]]

## The timeline crop

X shows an attached image at a fixed aspect in the timeline and expands it on tap.
A tall image is therefore reduced to a horizontal band from its middle, and text at
the top or bottom simply is not there until someone taps.

This has one strong implication: **compose the point of the image into the centre**.
Screenshots of text are the worst offenders — a screenshot of a paragraph becomes a
band of three illegible lines, and people scroll past it.

For multi-image posts, each slot is cropped differently depending on how many images
you attach, which is why a set of four images composed individually rarely looks
deliberate. If the images belong together, make one image.

## Link cards are a separate asset

When you post a URL, X renders a card from the page's Open Graph and Twitter card
tags — not from anything you attach. That card is generated from the linked page, so
fixing a bad card means fixing the page, not the post.

[[tool:link-preview-debugger]]

The debugger fetches a URL once and draws the card as X, Facebook, LinkedIn and chat
apps each render it. See [Open Graph tags](/blog/open-graph-tags), and if a card is
showing the wrong thing, [link preview not showing](/blog/link-preview-not-showing).

The two card types are worth knowing:

- `summary_large_image` — the big card, roughly 1.91:1. Almost always what you want.
- `summary` — a small square thumbnail beside the text. Fine for a text-heavy page,
  weak for anything visual.

X publishes the card specification in its
[developer documentation](https://developer.x.com/en/docs/x-for-websites/cards/overview/abouts-cards).

## Why the crop matters more than the resolution

Resolution problems announce themselves: an image looks soft and you fix it. Crop
problems do not - the image looks perfect in your editor, perfect when tapped, and
useless in the one place it is actually seen.

The feed is a scanning environment. An image gets a fraction of a second in a band
across the middle of itself, at a size where fine detail is gone. So the question to
ask before attaching an image is not "is this a good image" but "what does the middle
band of this say on its own". If the answer is "nothing", the post has an attachment
rather than a picture.

Three practical consequences:

- **Screenshots of text need to be reformatted**, not attached. Crop to the sentence
  that matters, or quote it in the post and skip the image.
- **Charts need their title inside the band.** A chart whose title sits above the plot
  area loses it.
- **Faces and products belong dead centre**, because centre is the only region
  guaranteed to survive every crop.

## Screenshots of posts

Sharing a post as an image is common and slightly fraught: an image of a post proves
nothing about who wrote it, and a screenshot at phone resolution looks poor when
scaled.

[[tool:tweet-screenshot-generator]]

Our generator draws the card cleanly and deliberately includes no verification badge,
because a drawn card is a mock-up rather than evidence. More in
[how to screenshot a tweet](/blog/screenshot-a-tweet).

## Threads of images

If you are posting a sequence, the same rules apply per post, and the first image is
the one that decides whether anyone reads the rest. Structure matters more than
resolution here — see
[how to write a Twitter thread](/blog/how-to-write-a-twitter-thread).

The complete cross-network reference is
[social media image sizes](/blog/social-media-image-sizes).

:::faq
Q: What is the best Twitter image size on X?
A: 1600×900 at 16:9 for a single image. It matches the timeline crop closely, so what
people see is close to what you uploaded.
Q: What size is a Twitter card image?
A: 1200×628 for the large `summary_large_image` card. It comes from the linked page's
Open Graph tags, not from the post.
Q: Why is my image cropped on X?
A: The timeline shows a fixed aspect and expands on tap. Anything at the top or bottom
of a tall image is hidden until someone opens it.
Q: Why is my link preview not showing on X?
A: Usually a missing or unreachable Open Graph image, or a cached older version of the
page. Fetch the URL through a preview debugger to see what X is actually reading.
:::

Check how any URL renders as a card before you post it with the
[link preview debugger](/tools/link-preview-debugger).
