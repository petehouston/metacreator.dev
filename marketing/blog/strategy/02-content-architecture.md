# 02 - Content architecture

Cluster -> keyword -> topic -> post, and the rules that keep those four levels from
collapsing into each other.

## The four levels

```
CLUSTER            a subject we intend to own          plan/clusters.json
  └─ PILLAR        one post, the map of the subject     archetype: pillar
       └─ SPOKE    one post, one primary keyword        archetype: howto | explainer | ...
            └─ SECTION  one H2, one question answered
```

Twelve clusters exist. A cluster is legitimate only if it has **money pages** - live
`/tools/*` URLs the cluster is built to feed. A cluster with no tool behind it is a
blog for its own sake, and we do not have the domain authority to afford one.

| Cluster | Pillar | Feeds |
| --- | --- | --- |
| YouTube SEO & metadata | `/blog/youtube-seo` | tag extractor, search suggestions, hashtag generator, metadata viewer |
| YouTube monetization | `/blog/youtube-monetization-requirements` | money calculator, YPP checker, monetization checker |
| Image sizes & safe zones | `/blog/social-media-image-sizes` | resizer, safe-zone guide, aspect-ratio calculator, compressor |
| Engagement rate & benchmarks | `/blog/engagement-rate` | engagement rate calculator |
| Hashtags & discovery | `/blog/how-to-use-hashtags` | hashtag generators |
| Instagram formats & profile | `/blog/instagram-growth-guide` | carousel splitter, Reels cropper, bio preview |
| TikTok payouts & formatting | `/blog/tiktok-growth-guide` | TikTok money calculator, safe-zone guide |
| X & Threads posting | `/blog/x-threads-posting-guide` | thread splitter, character counter, link-preview debugger |
| LinkedIn, Facebook & link previews | `/blog/linkedin-post-guide` | LinkedIn preview, ad-text counter, link-preview debugger |
| Pinterest SEO | `/blog/pinterest-seo` | Pin SEO checker, Pin sizer, Rich Pin validator |
| Writing, scripts & planning | `/blog/content-calendar-guide` | headline analyzer, readability checker, script timer |
| Handles, links & housekeeping | `/blog/claim-your-username` | username checker, UTM builder, QR generator |

## One post, one keyword, one URL

This is the rule that prevents the single most common failure in a content
programme: two posts competing for the same query, splitting the links between them
and ranking neither. `scripts/validate_plan.py` treats a duplicated primary keyword
as an **error**, not a warning.

The corollary: when a new idea arrives, the first question is not "is this a good
post" but "which existing row does this belong to". Most good ideas are an H2 inside
a post that already exists.

## Categories are jobs; platforms are tags

The tool catalog learned this the hard way (docs/07): when categories were named
after platforms, every platform appeared twice in the UI and related tools were
split across two homes. The blog uses the same two axes from the start.

- **Category** (exactly one per post, secondary categories allowed): what job the
  post does - `growth`, `seo`, `content`, `design`, `analytics`, `monetization`.
- **Tags** (several): the platform it covers, the topic, and at most one format.

The intent is that `/blog/category/design` collects every sizing and asset post
across all platforms, while `/blog/tag/instagram` collects every Instagram post
across all jobs: two orthogonal ways in, no duplicated archive.

**Those archive routes do not exist yet.** The frontend has `/blog` and
`/blog/{slug}` only, and the sitemap emits categories as `/blog?category={slug}`
query URLs (see `09-technical-seo-gaps.md`). Two consequences for anyone writing
today: keep maintaining the taxonomy - it drives related posts, admin filtering and
the archives when they land - and **never link to an archive URL from a post body**,
because it is either a query facet or a 404. Cross-link to sibling posts directly
instead.

Six categories is the cap. A seventh means some post has two plausible homes, and
from then on the archives are split arbitrarily.

**A tag is not created until two planned posts need it.** Two is where a tag starts
doing work - it is the key related-posts ranking joins on - and a tag used once is a
label rather than a taxonomy. `sync_taxonomy.py` enforces it.

## URL rules

Inherited from docs/16 and non-negotiable:

- `/blog/{slug}` - no dates, no nesting, lowercase, hyphenated, no trailing slash.
- Slugs never change after publication. A slug edit writes a redirect row; that is a
  repair mechanism, not an editing convenience.
- The slug contains the primary keyword, and is shorter than the title. `audit.py`
  warns when it does not.

## Archetypes

Seven, each with a fixed shape in `templates/`. The archetype is chosen in the plan,
not by the writer at the keyboard, because the shape is what the search result
needs, not what the subject feels like.

| Archetype | Serves | Shape |
| --- | --- | --- |
| `pillar` | the planning reader | Map of a subject, one H2 per spoke, links down |
| `howto` | the stuck reader | Answer, prerequisites, numbered steps, the tool, failure modes |
| `explainer` | the checking reader | Answer in 40 words, then why, then edge cases |
| `troubleshooting` | the stuck reader | Ordered causes, most likely first, one check each |
| `benchmarks` | the checking reader | Numbers with dates and method, by tier |
| `comparison` | the checking reader | Recommendation first, criteria stated, then the table |
| `templates` | the planning reader | The template before any preamble, then how to fill it |

## Where a post's parts live

| Thing | File |
| --- | --- |
| The decision to write it | `plan/posts.json` |
| Why it exists, what it must contain | `briefs/<slug>.md` (generated) |
| The writing | `posts/<slug>.md` |
| What it becomes in the CMS | block JSON, compiled by `scripts/md2blocks.py` |
| Whether it is allowed out | `scripts/audit.py` |
| Where it went | `posts/.published.json` |
