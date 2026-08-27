"use client";

import * as React from "react";

import { FormAlert } from "@/components/auth/form-alert";
import { Button } from "@/components/ui/button";
import { accountApi } from "@/lib/auth-api";
import type { NotificationPreferenceGroup } from "@/lib/types";

/**
 * Per-event channel toggles, grouped the way the API groups them.
 *
 * Only opt-out-able events are returned, so there is no toggle here that the server
 * would quietly ignore — security and billing notices simply do not appear.
 */
export function NotificationPreferences() {
  const [groups, setGroups] = React.useState<NotificationPreferenceGroup[]>([]);
  const [loading, setLoading] = React.useState(true);
  const [pending, setPending] = React.useState(false);
  const [saved, setSaved] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);

  React.useEffect(() => {
    let cancelled = false;

    void (async () => {
      const result = await accountApi.notificationPreferences();
      if (cancelled) return;

      if (result.ok) {
        setGroups(result.data);
      } else {
        setError(result.error.message);
      }

      setLoading(false);
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  function toggle(eventKey: string, channel: "email" | "in_app", value: boolean) {
    setSaved(false);
    setGroups((current) =>
      current.map((group) => ({
        ...group,
        events: group.events.map((event) =>
          event.key === eventKey
            ? { ...event, channels: { ...event.channels, [channel]: value } }
            : event,
        ),
      })),
    );
  }

  async function save() {
    setPending(true);
    setError(null);

    const preferences = groups.flatMap((group) =>
      group.events.map((event) => ({
        event_key: event.key,
        // An event with no email channel still needs a value; `true` means "unchanged
        // default" and the server ignores the channel either way.
        email: event.channels.email ?? true,
        in_app: event.channels.in_app,
      })),
    );

    const result = await accountApi.saveNotificationPreferences(preferences);
    setPending(false);

    if (result.ok) {
      setGroups(result.data);
      setSaved(true);
    } else {
      setError(result.error.message);
    }
  }

  if (loading) {
    return <p className="text-sm text-[var(--color-foreground-subtle)]">Loading…</p>;
  }

  return (
    <div className="flex flex-col gap-8">
      {error && <FormAlert>{error}</FormAlert>}
      {saved && <FormAlert tone="success">Preferences saved.</FormAlert>}

      <p className="text-sm text-[var(--color-foreground-muted)]">
        Security and billing notices are always sent — they are how you find out about
        things you would want to act on.
      </p>

      {groups.map((group) => (
        <section key={group.key}>
          <h2 className="mb-3 text-sm font-semibold text-[var(--color-foreground)]">
            {group.label}
          </h2>

          <div className="overflow-hidden panel">
            <div className="hidden grid-cols-[minmax(0,1fr)_5rem_5rem] gap-4 border-b border-[var(--color-border-subtle)] px-4 py-2 text-xs font-medium uppercase tracking-wider text-[var(--color-foreground-subtle)] sm:grid">
              <span>Notification</span>
              <span className="text-center">Email</span>
              <span className="text-center">In-app</span>
            </div>

            {group.events.map((event) => (
              <div
                key={event.key}
                className="grid grid-cols-[minmax(0,1fr)_5rem_5rem] items-center gap-4 border-b border-[var(--color-border-subtle)] px-4 py-3 last:border-b-0"
              >
                <span className="text-sm text-[var(--color-foreground)]">
                  {humanise(event.title)}
                </span>

                <span className="flex justify-center">
                  {event.channels.email === null ? (
                    <span
                      className="text-xs text-[var(--color-foreground-subtle)]"
                      aria-label="No email for this notification"
                    >
                      —
                    </span>
                  ) : (
                    <input
                      type="checkbox"
                      className="size-4 rounded border-[var(--color-border-strong)] accent-[var(--color-primary)]"
                      checked={event.channels.email}
                      aria-label={`Email me about ${humanise(event.title)}`}
                      onChange={(e) => toggle(event.key, "email", e.target.checked)}
                    />
                  )}
                </span>

                <span className="flex justify-center">
                  <input
                    type="checkbox"
                    className="size-4 rounded border-[var(--color-border-strong)] accent-[var(--color-primary)]"
                    checked={event.channels.in_app}
                    aria-label={`Show ${humanise(event.title)} in the app`}
                    onChange={(e) => toggle(event.key, "in_app", e.target.checked)}
                  />
                </span>
              </div>
            ))}
          </div>
        </section>
      ))}

      <div>
        <Button loading={pending} onClick={save}>
          Save preferences
        </Button>
      </div>
    </div>
  );
}

/** Catalog titles carry `:placeholder` tokens; a settings row has no payload to fill them. */
function humanise(title: string): string {
  return title.replace(/:(\w+)/g, (_, token: string) => token.replace(/_/g, " "));
}
