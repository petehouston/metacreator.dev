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
];

const nextConfig: NextConfig = {
  images: {
    remotePatterns,
    formats: ["image/avif", "image/webp"],
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
    ];
  },
};

export default nextConfig;
