import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";

import { ResultRenderer } from "@/components/tools/results";
import type { ToolResult } from "@/lib/types";

const XML = '<?xml version="1.0"?><feed><entry>Hello</entry></feed>';

/**
 * The feed document is shown beside the table, not inside it — and only when the
 * tool actually read one. A card holding nothing would read as "the feed is
 * broken" for a URL that is perfectly good, which is the bug this pins down.
 */
function result(
  meta: Record<string, unknown>,
  warnings: string[] = [],
): ToolResult {
  return {
    view: "table",
    summary: "Feed for “Marques Brownlee”, returning 1 entry.",
    data: {
      columns: [
        { key: "label", label: "Field" },
        { key: "value", label: "Value", copyable: true },
      ],
      rows: [
        {
          label: "Channel RSS feed",
          value:
            "https://www.youtube.com/feeds/videos.xml?channel_id=UCBJycsmduvYEL83R_U4JriQ",
        },
      ],
    },
    meta,
    warnings,
    artifacts: [],
  } as unknown as ToolResult;
}

describe("code card", () => {
  it("renders meta.code as a labelled block with the whole document", () => {
    render(
      <ResultRenderer
        result={result({ code: { label: "RSS feed", text: XML } })}
      />,
    );

    expect(screen.getByText("RSS feed")).toBeInTheDocument();
    expect(screen.getByText(XML)).toBeInTheDocument();
  });

  it("shows the warning and no block when the feed could not be read", () => {
    render(
      <ResultRenderer
        result={result({ feed_reachable: false }, [
          "YouTube would not return the feed.",
        ])}
      />,
    );

    expect(screen.queryByText("RSS feed")).not.toBeInTheDocument();
    expect(
      screen.getByText("YouTube would not return the feed."),
    ).toBeInTheDocument();
  });

  it("ignores a malformed or empty code block rather than drawing an empty card", () => {
    render(
      <ResultRenderer
        result={result({ code: { label: "RSS feed", text: "" } })}
      />,
    );

    expect(screen.queryByText("RSS feed")).not.toBeInTheDocument();
  });

  it("still renders the value column as a link with a copy button", () => {
    render(
      <ResultRenderer
        result={result({ code: { label: "RSS feed", text: XML } })}
      />,
    );

    expect(
      screen.getByRole("link", {
        name: "https://www.youtube.com/feeds/videos.xml?channel_id=UCBJycsmduvYEL83R_U4JriQ",
      }),
    ).toBeInTheDocument();
    expect(screen.getAllByRole("button").length).toBeGreaterThan(0);
  });
});
