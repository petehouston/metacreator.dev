#!/usr/bin/env python3
"""On-page SEO lint for a written post, run before it is allowed to publish.

Every rule here is one of the rules in strategy/06-onpage-seo-standard.md. If you
change one, change it there too - the document is what a human reads and this is
what the pipeline enforces.

    python3 scripts/audit.py posts/*.md
"""
from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

from _common import categories, pixel_width, posts, tags, tools
from md2blocks import compile_post, plain_text, tool_slugs_in

def stem(word: str) -> str:
    return word[:-1] if len(word) > 3 and word.endswith("s") else word


def word_set(text: str) -> set[str]:
    # Commas are stripped first so "1,000" matches "1000".
    return {stem(w) for w in re.findall(r"[a-z0-9]+", text.lower().replace(",", ""))}


# Function words carry no ranking weight and force stilted copy when a linter
# insists on them being present.
STOP = {"a", "an", "the", "to", "of", "on", "in", "for", "is", "how", "do", "does",
        "and", "your", "my", "with", "from", "what", "why", "can", "you"}

PLAN = {p["slug"]: p for p in posts()}
CATEGORIES, TAGS, TOOLS = categories(), tags(), tools()


def audit(path: Path) -> tuple[list[str], list[str]]:
    errors: list[str] = []
    warns: list[str] = []
    meta, document = compile_post(path)
    blocks = document["blocks"]
    text = plain_text(document)
    words = len(text.split())
    keyword = (meta.get("primary_keyword") or "").lower()
    seo = meta.get("seo") or {}

    for field in ("slug", "title", "excerpt", "category", "tags", "primary_keyword", "status"):
        if not meta.get(field):
            errors.append(f"header is missing `{field}`")

    plan = PLAN.get(meta.get("slug", ""))
    if plan is None:
        warns.append("slug is not in plan/posts.json - unplanned posts do not get internal links")
    else:
        if plan["primary_keyword"].lower() != keyword:
            errors.append(f"primary keyword drifted from the plan: {plan['primary_keyword']!r}")
        target = plan["target_words"]
        if words < target * 0.7:
            warns.append(f"{words} words against a {target}-word target")
        if words > target * 1.6:
            warns.append(f"{words} words is well past the {target}-word target - split it")

    if meta.get("category") not in CATEGORIES:
        errors.append(f"unknown category: {meta.get('category')}")
    for tag in meta.get("tags", []):
        if tag not in TAGS:
            errors.append(f"unknown tag: {tag}")

    # ── titles and metas ────────────────────────────────────────────────────
    seo_title = seo.get("title") or meta.get("title", "")
    if pixel_width(seo_title, 9.5) > 580:
        warns.append(f"seo.title will truncate (~{pixel_width(seo_title, 9.5):.0f}px of 580): {seo_title!r}")
    description = seo.get("description", "")
    if not description:
        errors.append("seo.description is empty - Google will write one for us")
    elif not 120 <= len(description) <= 165:
        warns.append(f"seo.description is {len(description)} characters; aim for 140-160")
    # Token coverage: "download a YouTube thumbnail" carries "download youtube
    # thumbnail" and reads like English. Requiring the exact string produces copy
    # written for a linter.
    kw_tokens_all = {stem(w) for w in re.findall(r"[a-z0-9]+", keyword)
                     if len(w) > 2 and w not in STOP}
    if keyword and not kw_tokens_all <= word_set(description):
        warns.append("primary keyword is not in the meta description")
    # Token-wise, not phrase-wise: "how-to-see-tags-on-a-youtube-video" carries
    # "youtube video tags" perfectly well and reads better in a SERP than the phrase
    # jammed in verbatim.
    slug_words = {stem(w) for w in meta.get("slug", "").split("-")}
    missing_in_slug = [w for w in keyword.split() if w not in STOP
                       if stem(w) not in slug_words]
    if keyword and missing_in_slug:
        warns.append(f"slug is missing keyword words ({', '.join(missing_in_slug)})")

    # ── structure ───────────────────────────────────────────────────────────
    headings = [b for b in blocks if b["type"] == "heading"]
    h2s = [b for b in headings if b["data"]["level"] == 2]
    if len(h2s) < 3:
        warns.append(f"only {len(h2s)} H2s - a scannable post has at least three")
    # Token coverage again, not substring: a heading that reads like a sentence and
    # carries the keyword's words is better copy than one with the phrase wedged in.
    kw_words = {stem(w) for w in re.findall(r"[a-z0-9]+", keyword)
                if len(w) > 2 and w not in STOP}
    covered = any(
        kw_words and len(kw_words & word_set(h["data"]["text"])) / len(kw_words) >= 0.6
        for h in headings
    )
    if keyword and not covered:
        warns.append("no heading carries the keyword")

    lead = next((b for b in blocks if b["type"] == "paragraph"), None)
    if lead is None:
        errors.append("no opening paragraph")
    elif keyword and not kw_tokens_all <= word_set(re.sub(r"<[^>]+>", "", lead["data"]["html"])):
        warns.append("keyword is not in the opening paragraph")

    # ── the point of the post: sending someone to a tool ────────────────────
    cards = tool_slugs_in(document)
    if not cards:
        errors.append("no toolCard block - the post has nowhere to send the reader")
    for slug in cards:
        if slug not in TOOLS:
            errors.append(f"toolCard points at a tool that does not exist: {slug}")

    links = re.findall(r'href="([^"]+)"', str(blocks))
    internal = [l for l in links if l.startswith("/")]
    external = [l for l in links if l.startswith("http")]
    if len(internal) < 3:
        warns.append(f"{len(internal)} internal links - three is the floor, five is better")
    for link in internal:
        if link.startswith("/tools/") and link.split("/tools/")[1].strip("/") not in TOOLS:
            errors.append(f"internal link to a tool that does not exist: {link}")
        if link.startswith("/blog/"):
            target = link.split("/blog/")[1].strip("/")
            # The archive routes docs/16 specifies are not built - see
            # strategy/09-technical-seo-gaps.md. Linking one publishes a 404.
            if target.startswith(("category/", "tag/")):
                errors.append(f"link to an archive route that does not exist: {link}")
            elif target and target not in PLAN:
                warns.append(f"internal link to an unplanned post: {link}")
    if not external:
        warns.append("no outbound citation - claims about a platform should point at its own docs")

    # ── honesty and hygiene ─────────────────────────────────────────────────
    for block in blocks:
        if block["type"] == "image" and not block["data"]["alt"].strip():
            errors.append(f"image without alt text: {block['data']['url']}")
    if keyword:
        density = text.lower().count(keyword) / max(words, 1) * 100
        if density > 2.5:
            warns.append(f"keyword density {density:.1f}% - that reads as stuffing")
    # Evergreen: no dates anywhere in the copy or the metadata (strategy/07).
    dated = re.findall(
        r"\b(?:20[2-3]\d(?![\dx×p])|"
        r"(?:January|February|March|April|May|June|July|August|September|October|"
        r"November|December)\s+20[2-9]\d|"
        r"as of \w+|last updated|checked on)\b",
        f"{text} {seo_title} {description} {meta.get('excerpt', '')}", re.I)
    for hit in sorted(set(dated)):
        errors.append(f"dated copy in an evergreen post: {hit!r}")

    if not any(b["type"] == "faq" for b in blocks):
        warns.append("no FAQ block, so the post emits no FAQPage schema")

    return errors, warns


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("paths", nargs="+")
    parser.add_argument("--strict", action="store_true", help="treat warnings as failures")
    args = parser.parse_args()

    failed = 0
    for name in args.paths:
        path = Path(name)
        errors, warns = audit(path)
        status = "FAIL" if errors else ("WARN" if warns else "PASS")
        print(f"{status}  {path.name}")
        for e in errors:
            print(f"      ERROR {e}")
        for w in warns:
            print(f"      warn  {w}")
        if errors or (args.strict and warns):
            failed += 1
    print(f"\n{len(args.paths) - failed} passing, {failed} failing")
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
