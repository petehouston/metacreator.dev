# 01 - Thesis and positioning

## What the blog is for

MetaCreator.Dev sells nothing on the blog. The blog exists to do two jobs, in this
order:

1. **Make the tool pages rank.** `/tools/*` are the money pages - 62 of them live
   today, every one free, every one a conversion surface for the account and premium
   tiers. A tool page can carry a keyword; it cannot carry a subject. Posts build the
   topical evidence that lets a two-month-old domain compete for `youtube seo` at all.
2. **Catch the queries a tool page cannot answer.** "How much do YouTubers make" is a
   question. "YouTube money calculator" is a tool. The same person types both, a few
   minutes apart, and today we only own the second.

Anything that does not serve one of those two jobs does not get written, however
good the idea is.

## Why this site can win, and where it cannot

**The advantage is the catalog.** Sixty-two working tools is an unusual amount of
proof for a new domain. Most competitors in this space are either a blog with no
tools (so every "how to" ends in an affiliate link) or one tool with a thin blog. We
can answer a question *and* hand over the thing that does the job, on the same
screen, for free, without an account. That is a genuinely better result than what
currently ranks, and "genuinely better result" is the only durable ranking strategy.

**The second advantage is honesty about numbers.** Half the pages ranking for
`good engagement rate instagram` cite a benchmark with no date, no sample and no
method. Our tools already refuse to fake it - the username checker says "check
manually" where a platform blocks probing, the post-screenshot tool draws no
verification badge (docs/07). The blog inherits that standard, and it is the thing
that earns links from people who need a citable source.

**The disadvantage is domain age.** metacreator.dev has no history, no backlink
profile and no brand searches. That has three consequences the plan is built around:

- We compete on **long-tail and specific** first. `youtube title length` before
  `youtube seo`. The pillar exists to collect the equity the spokes earn, not to
  rank on day one.
- We publish in **complete clusters**, not scattered posts. Topical completeness is
  the one signal a young site can generate on its own schedule.
- We measure in **impressions before clicks**. Months 1-3 are about being indexed
  and impressed on; expecting position-3 rankings in month two is how a plan gets
  abandoned in month three.

## What "ranking high in a few months" honestly means

An expectation set now is worth more than an excuse later. With the plan in this
directory executed at cadence, on a clean domain, a realistic shape is:

| Month | What should be true |
| --- | --- |
| 1 | Cluster 1 and 2 published and interlinked. Indexed within days. First impressions on long-tail queries. Almost no clicks. |
| 2 | 35-40 posts live. Long-tail terms (`youtube subscribe link`, `threads character limit`) start appearing in the top 20-30. First tool-page assists visible in the funnel table. |
| 3 | Early long-tail wins land on page one. Pillars start collecting internal equity. Impressions in the thousands per month, clicks in the hundreds. |
| 4-6 | Mid-tail terms (`instagram carousel size`, `how much does tiktok pay`) reach page one or two. Tool pages the posts feed begin ranking for their own names. Clicks in the low thousands per month. |
| 7-12 | Head terms (`social media image sizes`, `engagement rate`) become plausible - and only if the cluster is complete and something has linked to it. |

Anyone promising page one for `engagement rate` in ninety days on a new domain is
selling something. What we can promise is that by month six there is a complete,
correct, internally-linked body of work that keeps compounding, and a measurement
loop that says which parts to double.

## The reader

One person, three moods:

- **Stuck** - "why is my link preview showing the old image". Wants the fix in the
  first paragraph. Converts to a tool run in under a minute if we let them.
- **Checking** - "what is a good engagement rate". Wants a number, its source and
  its date. Converts if the number comes with a calculator.
- **Planning** - "how do I grow on Instagram". Wants the shape of the whole problem.
  Converts later, if at all, and is worth writing for anyway because that reader is
  the one who links to us.

Every archetype in `templates/` maps to one of those three.

## What we will not do

- No "X best tools in 2026" listicles where we are number one. It is transparent,
  it earns nothing, and it is the exact genre we are trying to beat.
- No invented statistics. If a number is not measured by us or attributable to a
  named source with a date, it does not appear. See `07-writing-standards.md`.
- No content about a tool we have not built. Every planned post points at a tool
  that exists in the live catalog today; `scripts/validate_plan.py` fails the build
  if that stops being true.
- No dated URLs, and no slug changes after publication (docs/16 turns a slug edit
  into a 301, which is a cost, not a feature).
