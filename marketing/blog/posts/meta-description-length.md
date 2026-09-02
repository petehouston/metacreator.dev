---
{
 "id": "SN-03",
 "slug": "meta-description-length",
 "title": "Meta Description Length: What Google Actually Shows",
 "excerpt": "Google gives a desktop snippet two lines and a phone three. The useful target is one clear promise inside about 155 characters — and Google may still use a passage instead.",
 "category": "seo",
 "categories": [],
 "tags": ["metadata", "titles", "explainer"],
 "primary_keyword": "meta description length",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "Meta Description Length: What Google Shows and What It Cuts",
  "description": "The practical meta description length is about 155 characters — two lines on desktop, three on mobile. Why Google often ignores it, and when that is good news.",
  "focus_keyword": "meta description length",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Two lines on desktop, three on a phone",
  "og_description": "What a meta description is really for, how much of it survives, and why Google swaps it out.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
The useful meta description length is **one clear promise inside about 155 characters**.
A desktop result draws the snippet on two lines and a phone on three, both in a fixed
column — so the real constraint is a width again, and the honest answer is that anything
past the second line is a bonus you cannot rely on.

[[tool:google-serp-preview]]

## What the field is for

A meta description does not rank your page. It has not been a ranking signal for a very
long time, and treating it as one produces the keyword-stuffed sentences that make
Google replace it.

It sells the click. That is its whole job: somebody is scanning ten results, and yours
has two lines to say what they get. Written well, it converts a scan into a visit.
Written as a summary of the page's topic — which is what most of them are — it says
nothing the title did not already say and earns nothing.

The best test is the one nobody applies: if you deleted the title and showed only the
description, would anyone click? If not, the description is a topic label rather than a
promise.

## Why Google often uses something else

Your description is a candidate, not an instruction. Google chooses the snippet **per
query**, and when a passage on the page answers the search better than your summary
does, it uses the passage. Its documentation on
[snippets](https://developers.google.com/search/docs/appearance/snippet) says so
plainly.

This is usually good news. A person searching for a specific question gets the paragraph
containing the answer, which is a far better advertisement for the page than any
sentence written before the question existed. It also means "Google is not showing my
meta description" is rarely a defect to fix. Check what it *is* showing: if the passage
reads well, the system worked. If the passage is a navigation menu or a cookie banner,
the page has a content problem rather than a metadata problem.

Where the description reliably does get used is the head term — the query the page is
obviously about. That is the one to write for.

## Meta description length, measured in lines

A desktop snippet is drawn at 14px, two lines of a roughly 600-pixel column. A phone
gets a third line in a narrower one, which is again *more* total room, not less.

So a 155-character description is a good default not because 155 is a rule but because
it is about what two lines of ordinary English hold. Write to the meaning, then check
the fit: the [Google SERP preview](/tools/google-serp-preview) measures the string
against both columns and greys out whatever falls past the line, so you can see which
clause you are gambling.

Two things follow that are worth stating outright:

- **Front-load, as always.** The first line is the one everybody reads.
- **Do not pad to fill the width.** A description stretched to reach 155 characters
  reads as stretched, and the filler is what gets cut.

## Leaving it empty is the only clear mistake

An empty description hands Google the whole job. It will assemble something from the
page, and on a page with a heavy header or a consent banner the something is frequently
that header or that banner. On a listing page it is often a run of link text.

Every page you want traffic to deserves a written candidate, even a short one. Pages you
do not want traffic to — internal search results, thin archives — deserve a `noindex`
instead, which is a different fix for a different problem.

## Write one in three steps

1. **Say what the reader gets**, in one sentence, in the words they would use.
2. **Add the one detail that separates you** from the other nine results: free, no
   account, works with a link, whatever is true.
3. **Check it in the preview** against the phone column, and cut whatever falls past the
   second line.

The same arithmetic governs the title above it —
[title tag length](/blog/title-tag-length) — and the same discipline applies one surface
over in an inbox, where the row is tighter still:
[email subject line length](/blog/email-subject-line-length). For how the whole result
is put together, see [how Google builds a search snippet](/blog/google-search-snippet).

:::faq
Q: What is the ideal meta description length?
A: About 155 characters, which is roughly two lines of a desktop snippet. Measure the width rather than trusting the count if your text is capital-heavy.
Q: Does the meta description affect rankings?
A: No. It affects the click-through rate on results where Google chooses to use it.
Q: Why is Google ignoring my meta description?
A: Because a passage on the page answered that particular query better. It is chosen per query, so the same page shows different snippets to different searchers.
Q: Should every page have one?
A: Every page you want organic traffic to. Pages you do not want in search should be handled with noindex rather than with a description.
:::

Check a description against both columns in the
[Google SERP preview](/tools/google-serp-preview), then see what the same page looks
like when somebody shares it with the
[link preview debugger](/tools/link-preview-debugger).
