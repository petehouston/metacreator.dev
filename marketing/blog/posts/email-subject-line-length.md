---
{
 "id": "SN-04",
 "slug": "email-subject-line-length",
 "title": "Email Subject Line Length: Where Each Inbox Cuts It",
 "excerpt": "Gmail on a desktop gives a subject line about 340 pixels — the tightest surface in email. A phone gives more. The count that matters is a width, not a character total.",
 "category": "content",
 "categories": [],
 "tags": ["email", "titles", "explainer"],
 "primary_keyword": "email subject line length",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "Email Subject Line Length: Where Gmail, Apple Mail and Outlook Cut",
  "description": "The right email subject line length is whatever survives the narrowest inbox row. Where each client cuts, why desktop Gmail is tightest, and how to check yours.",
  "focus_keyword": "email subject line length",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Desktop Gmail is the tightest inbox you send to",
  "og_description": "A subject line is clamped by a column width, and the four major clients do not agree on it.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
Email subject line length is decided by the **width of the row**, not by a character
count, and the narrowest surface you send to is desktop Gmail — which pays for the
sender column and the date first and gives the subject whatever is left. Land the
promise in the first thirty characters and every inbox on the list shows it.

[[tool:email-subject-line-preview]]

## An inbox is a list of fixed-width rows

That single fact explains every confusing thing about subject lines.

Each client lays out a row, allocates part of it to the sender name, part to the date or
time, and gives the subject the remainder. How much is left depends on settings you do
not control: Gmail's display density changes the row height and the space available, and
its own [help centre](https://support.google.com/mail/) documents the setting without
ever quoting a subject-line limit — because there is not one to quote. Which means the same subject fits in one
client and is cut in another, and the difference has nothing to do with how many
characters you typed.

It also means the usual advice — "keep it under 50 characters", "keep it under 9 words"
— is a rounding of somebody else's layout. Useful as a habit, wrong as a rule, and
wrong most often for the capital-heavy subject lines marketers write.

## The four surfaces, and which one to design for

**Gmail on a desktop** is the tightest. The sender column and the date are paid first,
and the subject and the preheader then share one line. It is simultaneously the meanest
surface for a subject and the most generous for preview text, which is a trade worth
knowing.

**Gmail on a phone** stacks three lines — sender, subject, preheader — each clamped to
the device width. More room for the subject than the desktop row, less for everything
else.

**Apple Mail on an iPhone** is the most generous preview in email: subject on its own
line, then two lines of preheader underneath by default. If you write a second sentence
worth reading, this is where it gets read.

**Outlook's desktop list** is narrow, and it is the useful pessimist. A subject that
survives Outlook survives everywhere.

Two things move those widths and neither is yours to set. A reading pane opened beside
the list takes width from every row in it. A long sender name takes width from the
subject in any client that pays the sender first. So treat the numbers as a floor rather
than a specification, and keep a margin at the end of the line rather than filling it.

There is a second reason to design for the narrowest surface, and it is not about
truncation at all. A subject that needs its last clause to make sense is a subject that
reads as a fragment to anybody scanning quickly — which is everybody. Fitting is a
proxy for a sentence that lands early, and landing early is the thing that actually
earns the open.

Design for the narrowest of the four you actually send to. For most senders that is
desktop Gmail, and the practical target is a promise that lands inside roughly the first
thirty characters, with the rest as elaboration that can safely be lost.

## Email subject line length is a width, not a count

`SAVE 40% ON EVERYTHING TODAY` and `subject line preview tool test` are both 28
characters. The first is far wider, because capitals and `W`, `M` and `%` are wide
glyphs and lowercase `i`, `l` and `t` are narrow ones.

That is why the [email subject line preview](/tools/email-subject-line-preview) draws
four real rows and measures the string in advance widths rather than counting it, with
the cut greyed out in place. Seeing which word disappears is the decision; a number is
not.

Emoji deserve a note of their own. They are drawn at full width — among the widest
characters you can spend — and some corporate filters strip them from the subject
entirely. Use one if it earns attention, but never let it carry meaning your words do
not repeat.

## The half of the preview nobody writes

Every client on that list draws **preview text** beside or under the subject, and if you
have not set it, the client fills the space from the first text in your email body.
Which is usually "View this email in your browser".

That is roughly a quarter of your inbox real estate spent on a link almost nobody uses.
Setting it is a two-minute change in any sender, and it is the single highest-return
edit in email:
[preheader text](/blog/email-preheader-text) covers what to put there.

## Test one before you send

1. Paste the subject and the preheader into the preview together — they compete for the
   same row and testing them separately hides the problem.
2. Look at the desktop Gmail frame first. If the promise survives there, it survives
   everywhere.
3. Check the Apple Mail frame for whether your second sentence is doing work or is
   filler.
4. Cut from the end, never from the front.

The identical arithmetic governs a search result —
[title tag length](/blog/title-tag-length) — and the identical mistake produces the same
surprise there. If you write for social surfaces too, the
[social media character counter](/tools/social-media-character-counter) applies each
platform's own rule the same way.

:::faq
Q: How long should an email subject line be?
A: Short enough that the promise lands inside the first thirty characters. In width terms, desktop Gmail gives the subject roughly 340 pixels, which is the tightest of the major clients.
Q: Do longer subject lines get lower open rates?
A: Length is a proxy for the thing that matters, which is whether the reason to open survives the cut. A long subject whose first clause is compelling beats a short vague one.
Q: Should I use emoji in a subject line?
A: They earn attention and cost width — an emoji is drawn at full width. Some corporate filters strip them, so never let one carry meaning your words do not repeat.
Q: Does the from name matter as much as the subject?
A: On a phone it is read first, and it is paid for out of the same row on a desktop. A recognisable sender name does more for the open rate than any subject line trick.
:::

Draw your next subject line in four real inbox rows with the
[email subject line preview](/tools/email-subject-line-preview), and set the
[preheader](/blog/email-preheader-text) while you are in there.
