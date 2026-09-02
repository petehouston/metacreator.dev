#!/usr/bin/env python3
"""Publishes a written post file to the production blog through the admin API.

    python3 scripts/publish.py posts/how-to-see-tags-on-a-youtube-video.md            # dry run
    python3 scripts/publish.py posts/*.md --apply                                     # create/update as draft
    python3 scripts/publish.py posts/x.md --apply --status published

Rules the script enforces, because a human will forget one:

* `audit.py` has to pass. Publishing something that fails the audit needs
  --skip-audit and is a deliberate act.
* Nothing reaches `published` unless you ask for it. The default status comes from
  the post header, and the header template says `draft`.
* A slug is claimed once. On the second run the post is PATCHed, never duplicated -
  and a slug change after publication would create a redirect, so the ledger in
  posts/.published.json is the record of which remote post a file owns.

Sharp edge worth knowing: `SavePostAction::resolveSlug` regenerates an *unpublished*
post's slug from its title on any save that omits `slug` (a published one is left
alone, because its slug is its ranking). This script therefore always sends the slug
from the post header. Hand-written PATCHes against a draft should do the same, or
the draft quietly moves to a different URL.
"""
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

import mc_api
from _common import ROOT
from audit import audit
from md2blocks import compile_post

LEDGER = ROOT / "posts" / ".published.json"
MEDIA_LEDGER = ROOT / "assets" / ".media.json"


def ledger() -> dict:
    """Post ids for the current site.

    Keyed by site, because a local stack and production have different post tables:
    publishing locally used to overwrite production's ids, after which the next
    production publish looked up an id that does not exist there.
    """
    return _all_ledgers().get(mc_api.SITE, {})


def _all_ledgers() -> dict:
    return json.loads(LEDGER.read_text()) if LEDGER.exists() else {}


def remember(slug: str, row: dict) -> None:
    data = _all_ledgers()
    data.setdefault(mc_api.SITE, {})[slug] = {
        "id": row.get("id"), "status": row.get("status"),
        "url": f"{mc_api.SITE}/blog/{slug}",
    }
    LEDGER.write_text(json.dumps(data, indent=1, sort_keys=True) + "\n")


def media_ledger() -> dict:
    return json.loads(MEDIA_LEDGER.read_text()) if MEDIA_LEDGER.exists() else {}


def featured_media_id(meta: dict) -> int | None:
    """Uploads the post's featured image once and remembers its numeric id.

    Keyed per site, because production and a local stack have different media
    tables and reusing an id across them would attach the wrong picture.
    """
    slug = meta["slug"]
    path = ROOT / "assets" / "featured" / f"{slug}.png"
    if not path.exists():
        return None
    ledger = media_ledger()
    site_key = mc_api.SITE
    if ledger.get(site_key, {}).get(slug):
        return ledger[site_key][slug]["numeric_id"]

    alt = meta.get("featured_image_alt") or (
        f"Article card for MetaCreator.dev: {meta['title']}")
    body = mc_api.upload("/admin/media", str(path),
                         {"alt_text": alt, "title": meta["title"]})
    row = body.get("data", {})
    ledger.setdefault(site_key, {})[slug] = {
        "id": row.get("id"), "numeric_id": row.get("numeric_id"), "url": row.get("url"),
    }
    MEDIA_LEDGER.write_text(json.dumps(ledger, indent=1, sort_keys=True) + "\n")
    return row.get("numeric_id")


def taxonomy_ids() -> tuple[dict, dict]:
    cats = {c["slug"]: c["id"] for c in mc_api.paged("/admin/post-categories")}
    tags = {t["slug"]: t["id"] for t in mc_api.paged("/admin/tags")}
    return cats, tags


def find_remote(slug: str) -> dict | None:
    known = ledger().get(slug)
    if known and known.get("id"):
        try:
            # `show` answers {"data": {"post": {...}, "blocks": ..., "seo": ...}};
            # the listing answers bare rows. Normalise to the row.
            data = mc_api.get(f"/admin/posts/{known['id']}").get("data", {})
            return data.get("post", data)
        except mc_api.ApiError as exc:
            if exc.status != 404:
                raise
    for row in mc_api.paged("/admin/posts", search=slug):
        if row.get("slug") == slug:
            return row
    return None


def payload_for(path: Path, cats: dict, tags: dict, status: str | None) -> dict:
    meta, document = compile_post(path)
    missing_cat = meta["category"] not in cats
    missing_tags = [t for t in meta.get("tags", []) if t not in tags]
    if missing_cat or missing_tags:
        raise SystemExit(
            f"{path.name}: taxonomy not synced yet - "
            f"{'category ' + meta['category'] if missing_cat else ''} "
            f"{'tags ' + ', '.join(missing_tags) if missing_tags else ''}\n"
            "Run: python3 scripts/sync_taxonomy.py --apply"
        )
    seo = meta.get("seo") or {}
    media_id = featured_media_id(meta)
    payload = {
        "title": meta["title"],
        "slug": meta["slug"],
        "excerpt": meta.get("excerpt", ""),
        "blocks": document,
        "status": status or meta.get("status", "draft"),
        "category_id": cats[meta["category"]],
        "category_ids": [cats[c] for c in meta.get("categories", []) if c in cats],
        "tags": [tags[t] for t in meta.get("tags", [])],
        "featured_media_id": media_id,
        "is_featured": bool(meta.get("is_featured", False)),
        "allow_comments": bool(meta.get("allow_comments", True)),
        "seo": {
            "title": seo.get("title") or meta["title"],
            "description": seo.get("description", ""),
            "focus_keyword": seo.get("focus_keyword") or meta.get("primary_keyword", ""),
            "canonical_url": seo.get("canonical_url") or None,
            "robots": seo.get("robots") or "index,follow",
            "og_title": seo.get("og_title") or seo.get("title") or meta["title"],
            "og_description": seo.get("og_description") or seo.get("description", ""),
            "twitter_card": seo.get("twitter_card") or "summary_large_image",
            "schema_type": seo.get("schema_type") or "BlogPosting",
            # The same card is the social card: one image, one look, no second asset
            # to keep in step.
            "og_media_id": media_id,
        },
    }
    if payload["status"] == "scheduled":
        if not meta.get("scheduled_for"):
            raise SystemExit(f"{path.name}: status is scheduled but no `scheduled_for` in the header")
        payload["scheduled_for"] = meta["scheduled_for"]
    return payload


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("paths", nargs="+")
    parser.add_argument("--apply", action="store_true")
    parser.add_argument("--status", choices=["draft", "scheduled", "published"])
    parser.add_argument("--skip-audit", action="store_true")
    args = parser.parse_args()

    files = [Path(p) for p in args.paths]
    if not args.skip_audit:
        blocked = False
        for path in files:
            errors, _ = audit(path)
            for e in errors:
                print(f"AUDIT FAIL {path.name}: {e}")
                blocked = True
        if blocked:
            print("\nNothing published. Fix the errors, or pass --skip-audit deliberately.")
            return 1

    cats, tags = taxonomy_ids()
    for path in files:
        payload = payload_for(path, cats, tags, args.status)
        remote = find_remote(payload["slug"])
        verb = "update" if remote else "create"
        print(f"{verb:6} {payload['slug']}  status={payload['status']}  "
              f"blocks={len(payload['blocks']['blocks'])}  tags={len(payload['tags'])}  "
              f"image={'yes' if payload['featured_media_id'] else 'MISSING'}")
        if not args.apply:
            continue
        if remote:
            body = mc_api.patch(f"/admin/posts/{remote['id']}", payload)
        else:
            body = mc_api.post("/admin/posts", payload)
        # The write endpoints answer with {"data": {"post": {...}}}; the listing
        # endpoints answer with a bare row. Accept either.
        data = body.get("data", {})
        row = data.get("post", data)
        remember(payload["slug"], row)
        print(f"       -> {mc_api.SITE}/blog/{payload['slug']}  ({row.get('status')})")

    if not args.apply:
        print("\nDry run. Nothing was written. Re-run with --apply.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
