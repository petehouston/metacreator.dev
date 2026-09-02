# 09 - Technical gaps that limit this plan

Findings from the codebase as it stands (`docs/24-implementation-status.md` is the
authority on what exists; this is the subset that touches organic performance).
Each one is stated with what it costs the content programme and what unblocks it.

## Fixed: featured images were broken on both sites

All 95 posts carry a featured image. They were invisible on both environments, for
two unrelated reasons, and both are now fixed.

**Production: nginx could not read the document root.** `deploy.sh` set ownership with
an unprivileged `chgrp -R www-data`, but the deploy user is not a member of that group,
so the call failed on every path it owned - and the failure was swallowed by
`2>/dev/null || true`. The release's `api/public` stayed group-owned by the deploy user
at mode 750, leaving nginx (www-data) with no permission on the document root. PHP and
the Next proxy were unaffected, because neither needs nginx to read a file; only static
paths did, which in practice meant everything under `/storage/`. The chgrp now runs
through `remote_sudo` and is no longer allowed to fail quietly.

**Both: `next/image` refused the URL.** The optimiser fetches the image server-side and
checks it against `images.remotePatterns`. `metacreator.dev` was not listed - the URL
Laravel emits is absolute, so it counts as remote even though it is same-origin - and in
development every storage host resolves to a private IP, which Next 16 blocks outright
as an SSRF guard. `next.config.ts` now lists the production host and lifts the private-IP
guard in development only.

**Local also had the wrong disk.** `FILESYSTEM_DISK` was `local`, so uploads went to
`storage/app/private` - unreachable by design - and `Storage::url()` returned a relative
`/storage/...` path that the browser resolved against the Next frontend rather than the
API. The stack already ships a MinIO container, a bucket with public read, and
`remotePatterns` entries for it; local now uses it. Because MinIO answers to
`localhost:9000` from the browser and `minio:9000` from inside the container - and
`next/image` needs one URL that works in both - media is proxied through the frontend's
own origin by a `/media` rewrite, with `AWS_URL` pointing at it.

## Blocking, or close to it

**0. There are no category or tag archive pages.**
docs/16 specifies `/blog/category/{slug}` and `/blog/tag/{slug}`, and docs/09 says
both are "paginated and independently SEO-configurable". Neither route exists:
`apps/web/src/app/(site)/blog` contains `page.tsx` and `[slug]/page.tsx` and nothing
else, and `sitemap.ts` emits categories as `/blog?category={slug}` query URLs. Tags
have no URL at all. *Cost:* this plan's second axis - the platform tag as a hub, so
a reader can browse every Instagram post across all six categories - has nowhere to
live, and 41 tags produce zero indexable pages. Query-parameter facets are also
weaker than paths and more likely to be folded together as duplicates. *Fix:* two
route files plus their `generateMetadata`; the public API already serves both
listings (`GET /blog/categories`, `GET /blog/tags`, and the post list filters by
either). Until then, **the taxonomy is still worth maintaining** - it drives related
posts, the admin's own filtering, and it is the data those routes will read on the
day they exist - but no internal link in a post should point at an archive URL.

**0b. ~~The sitemap caps blog posts at 100.~~ Fixed, and it was worse than this note
said.**
`sitemap.ts` asked for `per_page: 100`, but a `per_page` is a *request*, not a
guarantee: `BlogController` caps it at **24**. So the ceiling was never at post 101 —
it was at post 25, and by the time anyone looked, eighty-four published posts were
absent from the sitemap with nothing failing to say so.

`sitemap.ts` now pages every list through an `allPages()` helper that reads
`meta.page.last_page` from the first response, with a hard stop at twenty pages. The
lesson worth keeping: **never trust a `per_page` you did not read back.** Anywhere the
frontend asks for "all of them", it should either page or assert the total it got
matches the total the API reported.

**1. No media upload path for post images.**
`AcceptsFiles` / Spaces uploads are not built, and the featured image can only be
chosen from the media library in the admin editor. The publishing pipeline in
`scripts/` therefore cannot set a featured image, and OG images fall back to the
site default. *Cost:* every post shares one social card, so shares look identical
and CTR from social suffers. *Workaround now:* set the featured image by hand in
`/c0ns0le/posts` after publishing - it is on the publish checklist in `06`.
*Fix:* the upload path, or a `featured_media_url` that creates a media row from a
URL.

**2. The header links to `/blog` even when the blog is switched off.**
`features.blog_enabled` 404s the API and drops sitemap URLs, but the Next.js header
still renders the link (docs/24). *Cost:* if the switch is ever flipped, the site
advertises a 404 in its global navigation - a real crawl-quality problem. *Fix:* the
public settings endpoint the frontend layout can read.

**3. A streamed 404 returns HTTP 200.**
Next cannot change the status once `loading.tsx` has begun the response; it injects
`noindex` instead. *Cost:* soft-404s in Search Console for any mistyped blog URL,
which dilutes crawl budget on a young site. It is documented Next behaviour, not a
defect, but it should be watched in the Coverage report rather than assumed benign.

## Worth fixing during waves 1-3

**4. Five block types do not exist yet** - `gallery`, `video`, `audio`, `gif` and
`newsletter`, all blocked on the media library. *Cost:* no inline newsletter capture
in an article, which is the single highest-converting placement a blog has, and no
galleries in the sizing posts where they would be most useful. The writing standard
in `06` avoids all five, so nothing breaks - we are simply leaving conversions on
the table. *Fix:* the `newsletter` block is worth building on its own, ahead of the
media-dependent four; `POST /newsletter/subscribe` already exists and works.

**5. Newsletter unsubscribe has no endpoint.** The footer promises one-click
unsubscribe and only an admin editing a row can honour it (docs/24). *Cost:* this is
a compliance exposure the moment we drive real signups from blog traffic, which is
exactly what this plan does. *Fix it before promoting the newsletter in posts.*

**6. `POST /api/v1/contact` does not exist.** The public form reports a failure.
*Cost:* outreach and link-building replies land nowhere. Anyone running a link
campaign off this content will need a working inbox first.

## Not blocking, but shapes the writing

**7. `content_html` is not generated** - the frontend renders blocks in an RSC, and
`content_text` is generated for search. This is a deliberate, well-reasoned
departure (docs/09), and it matters here only in one way: there is no HTML to hand a
newsletter renderer or a syndication partner, so republishing to Medium/Substack
would need the render endpoint built first.

**8. Thin tag archives are noindexed by design** (docs/16). That is correct, and it
is why `sync_taxonomy.py` holds a tag back until three posts need it. Do not create
tags ahead of the posts.

**9. Lighthouse budgets fail the build** - LCP < 2.0s, JS < 120KB on a tool page.
Relevant to the blog because a post that embeds heavy media will trip the same
budget. Keep embeds to one per post; the `embed` block already renders a facade
before the real iframe.

## Recommended sequence

If engineering time is available alongside the content waves:

| Priority | Item | Unblocks |
| --- | --- | --- |
| 1 | Newsletter unsubscribe endpoint | Promoting the list from blog posts at all |
| 2 | `newsletter` block | Mid-article capture, the best conversion surface a post has |
| 3 | Featured-image-from-URL, or the upload path | Distinct OG images per post |
| 4 | Public settings endpoint for the header | Blog kill switch that does not advertise a 404 |
| 5 | `POST /contact` | Link-building and outreach replies |

Ahead of all five, if the blog is the acquisition channel: **the category and tag
archive routes**. They are two page files against 41 tags and 6 categories that
currently index nowhere.
