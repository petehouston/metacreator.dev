import { describe, expect, it } from "vitest";

import { actionPending, actionVerb } from "@/lib/tool-action";

/**
 * The button label is derived from the tool name, which means a badly-behaved rule
 * ships a wrong verb on every future tool at once rather than on one page. These
 * tests pin the derivation, not the current catalog.
 */
describe("actionVerb", () => {
  it("takes the verb from the agent noun at the end of the name", () => {
    expect(actionVerb("Headline & Title Analyzer")).toBe("Analyze");
    expect(actionVerb("Hashtag Generator")).toBe("Generate");
    expect(actionVerb("X Thread Splitter")).toBe("Split");
    expect(actionVerb("Caption Translator")).toBe("Translate");
  });

  it("keeps looking further left when the last word carries no action", () => {
    expect(actionVerb("Thumbnail Downloader for Shorts")).toBe("Download");
  });

  it("prefers a slug override over the derived verb", () => {
    expect(actionVerb("Giveaway Winner Picker", "giveaway-winner-picker")).toBe(
      "Pick a winner",
    );
    // …and only for that slug — the same name elsewhere still derives.
    expect(actionVerb("Giveaway Winner Picker")).toBe("Pick");
  });

  it("does not read the site's own vocabulary as an action", () => {
    // "creator" is a descriptor here, not an agent noun — it must not yield "Create".
    expect(actionVerb("Creator Media Kit")).toBe("Run tool");
  });

  it("falls back to 'Run tool' rather than inventing a verb", () => {
    expect(actionVerb("Platform Almanac")).toBe("Run tool");
  });
});

describe("actionPending", () => {
  it("inflects the verb rather than reverting to a generic spinner label", () => {
    expect(actionPending("Hashtag Generator")).toBe("Generating…");
    expect(actionPending("Headline Analyzer")).toBe("Analyzing…");
  });

  it("doubles a final consonant only where English does", () => {
    expect(actionPending("Caption Trimmer")).toBe("Trimming…");
    expect(actionPending("Caption Translator")).toBe("Translating…");
  });

  it("inflects only the leading word of a multi-word action", () => {
    expect(actionPending("Giveaway Winner Picker", "giveaway-winner-picker")).toBe(
      "Picking a winner…",
    );
  });

  it("stays generic when the verb is generic", () => {
    expect(actionPending("Platform Almanac")).toBe("Running…");
  });
});
