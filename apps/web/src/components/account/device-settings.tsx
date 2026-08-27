"use client";

import { Laptop } from "lucide-react";
import { useRouter } from "next/navigation";
import * as React from "react";

import { FormAlert } from "@/components/auth/form-alert";
import { useSession } from "@/components/auth/session-provider";
import { Button } from "@/components/ui/button";
import { accountApi } from "@/lib/auth-api";
import type { UserDevice } from "@/lib/types";

export function DeviceSettings() {
  const router = useRouter();
  const { setUser } = useSession();

  const [devices, setDevices] = React.useState<UserDevice[]>([]);
  const [loading, setLoading] = React.useState(true);
  const [error, setError] = React.useState<string | null>(null);
  const [revoking, setRevoking] = React.useState<number | null>(null);

  React.useEffect(() => {
    let cancelled = false;

    void (async () => {
      const result = await accountApi.devices();
      if (cancelled) return;

      if (result.ok) {
        setDevices(result.data.data);
      } else {
        setError(result.error.message);
      }

      setLoading(false);
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  async function revoke(device: UserDevice) {
    setRevoking(device.id);
    const result = await accountApi.revokeDevice(device.id);
    setRevoking(null);

    if (!result.ok) {
      setError(result.error.message);
      return;
    }

    // Revoking the device you are holding is a sign-out. Clear the session in place
    // and refresh, so Server Components re-render without the signed-in content they
    // may already have cached.
    if (result.data.was_current) {
      setUser(null);
      router.refresh();
      router.replace("/");
      return;
    }

    setDevices((current) => current.filter((entry) => entry.id !== device.id));
  }

  if (loading) {
    return <p className="text-sm text-[var(--color-foreground-subtle)]">Loading…</p>;
  }

  return (
    <div className="flex flex-col gap-4">
      {error && <FormAlert>{error}</FormAlert>}

      {devices.length === 0 ? (
        <p className="text-sm text-[var(--color-foreground-muted)]">
          No other devices are signed in.
        </p>
      ) : (
        <ul className="divide-y divide-[var(--color-border-subtle)] panel">
          {devices.map((device) => (
            <li key={device.id} className="flex items-center justify-between gap-4 px-4 py-3">
              <div className="flex min-w-0 items-center gap-3">
                <Laptop
                  className="size-4 shrink-0 text-[var(--color-foreground-subtle)]"
                  aria-hidden="true"
                />

                <div className="min-w-0">
                  <p className="truncate text-sm font-medium text-[var(--color-foreground)]">
                    {device.label}
                    {device.is_current && (
                      <span className="ml-2 rounded-full bg-[var(--color-primary-subtle)] px-2 py-0.5 text-xs font-medium text-[var(--color-primary)]">
                        This device
                      </span>
                    )}
                  </p>
                  <p className="text-xs text-[var(--color-foreground-subtle)]">
                    {device.location ? `${device.location} · ` : ""}
                    Last active {formatRelative(device.last_seen_at)}
                  </p>
                </div>
              </div>

              <Button
                variant="ghost"
                size="sm"
                loading={revoking === device.id}
                onClick={() => revoke(device)}
              >
                {device.is_current ? "Sign out" : "Revoke"}
              </Button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

function formatRelative(iso: string | null): string {
  if (!iso) return "unknown";

  const days = Math.floor((Date.now() - new Date(iso).getTime()) / 86_400_000);

  if (days === 0) return "today";
  if (days === 1) return "yesterday";
  if (days < 30) return `${days} days ago`;

  return new Date(iso).toLocaleDateString(undefined, { month: "short", day: "numeric" });
}
