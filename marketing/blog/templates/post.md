---
{
 "id": "YT-00",
 "slug": "the-url-slug",
 "title": "The H1, which may be longer than the SERP allows",
 "excerpt": "One sentence, 120-160 characters, that works as a standalone answer to the query.",
 "category": "seo",
 "categories": [],
 "tags": ["youtube", "metadata", "how-to"],
 "primary_keyword": "the exact phrase this post owns",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "Shorter title that fits 580px with the brand suffix",
  "description": "140-160 characters, contains the keyword, makes the promise the first paragraph keeps.",
  "focus_keyword": "the exact phrase this post owns",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "",
  "og_description": "",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
The first paragraph answers the query in about 40 words and contains the primary
keyword. No throat-clearing, no definition of the platform, no "in today's".

## Every heading is H2 to H4 - never H1

H1 belongs to the title; the sanitiser clamps anything else. Paragraphs are plain
markdown with **bold**, *italic*, `code`, ~~strikethrough~~, ==highlight== and
[links](/tools/youtube-tag-extractor).

- Bullet lists work
- So do numbered lists
- [ ] And checklists, via `- [ ] item`

| Surface | Limit | Checked |
| --- | --- | --- |
| Search results | ~70 characters | 2026-09-01 |
| Mobile | ~40 characters | 2026-09-01 |

> A quote block.
> -- Attribution after a double dash

```bash
code fences keep their language and are never HTML-sanitised
```

:::tip Callouts take a tone and a title
info, tip, warning or danger. Use at most one per post - four callouts means none of
them are read.
:::

Put the tool card where the reader needs it, not at the end:

[[tool:youtube-tag-extractor]]

[[button:Open the tag extractor|/tools/youtube-tag-extractor]]

[[embed:youtube|https://www.youtube.com/watch?v=xxxxxxxxxxx|16:9]]

![Alt text that describes the content](https://example.com/image.png "Optional caption")

[[divider:dots]]

:::faq
Q: A question someone actually types next?
A: The answer, in two sentences.
Q: A second one?
A: This block is what emits FAQPage JSON-LD - without it the post has none.
:::

The last line tells the reader what to do next, and it is
[a link](/tools/youtube-tag-extractor).
