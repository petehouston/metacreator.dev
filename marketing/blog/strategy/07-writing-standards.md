# 07 - Writing standards

## Evergreen, and what that costs

Nothing on this blog carries a date. `blog.show_published_date` is off, so no date
renders on an article or a card, and the copy follows the same rule.

That is a constraint on how facts are written, not permission to be vague. A volatile
number gets one of two treatments: **link the page that defines it**, or **hand the
reader the tool that reads it live**. Both stay true without maintenance. What is
banned is the third option - printing a number, dating it, and letting the date do
the work of accuracy.

Machine-readable dates in the `BlogPosting` JSON-LD are a separate thing and are left
alone: they are invisible to readers, valid schema, and removing them would cost the
freshness signal in both directions.

## Voice

The product's documentation already has a voice: direct, specific, willing to say
what is not true. The blog uses the same one. Concretely:

- **Say the number.** "Instagram cuts the caption at 125 characters in the feed" -
  not "captions are truncated fairly early".
- **State limits as facts and choices as choices.** docs/07 puts it well: a fold
  position is a fact; your call to action sitting under "See more" is a decision.
- **No hedging stacks.** "It's generally considered that you should probably" is
  four words of nothing. Either we know, or we say we do not and explain what it
  depends on.
- **Second person, active voice, contractions allowed.** Write to one person.
- **British or American spelling: pick American**, consistently, because the primary
  search market is US.

## What never appears

- **Invented statistics.** No "studies show", no benchmark without a source, no
  search volumes at all (see `03-keyword-method.md`).
- **Dates.** These posts are evergreen: no published or updated date is shown on the
  site, and none appears in the copy either. No "as of March 2026", no "in 2026", no
  "recently". A post that dates itself is a post with a shelf life, and the reader
  who finds it eighteen months later discounts everything in it.
- **Superlatives about our own tools.** Describe what it does. "Paste the URL and it
  returns every thumbnail resolution" is stronger than "the best thumbnail
  downloader" and does not age into a lie.
- **Filler openers.** "In today's digital landscape" is a signal to the reader that
  nobody edited this.
- **Anything about a tool we have not shipped.** Cross-check `plan/tools-snapshot.json`;
  the validator does it automatically.

## Accuracy, and how to keep it

Each factual claim about a platform gets one of three treatments:

1. **Verified against the source** - checked against the platform's own
   documentation. Link it, so the reader can re-check it the day they read:
   *"YouTube caps titles at 100 characters ([YouTube Help](URL))."* The link is what
   replaces a date - it stays true because it points at the page that defines the
   truth.
2. **Measured by us** - produced by one of our tools or a documented method. Say
   which tool and what the method was. Better still, let the reader run it: a tool
   that reads the live value cannot go stale, and "run it on your own account" ages
   better than any number we could print.
3. **Reported, not verified** - a number from a named third party. Attribute it,
   date it, and do not launder it into a fact by dropping the source.

If a claim fits none of the three, it is not a claim; it is an opinion, and it gets
written as one or cut.

## Structure that survives scanning

Nobody reads the middle. Write for the person who reads the first paragraph, the
headings, one table and the last line:

- The answer in the first 40 words.
- One idea per paragraph, three sentences maximum.
- A table wherever there are more than three parallel facts. Tables get pulled into
  snippets; prose lists do not.
- A callout only when something genuinely goes wrong otherwise. Four callouts in a
  post means none of them are read.
- The last line tells the reader what to do next, and it is a link.

## E-E-A-T without the theatre

The site's credibility comes from the tools, so use them:

- **Show the tool's output.** A screenshot or a quoted result is first-hand evidence
  that we did the thing we are describing. That is the "experience" half of E-E-A-T,
  and almost nobody in this niche has it.
- **Name the method.** "We compared the rendered fold across three app versions" is
  worth more than any author bio.
- **Say what we cannot know.** The username checker's honest "check manually" answer
  (docs/07) is a template for the whole blog: naming a limit builds more trust than
  covering it.
- Author attribution is real - posts are attributed to the account that wrote them,
  and the API refuses to let that be reassigned by a form field.

## On assisted drafting

Drafts may be produced with assistance; published posts are edited by a person who
verified every number in them. The audit script checks structure, not truth, and no
script can. Two rules make this safe:

1. **Every factual claim gets one of the three treatments above** before publish.
2. **Nothing publishes as `published` in one step.** Write, audit, publish as
   `draft`, read the rendered page, then promote. `publish.py` defaults to draft on
   purpose.

## The house style checklist

- [ ] The first 40 words answer the query.
- [ ] Every number links its source, and no date appears anywhere in the copy.
- [ ] At least one table.
- [ ] The tool card sits where the reader needs it, not at the end.
- [ ] The pillar link reads like a sentence, not a signpost.
- [ ] The FAQ questions are ones people actually type.
- [ ] Nothing claims we are the best at anything.
- [ ] `python3 scripts/audit.py` passes with zero errors.
