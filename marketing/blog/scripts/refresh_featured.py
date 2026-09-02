#!/usr/bin/env python3
"""Re-uploads regenerated featured images and repoints each post at the new file.

Why this exists rather than another publish.py run: publish.py's featured_media_id()
returns the cached id from assets/.media.json and never re-uploads, so a redrawn card
never reaches the site - and a publish run would rewrite the *body* of 133 live posts
to change a picture. This script touches the image and nothing else.

There is also no file-replace endpoint: MediaController::update accepts alt text and
titles only. Replacing the picture therefore means a new media row plus a PATCH, and
the row that was there before is superseded rather than overwritten.

The PATCH deliberately carries only slug, featured_media_id and version:

* no `blocks`, so SavePostAction takes no revision snapshot and rewrites no content
* no `status`, so a published post stays published and nothing is re-dated
* `slug` is required - resolveSlug regenerates an unpublished post's slug from its
  title on any save that omits it (publish.py's docstring has the long version)
* `version` is the optimistic-concurrency counter; a stale one is rejected, which is
  what should happen if somebody edited the post in the admin while this ran

    ../.venv/bin/python refresh_featured.py                 # dry run - reads only
    ../.venv/bin/python refresh_featured.py --apply         # upload + repoint
    ../.venv/bin/python refresh_featured.py --apply --slug x
    ../.venv/bin/python refresh_featured.py --cleanup       # soft-delete superseded rows

Cleanup only ever touches media ids this toolchain uploaded and has since replaced,
recorded under "superseded" in the ledger. It does not go hunting for orphans in the
library at large: media referenced from inside block content is not queryable through
the admin API, and `usage_count` is a dead column (nothing in the API ever increments
it), so "unused" cannot be established from outside. Deleting on that basis would
eventually take out a picture that is on a page.
"""
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

import mc_api
from _common import ROOT

MEDIA_LEDGER = ROOT / "assets" / ".media.json"
POST_LEDGER = ROOT / "posts" / ".published.json"
FEATURED = ROOT / "assets" / "featured"


def read(path: Path) -> dict:
    return json.loads(path.read_text()) if path.exists() else {}


def write(path: Path, data: dict) -> None:
    path.write_text(json.dumps(data, indent=1, sort_keys=True) + "\n")


def alt_for(slug: str, title: str) -> str:
    return f"Article card for MetaCreator.dev: {title}"


def refresh(slug: str, entry: dict, media: dict, apply: bool) -> str:
    """Uploads the current PNG for one slug and points its post at it."""
    path = FEATURED / f"{slug}.png"
    if not path.exists():
        return "no image on disk"

    post_id = entry.get("id")
    if not post_id:
        return "no post id in ledger"

    # Read the live row first: the version counter has to be the current one, and
    # the title is what the alt text is built from.
    data = mc_api.get(f"/admin/posts/{post_id}").get("data", {})
    row = data.get("post", data)
    old_id = media.get(mc_api.SITE, {}).get(slug, {}).get("numeric_id")

    if not apply:
        return (f"would upload {path.name} ({path.stat().st_size // 1024}KB) and "
                f"repoint post {post_id} v{row.get('version')} "
                f"(media {old_id} -> new)")

    body = mc_api.upload("/admin/media", str(path), {
        "alt_text": alt_for(slug, row.get("title", "")),
        "title": row.get("title", slug),
    })
    new = body.get("data", {})
    new_id = new.get("numeric_id")

    # og_media_id as well as featured_media_id: the blog page resolves its card as
    # `post.seo.og_image_url ?? post.featured_image?.url`, so a post whose SEO record
    # still points at the old row keeps serving the old picture to every scraper even
    # though the page itself shows the new one. publish.py sets both from one id and
    # so must this. Only og_media_id is sent inside `seo` - saveSeo() intersects the
    # keys it was given, so the title, description and robots line are left alone.
    mc_api.patch(f"/admin/posts/{post_id}", {
        "slug": slug,
        "featured_media_id": new_id,
        "seo": {"og_media_id": new_id},
        "version": row.get("version"),
    })

    site = media.setdefault(mc_api.SITE, {})
    previous = site.get(slug, {})
    site[slug] = {
        "id": new.get("id"), "numeric_id": new_id, "url": new.get("url"),
        # Kept so --cleanup knows exactly which rows this script replaced, and so a
        # rollback has the old id to point back at.
        "superseded": previous.get("superseded", []) + (
            [{"id": previous.get("id"), "numeric_id": previous.get("numeric_id")}]
            if previous.get("numeric_id") else []
        ),
    }
    write(MEDIA_LEDGER, media)
    return f"media {old_id} -> {new_id}, post {post_id} repointed"


def repoint_seo(slug: str, entry: dict, media: dict, apply: bool) -> str:
    """Points a post's SEO record at the media row already uploaded for it.

    Exists because the first production run repointed featured_media_id only, and
    og:image resolves from the SEO record first - so the pages were correct while
    every share card still served the superseded file. This repairs that without
    uploading anything a second time.
    """
    post_id = entry.get("id")
    current = media.get(mc_api.SITE, {}).get(slug, {}).get("numeric_id")
    if not post_id or not current:
        return "nothing to point at"

    data = mc_api.get(f"/admin/posts/{post_id}").get("data", {})
    row = data.get("post", data)
    seo_now = (data.get("seo") or {}).get("og_media_id")
    if seo_now == current:
        return f"already og_media_id={current}"
    if not apply:
        return f"would set og_media_id {seo_now} -> {current}"

    mc_api.patch(f"/admin/posts/{post_id}", {
        "slug": slug,
        "seo": {"og_media_id": current},
        "version": row.get("version"),
    })
    return f"og_media_id {seo_now} -> {current}"


def cleanup(media: dict, apply: bool) -> int:
    """Soft-deletes the media rows this script has replaced."""
    site = media.get(mc_api.SITE, {})
    done = 0
    for slug, entry in sorted(site.items()):
        for old in list(entry.get("superseded", [])):
            ref = old.get("id") or old.get("numeric_id")
            if not ref:
                continue
            if not apply:
                print(f"  would delete media {ref} (was {slug})")
                done += 1
                continue
            try:
                mc_api.request("DELETE", f"/admin/media/{ref}")
            except mc_api.ApiError as exc:
                if exc.status != 404:
                    raise
            entry["superseded"].remove(old)
            print(f"  deleted media {ref} (was {slug})")
            done += 1
        if apply:
            write(MEDIA_LEDGER, media)
    return done


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--slug")
    parser.add_argument("--apply", action="store_true",
                        help="actually write; without it nothing is uploaded or changed")
    parser.add_argument("--seo-only", action="store_true",
                        help="repoint seo.og_media_id at the media already uploaded; "
                             "uploads nothing")
    parser.add_argument("--cleanup", action="store_true",
                        help="soft-delete superseded media rows instead of refreshing")
    parser.add_argument("--limit", type=int, help="stop after N posts")
    args = parser.parse_args()

    who = mc_api.whoami()
    print(f"site {mc_api.SITE} as {who.get('email') or who.get('name') or '?'}"
          f"{'' if args.apply else '   [DRY RUN - no writes]'}\n")

    media = read(MEDIA_LEDGER)

    if args.cleanup:
        n = cleanup(media, args.apply)
        print(f"\n{'deleted' if args.apply else 'would delete'} {n} superseded media rows")
        return 0

    posts = read(POST_LEDGER).get(mc_api.SITE, {})
    if not posts:
        sys.exit(f"no posts recorded for {mc_api.SITE} in {POST_LEDGER}")

    n = 0
    for slug, entry in sorted(posts.items()):
        if args.slug and slug != args.slug:
            continue
        if args.limit and n >= args.limit:
            break
        if args.seo_only:
            print(f"{slug}: {repoint_seo(slug, entry, media, args.apply)}")
        else:
            print(f"{slug}: {refresh(slug, entry, media, args.apply)}")
        n += 1

    print(f"\n{n} posts {'refreshed' if args.apply else 'planned'}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
