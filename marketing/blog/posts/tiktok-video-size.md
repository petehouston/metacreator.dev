---
{
 "id": "SZ-05",
 "slug": "tiktok-video-size",
 "title": "TikTok Video Size and the Safe Zone That Eats Your Text",
 "excerpt": "TikTok video size is 1080x1920 at 9:16. The harder part is the safe zone - the caption, the action rail and the username cover a third of your frame.",
 "category": "design",
 "categories": [],
 "tags": ["tiktok", "image-sizes", "safe-zones", "explainer"],
 "primary_keyword": "tiktok video size",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "TikTok Video Size and the Safe Zone",
  "description": "TikTok video size is 1080x1920 at 9:16 - but the interface covers the right edge and the bottom third. Here is the usable area and how to design for it.",
  "focus_keyword": "tiktok video size",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "TikTok video size: the frame and the usable part of it",
  "og_description": "9:16 at 1080x1920, minus the third the interface takes back.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
TikTok video size is 1080×1920 pixels — 9:16, full screen, vertical. That part is
easy. The part that ruins otherwise good videos is that TikTok draws its own interface
over your frame, and roughly a third of the screen is spoken for before your content
arrives.

## TikTok video size: the frame, and the part you control

| Region | Roughly | What is there |
| --- | --- | --- |
| Top | ~130 px | Search, "Following / For You", occasional banners |
| Right edge | ~200 px wide | Profile, like, comment, bookmark, share, sound disc |
| Bottom | ~300-500 px | Username, caption, music ticker, progress bar |
| Usable centre | The rest | Where your subject and any text has to live |

Those bands vary by device and by app version, and they have grown over time as
features were added. That is precisely why designing to the exact numbers is fragile
and designing to a generous margin is not.

[[tool:safe-zone-guide]]

The safe-zone guide overlays the covered regions on your own frame, per platform, so
you can see what is buried rather than estimating.

## The rule that follows

**Keep text and faces out of the bottom third and away from the right edge.** If a
caption is long, it expands upward — a two-line caption can reach considerably higher
than you planned, and it lands exactly where most creators put their subtitles.

Two habits that fix most of it:

- Put burned-in subtitles in the middle third, not the lower third.
- Leave the right 200 pixels for the action rail, permanently. Treat it as if it were
  not part of the canvas.

## Export settings that survive TikTok's compression

TikTok re-encodes everything. You cannot avoid that, but you can avoid feeding it a
file that compresses badly:

| Setting | Value |
| --- | --- |
| Resolution | 1080×1920 |
| Aspect ratio | 9:16 |
| Frame rate | 30 or 60fps, matched to the source |
| Codec | H.264, MP4 |
| Audio | AAC |

Uploading from a desktop browser generally preserves more quality than exporting from
the in-app editor, because the app compresses before upload as well as after.

## The cover frame is a still, and it follows still rules

The cover appears in your profile grid and in search results. It is cropped from your
9:16 frame, so a cover with the title at the very bottom loses it in the grid — the
same problem [Instagram Reels covers](/blog/instagram-reels-cover-size) have, for the same
reason.

[[tool:social-image-resizer]]

## When the same video goes to Reels and Shorts

It can, and it should — but the safe zones are not identical, and TikTok's are the
most aggressive of the three. Design for TikTok's usable centre and the frame will
survive on Reels and Shorts; do it the other way round and text that was clear on
YouTube is under a caption on TikTok.

The cross-platform version of this problem is
[safe zones on social media](/blog/social-media-safe-zones), and the full ratio reference
is [social media image sizes](/blog/social-media-image-sizes).

TikTok's own guidance on video specifications is in their
[Creator Portal](https://www.tiktok.com/creators/creator-portal/), which is worth
checking directly when you are producing at volume.

## Caption length is part of the layout

The caption is not separate from the frame — it sits on top of it, and its length
decides how much of your video it covers. A caption that runs to three lines is a
design decision as much as a copy decision.

[[tool:social-media-character-counter]]

See [TikTok caption length](/blog/tiktok-caption-length) for where the fold lands.

:::faq
Q: What is the correct TikTok video size?
A: 1080×1920 pixels, 9:16, MP4 with H.264 video and AAC audio. Anything else is
letterboxed or cropped.
Q: What is the TikTok safe zone?
A: The area of your 9:16 frame not covered by the interface - roughly excluding the
top 130 pixels, the right 200 pixels and the bottom 300 to 500. Keep text and faces
inside it.
Q: Why does TikTok make my video look worse?
A: It re-encodes every upload. Export at 1080×1920 with a sensible bitrate and upload
from a browser where possible, rather than letting the app compress twice.
Q: Can I upload a horizontal video to TikTok?
A: You can, and it appears letterboxed with large empty bands. On a full-screen
vertical feed that is a considerable disadvantage.
:::

See exactly what the interface covers on your own frame with the
[safe-zone guide](/tools/safe-zone-guide).
