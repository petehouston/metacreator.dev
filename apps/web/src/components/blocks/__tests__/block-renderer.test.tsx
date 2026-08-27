import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";

import { BlockRenderer } from "@/components/blocks/block-renderer";
import type { Block } from "@/lib/types";

/**
 * The renderer is shared by tool instructions and blog posts, and those two carry
 * different shapes for the same block types. These tests pin the shapes down: a
 * "simplification" that drops one of them silently blanks half the content on the
 * site, and nothing else would catch it.
 */
function renderBlocks(blocks: Omit<Block, "id">[]) {
  return render(
    <BlockRenderer
      document={{
        version: 1,
        blocks: blocks.map((block, index) => ({ id: `b_${index}`, ...block })),
      }}
    />,
  );
}

describe("BlockRenderer", () => {
  it("renders a labelled placeholder for an unknown block instead of throwing", () => {
    // Content written by a newer deploy must survive a rollback intact.
    renderBlocks([{ type: "fromTheFuture", data: {} }]);

    expect(screen.getByText(/not supported by the current version/i)).toBeInTheDocument();
  });

  it("renders list items given as plain strings (tool instructions)", () => {
    renderBlocks([
      { type: "list", data: { style: "unordered", items: ["<strong>First</strong>", "Second"] } },
    ]);

    expect(screen.getByText("First")).toBeInTheDocument();
    expect(screen.getByText("Second")).toBeInTheDocument();
  });

  it("renders list items given as objects, including checklist state (blog posts)", () => {
    renderBlocks([
      {
        type: "list",
        data: {
          style: "checklist",
          items: [
            { html: "Done thing", checked: true },
            { html: "Pending thing", checked: false },
          ],
        },
      },
    ]);

    expect(screen.getByText("Done thing")).toBeInTheDocument();
    expect(screen.getByText("Pending thing")).toBeInTheDocument();
  });

  it("renders FAQ items under either key pair", () => {
    renderBlocks([
      { type: "faq", data: { items: [{ q: "Tool style?", a: "Yes" }] } },
      { type: "faq", data: { items: [{ question: "Post style?", answer: "Also yes" }] } },
    ]);

    expect(screen.getByText("Tool style?")).toBeInTheDocument();
    expect(screen.getByText("Post style?")).toBeInTheDocument();
  });

  it("renders a heading at the right level and clamps it to H2–H4", () => {
    renderBlocks([
      { type: "heading", data: { level: 2, text: "Section" } },
      { type: "heading", data: { level: 9, text: "Too deep" } },
    ]);

    expect(screen.getByRole("heading", { level: 2, name: "Section" })).toBeInTheDocument();
    // H1 belongs to the page title, so body headings never escape the 2–4 range.
    expect(screen.getByRole("heading", { level: 4, name: "Too deep" })).toBeInTheDocument();
  });

  it("renders a table with a header row", () => {
    renderBlocks([
      {
        type: "table",
        data: { has_header: true, rows: [["Tactic", "Result"], ["Arrows", "Fails"]] },
      },
    ]);

    expect(screen.getByRole("columnheader", { name: "Tactic" })).toBeInTheDocument();
    expect(screen.getByRole("cell", { name: "Arrows" })).toBeInTheDocument();
  });

  it("treats every row as data when the table has no header", () => {
    renderBlocks([
      { type: "table", data: { has_header: false, rows: [["a", "b"]] } },
    ]);

    expect(screen.queryByRole("columnheader")).not.toBeInTheDocument();
    expect(screen.getByRole("cell", { name: "a" })).toBeInTheDocument();
  });

  it("shows a callout's title alongside its body", () => {
    renderBlocks([
      { type: "callout", data: { tone: "tip", title: "Test it properly", html: "<p>Shrink it.</p>" } },
    ]);

    expect(screen.getByText("Test it properly")).toBeInTheDocument();
    expect(screen.getByText("Shrink it.")).toBeInTheDocument();
  });

  it("renders a button block as a link, and drops one with no destination", () => {
    renderBlocks([
      { type: "button", data: { label: "Go", href: "/tools/x" } },
      // The sanitiser empties a javascript: href; the renderer must not emit a
      // dead link for it.
      { type: "button", data: { label: "Blocked", href: "" } },
    ]);

    expect(screen.getByRole("link", { name: "Go" })).toHaveAttribute("href", "/tools/x");
    expect(screen.queryByRole("link", { name: "Blocked" })).not.toBeInTheDocument();
  });

  it("renders nothing for an empty document", () => {
    const { container } = render(<BlockRenderer document={{ version: 1, blocks: [] }} />);

    expect(container).toBeEmptyDOMElement();
  });
});
