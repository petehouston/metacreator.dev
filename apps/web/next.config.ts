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
};

export default nextConfig;
