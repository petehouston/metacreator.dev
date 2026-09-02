---
{
 "id": "SN-01",
 "slug": "google-search-snippet",
 "title": "How Google Builds a Search Snippet",
 "excerpt": "A search snippet is assembled, not published. Google picks the title, often rewrites it, chooses the description from the page, and cuts both to fit a fixed column.",
 "category": "seo",
 "categories": [],
 "tags": ["titles", "metadata", "guide"],
 "primary_keyword": "google search snippet",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "How Google Builds a Search Snippet (And Where It Cuts Yours)",
  "description": "A Google search snippet is assembled from your page, not published from it. What Google takes, what it rewrites, and where the result gets cut.",
  "focus_keyword": "google search snippet",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Your snippet is assembled, not published",
  "og_description": "What Google takes from the page, what it rewrites, and the pixel column that decides where it stops.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
A Google search snippet is **assembled**, not published. Google chooses a title — often
your title tag, frequently something else from the page — picks a description that
answers the query, and then cuts both to fit a fixed-width column. You control the
input. The cut is arithmetic, and it is the part you can test.

[[tool:google-serp-preview]]

## What a Google search result is made of

A result has three elements, and they come from different places.

**The identity line** — favicon, site name, breadcrumb — is derived from your domain and
your URL structure. Google has not drawn a raw URL in a result for years; it draws the
site name and the path segments as a trail. You influence it by having readable path
segments, and almost not at all otherwise.

**The title** starts as your `<title>` tag. Google reserves the right to replace it, and
it exercises that right constantly: a title that is boilerplate, keyword-stuffed, or
identical across a hundred pages gets swapped for an H1, an anchor, or the site name
plus something from the page. Google documents the behaviour and the reasons in its
guidance on
[title links](https://developers.google.com/search/docs/appearance/title-link).

**The description** is chosen per query. Your meta description is a candidate, not an
instruction. When a passage on the page answers the search better than your summary
does, Google uses the passage. This is not a failure — a snippet drawn from the exact
paragraph somebody needed usually earns the click your meta description would not have.

The practical consequence is that snippet work is *input* work. Write the best candidate
you can, then check the one thing that is fully deterministic: whether it fits.

## The fold is a width, not a character count

Every rule of thumb you have read — 60 characters for a title, 155 for a description —
is a rounding of the real constraint. Google draws the result in a column of roughly 600
CSS pixels on a desktop and a narrower one on a phone, and it stops when the glyphs run
out of room.

Which means character counts are wrong in both directions. Forty capital W's are more
than twice the width of forty lowercase i's, and a title full of capitals and wide
letters blows through the column at fifty characters while an i-heavy one still has
room at seventy.

It also means the two surfaces disagree. A phone column is narrower but gives the title
a second line and the snippet a third, so it has *more* total room and an *earlier*
first-line break. A title whose first four words are your brand reads as brand-only on
the surface where most of your clicks happen.

The [Google SERP preview](/tools/google-serp-preview) measures the string rather than
counting it, and draws both surfaces, greying out the part that gets cut instead of
deleting it — because seeing which clause disappears is the decision, and a number is
not.

## Why two people see different snippets for the same page

Because the description is chosen per query, one page can produce a dozen different
snippets. Somebody searching for a specific error message gets the paragraph containing
it; somebody searching for the topic gets your summary. This is why "my snippet is
wrong" is usually the wrong diagnosis — there is no single snippet to be wrong.

It also explains a pattern that looks like a bug and is not: a page that ranks for
twenty long-tail phrases will show your meta description for the head term and a passage
for most of the rest. The fix, when the passage is bad, is to fix the paragraph rather
than the description. Google is quoting your page accurately; the page is what needs the
edit.

Two things are stable across every query, though, and they are the two worth testing.
The title changes rarely once Google has accepted it. And the column width never changes
at all.

## Title tags: what to spend the width on

The title is the single strongest on-page signal you control, and the whole game is
what survives the fold.

Put the phrase somebody typed first. Put your brand last, or leave it off entirely on
pages where the brand is not the reason for the click. Say what the page is, not what
kind of page it is: "Title Tag Length" beats "SEO Guide | Chapter 4 | Our Blog" on every
axis that matters.

Full detail, including why the 60-character rule survives despite being wrong:
[title tag length](/blog/title-tag-length).

## Meta descriptions: a promise the page keeps

A meta description does not rank the page. It sells the click, and only when Google
chooses to use it.

Write it as the answer the first paragraph delivers, in one or two sentences, and stop.
A description padded to fill the available width reads as padding, and the padding is
the part at risk of being cut. Leaving the field empty is the one clearly wrong move:
Google then writes one for you from whatever it finds, and the result is often a
navigation menu.

More on what Google shows and when it substitutes:
[meta description length](/blog/meta-description-length).

## The same problem, one surface over

Search is not the only place your writing is clamped by somebody else's column. An inbox
does exactly the same thing to a subject line, and with less mercy: Gmail on a desktop
pays for the sender name and the date first and gives the subject whatever is left.

The arithmetic is identical, the fix is identical, and the mistake is identical — people
count characters and are surprised. See
[email subject line length](/blog/email-subject-line-length), and the half of the preview
almost nobody writes, [preheader text](/blog/email-preheader-text).

## What to do first

1. **Take one page that matters.** Not the home page — a page you want to rank.
2. **Paste its title and description into the preview.** Note which surface cuts what.
3. **Move the phrase somebody typed to the front** of the title, and cut the brand suffix
   if the title is over the desktop column.
4. **Rewrite the description as one promise**, and check it against the phone column
   rather than the desktop one.
5. **Check the link card too.** The same page shared into a chat or a timeline is drawn
   by different rules again — that is what the
   [link preview debugger](/tools/link-preview-debugger) is for.

Then leave it alone. Snippets are worth getting right once and worth re-testing when you
change a template, not worth tuning weekly.

:::faq
Q: Why is Google showing a different title than mine?
A: Because it judged yours unhelpful for the query — usually boilerplate, keyword stuffing, or a title repeated across many pages. Google documents the substitution and its reasons in its title link guidance.
Q: How long should a title tag be?
A: About 600 pixels on a desktop result, which is roughly 55 to 60 average characters. Measure it rather than counting, because capitals and wide letters change the answer.
Q: Does the meta description affect rankings?
A: No. It affects whether the result gets clicked, and only when Google chooses to use it rather than a passage from the page.
Q: Should I write a description for every page?
A: For every page you want traffic to. Left empty, Google assembles one from the page, and what it finds is frequently a menu or a cookie notice.
:::

Test a title and description in the
[Google SERP preview](/tools/google-serp-preview), then take the same discipline to the
[social media character counter](/tools/social-media-character-counter) and check where
every other surface cuts you off.
