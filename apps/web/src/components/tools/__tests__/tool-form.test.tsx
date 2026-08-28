import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";

import { ToolForm } from "@/components/tools/tool-form";
import type { JsonSchema } from "@/lib/types";

/**
 * ToolForm is the component that makes a new tool cost zero frontend work, which
 * also makes it the component whose failure breaks every tool at once. It is tested
 * through the accessibility tree — if a test can find a field the way a screen
 * reader does, so can a user.
 */
const schema: JsonSchema = {
  type: "object",
  required: ["handle", "followers"],
  properties: {
    handle: { type: "string", title: "Account handle", description: "Without the @" },
    followers: { type: "integer", title: "Followers", minimum: 1 },
    platform: {
      type: "string",
      title: "Platform",
      enum: ["youtube", "tiktok"],
      default: "youtube",
    },
    detailed: { type: "boolean", title: "Detailed breakdown", default: false },
    notes: { type: "string", title: "Notes", maxLength: 5000 },
    link: { type: "string", title: "Video link", maxLength: 500, "x-control": "text" },
    starts_on: { type: "string", title: "Starts on", format: "date" },
  },
};

function renderForm(overrides: Partial<React.ComponentProps<typeof ToolForm>> = {}) {
  const onSubmit = vi.fn();

  render(
    <ToolForm schema={schema} pending={false} onSubmit={onSubmit} {...overrides} />,
  );

  return { onSubmit };
}

describe("ToolForm", () => {
  it("renders a labelled control for every schema property", () => {
    renderForm();

    expect(screen.getByLabelText(/account handle/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/followers/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/platform/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/detailed breakdown/i)).toBeInTheDocument();
  });

  it("renders fields in schema order, because that order is a UX decision", () => {
    renderForm();

    const labels = screen
      .getAllByText(/account handle|^followers|^platform|notes/i)
      .map((element) => element.textContent?.toLowerCase().replace("*", "").trim());

    expect(labels[0]).toContain("account handle");
  });

  it("chooses a select for enums and a checkbox for booleans", () => {
    renderForm();

    expect(screen.getByLabelText(/platform/i).tagName).toBe("SELECT");
    expect(screen.getByLabelText(/detailed breakdown/i)).toHaveAttribute("type", "checkbox");
  });

  it("uses a textarea for long text, so a script is not typed into a single line", () => {
    renderForm();

    expect(screen.getByLabelText(/notes/i).tagName).toBe("TEXTAREA");
  });

  it("keeps a long-but-single-line field on one line", () => {
    renderForm();

    // A YouTube link needs a generous maxLength and is still one line. Without
    // the hint the length rule alone would give it an eight-row box.
    expect(screen.getByLabelText(/video link/i).tagName).toBe("INPUT");
  });

  it("gives a date field a date picker rather than a bare text box", () => {
    renderForm();

    expect(screen.getByLabelText(/starts on/i)).toHaveAttribute("type", "date");
  });

  it("applies schema defaults", () => {
    renderForm();

    expect(screen.getByLabelText(/platform/i)).toHaveValue("youtube");
  });

  it("marks required fields for assistive technology, not just visually", () => {
    renderForm();

    expect(screen.getByLabelText(/account handle/i)).toHaveAttribute("aria-required", "true");
    expect(screen.getByLabelText(/notes/i)).not.toHaveAttribute("aria-required");
  });

  it("submits the entered values", async () => {
    const user = userEvent.setup();
    const { onSubmit } = renderForm();

    await user.type(screen.getByLabelText(/account handle/i), "creator");
    await user.type(screen.getByLabelText(/followers/i), "12500");
    await user.click(screen.getByRole("button", { name: /run tool/i }));

    // A normal submit carries no source; only the sample-data path tags itself.
    expect(onSubmit).toHaveBeenCalledWith(
      expect.objectContaining({ handle: "creator", followers: 12500, platform: "youtube" }),
    );
  });

  it("associates a server-side field error with its input", () => {
    renderForm({ fieldErrors: { handle: ["Account handle is required."] } });

    const input = screen.getByLabelText(/account handle/i);

    expect(input).toHaveAttribute("aria-invalid", "true");
    expect(screen.getByRole("alert")).toHaveTextContent("Account handle is required.");
    // The error must be announced with the field, not floating unattached.
    expect(input.getAttribute("aria-describedby")).toContain("error");
  });

  it("offers sample data only when the tool provides an example", async () => {
    const user = userEvent.setup();
    const { onSubmit } = renderForm({
      example: { input: { handle: "sample", followers: 500, platform: "tiktok" } },
    });

    await user.click(screen.getByRole("button", { name: /try with sample data/i }));

    // One click must both fill AND run — it is the highest-converting element on a
    // tool page precisely because it needs no second step.
    expect(onSubmit).toHaveBeenCalledWith(
      expect.objectContaining({ handle: "sample", followers: 500 }),
      "example",
    );
  });

  it("hides the sample-data button when there is no example", () => {
    renderForm();

    expect(screen.queryByRole("button", { name: /try with sample data/i })).toBeNull();
  });

  it("disables every action while a run is pending", () => {
    renderForm({ pending: true });

    expect(screen.getByRole("button", { name: /running/i })).toBeDisabled();
    expect(screen.getByRole("button", { name: /reset/i })).toBeDisabled();
  });

  it("gives a field enough width for the text it accepts", () => {
    // The failure this pins: a 300-character title sharing a row with a select,
    // so the user cannot see the end of what they typed. Anything that can hold a
    // sentence takes the whole row; a two-digit number does not.
    const wide: JsonSchema = {
      type: "object",
      properties: {
        title: { type: "string", title: "Title", maxLength: 300 },
        subject: { type: "string", title: "Subject", maxLength: 60 },
        handle: { type: "string", title: "Handle", maxLength: 30 },
        followers: { type: "integer", title: "Followers" },
        link: { type: "string", title: "Link", format: "uri" },
        unbounded: { type: "string", title: "Unbounded" },
      },
    };

    render(<ToolForm schema={wide} pending={false} onSubmit={vi.fn()} />);

    const spanOf = (label: string) =>
      screen.getByLabelText(new RegExp(`^${label}$`, "i")).closest("div.min-w-0")
        ?.className ?? "";

    // 300 characters of headline needs the whole row — this is the reported bug.
    expect(spanOf("Title")).toContain("md:col-span-12");
    // A URL is always long, whatever the schema says about its length.
    expect(spanOf("Link")).toContain("md:col-span-12");
    // No maxLength means no ceiling on the text, so it cannot be assumed short.
    expect(spanOf("Unbounded")).toContain("md:col-span-12");
    expect(spanOf("Subject")).toContain("md:col-span-6");
    expect(spanOf("Handle")).toContain("md:col-span-4");
    expect(spanOf("Followers")).toContain("md:col-span-3");
  });

  it("labels the submit button with the tool's own action", async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn();

    render(
      <ToolForm
        schema={schema}
        pending={false}
        toolName="X Thread Splitter"
        toolSlug="x-thread-splitter"
        onSubmit={onSubmit}
      />,
    );

    // "Run tool" is what a button says when nobody decided what it does.
    expect(screen.queryByRole("button", { name: /run tool/i })).toBeNull();
    await user.click(screen.getByRole("button", { name: /^split$/i }));
    expect(onSubmit).toHaveBeenCalled();
  });

  it("shows the tool's action in the pending state too", () => {
    render(
      <ToolForm
        schema={schema}
        pending
        toolName="X Thread Splitter"
        toolSlug="x-thread-splitter"
        onSubmit={vi.fn()}
      />,
    );

    expect(screen.getByRole("button", { name: /splitting/i })).toBeDisabled();
  });

  it("resets fields back to their schema defaults", async () => {
    const user = userEvent.setup();
    renderForm();

    const handle = screen.getByLabelText(/account handle/i);
    await user.type(handle, "creator");
    expect(handle).toHaveValue("creator");

    await user.click(screen.getByRole("button", { name: /reset/i }));
    expect(handle).toHaveValue("");
  });
});
