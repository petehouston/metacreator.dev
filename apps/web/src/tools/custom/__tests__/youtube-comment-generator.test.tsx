import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeAll, describe, expect, it, vi } from "vitest";

import type { ToolDetail } from "@/lib/types";
import YouTubeCommentGenerator from "@/tools/custom/youtube-comment-generator";

/**
 * jsdom has no canvas, so `drawComment` bails out and nothing is rasterised here.
 * That leaves the two things worth asserting anyway: the controls exist and are
 * labelled, and the canvas carries an accessible description of what it drew —
 * which is the only thing a screen reader gets out of this tool.
 */
// jsdom logs a "not implemented" notice for every `getContext` call it declines.
// Stubbing it to null is the same answer, quietly — and `drawComment` already
// returns early on a null context, which is the branch these tests exercise.
beforeAll(() => {
  vi.spyOn(HTMLCanvasElement.prototype, "getContext").mockReturnValue(null);
});

function tool(): ToolDetail {
  return {
    id: "tool_1",
    key: "youtube.comment-generator",
    slug: "fake-youtube-comment-generator",
    name: "Fake YouTube Comment Generator",
    tagline: null,
    tier: { value: "free", label: "Free", description: "" },
    platforms: ["youtube"],
    is_featured: false,
    is_deprecated: false,
    description: null,
    version: 1,
    input_schema: { type: "object", properties: {} },
    instructions: null,
    example: { input: { username: "John_Smith", content: "This video was very funny" } },
    faq: [],
    related: [],
    stats: { runs: 0, avg_duration_ms: 0, success_rate: 1 },
    updated_at: null,
  };
}

describe("YouTubeCommentGenerator", () => {
  it("starts from the catalog's worked example rather than an empty canvas", () => {
    render(<YouTubeCommentGenerator tool={tool()} />);

    expect(screen.getByLabelText(/username/i)).toHaveValue("John_Smith");
    expect(screen.getByLabelText(/content/i)).toHaveValue("This video was very funny");
  });

  it("describes the drawn card for anyone who cannot see it", async () => {
    render(<YouTubeCommentGenerator tool={tool()} />);

    await userEvent.clear(screen.getByLabelText(/content/i));
    await userEvent.type(screen.getByLabelText(/content/i), "Nice one");

    expect(
      screen.getByRole("img", { name: /YouTube comment by John_Smith: Nice one/i }),
    ).toBeInTheDocument();
  });

  it("offers all three download formats", () => {
    render(<YouTubeCommentGenerator tool={tool()} />);

    for (const format of ["PNG", "JPG", "WebP"]) {
      expect(screen.getByRole("button", { name: `Download ${format}` })).toBeInTheDocument();
    }
  });

  it("says plainly that nothing is uploaded", () => {
    render(<YouTubeCommentGenerator tool={tool()} />);

    expect(screen.getByText(/never uploaded and nothing is stored/i)).toBeInTheDocument();
  });
});
