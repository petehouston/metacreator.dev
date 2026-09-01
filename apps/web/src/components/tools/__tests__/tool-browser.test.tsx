import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";

import { ToolBrowser } from "@/components/tools/tool-browser";
import type { ToolCategory, ToolSummary } from "@/lib/types";

// The component reads the live URL through this hook; Next keeps it in step with the
// history writes the component makes.
const searchParams = vi.hoisted(() => ({ current: new URLSearchParams() }));

vi.mock("next/navigation", () => ({
  useSearchParams: () => searchParams.current,
}));

/**
 * Trending is fetched rather than delivered with the page, so the ranking is
 * stubbed here. The default is an empty one — the shape a first paint has, and
 * the shape a failed request leaves behind.
 */
const trending = vi.hoisted(() => ({
  current: { days: 3, minimum_runs: 1, slugs: [] as string[] },
}));

vi.mock("@/lib/http", () => ({
  apiFetch: vi.fn(async () => ({ ok: true as const, data: { data: trending.current } })),
}));

/**
 * Favourites come from the provider, which the browser renders outside of. The
 * hook falls back to an inert value there, so a signed-in case has to be stubbed.
 */
const favorites = vi.hoisted(() => ({ current: { slugs: [] as string[], enabled: false } }));

vi.mock("@/components/tools/favorites-provider", () => ({
  useFavorites: () => ({
    slugs: favorites.current.slugs,
    enabled: favorites.current.enabled,
    loading: false,
    isFavorite: (slug: string) => favorites.current.slugs.includes(slug),
    toggle: vi.fn(),
  }),
}));

function tool(name: string, platform: string, runs = 0): ToolSummary {
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
    stats: { runs, avg_duration_ms: 0 },
  };
}

const tools = [
  tool("Alpha", "youtube", 10),
  tool("Zeta", "youtube", 5),
  tool("Beta", "tiktok", 1),
];
const categories: ToolCategory[] = [];

function activeChips() {
  return screen
    .getAllByRole("button", { pressed: true })
    .map((button) => button.textContent?.trim());
}

function sortControl(): HTMLSelectElement {
  return screen.getByLabelText(/sort/i) as HTMLSelectElement;
}

/** The card headings, in the order the grid renders them. */
function renderedOrder(): string[] {
  return screen.getAllByRole("heading", { level: 3 }).map((heading) => heading.textContent ?? "");
}

function setup(params: string, initial = {}) {
  searchParams.current = new URLSearchParams(params);

  return render(<ToolBrowser tools={tools} categories={categories} initial={initial} />);
}

describe("ToolBrowser filter seeding", () => {
  it("seeds the controls from the server-supplied params on a fresh load", () => {
    setup("platform=youtube&sort=az", { platform: "youtube", sort: "az" });

    expect(activeChips()).toContain("YouTube");
    expect(sortControl().value).toBe("az");
  });

  /**
   * The regression this file exists for. Pressing Back replays the props the server
   * rendered for the URL that was *first* requested — here, an unfiltered /tools —
   * while the address bar still carries the filters the user had applied. Trusting
   * `initial` over the URL is what silently reset the filters.
   */
  it("prefers the URL over stale server props when the browser restores the page", () => {
    setup("platform=tiktok&sort=za");

    expect(activeChips()).toContain("TikTok");
    expect(sortControl().value).toBe("za");
    expect(screen.getByText(/1 of 3 tools/)).toBeInTheDocument();
  });

  it("drops values that do not name a real filter", () => {
    setup("platform=myspace&sort=sideways");

    expect(activeChips()).toContain("Any");
    expect(sortControl().value).toBe("popular");
    expect(screen.getByText(/3 of 3 tools/)).toBeInTheDocument();
  });
});

describe("ToolBrowser sorting", () => {
  it("puts the sort control beside the result count, not among the filters", () => {
    setup("");

    const count = screen.getByText(/3 of 3 tools/);
    const row = count.parentElement as HTMLElement;

    // Same row as the count, which is what makes it read as "ordering these"
    // rather than as one more thing narrowing them.
    expect(within(row).getByLabelText(/sort/i)).toBe(sortControl());
  });

  it("orders by lifetime runs under Popular", () => {
    setup("");

    expect(renderedOrder()).toEqual(["Alpha", "Zeta", "Beta"]);
  });

  it("puts saved tools first and alphabetises each half under Favourites", async () => {
    favorites.current = { slugs: ["zeta"], enabled: true };
    setup("");

    await userEvent.selectOptions(sortControl(), "favorites");

    expect(renderedOrder()).toEqual(["Zeta", "Alpha", "Beta"]);

    favorites.current = { slugs: [], enabled: false };
  });

  it("offers Favourites to a signed-out visitor but disables it", () => {
    favorites.current = { slugs: [], enabled: false };
    setup("");

    const option = within(sortControl()).getByRole("option", { name: /favourites/i });

    // Disabled rather than hidden: a control that vanishes teaches nobody the
    // feature exists.
    expect(option).toBeDisabled();
  });

  it("ranks by the server's trending order, with the rest below by lifetime runs", async () => {
    trending.current = { days: 3, minimum_runs: 1, slugs: ["beta", "zeta"] };
    setup("");

    await userEvent.selectOptions(sortControl(), "trending");

    // The ranking arrives from a fetch, so the order settles a tick after the
    // sort is chosen — waiting on the order itself is the only honest signal now
    // that the option label no longer announces the window.
    await waitFor(() => expect(renderedOrder()).toEqual(["Beta", "Zeta", "Alpha"]));

    trending.current = { days: 3, minimum_runs: 1, slugs: [] };
  });
});
