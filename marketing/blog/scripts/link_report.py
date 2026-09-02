#!/usr/bin/env python3
"""Orphan and internal-link report across everything in posts/.

An orphan - a post no other post links to - is the most common way a content
programme quietly wastes a post. Run this at the end of every wave.
"""
from __future__ import annotations

import re
from collections import defaultdict
from pathlib import Path

from _common import ROOT, clusters, posts
from md2blocks import compile_post, tool_slugs_in

written = sorted((ROOT / "posts").glob("*.md"))
plan = {p["slug"]: p for p in posts()}
pillars = {c["pillar"] for c in clusters().values()}

inbound: dict[str, set[str]] = defaultdict(set)
tool_links: dict[str, set[str]] = defaultdict(set)
seen: list[str] = []

for path in written:
    meta, document = compile_post(path)
    slug = meta.get("slug", path.stem)
    seen.append(slug)
    body = str(document)
    for target in set(re.findall(r'href="/blog/([a-z0-9-]+)"', body)):
        inbound[target].add(slug)
    for tool in set(re.findall(r'href="/tools/([a-z0-9-]+)"', body)) | set(tool_slugs_in(document)):
        tool_links[tool].add(slug)

print(f"{len(seen)} written posts\n")

print("Orphans (no other written post links to them)")
orphans = [s for s in seen if not inbound[s] - {s}]
for slug in orphans or ["  none"]:
    print(f"  {slug}")

print("\nPillars and the spokes pointing at them")
for pillar in sorted(pillars):
    if pillar in seen:
        print(f"  {pillar}: {len(inbound[pillar])} inbound")

print("\nTool pages by inbound post links")
for tool, sources in sorted(tool_links.items(), key=lambda kv: -len(kv[1])):
    print(f"  {len(sources):3}  /tools/{tool}")

missing = [s for s in seen if s not in plan]
if missing:
    print("\nWritten but not in the plan: " + ", ".join(missing))
