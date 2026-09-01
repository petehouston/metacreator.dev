import { describe, expect, it } from "vitest";

import { navItemsFor, navSectionsFor } from "@/components/app/nav-items";
import { footerNavFor, primaryNavFor } from "@/config/site";

/**
 * The switch is only useful if every surface drops the same links.
 *
 * These are pure list helpers precisely so that "the sidebar hid it but the ⌘K
 * palette still opens it" is a test failure rather than something discovered by a
 * user landing on a 404.
 */
describe("navigation with billing disabled", () => {
  it("drops Plan & billing from the dashboard nav", () => {
    const hrefs = navItemsFor(false).map((item) => item.href);

    expect(hrefs).not.toContain("/dashboard/billing");
    expect(hrefs).toContain("/dashboard/runs");
  });

  it("keeps the dashboard nav intact while billing is on", () => {
    expect(navItemsFor(true).map((item) => item.href)).toContain("/dashboard/billing");
  });

  it("leaves no empty section behind", () => {
    for (const section of navSectionsFor(false)) {
      expect(section.items.length).toBeGreaterThan(0);
    }
  });

  it("drops Pricing from the header and the footer together", () => {
    expect(primaryNavFor(false).map((item) => item.href)).not.toContain("/pricing");

    const footerHrefs = footerNavFor(false).flatMap((group) =>
      group.links.map((link) => link.href),
    );

    expect(footerHrefs).not.toContain("/pricing");
    // Only the priced link goes; the group it lived in is still useful.
    expect(footerHrefs).toContain("/tools");
  });

  it("keeps Pricing in both while billing is on", () => {
    expect(primaryNavFor(true).map((item) => item.href)).toContain("/pricing");
    expect(
      footerNavFor(true).flatMap((group) => group.links.map((link) => link.href)),
    ).toContain("/pricing");
  });
});
