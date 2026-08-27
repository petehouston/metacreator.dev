import type { MetadataRoute } from "next";

import { siteConfig } from "@/config/site";

export default function robots(): MetadataRoute.Robots {
  return {
    rules: [
      {
        userAgent: "*",
        allow: "/",
        // Authenticated areas and the API carry no ranking value and would waste
        // crawl budget; search-result URLs create infinite duplicate paths.
        disallow: ["/admin", "/dashboard", "/api/", "/login", "/register", "/*?q="],
      },
    ],
    sitemap: `${siteConfig.url}/sitemap.xml`,
    host: siteConfig.url,
  };
}
