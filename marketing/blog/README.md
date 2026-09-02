# marketing/blog

The blog content programme for metacreator.dev: the plan, the standards, and the
tooling that turns a markdown file into a published, SEO-complete post.

Start with [`strategy/01-thesis-and-positioning.md`](strategy/01-thesis-and-positioning.md)
if you are reading this for the first time. Start with the quickstart below if you
are here to write something.

## Quickstart

```bash
cd marketing/blog/scripts
export MC_COOKIE="$(cat ../.cookie)"        # admin session; see "Auth" below

python3 validate_plan.py                    # is the plan self-consistent?
python3 sync_taxonomy.py                    # dry run: what categories/tags are missing
python3 sync_taxonomy.py --apply            # create them

python3 gen_briefs.py --wave 1 --stub       # briefs + empty post files for wave 1
# ...write posts/<slug>.md...
python3 audit.py ../posts/<slug>.md         # on-page SEO lint; must pass clean
python3 publish.py ../posts/<slug>.md       # dry run
python3 publish.py ../posts/<slug>.md --apply                     # creates a draft
python3 publish.py ../posts/<slug>.md --apply --status published  # promotes it
```

## What is where

| Path | What it is | Edited by |
| --- | --- | --- |
| `strategy/` | The reasoning: positioning, architecture, keyword method, linking, on-page standard, voice, measurement, technical gaps | Hand |
| `strategy/04-editorial-calendar.md` | Six-month wave plan | **Generated** |
| `plan/clusters.json` | Twelve clusters, each with the tool pages it feeds | Hand |
| `plan/posts.json` | **The master map.** One row per planned post | Hand |
| `plan/keyword-map.csv` | Spreadsheet view of the same thing | **Generated** |
| `plan/tools-snapshot.json` | The 62 live tools, so the plan cannot reference one that does not exist | Refreshed from the API |
| `taxonomy/categories.json` | The six blog categories | Hand |
| `taxonomy/tags.json` | The tag vocabulary | Hand |
| `briefs/` | One writing brief per planned post | **Generated** |
| `posts/` | The writing | Hand |
| `posts/.published.json` | Which remote post each file owns | Written by `publish.py` |
| `templates/` | The post file format, and the seven archetype shapes | Hand |
| `scripts/` | The toolchain | Hand |

Generated files are regenerated from `plan/posts.json`. Never hand-edit one - the
next run will silently discard the edit.

## The model, in one paragraph

Twelve **clusters**, each with a **pillar** post and a set of **spokes**. One spoke
owns exactly one primary keyword and one URL; a duplicate keyword is a hard error,
not a style question. Every post exists to send a reader to a live `/tools/*` page,
and a planned post with no tool behind it is flagged. **Categories** describe the
job a post does (six of them); **platform is a tag, never a category** - the same
two-axis rule the tool catalog uses in `docs/07`. The full reasoning is in
[`strategy/02-content-architecture.md`](strategy/02-content-architecture.md).

## Current state

- **95 posts written and published** to production, all 12 clusters complete
- 6 categories and 41 tags live; every post carries a generated featured image
- Every post points at at least one of the 62 live tools; no orphans
- `validate_plan.py`: 0 errors. `audit.py`: 95 passing, 0 failing
- The same 95 posts are published on the local stack for manual testing

### Working locally

```bash
cd scripts
MC_SITE=http://localhost:8080 python3 login.py admin@metacreator.dev password
export MC_SITE=http://localhost:8080 MC_ORIGIN=http://localhost:3000
export MC_COOKIE="$(cat ../.cookie.local)"
python3 sync_taxonomy.py --apply
python3 publish.py ../posts/*.md --apply --status published
```

`MC_ORIGIN` matters on the local stack: Sanctum matches the request origin against
`SANCTUM_STATEFUL_DOMAINS`, which is the frontend on :3000 rather than the API on :8080.

### Featured images

Every post has a 1200x630 card generated from the site's own palette:

```bash
../.venv/bin/python scripts/gen_images.py          # all planned posts
../.venv/bin/python scripts/gen_images.py --force  # redraw
```

They are generated rather than stock because a stock photo of someone holding a phone says
nothing about "where a YouTube title gets cut off", every competitor uses the same twelve,
and a library that is free today can change its terms tomorrow. To use a photograph
instead, drop a 1200x630 file at `assets/featured/<slug>.png` and republish - `publish.py`
uploads whatever is there.

Images render on both environments. Getting there took three fixes, written up in
`strategy/09-technical-seo-gaps.md`: a permissions bug in `deploy.sh` that left nginx
unable to read the document root, two missing `next/image` allowances, and a local stack
that was writing uploads to an unreachable disk instead of the MinIO bucket sitting ready
for them.

Known constraint worth reading before you plan around the taxonomy: the
`/blog/category/*` and `/blog/tag/*` archive routes **do not exist yet**. See
[`strategy/09-technical-seo-gaps.md`](strategy/09-technical-seo-gaps.md).

## Auth

The scripts talk to the production admin API as you, using the browser's Sanctum
session cookie. Put the whole `Cookie:` header value in `marketing/blog/.cookie`
(gitignored) or export it as `MC_COOKIE`.

**That cookie is a live super-admin credential.** It is never committed, never
passed on a command line, and it expires - a 401 or 419 from any script means sign
in at `/c0ns0le` again and refresh it.

## The rules worth knowing before you touch anything

1. **Nothing publishes in one step.** `publish.py` creates drafts unless you ask for
   `--status published`, and it refuses to run at all if `audit.py` reports an error.
2. **A post's slug never changes after publication** - a slug edit writes a redirect
   row (docs/16). Get it right in the plan.
3. **A tag is not created until two planned posts need it.** A tag used once is a
   label, not a taxonomy - and tags are what related-posts ranking joins on.
4. **No invented numbers.** No search volumes, no undated platform facts, no
   benchmarks without a source. See `strategy/07-writing-standards.md`.
5. **When a spoke goes live, its pillar is edited the same day** to link down to it.
   That step is the difference between a cluster and a pile of posts.
6. **No dates anywhere.** These posts are evergreen: `blog.show_published_date` is off and
   the copy carries no dates either. `audit.py` fails a post containing one.
