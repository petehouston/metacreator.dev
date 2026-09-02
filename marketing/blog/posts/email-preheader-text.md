---
{
 "id": "SN-05",
 "slug": "email-preheader-text",
 "title": "Preheader Text: The Half of Your Email Preview Nobody Writes",
 "excerpt": "Leave the preheader empty and every inbox fills it from the first text in your email — usually a link nobody clicks. It is the cheapest fix in email marketing.",
 "category": "content",
 "categories": [],
 "tags": ["email", "titles", "how-to"],
 "primary_keyword": "preheader text",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "Preheader Text: What It Is and What to Put in It",
  "description": "Preheader text is the preview line every inbox draws beside your subject. What it is, where to set it, how long it can be, and what to write in it.",
  "focus_keyword": "preheader text",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "The line the inbox writes for you if you don't",
  "og_description": "Left empty, it becomes “View this email in your browser”. That is a quarter of your preview.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
Preheader text is the preview line an inbox draws beside or under your subject. Set it
and you get a second sentence to earn the open; leave it empty and the client fills the
space from the first text in your email body — which, for most senders, is "View this
email in your browser".

[[tool:email-subject-line-preview]]

## What preheader text actually is

It is not a field in the email protocol. There is no `Preheader:` header. What every
client does is take the first readable text in the HTML body and draw it after the
subject, and "setting the preheader" means putting the sentence you want there and
hiding it visually from people who open the email.

Every major sending platform gives you a field for this and does the hiding for you, and
the clients themselves treat the line as a display setting rather than as data — Apple's
[Mail user guide](https://support.apple.com/guide/mail/welcome/mac) covers the preview
lines control, which the reader can set to anywhere from none to five.

Where the field lives varies — usually beside the subject line in the campaign settings — and
if your platform does not have one, the manual version is a `<div>` at the very top of
the body with inline styles that hide it from display while leaving it in the source.

The mechanism matters because it explains the default failure. If the first text in your
template is a "view in browser" link, that link *is* your preheader, on every send,
until somebody notices.

## How much room you get

More than you would guess, and it varies more than the subject does.

Desktop Gmail runs the preheader on the same line as the subject, so the two compete for
one width — a long subject leaves almost nothing. Gmail on a phone gives it its own
line. Apple Mail on an iPhone is the most generous surface in email, defaulting to two
full lines under the subject, which is enough for a real sentence rather than a
fragment.

Because the space is shared with the subject in the tightest client, the two have to be
written together. The
[email subject line preview](/tools/email-subject-line-preview) draws both in the same
row for exactly that reason: a subject that fits and a preheader that starts with
boilerplate have between them wasted the whole preview.

## What to write in it

The preheader is not a summary. It is the second half of an argument the subject started.

**Continue the subject; do not repeat it.** If the subject is "The three metrics I
actually watch", the preheader is not "Three metrics to watch" — it is "Plus the
spreadsheet I use every Sunday."

**Add the concrete detail.** The subject earns attention with a promise; the preheader
converts it with specifics. A number, a name, a format, a length.

**Front-load, again.** Desktop Gmail may only show you the first few words of it.

**Never leave it as filler.** A preheader reading "Newsletter #47" or "Having trouble
viewing this email?" is worse than no preheader at all, because it actively signals that
nobody was paying attention.

One thing to avoid: the padding trick, where senders fill the preheader with whitespace
characters so no body text leaks into it. It works, and it is also a wasted opportunity
— you had the space and chose to blank it.

## Set one in four steps

1. Find the preheader or preview-text field in your sending platform, beside the subject.
2. Write one sentence that continues the subject rather than restating it.
3. Paste subject and preheader together into the
   [preview](/tools/email-subject-line-preview) and read the desktop Gmail row, where
   they compete hardest.
4. Send yourself a test and check it on a phone, because that is where most opens happen.

If the subject is doing all the work and the preheader is repeating it, cut the subject
shorter and let the preheader carry the detail — see
[email subject line length](/blog/email-subject-line-length) for how much room that
frees. The same "measure the width, not the count" discipline applies to search results
in [title tag length](/blog/title-tag-length).

:::faq
Q: What is preheader text?
A: The preview line an inbox draws beside or under the subject. It is taken from the first readable text in your email body, which is why sending platforms give you a field to control it.
Q: How long should a preheader be?
A: Long enough to be a sentence, short enough that the first clause survives desktop Gmail — where the subject and the preheader share a single line.
Q: What happens if I leave it blank?
A: The client fills it from your template, usually with a "view in browser" link or an alt attribute. It is the most common wasted space in email.
Q: Can I use emoji in a preheader?
A: Yes, and they cost width like any other full-width character. Keep them out of the first few words, which are the part the tightest client shows.
:::

Write the subject and the preheader together and check them in four real inbox rows with
the [email subject line preview](/tools/email-subject-line-preview).
