import { fireEvent, render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

import { ResultRenderer } from "@/components/tools/results";
import type { ToolResult } from "@/lib/types";

/**
 * The palette tools hand back colours, not just strings about colours: the strip and
 * the swatch column are the whole point, and the copy buttons are what people came
 * for. All three are declared by the tool, so this pins the contract.
 */
function result(): ToolResult {
  return {
    view: "table",
    summary: "Dominant colour is #35A5B4.",
    data: {
      columns: [
        { key: "swatch", label: "Colour", type: "color" },
        { key: "rank", label: "Rank" },
        { key: "hex", label: "Hex", copyable: true },
        { key: "rgb", label: "RGB", copyable: true },
      ],
      rows: [
        {
          swatch: "#35A5B4",
          rank: "Dominant",
          hex: "#35A5B4",
          rgb: "rgb(53, 165, 180)",
        },
        {
          swatch: "#5295CD",
          rank: "#2",
          hex: "#5295CD",
          rgb: "rgb(82, 149, 205)",
        },
      ],
    },
    meta: { palette: ["#35A5B4", "#5295CD", "javascript:alert(1)"] },
    warnings: [],
    artifacts: [],
  } as unknown as ToolResult;
}

describe("table result", () => {
  it("draws one band per valid colour in meta.palette", () => {
    render(<ResultRenderer result={result()} />);

    const strip = screen.getByLabelText("Extracted palette: #35A5B4, #5295CD");
    expect(strip.children).toHaveLength(2);
  });

  it("fills the swatch column with the row's colour", () => {
    render(<ResultRenderer result={result()} />);

    expect(screen.getByLabelText("Colour #35A5B4")).toHaveStyle({
      backgroundColor: "rgb(53, 165, 180)",
    });
  });

  it("gives every copyable cell its own copy button", () => {
    render(<ResultRenderer result={result()} />);

    // Two copyable columns across two rows.
    expect(screen.getAllByLabelText("Copy value")).toHaveLength(4);
  });
});

/**
 * A tag list is only useful pasted whole, so a column can opt into one button that
 * copies every value in it.
 */
describe("copy all column", () => {
  const tags: ToolResult = {
    view: "table",
    summary: "3 tags.",
    data: {
      columns: [
        { key: "tag", label: "Tag", copyable: true, copy_all: true },
        { key: "words", label: "Words", align: "right" },
      ],
      rows: [
        { tag: "claude code", words: 2 },
        { tag: "ai agents", words: 2 },
        { tag: "", words: 0 },
      ],
    },
    meta: {},
    warnings: [],
    artifacts: [],
  } as unknown as ToolResult;

  it("copies every non-empty value in the column, comma separated", async () => {
    const write = vi.fn().mockResolvedValue(undefined);
    Object.assign(navigator, { clipboard: { writeText: write } });

    render(<ResultRenderer result={tags} />);
    fireEvent.click(screen.getByLabelText("Copy all Tag values"));

    expect(write).toHaveBeenCalledWith("claude code, ai agents");
  });

  it("honours a column's own separator", async () => {
    const write = vi.fn().mockResolvedValue(undefined);
    Object.assign(navigator, { clipboard: { writeText: write } });

    // Hashtags are pasted space-separated; "#a, #b" is not what goes in a
    // description.
    render(
      <ResultRenderer
        result={
          {
            ...tags,
            data: {
              ...tags.data,
              columns: [
                {
                  key: "tag",
                  label: "Tag",
                  copyable: true,
                  copy_all: true,
                  copy_separator: " ",
                },
              ],
              rows: [{ tag: "#claudecode" }, { tag: "#aiagents" }],
            },
          } as unknown as ToolResult
        }
      />,
    );
    fireEvent.click(screen.getByLabelText("Copy all Tag values"));

    expect(write).toHaveBeenCalledWith("#claudecode #aiagents");
  });

  it("puts the button above the table, not inside its header", () => {
    render(<ResultRenderer result={tags} />);

    const button = screen.getByLabelText("Copy all Tag values");

    // On a wide table a header button scrolls out of reach, and it reads as a
    // control for the column rather than for the list.
    expect(button.closest("table")).toBeNull();
  });

  it("keeps a per-row copy button on the tag cells", () => {
    render(<ResultRenderer result={tags} />);

    // The empty tag renders as a dash, with nothing to copy.
    expect(screen.getAllByLabelText("Copy value")).toHaveLength(2);
  });

  it("hides the column button when there are no rows", () => {
    render(
      <ResultRenderer
        result={
          { ...tags, data: { ...tags.data, rows: [] } } as unknown as ToolResult
        }
      />,
    );

    expect(screen.queryByLabelText("Copy all Tag values")).toBeNull();
  });
});

/**
 * Some tools return several kinds of row — YouTube's direct completions and its A–Z
 * sweep are not the same list — and a tool can split them into `groups` rather than
 * concatenating them into one undifferentiated dump.
 */
function groupedResult(): ToolResult {
  return {
    view: "table",
    summary: "4 suggestions across 2 groups.",
    data: {
      columns: [
        { key: "rank", label: "#" },
        {
          key: "suggestion",
          label: "Search suggestion",
          copy_all: true,
          wrap: false,
        },
        {
          key: "search",
          label: "Open on YouTube",
          type: "link",
          text_key: "suggestion",
        },
      ],
      rows: [
        {
          rank: 1,
          suggestion: "sourdough starter recipe",
          search:
            "https://www.youtube.com/results?search_query=sourdough%20starter%20recipe",
        },
        {
          rank: 2,
          suggestion: "sourdough starter discard",
          search:
            "https://www.youtube.com/results?search_query=sourdough%20starter%20discard",
        },
        {
          rank: 1,
          suggestion: "how to feed sourdough starter",
          search:
            "https://www.youtube.com/results?search_query=how%20to%20feed%20sourdough%20starter",
        },
      ],
      groups: [
        {
          label: "Direct suggestions",
          hint: "What the search box completes for the seed on its own.",
          count: 2,
          rows: [
            {
              rank: 1,
              suggestion: "sourdough starter recipe",
              search:
                "https://www.youtube.com/results?search_query=sourdough%20starter%20recipe",
            },
            {
              rank: 2,
              suggestion: "sourdough starter discard",
              search:
                "https://www.youtube.com/results?search_query=sourdough%20starter%20discard",
            },
          ],
        },
        {
          label: "Questions & long-tail",
          count: 1,
          rows: [
            {
              rank: 1,
              suggestion: "how to feed sourdough starter",
              search:
                "https://www.youtube.com/results?search_query=how%20to%20feed%20sourdough%20starter",
            },
          ],
        },
      ],
    },
    meta: {},
    warnings: [],
    artifacts: [],
  } as unknown as ToolResult;
}

describe("grouped table result", () => {
  it("draws one table per group, under its own heading", () => {
    render(<ResultRenderer result={groupedResult()} />);

    expect(
      screen.getByRole("heading", { name: "Direct suggestions" }),
    ).toBeInTheDocument();
    expect(
      screen.getByText(
        "What the search box completes for the seed on its own.",
      ),
    ).toBeInTheDocument();
    expect(screen.getAllByRole("table")).toHaveLength(2);
  });

  it("keeps each group's rows inside that group", () => {
    render(<ResultRenderer result={groupedResult()} />);

    const [direct, questions] = screen.getAllByRole("table");

    expect(direct).toHaveTextContent("sourdough starter discard");
    expect(direct).not.toHaveTextContent("how to feed sourdough starter");
    expect(questions).toHaveTextContent("how to feed sourdough starter");
  });

  it("gives each group its own copy-all button, beside the heading", () => {
    render(<ResultRenderer result={groupedResult()} />);

    const buttons = screen.getAllByLabelText(
      "Copy all Search suggestion values",
    );

    // One per group, and outside the tables rather than in a column header.
    expect(buttons).toHaveLength(2);
    buttons.forEach((button) => {
      expect(button.closest("table")).toBeNull();
    });
  });

  it("labels the YouTube link with the search it runs", () => {
    render(<ResultRenderer result={groupedResult()} />);

    const link = screen.getByRole("link", { name: "sourdough starter recipe" });

    expect(link).toHaveAttribute(
      "href",
      "https://www.youtube.com/results?search_query=sourdough%20starter%20recipe",
    );
  });

  it("keeps a suggestion on one line", () => {
    render(<ResultRenderer result={groupedResult()} />);

    // The suggestion appears twice per row — once as the phrase, once as the
    // link's text — and it is the phrase's cell that must not wrap.
    const cell = screen
      .getAllByText("sourdough starter discard")[0]
      .closest("td");

    expect(cell).toHaveClass("whitespace-nowrap");
  });
});
