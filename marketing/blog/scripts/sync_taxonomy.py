#!/usr/bin/env python3
"""Creates (and updates) the blog's categories and tags in production.

Idempotent: it matches on slug, creates what is missing, patches what has drifted,
and never deletes. A tag is created once two planned posts need it: two is the point
at which the tag starts doing real work (it is what related-posts ranking joins on),
and a tag used by a single post is a label, not a taxonomy. Archive thinness is a
separate problem, handled by noindex rather than by withholding the tag - and today
those archive routes do not exist at all (strategy/09).

    python3 scripts/sync_taxonomy.py               # dry run, prints the plan
    python3 scripts/sync_taxonomy.py --apply       # writes
    python3 scripts/sync_taxonomy.py --apply --all-tags
"""
from __future__ import annotations

import argparse
import sys
from collections import Counter

import mc_api
from _common import categories, posts, tags

MIN_POSTS_PER_TAG = 2


def sync(kind: str, path: str, wanted: list[dict], apply: bool) -> None:
    existing = {row["slug"]: row for row in mc_api.paged(path)}
    for item in wanted:
        payload = {k: v for k, v in item.items() if k != "group"}
        current = existing.get(item["slug"])
        if current is None:
            print(f"  create {kind}: {item['slug']}")
            if apply:
                mc_api.post(path, payload)
            continue
        drift = {k: v for k, v in payload.items()
                 if k in current and current.get(k) not in (v, None) and current.get(k) != v}
        if drift:
            print(f"  update {kind}: {item['slug']} -> {', '.join(sorted(drift))}")
            if apply:
                # Both models route-bind on `slug`, not the numeric key the resource
                # exposes for the post editor's pickers.
                mc_api.patch(f"{path}/{current['slug']}", payload)
        else:
            print(f"  ok     {kind}: {item['slug']}")

    orphans = sorted(set(existing) - {i["slug"] for i in wanted})
    for slug in orphans:
        print(f"  note   {kind} exists remotely but is not in the plan: {slug} (not touched)")


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--apply", action="store_true", help="actually write to production")
    parser.add_argument("--all-tags", action="store_true",
                        help="create every tag, including ones with fewer than three planned posts")
    args = parser.parse_args()

    usage = Counter(t for p in posts() for t in p["tags"])
    wanted_tags = [t for t in tags().values()
                   if args.all_tags or usage[t["slug"]] >= MIN_POSTS_PER_TAG]
    held = sorted(set(tags()) - {t["slug"] for t in wanted_tags})

    print(f"{'APPLY' if args.apply else 'DRY RUN'} against {mc_api.SITE}\n")
    print("Categories")
    sync("category", "/admin/post-categories", list(categories().values()), args.apply)
    print(f"\nTags ({len(wanted_tags)} of {len(tags())}; {len(held)} held back)")
    sync("tag", "/admin/tags", wanted_tags, args.apply)
    if held:
        print("  held: " + ", ".join(held))
    if not args.apply:
        print("\nNothing was written. Re-run with --apply.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
