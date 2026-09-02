# 05 - Internal linking

The least glamorous part of the plan and the one that decides whether it works.
A young site has almost no external authority, so the only equity we control is our
own, and the only question is where we point it.

## Where equity is supposed to end up

At `/tools/*`. Every link decision resolves that way: posts exist to make tool pages
rank, and the pillar exists to gather what the spokes earn and pass it down.

```
        external links (rare, early)
                 │
                 ▼
        ┌──────────────────┐
        │  PILLAR          │◀────── every spoke links up, once, in the body
        │  /blog/<cluster> │
        └────────┬─────────┘
                 │ links down to every spoke, in a list, with the spoke's keyword as anchor
                 ▼
        ┌──────────────────┐  ── sideways ──▶ 2-3 siblings in the same cluster
        │  SPOKE POSTS     │
        └────────┬─────────┘
                 │ toolCard blocks + prose links
                 ▼
        ┌──────────────────┐
        │  /tools/<slug>   │  the money page
        └──────────────────┘
```

## The five rules

**1. Every spoke links up to its pillar exactly once**, in the body, in a sentence
that would exist anyway. Not in a "related reading" box - a box is a footer, and
footers are discounted.

**2. Every pillar links down to every one of its spokes.** When a spoke goes live,
the pillar is edited the same day. This is a step in the publish checklist, not an
afterthought: a spoke with no inbound internal link is invisible to a crawler that
has not found it in the sitemap yet, and it is the single most common way a content
programme wastes a post.

**3. Two to three sideways links per post**, to siblings in the same cluster only.
Cross-cluster links are allowed when the sentence genuinely needs one (an Instagram
sizing post referencing the safe-zone post), but they are not a target to hit.

**4. At least one `toolCard` block in the body, at the moment the reader needs it.**
Not parked at the end. The reader who has just learned that their title truncates at
100 characters wants the counter *there*. `audit.py` treats a post with no tool card
as an error - it means the post has nowhere to send anyone.

**4b. Never link to a category or tag archive from a post.** Those routes are not
built (`09-technical-seo-gaps.md`); the link is a 404 or a query facet either way.
Link to the sibling post directly.

**5. Anchor text is the target's keyword, not "click here" and not the raw URL.**
Vary it naturally across posts: `engagement rate calculator`, `our engagement rate
calculator`, `calculate it against your reach`. Identical anchors on every link read
as a template, because they are one.

## Tool pages link back

The other half of the loop already exists in the product: docs/16 specifies that
every tool page lists relevant blog posts, and that the footer carries top tools by
category. Two consequences for this plan:

- When a cluster completes, its pillar should be attached to the tool pages it
  feeds, so the money page passes context back and readers who land on a tool with a
  question have somewhere to go.
- Do not duplicate the tool page's own copy in the post. The post explains the
  problem; the tool page explains the tool. Two pages saying the same thing compete.

## Anchor and link budget per post

| Post length | Internal links | Tool cards | External citations |
| --- | --- | --- | --- |
| ~1,200 words | 3-5 | 1 | 1-2 |
| ~1,800 words | 5-8 | 1-2 | 2-3 |
| pillar (2,400+) | 10-20 (mostly down to spokes) | 2-3 | 3-5 |

Outbound links go to the platform's own documentation, and they are a feature: a
post that states a limit without linking the page that defines it is asking to be
trusted for no reason. `audit.py` warns when a post has no outbound citation.

## Orphans and the weekly check

docs/16 already specifies a weekly SEO health job that flags "posts with no internal
links pointing at them". Until that panel is wired for blog posts, the check is
manual and takes two minutes:

```bash
# every published slug that no other post's body links to
python3 scripts/link_report.py
```

Run it at the end of every wave. An orphan at the end of a wave is a bug in the
wave, not a small tidy-up for later.
