import { describe, expect, it } from "vitest";

import { formatMoney, formatNumber } from "@/lib/utils";

describe("formatNumber", () => {
  it("abbreviates large numbers the way a dashboard should", () => {
    expect(formatNumber(999)).toBe("999");
    expect(formatNumber(1000)).toBe("1k");
    expect(formatNumber(12500)).toBe("12.5k");
    expect(formatNumber(1_000_000)).toBe("1M");
    expect(formatNumber(2_400_000)).toBe("2.4M");
  });

  it("drops a trailing .0 rather than showing '1.0k'", () => {
    expect(formatNumber(2000)).toBe("2k");
    expect(formatNumber(3_000_000)).toBe("3M");
  });
});

describe("formatMoney", () => {
  it("omits cents on whole amounts, because '$19.00' reads worse on a pricing card", () => {
    expect(formatMoney(1900)).toBe("$19");
    expect(formatMoney(18000)).toBe("$180");
  });

  it("keeps cents when they are meaningful", () => {
    expect(formatMoney(1999)).toBe("$19.99");
    expect(formatMoney(950)).toBe("$9.50");
  });
});
