# 06 - The on-page standard

Every rule here is enforced by `scripts/audit.py`. If you change one, change it in
both places - this document is what a person reads, that script is what the pipeline
believes.

## The header block

Each post file starts with a JSON header. These fields are what the API stores
(`SavePostRequest`, and the `seo_meta` resolution chain in docs/16):

```json
{
  "id": "YT-03",
  "slug": "youtube-title-length",
  "title": "YouTube Title Length: Where Your Title Gets Cut Off",
  "excerpt": "One sentence, 120-160 characters, that would work as a standalone answer.",
  "category": "content",
  "categories": ["seo"],
  "tags": ["youtube", "titles", "explainer"],
  "primary_keyword": "youtube title length",
  "status": "draft",
  "seo": {
    "title": "YouTube Title Length: Where It Gets Cut Off",
    "description": "YouTube titles are capped at 100 characters, but search cuts them near 70 and mobile sooner. Here is where each surface truncates, and how to check yours.",
    "focus_keyword": "youtube title length",
    "twitter_card": "summary_large_image",
    "schema_type": "BlogPosting"
  }
}
```

`seo.title` is a **separate string** from the H1 and usually shorter. The H1 can
read like a sentence; the SERP title has ~580px to work with, including the
` | MetaCreator.Dev` the template appends. Write both.

## Titles

| Rule | Why |
| --- | --- |
| Primary keyword in the first half | It is what the reader is scanning for |
| `seo.title` under ~580px (~55 characters before the brand suffix) | Google truncates by pixel width, not characters (docs/16) |
| One promise, no stacked colons | "X: Y - Z, and W" reads as SEO furniture |
| Never a year, in the title, slug, description or body | These posts are evergreen; nothing on them is dated, and a title with a year is stale twelve months later |

## Meta description

140-160 characters. It contains the primary keyword, and it makes a promise the
first paragraph keeps. Do not summarise the article - answer the query and stop.
An empty description means Google writes one for us, which docs/16 explicitly calls
an unacceptable failure mode. `audit.py` errors on a blank one.

## The first 40 words

The most-tested rule in this standard: **the opening paragraph answers the query**.
Not context, not "in today's fast-moving landscape", not a definition of the
platform. The number, the limit, the yes or no - then the article earns the rest of
the reader's time.

This is also what wins featured snippets and what an AI overview quotes. A post that
buries its answer under 300 words of warm-up loses both.

## Headings

- H1 is the post title, emitted by the template. Never write one in the body - the
  editor clamps body headings to H2-H4 for exactly this reason (`BlockSanitizer`).
- At least three H2s, each a question or a claim, not a label. "Where the title gets
  cut off" beats "Truncation".
- The primary keyword appears in at least one heading, naturally.
- No skipped levels. H4 only inside an H3.

## Structured data

Emitted automatically by the API (docs/16): `BlogPosting` with author, dates, image
and word count, plus `BreadcrumbList`. The one piece the writer controls:

**A FAQ block emits `FAQPage` JSON-LD**, and only when the block is present. Two to
four real questions - the ones a reader would actually type next, taken from
autocomplete, not invented to fill the slot. `audit.py` warns when a post has none.

## Images

- Every image needs alt text describing the content, not the file. `audit.py` errors
  on empty alt.
- Images are referenced by public URL today: the upload path to Spaces is not built
  (docs/24), and the media library modal in the editor is the only route for a
  featured image.
- A featured image is set in the admin editor after publishing, because the API's
  `featured_media_id` needs a media row that this pipeline cannot create yet. Until
  then the OG image falls back to the site default, which is acceptable but not good.

## Keyword use

- Primary keyword: title, slug, meta description, opening paragraph, one H2. That is
  five placements and it is enough.
- Density above 2.5% is stuffing and `audit.py` warns on it. Synonyms and the
  secondary keywords do the rest of the work.
- Never repeat the keyword in a heading *and* the sentence directly under it.

## Length

Target lengths are in the plan, per post, and they are a budget rather than a goal:
around 1,200 words for a pillar and 800-950 for a spoke. Under 70% of target usually
means a thin answer; over 160% usually means two posts sharing a URL. Both are warnings,
not errors.

These targets were **revised down** after the first thirty posts were written. The
original plan budgeted 1,300-3,000 words apiece, and hitting those numbers on questions
like "what is the Twitter character limit" meant padding - which is the exact failure the
standard exists to prevent. A complete 800-word answer with a table, a tool and an FAQ
beats a 1,600-word one that restates itself twice, and it is what actually wins a
question-shaped query. Pillars stay longer because they genuinely cover more ground.

## Before publishing

```bash
python3 scripts/audit.py posts/<slug>.md      # must pass with zero errors
python3 scripts/publish.py posts/<slug>.md    # dry run, shows the block count
python3 scripts/publish.py posts/<slug>.md --apply --status published
```

Then, the same day:

1. Add the post to its pillar's list of spokes and republish the pillar.
2. Set the featured image in `/c0ns0le/posts`.
3. Confirm the URL is in `/sitemap-posts.xml`.
4. Request indexing in Search Console for the pillar (not for every spoke - the
   sitemap handles those).
