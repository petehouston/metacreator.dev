import type { NextConfig } from "next";

/**
 * Hosts `next/image` is allowed to optimise from.
 *
 * Media is admin-uploaded, so the set is the storage backends we actually use —
 * MinIO locally, Spaces/CDN in production — plus the stock host the demo seeder
 * points at. An unlisted host renders nothing rather than proxying arbitrary URLs,
 * which is the behaviour we want.
 */
const remotePatterns: NonNullable<NextConfig["images"]>["remotePatterns"] = [
  { protocol: "http", hostname: "localhost", port: "9000" },
  { protocol: "http", hostname: "minio", port: "9000" },
  { protocol: "https", hostname: "**.digitaloceanspaces.com" },
  { protocol: "https", hostname: "**.cdn.digitaloceanspaces.com" },
  { protocol: "https", hostname: "images.unsplash.com" },
  // Media served by the API's own public disk, which nginx serves from
  // `/storage/` on the same host as this app (deploy/templates/nginx-api.conf).
  // The URL Laravel emits is absolute — built from APP_URL — so it is a *remote*
  // pattern as far as next/image is concerned even though it is same-origin.
  // Without this entry every featured image renders as its alt text.
  { protocol: "https", hostname: "metacreator.dev", pathname: "/storage/**" },
  { protocol: "https", hostname: "www.metacreator.dev", pathname: "/storage/**" },
  // Development only: media is proxied through this app's own origin by the
  // `/media` rewrite below, so the URL is identical for the browser and for the
  // server-side fetch `next/image` makes. See MEDIA_UPSTREAM.
  { protocol: "http", hostname: "localhost", port: "3000", pathname: "/media/**" },
];

/**
 * Where `/media/*` is proxied to in development.
 *
 * Local uploads live in MinIO, which is reachable at two different addresses:
 * `localhost:9000` from the browser and `minio:9000` from inside the container.
 * `next/image` fetches the image server-side, so any absolute URL that is correct
 * for one is broken for the other - which is why images 404 or ECONNREFUSE
 * whichever of the two the API emits.
 *
 * Proxying through this app removes the split: the URL is `/media/...` on this
 * origin, which the browser and the Node server both resolve correctly. Unset in
 * production, where nginx serves media directly from the API's public disk and no
 * rewrite is registered.
 */
const MEDIA_UPSTREAM = process.env.MEDIA_UPSTREAM;

const nextConfig: NextConfig = {
  /**
   * Bundles the server together with only the dependencies it actually imports,
   * emitting `.next/standalone/server.js`.
   *
   * The production host runs that one file under systemd, so a release ships no
   * `node_modules` at all — which is what makes keeping five releases on a
   * droplet shared with ten other sites affordable.
   *
   * Next deliberately does not copy `.next/static` or `public/` into the
   * standalone output, on the assumption they may be served from a CDN.
   * We serve them from the same process, so `deploy/scripts/deploy.sh` copies
   * them in after the build. Removing this line would break that step and the
   * systemd unit's ExecStart path.
   */
  output: "standalone",

  images: {
    remotePatterns,
    formats: ["image/avif", "image/webp"],

    /**
     * Next 16 refuses to optimise an image whose host resolves to a private IP,
     * as an SSRF guard. Locally every storage host does: MinIO is `localhost:9000`
     * from the browser and `minio:9000` (172.16/12) from inside the container, so
     * *every* uploaded image 400s with "hostname resolved to private IP" no matter
     * what `remotePatterns` says.
     *
     * The guard is worth keeping in production, where the storage host is public
     * and an unexpected private-IP fetch would be a genuine SSRF signal. So this is
     * scoped to development rather than switched on outright.
     */
    dangerouslyAllowLocalIP: process.env.NODE_ENV !== "production",
  },

  async rewrites() {
    return {
      /**
       * Blog pagination as a path: `/blog/2`, `/blog/3`, … served by the listing
       * at `/blog`, which still reads the number from `?page=`.
       *
       * It has to be a rewrite rather than its own route: `/blog/[slug]` already
       * owns this segment, and two dynamic segments cannot share a level. Running
       * `beforeFiles` puts the numeric form in front of that route, so a post is
       * only ever resolved for a non-numeric slug — which every generated slug is.
       *
       * The internal param is `paged`, not `page`, so the listing can tell the two
       * forms apart: a request that arrives with `?page=` is a legacy URL, and is
       * redirected to the path form from the page itself.
       */
      beforeFiles: [{ source: "/blog/:page(\\d+)", destination: "/blog?paged=:page" }],
      afterFiles: MEDIA_UPSTREAM
        ? [{ source: "/media/:path*", destination: `${MEDIA_UPSTREAM}/:path*` }]
        : [],
      fallback: [],
    };
  },

  async redirects() {
    return [
      {
        // There is one catalog, not two. `/tools` is the page that ranks, it is
        // the page every card and every share links to, and it already knows who
        // is signed in — so a second in-app copy at `/dashboard/tools` was two
        // surfaces to keep in step for no gain.
        //
        // Permanent: the address is gone for good, and old links (bookmarks, the
        // ⌘K palette, anything pasted into a thread) should be rewritten by the
        // browser rather than re-resolved on every visit.
        source: "/dashboard/tools",
        destination: "/tools",
        permanent: true,
      },
      {
        source: "/dashboard/tools/:path*",
        destination: "/tools/:path*",
        permanent: true,
      },
      {
        // The query string this listing used to paginate with, moved to the path
        // form so each page is indexed under one URL. Next re-appends the matched
        // query to the destination, so the visitor lands on `/blog/3?page=3` - the
        // page ignores `page` and renders from the path, and the canonical it
        // emits is the clean `/blog/3`.
        source: "/blog",
        has: [{ type: "query", key: "page", value: "(?<page>\\d+)" }],
        destination: "/blog/:page",
        permanent: true,
      },
      {
        // `/blog/1` is the long way of saying `/blog`: page one has no number, so
        // there is one URL for it rather than two.
        //
        // `missing` breaks a redirect loop, and is not optional: `/blog?page=1`
        // becomes `/blog/1?page=1` above, which without this guard would come
        // straight back here and bounce between the two rules forever.
        source: "/blog/1",
        missing: [{ type: "query", key: "page" }],
        destination: "/blog",
        permanent: true,
      },
    ];
  },
};

export default nextConfig;
