import { describe, expect, it } from "vitest";

import { pageWindow } from "@/lib/pagination";

/**
 * The elision rules, which are the whole point of the helper: a reader must always
 * be able to reach the first and last pages, and a gap must never hide fewer pages
 * than the ellipsis it replaces.
 */
describe("pageWindow", () => {
  it("lists every page when they all fit inside the window", () => {
    expect(pageWindow(1, 5)).toEqual([1, 2, 3, 4, 5]);
    expect(pageWindow(3, 5)).toEqual([1, 2, 3, 4, 5]);
  });

  it("keeps the first and last page reachable from anywhere in the middle", () => {
    expect(pageWindow(30, 60)).toEqual([1, null, 28, 29, 30, 31, 32, null, 60]);
  });

  it("elides only on the far side when the current page is near an end", () => {
    expect(pageWindow(2, 60)).toEqual([1, 2, 3, 4, null, 60]);
    expect(pageWindow(59, 60)).toEqual([1, null, 57, 58, 59, 60]);
  });

  it("draws a one-page gap as the page itself rather than as an ellipsis", () => {
    // 1 … 3 4 5 6 7 … 9 would be longer than the numbers it hides, and would put a
    // page behind an ellipsis that the reader could simply have clicked.
    expect(pageWindow(5, 9)).toEqual([1, 2, 3, 4, 5, 6, 7, 8, 9]);
  });

  it("handles a single page without a duplicate or a gap", () => {
    expect(pageWindow(1, 1)).toEqual([1]);
  });

  it("never returns a page outside the range", () => {
    for (const entry of pageWindow(1, 3)) {
      if (entry !== null) expect(entry).toBeGreaterThanOrEqual(1);
      if (entry !== null) expect(entry).toBeLessThanOrEqual(3);
    }
  });
});
