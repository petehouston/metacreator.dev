import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { SettingsScreen } from "@/components/admin/screens/settings-screen";
import type { MailStatus, SettingItem, SettingsPayload } from "@/lib/admin/types";

/**
 * The Email section of the settings screen.
 *
 * The section is worth its own tests because it is the one place on this screen
 * where getting it wrong is silent. A credential rendered in the wrong provider's
 * card is a Postmark token pasted into the Mailgun field; a delivery check that
 * tests the draft rather than the saved settings is a green tick for a
 * configuration nobody is running. Neither shows up as an error anywhere.
 */

const mailStatus = vi.hoisted(() => ({
  current: {
    provider: "mailgun",
    provider_label: "Mailgun",
    configured: false,
    missing: ["mail.mailgun.secret"],
    from_address: "hello@example.com",
    reply_to_address: "",
    delivers_via_flow: false,
  } as MailStatus,
}));

const testResult = vi.hoisted(() => ({
  current: { ok: true, data: { sent: true, recipient: "admin@example.com" } } as {
    ok: boolean;
    data?: unknown;
    error?: { message: string };
  },
}));

const testSend = vi.hoisted(() => ({ fn: vi.fn() }));

vi.mock("@/lib/admin/api", () => ({
  adminApi: {
    settings: {
      get: async () => ({ ok: true as const, data: payload() }),
      update: async () => ({ ok: true as const, data: { updated: [] } }),
      mail: {
        status: async () => ({ ok: true as const, data: mailStatus.current }),
        test: async (email?: string) => {
          testSend.fn(email);

          return testResult.current;
        },
      },
    },
  },
}));

vi.mock("@/components/admin/feedback", () => ({
  useToast: () => ({ notify: vi.fn(), reportError: vi.fn() }),
}));

function setting(key: string, overrides: Partial<SettingItem> = {}): SettingItem {
  return {
    key,
    type: "string",
    group: "mail",
    is_public: false,
    is_secret: false,
    description: null,
    value: "",
    is_set: null,
    ...overrides,
  };
}

function payload(): SettingsPayload {
  return {
    groups: [
      {
        group: "branding",
        can_update: true,
        settings: [setting("site.name", { group: "branding", value: "MetaCreator" })],
      },
      {
        group: "mail",
        can_update: true,
        settings: [
          setting("mail.provider", { value: "mailgun" }),
          setting("mail.from_address", { value: "hello@example.com" }),
          setting("mail.from_name"),
          setting("mail.reply_to_address"),
          setting("mail.smtp.host"),
          setting("mail.smtp.port"),
          setting("mail.smtp.scheme", { value: "auto" }),
          setting("mail.smtp.username"),
          setting("mail.smtp.password", { type: "secret", is_secret: true, value: null, is_set: false }),
          setting("mail.mailgun.domain", { value: "mg.example.com" }),
          setting("mail.mailgun.secret", { type: "secret", is_secret: true, value: null, is_set: false }),
          setting("mail.mailgun.endpoint", { value: "api.mailgun.net" }),
          setting("mail.postmark.token", { type: "secret", is_secret: true, value: null, is_set: true }),
          setting("mail.postmark.message_stream", { value: "outbound" }),
          setting("mail.resend.key", { type: "secret", is_secret: true, value: null, is_set: false }),
          setting("mail.ses.key"),
          setting("mail.ses.secret", { type: "secret", is_secret: true, value: null, is_set: false }),
          setting("mail.ses.region", { value: "us-east-1" }),
          setting("mail.klaviyo.api_key", { type: "secret", is_secret: true, value: null, is_set: false }),
          setting("mail.klaviyo.metric", { value: "Transactional Email" }),
        ],
      },
    ],
    permissions: {
      "settings.update": true,
      "settings.scripts.update": true,
      "settings.secrets.update": true,
    },
  };
}

/** Render the screen and switch to the Email section. */
async function openMailSection() {
  render(<SettingsScreen />);

  const tab = await screen.findByRole("button", { name: /^Email$/ });

  await userEvent.click(tab);

  return tab;
}

describe("settings screen — Email", () => {
  beforeEach(() => {
    testSend.fn.mockClear();
    testResult.current = { ok: true, data: { sent: true, recipient: "admin@example.com" } };
    mailStatus.current = {
      provider: "mailgun",
      provider_label: "Mailgun",
      configured: false,
      missing: ["mail.mailgun.secret"],
      from_address: "hello@example.com",
      reply_to_address: "",
      delivers_via_flow: false,
    };
  });

  it("keeps each provider's credentials in its own card", async () => {
    await openMailSection();

    // The failure this guards against is a Postmark token pasted into the Mailgun
    // field, which authenticates nowhere and reports nothing useful.
    const mailgun = (await screen.findByRole("heading", { name: "Mailgun" })).closest("section");
    const postmark = screen.getByRole("heading", { name: "Postmark" }).closest("section");

    expect(within(mailgun as HTMLElement).getByLabelText(/Sending domain/)).toBeTruthy();
    expect(within(postmark as HTMLElement).getByLabelText(/Server API token/)).toBeTruthy();
    expect(within(mailgun as HTMLElement).queryByLabelText(/Server API token/)).toBeNull();
  });

  it("never renders a stored credential, only whether one is set", async () => {
    await openMailSection();

    const postmark = (await screen.findByRole("heading", { name: "Postmark" })).closest("section");
    const token = within(postmark as HTMLElement).getByLabelText(/Server API token/);

    // The API sends `value: null` with `is_set: true`; anything else in the box
    // would mean a credential had been round-tripped through the browser.
    expect((token as HTMLInputElement).value).toBe("");
    expect((token as HTMLInputElement).type).toBe("password");
    expect(within(postmark as HTMLElement).getByText("Set")).toBeTruthy();
  });

  it("names the settings still missing rather than reporting a bare failure", async () => {
    await openMailSection();

    expect(await screen.findByText(/Still empty for this provider/)).toBeTruthy();
    expect(screen.getByText("mail.mailgun.secret")).toBeTruthy();
    expect(screen.getByText("Incomplete")).toBeTruthy();
  });

  it("sends a test to the signed-in admin when no address is given", async () => {
    await openMailSection();

    await userEvent.click(await screen.findByRole("button", { name: /Send test/ }));

    // Undefined, not an empty string: the endpoint reads that as "use my address".
    await waitFor(() => expect(testSend.fn).toHaveBeenCalledWith(undefined));
    expect(await screen.findByText(/admin@example.com/)).toBeTruthy();
  });

  it("shows the provider's own error verbatim when a test fails", async () => {
    testResult.current = {
      ok: true,
      data: { sent: false, error: "Domain not found: mg.example.com" },
    };

    await openMailSection();

    await userEvent.click(await screen.findByRole("button", { name: /Send test/ }));

    // "Domain not found" and "invalid API key" need different fixes; a generic
    // "could not send" loses which one you have.
    expect(await screen.findByText(/Domain not found: mg\.example\.com/)).toBeTruthy();
  });

  it("warns that a test uses the saved settings, not the unsaved ones", async () => {
    await openMailSection();

    const fromName = await screen.findByLabelText(/From name/);

    await userEvent.type(fromName, "MetaCreator");

    expect(await screen.findByText(/The test uses the/)).toBeTruthy();
  });

  it("says plainly that Klaviyo delivers through a flow", async () => {
    mailStatus.current = {
      ...mailStatus.current,
      provider: "klaviyo",
      provider_label: "Klaviyo (via flow)",
      configured: true,
      missing: [],
      delivers_via_flow: true,
    };

    await openMailSection();

    // A green "ready" here means the key works, not that mail arrives — the screen
    // has to say so or an operator will ship password resets into a void.
    expect(await screen.findByText(/a flow you\s+build there sends the email/)).toBeTruthy();
  });
});
