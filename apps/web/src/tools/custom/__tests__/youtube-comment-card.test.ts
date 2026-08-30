import { describe, expect, it } from "vitest";

import {
  compactCount,
  normaliseHandle,
  relativeAge,
  wrapText,
} from "@/tools/custom/youtube-comment-card";

/**
 * The card is drawn on a canvas, which jsdom cannot rasterise — so what is worth
 * testing is the arithmetic the drawing depends on. Every case below is a detail
 * that gives a mock-up away when it is wrong: "1 hours ago", "5.0K", a URL running
 * off the edge of the card.
 */
describe("relativeAge", () => {
  it("singularises the unit at one", () => {
    expect(relativeAge(1, "hours")).toBe("1 hour ago");
    expect(relativeAge(1, "days")).toBe("1 day ago");
  });

  it("keeps the plural above one", () => {
    expect(relativeAge(5, "hours")).toBe("5 hours ago");
  });

  it("floors to at least one, because YouTube never shows a zero", () => {
    expect(relativeAge(0, "minutes")).toBe("1 minute ago");
    expect(relativeAge(Number.NaN, "weeks")).toBe("1 week ago");
  });
});

describe("compactCount", () => {
  it("leaves counts under a thousand alone", () => {
    expect(compactCount(0)).toBe("0");
    expect(compactCount(999)).toBe("999");
  });

  it("drops a trailing .0 rather than printing it", () => {
    expect(compactCount(5000)).toBe("5K");
    expect(compactCount(2_000_000)).toBe("2M");
  });

  it("keeps one decimal when there is one", () => {
    expect(compactCount(5200)).toBe("5.2K");
    expect(compactCount(1_400_000)).toBe("1.4M");
  });
});

describe("normaliseHandle", () => {
  it("shows exactly one @, however many were typed", () => {
    expect(normaliseHandle("john")).toBe("@john");
    expect(normaliseHandle("@john")).toBe("@john");
    expect(normaliseHandle("  @@john ")).toBe("@john");
  });
});

describe("wrapText", () => {
  // One "pixel" per character, so the expectations read as character counts.
  const measure = (text: string) => text.length;

  it("wraps on words", () => {
    expect(wrapText("aaa bbb ccc ddd", 7, measure)).toEqual(["aaa bbb", "ccc ddd"]);
  });

  it("keeps blank lines, because a comment's paragraph breaks are the comment", () => {
    expect(wrapText("one\n\ntwo", 10, measure)).toEqual(["one", "", "two"]);
  });

  it("breaks a word wider than the line instead of letting it overrun", () => {
    expect(wrapText("abcdefghij", 4, measure)).toEqual(["abcd", "efgh", "ij"]);
  });

  it("never returns nothing, so the layout always has a line to measure", () => {
    expect(wrapText("", 10, measure)).toEqual([""]);
  });
});
