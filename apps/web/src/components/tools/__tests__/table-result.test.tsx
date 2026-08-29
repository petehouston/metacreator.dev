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
        { swatch: "#35A5B4", rank: "Dominant", hex: "#35A5B4", rgb: "rgb(53, 165, 180)" },
        { swatch: "#5295CD", rank: "#2", hex: "#5295CD", rgb: "rgb(82, 149, 205)" },
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
