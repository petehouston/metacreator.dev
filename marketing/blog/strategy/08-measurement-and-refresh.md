# 08 - Measurement and refresh

## The one dashboard question

*Are tool runs from organic search going up?* Everything else is diagnostic. The
product already records what is needed: `tool_funnel_daily` and `FunnelRecorder`
exist and are populated, and the admin overview shows run volume, funnel and top
tools (docs/24). The blog's contribution is visible as runs whose session started on
a `/blog/*` URL.

## Setup, before wave 1 ships

- [ ] Search Console property verified for `metacreator.dev` (settings-configurable
      meta tag, docs/16), plus Bing Webmaster.
- [ ] `/sitemap-posts.xml` submitted, and confirmed to be non-empty once the first
      post is live.
- [ ] GA4 configured through Settings → Tracking, with a `/blog/*` landing-page
      segment saved.
- [ ] A baseline snapshot: today, organic clicks are zero. Record the date the first
      post went live; every later chart is measured from it.

## What to look at, and when

| Cadence | Look at | Act when |
| --- | --- | --- |
| Weekly (10 min) | GSC: new queries, pages with impressions but no clicks | A page has impressions and a CTR under 2% → rewrite the title and meta, nothing else |
| Weekly | Indexation: submitted vs indexed | A post is unindexed after 10 days → check internal links to it first, then request indexing |
| End of each wave | Orphan check (`link_report.py`), pillar↔spoke completeness | Any orphan → fix before starting the next wave |
| Monthly | Positions 5-20 per cluster | A cluster with several page-two terms → add the two spokes the cluster is missing, do not start a new cluster |
| Monthly | Blog → tool run funnel in `/c0ns0le` | Posts with traffic and no runs → the tool card is in the wrong place, or the wrong tool |
| Quarterly | Every dimension, limit and payout figure | Anything the platform changed → refresh, and update the checked-on date regardless |

## The numbers to expect

Set against the month the first post publishes. These are planning figures for a new
domain executing the full cadence, not promises - see `01-thesis-and-positioning.md`.

| Month | Posts live | Indexed | Impressions/mo | Clicks/mo | What matters |
| --- | --- | --- | --- | --- | --- |
| 1 | ~19 | most within 2 weeks | 500-2,000 | <50 | Indexation, not traffic |
| 2 | ~39 | ~all | 3,000-8,000 | 50-250 | First long-tail positions under 20 |
| 3 | ~52 | all | 8,000-20,000 | 200-700 | First page-one long-tail |
| 4 | ~65 | all | 15,000-40,000 | 500-1,500 | Mid-tail entering page two |
| 5 | ~81 | all | 25,000-60,000 | 1,000-3,000 | Tool pages ranking for their own names |
| 6 | ~95 | all | 40,000-90,000 | 2,000-5,000 | Pillars collecting; refresh cycle starts |

If month 3 shows impressions but no click growth, the problem is titles and
descriptions, and it is fixable in an afternoon. If month 3 shows neither, the
problem is indexation or internal linking, and no amount of new posts will fix it.

## The refresh cycle

docs/16 states the economics plainly: refreshing the highest-traffic posts is
consistently cheaper than writing new ones. From month 4, one day per month is
refresh, not new writing.

**Refresh triggers, in priority order:**

1. A platform changed a limit, size or payout the post states. (Immediate, not
   monthly.)
2. A post lost more than 20% of impressions month over month.
3. A post ranks 5-15 for its primary keyword - the cheapest wins on the board.
4. The post is older than two quarters and states dated facts.

**What a refresh is:** verify every number, update the checked-on date, add the two
questions Search Console shows people now ask, add internal links to spokes written
since, improve the title if CTR is under 2%. What it is not: a rewrite, and never a
slug change.

## When to stop and rethink

Three honest failure signals, with the response each deserves:

- **Indexed but never impressed, after 8 weeks.** The pages are considered
  low-value. Response: consolidate. Merge the thinnest three posts of a cluster into
  one strong page and redirect.
- **Impressions flat across a whole cluster while another climbs.** The losing
  cluster is out of our reach for now. Response: stop feeding it, move the wave's
  budget to the cluster that is moving.
- **Traffic without runs.** The blog is working and the product link is not.
  Response: this is a tool-card placement problem, and it is the most valuable one
  on this list to fix.
