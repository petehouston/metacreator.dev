---
{
 "id": "ID-03",
 "slug": "qr-code-generator-for-bio",
 "title": "QR Code Generator for a Bio Link That Still Scans",
 "excerpt": "A QR code that fails to scan is worse than no QR code. Here is the size, contrast and error correction that make one work on a screen or in print.",
 "category": "growth",
 "categories": ["design"],
 "tags": ["bio", "how-to"],
 "primary_keyword": "qr code generator",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "QR Code Generator for a Link That Still Scans",
  "description": "How to use a QR code generator properly: the size, contrast and error correction a code needs to scan reliably on a screen, in print and on video.",
  "focus_keyword": "qr code generator",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "QR codes that actually scan",
  "og_description": "Size, contrast, error correction, and the tracking most people forget.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
A QR code generator produces something that either scans in one second or gets abandoned.
The difference is almost always size, contrast or error correction — not the design — and
all three are decided before the code is made.

[[tool:qr-code-generator]]

## What a QR code generator has to get right

| Factor | Rule |
| --- | --- |
| Size | At least 2cm square in print; on screen, at least 200px |
| Quiet zone | Clear margin all round, roughly four modules wide |
| Contrast | Dark code on a light background. Never the reverse |
| Error correction | Higher levels tolerate damage and logos |
| Scan distance | Roughly 10× the code's width — a code on a poster needs to be large |

The quiet zone is the one most often broken. A code butted up against text or a border
fails to scan on many readers, and the failure looks like a broken code rather than a
layout mistake.

## Error correction, and putting a logo in the middle

QR codes carry redundancy so a partially damaged code still resolves. Higher error
correction means more redundancy — and more redundancy is what allows a logo in the centre
without breaking the code.

Two rules if you are branding one: keep the logo under roughly 30% of the code's area, and
test the result on more than one phone before printing anything.

## Shorten the URL first

A long URL produces a denser code with smaller modules, which is harder to scan at a
distance and less tolerant of poor printing. A short destination produces a visually
simpler code.

This matters particularly if you are adding tracking parameters, which are long:

[[tool:utm-link-builder]]

Tag the destination so a scan is not anonymous — a QR scan carries no referrer, so without
parameters you cannot tell it from direct traffic. See
[UTM parameters for creators](/blog/utm-parameters-for-creators) and
[link-in-bio tracking](/blog/link-in-bio-tracking).

## Where creators use them

**On screen in a video.** Leave it up for at least five seconds — people need to notice it,
unlock a phone and aim. Keep it out of the interface bands, or it is partly covered:
[safe zones](/blog/social-media-safe-zones).

[[tool:safe-zone-guide]]

**In print.** Business cards, packaging, posters. Size for the scan distance, not for the
layout.

**On a slide.** Same rules as video, with more time.

**In a bio.** Rarely useful — the person is already on a phone and a tappable link is
faster. The exception is pointing to something outside the app entirely.
[Instagram bio ideas](/blog/instagram-bio-ideas) covers what belongs there instead.

## Test before you commit

Print it, or display it at final size, and scan it with two different phones from the
distance people will actually be at. This takes two minutes and is the only test that
matters — a code that scans from 20cm on your own phone can fail at two metres on someone
else's.

The QR specification itself, including error correction levels, is documented by
[ISO/IEC 18004](https://www.iso.org/standard/62021.html) if you need the underlying detail.

:::faq
Q: What size should a QR code be?
A: At least 2cm square in print, larger for greater scan distances - roughly one tenth of
the distance it will be scanned from.
Q: Can I put a logo in a QR code?
A: Yes, at higher error correction, keeping the logo under about 30% of the area. Always
test the result on more than one phone.
Q: Do QR codes expire?
A: The code does not. The URL it points to can, which is why a stable destination matters
more than the code itself.
Q: How do I track QR code scans?
A: Add campaign parameters to the destination URL. A scan carries no referrer, so without
them it is indistinguishable from direct traffic.
:::

Generate a code with the right error correction and size:
[QR code generator](/tools/qr-code-generator).
