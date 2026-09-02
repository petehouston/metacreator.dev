import { describe, expect, it } from "vitest";

import { isHome, parseSitemap, pathOf, sectionOf } from "../sitemap-parse";

/** A fragment shaped exactly like the one `app/sitemap.ts` renders. */
const XML = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<url>
<loc>https://metacreator.dev</loc>
<lastmod>2026-09-02T10:00:00.000Z</lastmod>
<changefreq>weekly</changefreq>
<priority>1</priority>
</url>
<url>
<loc>https://metacreator.dev/tools?category=analytics&amp;x=1</loc>
<lastmod>2026-09-02T10:00:00.000Z</lastmod>
<changefreq>weekly</changefreq>
<priority>0.7</priority>
</url>
<url>
<loc>https://metacreator.dev/blog/some-post</loc>
</url>
</urlset>`;

describe("parseSitemap", () => {
  it("reads every url, with the fields that are present", () => {
    const entries = parseSitemap(XML);

    expect(entries).toHaveLength(3);
    expect(entries[0]).toEqual({
      loc: "https://metacreator.dev",
      path: "/",
      lastmod: "2026-09-02T10:00:00.000Z",
      changefreq: "weekly",
      priority: 1,
    });
  });

  it("decodes the escaped ampersand in a filter URL", () => {
    // Getting this wrong would make every filtered listing look like a URL the
    // generator no longer produces, and the whole screen would cry drift.
    const [, filtered] = parseSitemap(XML);

    expect(filtered.loc).toBe("https://metacreator.dev/tools?category=analytics&x=1");
    expect(filtered.path).toBe("/tools?category=analytics&x=1");
  });

  it("leaves optional fields null rather than inventing them", () => {
    const [, , post] = parseSitemap(XML);

    expect(post).toMatchObject({ lastmod: null, changefreq: null, priority: null });
  });

  it("returns nothing for a response that is not a sitemap", () => {
    expect(parseSitemap("<html><body>502 Bad Gateway</body></html>")).toEqual([]);
  });
});

describe("sectionOf", () => {
  it.each([
    ["/", "home"],
    ["", "home"],
    ["/about", "static"],
    ["/tools", "static"],
    ["/tools?category=analytics", "tool-filters"],
    ["/tools/headline-analyzer", "tools"],
    ["/blog", "static"],
    ["/blog?category=growth", "blog-filters"],
    ["/blog/some-post", "blog"],
    ["/changelog", "changelog"],
    ["/changelog/v2", "changelog"],
  ])("puts %s in %s", (path, expected) => {
    expect(sectionOf(path)).toBe(expected);
  });
});

describe("pathOf", () => {
  it("keeps the query, which is what distinguishes a facet from its listing", () => {
    expect(pathOf("https://metacreator.dev/blog?category=growth")).toBe("/blog?category=growth");
  });

  it("hands back anything it cannot parse, rather than dropping the row", () => {
    expect(pathOf("not a url")).toBe("not a url");
  });
});

describe("isHome", () => {
  it("treats the bare origin and a lone slash as the same page", () => {
    // `siteConfig.url` carries no trailing slash, so the home entry parses to "/".
    expect(isHome(pathOf("https://metacreator.dev"))).toBe(true);
    expect(isHome("/tools")).toBe(false);
  });
});
