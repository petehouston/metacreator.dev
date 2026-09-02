---
{
 "id": "SN-02",
 "slug": "title-tag-length",
 "title": "Title Tag Length: How Long Should a Title Be in Pixels?",
 "excerpt": "A title tag is cut by width, not by character count. The desktop column is about 600 pixels — which is anywhere from 45 to 75 characters depending on the letters.",
 "category": "seo",
 "categories": [],
 "tags": ["titles", "metadata", "explainer"],
 "primary_keyword": "title tag length",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "Title Tag Length: Measure It in Pixels, Not Characters",
  "description": "The right title tag length is about 600 pixels, not 60 characters. Why the two disagree, which letters cost you most, and how to check a title before you ship it.",
  "focus_keyword": "title tag length",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "60 characters is the wrong unit",
  "og_description": "Google cuts a title by width. Two titles of identical length can differ by 200 pixels.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
The right title tag length is about **600 pixels**, which is what a desktop Google
result gives you. In characters that is anywhere from 45 to 75, because the number
depends entirely on which letters you used — and that is why every "keep it under 60
characters" rule is wrong for half the titles it is applied to.

[[tool:google-serp-preview]]

## Why the character rule survives despite being wrong

Sixty characters is a useful lie. It is roughly right for ordinary English sentence
case, it is easy to check in any text editor, and for most titles it lands close
enough. So it spread, and it keeps spreading, because the cases where it fails are
invisible unless you go looking.

They fail in both directions. A title in Title Case With Lots Of Capitals is
substantially wider than the same words in sentence case. A title full of `W`, `M` and
`@` runs over the column at fifty characters. A title full of `i`, `l`, `t` and `.`
still has room at seventy-five. Twenty capital W's and twenty lowercase i's are the same
character count and more than twice the width apart.

Google's own guidance never quotes a character number, because there is not one to
quote. Its
[title link documentation](https://developers.google.com/search/docs/appearance/title-link)
talks about descriptive, concise titles and about when Google will replace yours — not
about a limit, because the limit is a rendering constraint rather than a rule.

## The real title tag length limit, in pixels

A desktop result draws the title at 20px in a column of roughly 600 CSS pixels, on one
line. Run past it and Google ellipsises — usually at a word boundary, occasionally
mid-word for a single long token or a URL.

A phone gives the title a **second line** in a narrower column. That is more total room,
which surprises people who assume mobile is the tighter surface. The tighter constraint
on a phone is not the total; it is the first line, which breaks much earlier and is what
somebody scanning actually reads.

So there are two questions, not one:

- **Does the whole title survive?** Ask the desktop column.
- **Does the important half survive the first line?** Ask the phone.

The [Google SERP preview](/tools/google-serp-preview) answers both at once, and it
measures the string in real advance widths rather than counting it. It greys out the
part that gets cut instead of deleting it, which is the only way to see whether losing
that clause matters.

## How to spend the width

The width is a budget, and most titles spend it badly.

**Front-load the phrase somebody typed.** Whatever survives the phone's first line is
what the result competes on. If the first four words are your brand, the result reads as
brand-only to a scanner.

**Cut the brand suffix on pages that are not about the brand.** ` | Company Name` costs
you 100 to 140 pixels on every page in the site. On a home page or an about page it
earns that back. On a how-to article it is 20% of the column spent on something the
reader can see in the identity line above the title anyway.

**Drop the separators you do not need.** ` — ` and ` | ` each cost real width, and three
of them in one title is a signal that the title is a list of fragments rather than a
sentence.

**Do not pad to fill the column.** There is no bonus for using the space. A short,
specific title outperforms a padded one and never gets cut.

## The failure mode nobody checks

Templated titles. A category template that produces `{Product} — {Category} — {Brand}`
is fine on `Mug — Kitchen — Us` and cut on `Stainless Steel Insulated Travel Mug —
Kitchen & Dining — Us`, and nobody notices because the template was tested on the short
one.

If you generate titles, test the *longest* value your data contains, not a typical one.
The same discipline applies to any headline that gets reused as a title tag — run it
through the [headline analyzer](/tools/headline-analyzer) for the writing, and the SERP
preview for the fit.

## Check a title before it ships

1. Paste the exact string, brand suffix included, into the preview.
2. Read the desktop frame: is anything greyed out?
3. Read the phone frame: does the first line contain the phrase somebody typed?
4. If either fails, cut the least load-bearing clause — usually the brand — and re-check.

That is the whole workflow, and it takes about fifteen seconds per title. The same
question one surface over is [meta description length](/blog/meta-description-length),
and the same arithmetic in an inbox is
[email subject line length](/blog/email-subject-line-length). The full picture of how
the result is assembled is in
[how Google builds a search snippet](/blog/google-search-snippet).

:::faq
Q: What is the maximum title tag length?
A: There is no maximum in the HTML. The practical limit is the roughly 600-pixel column a desktop result draws the title in, which is about 55 to 60 average characters.
Q: Does a longer title hurt rankings?
A: No. A cut title is a click problem rather than a ranking one — the words are still read, they are just not all shown.
Q: Should I include my brand in every title?
A: Only where it earns its width. On a home page or a product page it helps; on an article it is often 20% of the column spent on something already shown in the identity line above.
Q: Why does Google show a different title than the one I wrote?
A: Because it judged yours unhelpful for the query — boilerplate, stuffed with keywords, or repeated across many pages. Length alone does not trigger a rewrite.
:::

Measure a real title in the [Google SERP preview](/tools/google-serp-preview) rather
than counting it, and check the same page's
[link card](/tools/link-preview-debugger) while you are there — it is cut by different
rules again.
