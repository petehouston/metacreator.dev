import { describe, expect, it } from "vitest";

import { unclaimedSections } from "@/components/admin/screens/settings-screen";
import type { SettingItem } from "@/lib/admin/types";

/**
 * The net under the curated section list.
 *
 * The sections on this screen are hand-written, so a setting none of them claims
 * is invisible rather than misplaced — an admin cannot reach it and nothing fails.
 * `features.search_enabled` shipped that way: seeded, public, defaulting to off,
 * and impossible to switch on from the UI.
 *
 * The seeder that would catch this by comparison lives in the other app, and each
 * container mounts only its own, so no test on either side can read both halves.
 * Hence a runtime guarantee instead of a drift check, and this asserts it.
 */
function setting(key: string, group: string): SettingItem {
  return {
    key,
    group,
    type: "bool",
    is_public: true,
    is_secret: false,
    description: null,
    value: false,
    is_set: null,
  };
}

describe("unclaimedSections", () => {
  it("adds nothing when every setting has a home", () => {
    expect(
      unclaimedSections([
        setting("features.blog_enabled", "features"),
        setting("features.search_enabled", "features"),
        // Claimed by its group rather than by name.
        setting("blog.posts_per_page", "blog"),
      ]),
    ).toEqual([]);
  });

  it("surfaces a feature flag no section claims by name", () => {
    const sections = unclaimedSections([
      setting("features.blog_enabled", "features"),
      setting("features.some_future_flag", "features"),
    ]);

    expect(sections).toHaveLength(1);
    expect(sections[0].id).toBe("other");
    // Only the orphan — a flag that already has a home must not be shown twice.
    expect(sections[0].keys).toEqual(["features.some_future_flag"]);
  });

  it("surfaces a setting in a group no section sweeps", () => {
    const sections = unclaimedSections([setting("quarantine.thing", "quarantine")]);

    expect(sections[0]?.keys).toEqual(["quarantine.thing"]);
  });

  it("gives the catch-all a panel, so its settings actually render", () => {
    // A section with no panel renders an empty tab, which would be the same bug
    // wearing a different hat.
    const sections = unclaimedSections([setting("quarantine.thing", "quarantine")]);

    expect(sections[0].panels).toHaveLength(1);
    expect(sections[0].panels[0].keys).toBeUndefined();
  });
});

describe("the sections that ship", () => {
  it("claims every feature flag the product has today", () => {
    // Not derived from the seeder — it is unreachable from this container — so it
    // is a list, and it needs the same edit the screen does when a flag is added.
    // Its value is that the edit fails loudly here rather than silently in admin.
    const flags = [
      "features.blog_enabled",
      "features.changelog_enabled",
      "features.registration_enabled",
      "features.google_login_enabled",
      "features.magic_link_enabled",
      "features.billing_enabled",
      "features.newsletter_enabled",
      "features.search_enabled",
    ].map((key) => setting(key, "features"));

    expect(unclaimedSections(flags)).toEqual([]);
  });
});
