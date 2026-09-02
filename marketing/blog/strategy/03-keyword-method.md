# 03 - How the keywords were chosen, and how to add one

## What this plan does not have

No paid keyword data. There is no Ahrefs, Semrush or Keyword Planner connection on
this project today, so **no volume or difficulty figure appears anywhere in this
plan**. Writing "1,900/mo" next to a keyword we have not measured would be the same
failure the tool catalog refuses to commit (docs/07: no invented volumes).

What we have instead is better than nothing and free:

1. **The catalog itself.** Sixty-two tools, each named after the query people type -
   docs/16 makes this explicit: "the tool's name *is* the keyword". That is 62
   validated commercial keywords before any research starts.
2. **The platforms' own autocomplete.** YouTube's suggest endpoint is what
   `/tools/youtube-search-suggestions` reads, and it is real demand data, not a
   model's guess. Google and Pinterest guided search are the same idea.
3. **Search Console, from month two.** Once pages are indexed, the impressions
   report tells us what we already rank for on page three - which is the cheapest
   ranking there is, and the primary input for waves 4-6.
4. **The platform limits that change.** Every character limit, payout rate and image
   dimension is a query, and it is a query that renews every time the platform moves.

## The selection rule

A keyword earns a row in `plan/posts.json` if it passes all four:

- **It has an answer we can prove.** A number, a limit, a procedure - not an opinion.
- **It ends at a tool.** There is a live `/tools/*` page that does the next step.
  `validate_plan.py` warns on any row with no tool.
- **It is specific enough to win.** On a domain this age, prefer the four-word
  question over the two-word category. The two-word category is the pillar, and the
  pillar wins later, on the equity the four-word posts collect.
- **Nothing else in the plan targets it.** Duplicate primary keyword is a hard error.

## Adding a keyword

```bash
# 1. Check nothing already covers it (and that it is not a section of an existing post)
grep -i "carousel" plan/keyword-map.csv

# 2. Add the row to plan/posts.json - id, cluster, wave, archetype, slug, title,
#    primary_keyword, secondary_keywords, funnel, category, tags, tools, target_words

# 3. Validate, then regenerate the derived files
cd scripts
python3 validate_plan.py
python3 gen_calendar.py
python3 gen_briefs.py --wave 3
```

If the validator reports a duplicate keyword, the answer is almost always to fold
the idea into the existing post as an H2 rather than to differentiate the keyword.

## Intent, and why the funnel column exists

| Stage | What the query looks like | What the post owes the reader |
| --- | --- | --- |
| TOFU | a limit, a size, a definition | The fact, immediately, and the tool that applies it |
| MOFU | a method, a comparison, a benchmark | The method, the trade-offs, and a saved-time tool |
| BOFU | "free tool for X", "X calculator" | The tool page, quickly - the post is a doorway, keep it short |

BOFU posts are deliberately rare here (three of ninety-five). The tool pages already
target those queries with generated SEO defaults (docs/16), and a post competing
with our own tool page for `engagement rate calculator` is cannibalisation with
extra steps.

## Seasonal and refresh keywords

Two families need re-checking rather than rewriting:

- **Dimensions and limits** (`social media image sizes`, every `* character limit`).
  Verify quarterly against the platform's own documentation; update the "checked on"
  date in the post even when nothing changed, because the date is the value.
- **Payout figures** (`how much does tiktok pay`, `how much do youtubers make`).
  Re-check every six months, and state the band rather than a single number.

The refresh cycle is defined in `08-measurement-and-refresh.md`. docs/16 is blunt
about the economics: refreshing the highest-traffic posts is consistently cheaper
than writing new ones.
