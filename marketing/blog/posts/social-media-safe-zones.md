---
{
 "id": "SZ-13",
 "slug": "social-media-safe-zones",
 "title": "Safe Zones on Social Media: Where the UI Covers You",
 "excerpt": "Every vertical feed draws its interface on top of your video. Here are the covered regions per platform, and how to design once for all of them.",
 "category": "design",
 "categories": [],
 "tags": ["safe-zones", "image-sizes", "explainer"],
 "primary_keyword": "safe zone social media",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "Safe Zones on Social Media, Per Platform",
  "description": "The safe zone on social media is the part of a frame the interface does not cover. The covered regions per platform, and one margin that works for all of them.",
  "focus_keyword": "safe zone social media",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Where the interface covers your frame",
  "og_description": "The safe zone per platform, and the one margin that works everywhere.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
A safe zone on social media is the part of your frame that the platform's own
interface does not cover. On a vertical feed that is roughly the middle 60% — the top
carries status and navigation, the bottom carries captions and controls, and the right
edge carries the action rail.

## Social media safe zones: what covers what

| Platform | Top | Bottom | Right edge |
| --- | --- | --- | --- |
| TikTok | Navigation and search | Username, caption, music ticker, progress | Action rail — the widest of any platform |
| Instagram Reels | Status and header | Username, caption, audio | Action rail |
| Instagram Stories | Profile row and progress bars | Reply field and stickers | Usually clear |
| YouTube Shorts | Status | Title, channel, description | Action rail |
| Facebook Stories | Profile row | Reply field | Usually clear |

These bands are approximate and they move — every platform has quietly grown its
interface as features were added. That is exactly why designing to a generous margin
beats designing to this month's exact pixel values.

[[tool:safe-zone-guide]]

The safe-zone guide overlays the covered regions on your own frame, per platform, so
you can see what is buried instead of estimating from a table.

## The one margin that works everywhere

Design vertical content with everything important inside the **middle 60% vertically
and the left 80% horizontally**. That single rectangle survives all five platforms
above, which means one export instead of five.

It sounds wasteful until you consider the alternative: a caption placed at the visual
bottom of the frame is covered on TikTok, clear on Stories, and half-covered on Reels,
so a "correct" design for one platform is broken on the others.

## Why the bands keep growing

Interfaces accumulate. Every new feature on a vertical feed - a shop button, a
remix control, a translation toggle, an ad label - needs somewhere to live, and the
places available are the edges of your video. None of these were removed when the next
one arrived.

That has a design implication worth stating: a template built to last should assume the
covered region will be larger next year than it is now, not smaller. The creators whose
old videos still read well are the ones who left more margin than they needed, and the
ones whose two-year-old Reels have captions sliced by a button that did not exist when
they published are the ones who measured precisely.

The second-order effect is that platform-specific templates age badly. One conservative
template that works everywhere survives interface changes on all five platforms;
five tight templates each break independently.

## Where this bites hardest

**Burned-in subtitles.** The most common casualty. Subtitles belong in the middle
third, not the lower third, whatever your editing software's default is.

**Calls to action.** "Link in bio" at the bottom of the frame is under the caption.
Move it up, or put it at the top.

**Logos in corners.** The bottom-right is the worst possible position — action rail on
one platform, progress bar on another.

**Faces.** A subject framed low in the shot is chin-deep in interface. Frame heads in
the upper-middle of a vertical composition.

## Safe zones are not only a video problem

**YouTube banners** have the most dramatic case: the file is at least 2048×1152, and
the region visible on every device is 1235×338 in the middle of it
([YouTube Help](https://support.google.com/youtube/answer/12950272)). Anything outside
that is seen by some viewers and not others.

**YouTube thumbnails** lose the bottom-right corner to the duration badge and the
bottom edge to the progress bar — see
[YouTube thumbnail size](/blog/youtube-thumbnail-size).

**Facebook cover photos** crop differently on desktop and mobile, which makes the
centre the only reliable area —
[Facebook image sizes](/blog/facebook-image-sizes).

**Instagram grid crops** are a form of the same problem: a portrait post is squared in
the profile grid, so a subject at the top or bottom is cut —
[Instagram image size](/blog/instagram-image-sizes).

## Building it into your template

The fix is a template, not vigilance. Whatever you edit in, put permanent guide layers
at the safe-zone boundaries and leave them switched on. Designers do this
automatically; creators editing on a phone usually do not, which is why the mistake is
so common in short-form.

[[tool:story-templates-sizer]]

The story template sizer exports the overlay for the platforms you post to, so the
guides come from measurements rather than memory. Platform-specific detail:
[TikTok video size](/blog/tiktok-video-size) and
[Instagram Story size](/blog/instagram-story-size).

:::faq
Q: What is a safe zone in social media?
A: The area of your frame that the platform's interface does not cover with captions,
buttons, usernames or progress bars.
Q: How big is the TikTok safe zone?
A: Roughly the frame minus the top navigation, the right action rail and the bottom
caption area - in practice the middle 60% vertically and the left 80% horizontally.
Q: Do safe zones change?
A: Yes. Every platform has expanded its interface over time, which is why a generous
margin is more durable than exact pixel values.
Q: Does this apply to still images too?
A: Yes - grid crops, banner safe areas and thumbnail badges are the same problem in a
different form.
:::

See the covered regions on your own frame with the
[safe-zone guide](/tools/safe-zone-guide).
