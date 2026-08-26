# 10 — Media Library

One library serves the blog, tool documentation, user avatars and tool-generated artifacts.

## Storage

| Environment | Disk | Notes |
| --- | --- | --- |
| Local | `local` (`storage/app/public`) | Served through the API |
| Production | `spaces` (DigitalOcean Spaces, S3 API) | CDN-fronted, immutable paths |

Path scheme: `media/{yyyy}/{mm}/{ulid}/{variant}.{ext}`. Paths are immutable — replacing an image
creates a new media row, so caches and CDNs are never stale and old posts never break.

## Upload pipeline

```
Client asks for a signed upload URL  →  uploads directly to Spaces (never through PHP)
   → POST /admin/media/complete
       ├─ verify size/mime by sniffing bytes, not the declared header
       ├─ reject anything not on the allow-list
       ├─ strip EXIF (GPS especially) and any embedded scripts from SVG
       ├─ sha256 checksum → dedupe against existing media
       ├─ probe dimensions / duration
       └─ dispatch GenerateMediaVariants (media queue → Go compute)
```

Allow-list: `jpeg png webp avif gif svg mp4 webm mov mp3 wav m4a pdf`. SVG is sanitised through a
strict allow-list of elements/attributes; if sanitisation changes the file at all, the original is
discarded.

## Variants

| Label | Longest edge | Formats | Used for |
| --- | --- | --- | --- |
| `thumb` | 240 px | webp | Library grid, pickers |
| `card` | 720 px | avif, webp | Blog cards, tool cards |
| `hero` | 1600 px | avif, webp | Featured images |
| `og` | 1200×630 (cropped) | jpg | Social cards — jpg for maximum crawler compatibility |

Originals are always retained. Delivery uses `<picture>` with AVIF → WebP → original fallback, plus
a base64 blur placeholder (`blurhash`-style, stored on the media row) so layout never shifts.

## Metadata and SEO

Every media item carries `alt_text`, `title`, `caption`, `credit`, `description` and `tags`.

- Alt text is **required** before an image can be inserted into a post — the editor blocks insertion
  and explains why. Decorative images are marked as such explicitly (`alt=""` with an intent flag).
- Filenames are slugified from the title on upload (`youtube-thumbnail-sizes.webp`, not
  `IMG_4821.jpg`) because filenames are a real image-search ranking signal.
- Images used as featured images emit `ImageObject` JSON-LD with dimensions, caption and credit.
- A library-wide **"missing alt text"** filter makes the accessibility/SEO debt visible and fixable
  in bulk.

## Organisation

Folders (single level plus tags — deep trees always rot), full-text search over filename, title, alt
and caption, and filters for type, date, uploader, dimensions and usage.

**Usage tracking**: a `media_usages` table records every place a media item is referenced (post
block, featured image, tool example, avatar). Deleting a used item requires confirmation that lists
exactly where it appears; unused media older than 90 days is surfaced in a cleanup report.

## Access

| Actor | Rights |
| --- | --- |
| Editor | Full CRUD on library media |
| Contributor | Upload and use; edit/delete own uploads only |
| User | Only their own avatar and their own tool artifacts |

Tool artifacts are stored privately and served via short-lived signed URLs (default 1 hour) — they
are never in the shared library and never publicly listable.
