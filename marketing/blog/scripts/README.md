# scripts

Stdlib-only Python 3.11+. No install step, no virtualenv, no dependencies - so the
pipeline works on any machine that can run the repo.

Run them from this directory (they import each other by module name).

| Script | Does | Writes to production |
| --- | --- | --- |
| `validate_plan.py` | Checks the plan against itself and the live tool catalog | no |
| `gen_calendar.py` | Renders `plan/keyword-map.csv` and `strategy/04-editorial-calendar.md` | no |
| `gen_briefs.py` | Renders `briefs/*.md`, optionally stub post files | no |
| `md2blocks.py` | Compiles a post file to block JSON (also a CLI for inspecting output) | no |
| `audit.py` | On-page SEO lint for written posts | no |
| `link_report.py` | Orphans, pillar inbound links, tool link counts | no |
| `sync_taxonomy.py` | Creates/updates categories and tags | **with `--apply`** |
| `publish.py` | Creates/updates posts | **with `--apply`** |
| `mc_api.py` | The admin API client used by the two above | - |

Both writing scripts are dry-run by default and print exactly what they would do.

## Auth

```bash
export MC_COOKIE="$(cat ../.cookie)"     # the full Cookie: header from a signed-in admin session
export MC_SITE="https://metacreator.dev" # optional; defaults to production
```

`mc_api.py` sends the session cookie, mirrors the `XSRF-TOKEN` cookie into the
`X-XSRF-TOKEN` header (Sanctum's stateful guard requires both), and sets a browser
`User-Agent` because the edge in front of production rejects urllib's default.

## Refreshing the tool snapshot

`plan/tools-snapshot.json` is what stops the plan referencing a tool that does not
exist. Refresh it whenever the catalog changes:

```bash
curl -s "$MC_SITE/api/v1/catalog/tools?per_page=200" | python3 -c "
import json,sys
d=json.load(sys.stdin)['data']
rows=[{'slug':t['slug'],'name':t['name'],'tagline':t['tagline'],'tier':t['tier']['value'],
       'platforms':t['platforms'],'category':t['category']['slug']} for t in d]
rows.sort(key=lambda r:(r['category'],r['slug']))
json.dump(rows,open('../plan/tools-snapshot.json','w'),indent=1)
print(len(rows),'tools')"
python3 validate_plan.py
```

## The post file format

Markdown with a JSON header. Full syntax reference, including every directive:
`templates/post.md`. Only the 14 block types that render today are emitted - the
five that depend on the media library are deliberately unreachable from this
compiler, so nothing here can publish a labelled placeholder.
