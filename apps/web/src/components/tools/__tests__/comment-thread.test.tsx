import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";

import { ResultRenderer } from "@/components/tools/results";
import type { ToolResult } from "@/lib/types";

/**
 * The point of the card over a table row is that the whole card is the jump to the
 * comment, so that is what these cover — plus the fallbacks for the two fields
 * YouTube does not always send.
 */
function result(comments: Record<string, unknown>[]): ToolResult {
  return {
    view: "list.comments",
    summary: "1 comment(s).",
    data: { comments },
    artifacts: [],
    warnings: [],
    meta: {},
  } as unknown as ToolResult;
}

const base = {
  author: "@AimanGarampil",
  body: "Great content.\nWatching all of it.",
  avatar: "https://yt3.ggpht.com/ytc/abc=s48-c-k",
  published_at: new Date(Date.now() - 3 * 86_400_000).toISOString(),
  published: "16 May 2026",
  likes: 1240,
  replies: 3,
  link: "https://www.youtube.com/watch?v=DXI7lqvhBDM&lc=Ugzz",
};

describe("comment thread result", () => {
  it("makes the whole card the link to the comment", () => {
    render(<ResultRenderer result={result([base])} />);

    const link = screen.getByRole("link");

    expect(link).toHaveAttribute("href", base.link);
    expect(link).toHaveAttribute("target", "_blank");
    // Author, body and counts all sit inside the one hit target.
    expect(link).toHaveTextContent("@AimanGarampil");
    expect(link).toHaveTextContent("Watching all of it.");
    // Counts are compacted the way the platform compacts them.
    expect(link).toHaveTextContent("1.2k");
  });

  it("stamps the comment with its age, not its date", () => {
    render(<ResultRenderer result={result([base])} />);

    expect(screen.getByText("3 days ago")).toBeInTheDocument();
  });

  it("falls back to the formatted date when the timestamp is unparseable", () => {
    render(<ResultRenderer result={result([{ ...base, published_at: "" }])} />);

    expect(screen.getByText("16 May 2026")).toBeInTheDocument();
  });

  it("draws an initial when there is no avatar, and never a non-https one", () => {
    render(
      <ResultRenderer
        result={result([{ ...base, avatar: "http://evil.example/a.png" }])}
      />,
    );

    expect(document.querySelector("img")).toBeNull();
    expect(screen.getByText("A")).toBeInTheDocument();
  });

  it("hides the reply count when there are no replies", () => {
    render(<ResultRenderer result={result([{ ...base, replies: 0 }])} />);

    expect(screen.queryByText("replies")).toBeNull();
  });

  it("renders a comment with no link as a plain card", () => {
    render(<ResultRenderer result={result([{ ...base, link: "" }])} />);

    expect(screen.queryByRole("link")).toBeNull();
    expect(screen.getByText("@AimanGarampil")).toBeInTheDocument();
  });
});
