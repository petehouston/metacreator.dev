import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";

import { ResultRenderer } from "@/components/tools/results";
import type { ToolResult } from "@/lib/types";

/**
 * The preview renderer is the only place a "your post is cut here" claim becomes
 * visible, so the thing worth testing is that the cut is drawn — both halves present,
 * in order, with the platform's own "more" label between them.
 */
function result(overrides: Record<string, unknown> = {}): ToolResult {
  return {
    view: "preview.social",
    summary: "512 characters.",
    data: {
      frames: [
        {
          platform: "facebook",
          surface: "Mobile app",
          kind: "post",
          author: { name: "The Bread Lab", meta: "Just now", initials: "TL" },
          body: {
            visible: "The part everyone reads.",
            hidden: " The part nobody reads.",
            full: "The part everyone reads. The part nobody reads.",
            more_label: "… See more",
            characters: 46,
          },
          status: { tone: "warn", label: "23 characters behind “See more”" },
          details: [{ label: "Fold", value: "250 characters" }],
          note: "Attaching a photo moves the fold earlier.",
        },
      ],
    },
    artifacts: [],
    warnings: [],
    meta: {},
    ...overrides,
  } as unknown as ToolResult;
}

describe("social preview result", () => {
  it("draws both sides of the fold", () => {
    render(<ResultRenderer result={result()} />);

    expect(screen.getByText(/The part everyone reads\./)).toBeInTheDocument();
    expect(screen.getByText(/The part nobody reads\./)).toBeInTheDocument();
    expect(screen.getByText("… See more")).toBeInTheDocument();
  });

  it("labels the frame, its verdict and the facts behind it", () => {
    render(<ResultRenderer result={result()} />);

    expect(screen.getByText("Mobile app")).toBeInTheDocument();
    expect(screen.getByText("The Bread Lab")).toBeInTheDocument();
    expect(screen.getByText(/23 characters behind/)).toBeInTheDocument();
    expect(screen.getByText("250 characters")).toBeInTheDocument();
  });

  it("describes a safe zone for a reader who cannot see it", () => {
    const safeZone = result({
      data: {
        frames: [
          {
            platform: "tiktok",
            surface: "TikTok",
            kind: "safe-zone",
            canvas: {
              width: 1080,
              height: 1920,
              top: 130,
              bottom: 500,
              left: 60,
              right: 260,
            },
          },
        ],
      },
    });

    render(<ResultRenderer result={safeZone} />);

    expect(
      screen.getByRole("img", { name: /safe area inset by 130 pixels top/i }),
    ).toBeInTheDocument();
  });
  it("draws a channel card with its artwork and a way out to the channel", () => {
    const channel = result({
      summary: "Deepvue has monetization enabled.",
      data: {
        frames: [
          {
            platform: "youtube",
            surface: "Channel page",
            kind: "channel",
            author: { name: "Deepvue", handle: "@Deepvue_", initials: "D" },
            body: {
              visible: "All-In-One Trading Platform.",
              hidden: "",
              full: "All-In-One Trading Platform.",
              more_label: "… more",
              characters: 28,
            },
            artwork: {
              banner: "https://yt3.googleusercontent.com/xyz=w2120",
              avatar: "https://yt3.googleusercontent.com/abc=s240",
            },
            cta: { label: "View channel", url: "https://www.youtube.com/@Deepvue_" },
            status: { tone: "ok", label: "Monetization enabled" },
            details: [
              { label: "Subscribers", value: "12K" },
              { label: "Videos", value: "179" },
            ],
          },
        ],
      },
    });

    render(<ResultRenderer result={channel} />);

    expect(screen.getByRole("heading", { name: "Deepvue" })).toBeInTheDocument();
    expect(screen.getByText("Monetization enabled")).toBeInTheDocument();

    // The counts belong on the one line under the name, not repeated beneath the card.
    expect(screen.getByText("@Deepvue_ · 12K · 179")).toBeInTheDocument();

    const link = screen.getByRole("link", { name: /view channel/i });

    expect(link).toHaveAttribute("href", "https://www.youtube.com/@Deepvue_");
    expect(link).toHaveAttribute("target", "_blank");
  });
});
