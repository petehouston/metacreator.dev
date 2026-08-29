import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

import { ToolBrowser } from "@/components/tools/tool-browser";
import type { ToolCategory, ToolSummary } from "@/lib/types";

// The component reads the live URL through this hook; Next keeps it in step with the
// history writes the component makes.
const searchParams = vi.hoisted(() => ({ current: new URLSearchParams() }));

vi.mock("next/navigation", () => ({
  useSearchParams: () => searchParams.current,
}));

function tool(name: string, platform: string): ToolSummary {
  const slug = name.toLowerCase().replace(/\s+/g, "-");

  return {
    id: slug,
    slug,
    name,
    tagline: null,
    tier: { value: "free", label: "Free", description: "" },
    platforms: [platform],
    is_featured: false,
    is_deprecated: false,
    stats: { runs: 0, avg_duration_ms: 0 },
  };
}

const tools = [tool("Alpha", "youtube"), tool("Zeta", "youtube"), tool("Beta", "tiktok")];
const categories: ToolCategory[] = [];

function activeChips() {
  return screen
    .getAllByRole("button", { pressed: true })
    .map((button) => button.textContent?.trim());
}

describe("ToolBrowser filter seeding", () => {
  it("seeds the controls from the server-supplied params on a fresh load", () => {
    searchParams.current = new URLSearchParams("platform=youtube&sort=az");

    render(
      <ToolBrowser
        tools={tools}
        categories={categories}
        initial={{ platform: "youtube", sort: "az" }}
      />,
    );

    expect(activeChips()).toContain("YouTube");
    expect(activeChips()).toContain("A-Z");
  });

  /**
   * The regression this file exists for. Pressing Back replays the props the server
   * rendered for the URL that was *first* requested — here, an unfiltered /tools —
   * while the address bar still carries the filters the user had applied. Trusting
   * `initial` over the URL is what silently reset the filters.
   */
  it("prefers the URL over stale server props when the browser restores the page", () => {
    searchParams.current = new URLSearchParams("platform=tiktok&sort=za");

    render(<ToolBrowser tools={tools} categories={categories} initial={{}} />);

    expect(activeChips()).toContain("TikTok");
    expect(activeChips()).toContain("Z-A");
    expect(screen.getByText(/1 of 3 tools/)).toBeInTheDocument();
  });

  it("drops values that do not name a real filter", () => {
    searchParams.current = new URLSearchParams("platform=myspace&sort=sideways");

    render(<ToolBrowser tools={tools} categories={categories} initial={{}} />);

    expect(activeChips()).toContain("Any");
    expect(activeChips()).toContain("Popular");
    expect(screen.getByText(/3 of 3 tools/)).toBeInTheDocument();
  });
});
