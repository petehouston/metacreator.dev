import { describe, expect, it } from "vitest";

import {
  compact,
  domainOf,
  handle,
  initials,
  paletteFor,
  tiktokAge,
  widthFor,
  wrapText,
} from "@/tools/custom/social-card";

/**
 * The cards are painted on a canvas, which jsdom cannot rasterise — so what is
 * worth testing here is the arithmetic and the normalisation the painting depends
 * on. Every case below is a detail that gives a mock-up away when it is wrong:
 * "5.0K" under a post, "3 hours ago" under a TikTok comment, a URL running off the
 * edge of the card.
 */

describe("compact", () => {
  it("writes counts the way a feed writes them", () => {
    expect(compact(999)).toBe("999");
    expect(compact(1000)).toBe("1K");
    expect(compact(5200)).toBe("5.2K");
    expect(compact(1_400_000)).toBe("1.4M");
  });

  it("never prints a trailing .0, because no platform does", () => {
    expect(compact(5000)).toBe("5K");
    expect(compact(2_000_000)).toBe("2M");
  });

  it("floors a negative or fractional count rather than drawing NaN", () => {
    expect(compact(-5)).toBe("0");
    expect(compact(12.9)).toBe("12");
  });
});

describe("handle", () => {
  it("shows exactly one @ however many were typed", () => {
    expect(handle("someone")).toBe("@someone");
    expect(handle("@someone")).toBe("@someone");
    expect(handle("  @@someone  ")).toBe("@someone");
  });
});

describe("initials", () => {
  it("takes the first and last word, ignoring a leading @", () => {
    expect(initials("Riverside Bakery")).toBe("RB");
    expect(initials("@sam")).toBe("S");
    expect(initials("Ada Byron Lovelace")).toBe("AL");
  });

  it("falls back rather than drawing an empty disc", () => {
    expect(initials("   ")).toBe("?");
  });
});

describe("tiktokAge", () => {
  it("shortens a spelled-out age to TikTok’s own notation", () => {
    expect(tiktokAge("3 hours ago")).toBe("3h");
    expect(tiktokAge("2 days")).toBe("2d");
    expect(tiktokAge("45 minutes ago")).toBe("45m");
  });

  it("leaves an already-short age alone", () => {
    expect(tiktokAge("5m")).toBe("5m");
    expect(tiktokAge("1W")).toBe("1w");
  });

  it("passes anything else through — a typed date is a deliberate choice", () => {
    expect(tiktokAge("12 March")).toBe("12 March");
    expect(tiktokAge("")).toBe("3h");
  });
});

describe("domainOf", () => {
  it("shows the bare host, which is all Pinterest ever shows", () => {
    expect(domainOf("https://www.example.com/a/deep/path?utm_source=x")).toBe("example.com");
    expect(domainOf("example.co.uk")).toBe("example.co.uk");
  });

  it("returns the input unchanged when it is not a URL at all", () => {
    expect(domainOf("not a url")).toBe("not a url");
  });
});

describe("wrapText", () => {
  // A fixed-width measure keeps the assertions about the algorithm rather than
  // about a font that is not installed in CI.
  const measure = (text: string) => text.length * 10;

  it("wraps on word boundaries", () => {
    expect(wrapText("one two three four", 100, measure)).toEqual(["one two", "three four"]);
  });

  it("breaks a single word too wide for the line, rather than overrunning the card", () => {
    const lines = wrapText("https://example.com/a/very/long/path", 100, measure);

    expect(lines.length).toBeGreaterThan(1);
    expect(lines.every((line) => measure(line) <= 100)).toBe(true);
  });

  it("keeps a blank line where the writer put a blank line", () => {
    expect(wrapText("one\n\ntwo", 100, measure)).toEqual(["one", "", "two"]);
  });

  it("returns one empty line for empty input, so the caller never divides by zero", () => {
    expect(wrapText("", 100, measure)).toEqual([""]);
  });
});

describe("widthFor and paletteFor", () => {
  it("draws mobile narrower than desktop, which is what moves the wrap", () => {
    expect(widthFor("facebook", "mobile")).toBeLessThan(widthFor("facebook", "desktop"));
  });

  it("falls back to the platform’s first theme rather than drawing nothing", () => {
    expect(paletteFor("tiktok", "nonsense")).toEqual(paletteFor("tiktok", "dark"));
  });

  it("gives X all three of its own themes", () => {
    expect(paletteFor("x-reply", "dim").bg).toBe("#15202B");
    expect(paletteFor("x-reply", "dark").bg).toBe("#000000");
    expect(paletteFor("x-reply", "light").bg).toBe("#FFFFFF");
  });
});
