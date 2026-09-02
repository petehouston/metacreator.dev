#!/usr/bin/env python3
"""Fails loudly if the plan references something that does not exist.

Run it after every edit to plan/posts.json. A post pointing at a tool slug that is
not in the live catalog is a broken internal link waiting to be published, and a
tag outside taxonomy/tags.json is a thin archive nobody decided to create.
"""
from __future__ import annotations

import re
import sys
from collections import Counter

from _common import categories, clusters, pixel_width, posts, tags, tools

STOP = {"a", "an", "the", "to", "of", "on", "in", "for", "is", "how", "do", "does", "and", "your", "my"}

# Enough stemming to stop "size"/"sizes" reading as a miss, and no more.
def stem(word: str) -> str:
    return word[:-1] if len(word) > 3 and word.endswith("s") else word


def words(text: str) -> set[str]:
    # Commas are stripped first so "1,000" matches "1000".
    return {stem(w) for w in re.findall(r"[a-z0-9]+", text.lower().replace(",", ""))}


errors: list[str] = []
infos: list[str] = []
warnings: list[str] = []

P, C, CAT, TAG, TOOL = posts(), clusters(), categories(), tags(), tools()
slugs = Counter(p["slug"] for p in P)
kws = Counter(p["primary_keyword"].lower() for p in P)

for slug, n in slugs.items():
    if n > 1:
        errors.append(f"duplicate slug: {slug}")
for kw, n in kws.items():
    if n > 1:
        errors.append(f"two posts target the same primary keyword (cannibalisation): {kw}")

pillars = {c["pillar"] for c in C.values()}
for want in pillars:
    if want not in slugs:
        errors.append(f"cluster pillar has no post row: {want}")

for p in P:
    where = p["id"]
    if p["cluster"] not in C:
        errors.append(f"{where}: unknown cluster {p['cluster']}")
    if p["category"] not in CAT:
        errors.append(f"{where}: unknown category {p['category']}")
    for t in p["tags"]:
        if t not in TAG:
            errors.append(f"{where}: unknown tag {t}")
    for t in p["tools"]:
        if t not in TOOL:
            errors.append(f"{where}: tool slug not in the live catalog: {t}")
    if not p["tools"]:
        warnings.append(f"{where}: no tool to send the reader to")
    if len(p["tags"]) > 6:
        warnings.append(f"{where}: {len(p['tags'])} tags - keep it under six")
    # The H1 may be longer than the SERP allows - `seo.title` is written separately
    # per post and is the string that has to fit (see strategy/06). This only catches
    # an H1 so long that no sensible seo.title can be derived from it.
    title = p["title"]
    if pixel_width(title, 9.5) > 700:
        warnings.append(f"{where}: H1 is very long, write a short seo.title: {title!r}")
    # Token coverage, not substring: "How to See the Tags on Any YouTube Video" covers
    # "youtube video tags" perfectly well, and reads like a sentence rather than a slug.
    kw_tokens = words(p["primary_keyword"]) - {stem(w) for w in STOP}
    have = words(title)
    if kw_tokens and len(kw_tokens & have) / len(kw_tokens) < 0.7:
        missing = ", ".join(sorted(kw_tokens - have))
        warnings.append(f"{where}: title misses most of the keyword ({missing}): {title!r}")

used_tools = {t for p in P for t in p["tools"]}
orphans = sorted(set(TOOL) - used_tools)
if orphans:
    warnings.append(f"{len(orphans)} live tools no post links to: {', '.join(orphans)}")

used_tags = {t for p in P for t in p["tags"]}
for t in sorted(set(TAG) - used_tags):
    warnings.append(f"tag defined but unused: {t}")
# Not a defect: a tag simply is not created until its third post is planned, which
# is what sync_taxonomy.py enforces. Listed so the count is visible.
thin = sorted(t for t in used_tags if sum(t in p["tags"] for p in P) < 2)
for t in thin:
    infos.append(f"tag held back until a second post needs it: {t} "
                 f"({sum(t in p['tags'] for p in P)} planned)")

print(f"{len(P)} posts, {len(C)} clusters, {len(CAT)} categories, {len(TAG)} tags")
for i in infos:
    print(f"  info  {i}")
for w in warnings:
    print(f"  warn  {w}")
for e in errors:
    print(f"  ERROR {e}")
print(f"\n{len(errors)} errors, {len(warnings)} warnings, {len(infos)} held-back tags")
sys.exit(1 if errors else 0)
