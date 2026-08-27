"use client";

import { CornerDownLeft, LogOut, Moon, Search, Sun, User as UserIcon } from "lucide-react";
import { useRouter } from "next/navigation";
import { useTheme } from "next-themes";
import * as React from "react";

import { visibleSections } from "@/components/admin/admin-nav";
import { useSession } from "@/components/auth/session-provider";
import { adminApi } from "@/lib/admin/api";
import type { AdminUser } from "@/lib/admin/types";
import { cn } from "@/lib/utils";

/**
 * ⌘K for the admin.
 *
 * Navigation plus *user lookup*, because "find this person" is the single most
 * common thing anyone does in a staff tool — a support agent with a customer on the
 * line should not be clicking through to a list screen to type an email into a
 * second box. Results are filtered by permission: someone without `users.view_any`
 * gets navigation only, and no lookup box that would 403 on every keystroke.
 */

interface Command {
  id: string;
  label: string;
  hint?: string;
  icon: React.ComponentType<{ className?: string }>;
  group: "Go to" | "People" | "Actions";
  run: () => void;
}

export function AdminCommandPalette({
  open,
  onOpenChange,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  if (!open) return null;

  return <Dialog onOpenChange={onOpenChange} />;
}

function Dialog({ onOpenChange }: { onOpenChange: (open: boolean) => void }) {
  const router = useRouter();
  const { setTheme, resolvedTheme } = useTheme();
  const { signOut, can } = useSession();

  const [query, setQuery] = React.useState("");
  const [people, setPeople] = React.useState<AdminUser[]>([]);
  const [index, setIndex] = React.useState(0);

  const mayLookUpPeople = can("users.view_any");

  React.useEffect(() => {
    // Debounced, and the timer is the only thing that needs clearing: a superseded
    // response is discarded by the `cancelled` flag rather than by aborting, so a
    // fast typist costs one render instead of six aborted requests.
    let cancelled = false;

    const timer = setTimeout(async () => {
      if (!mayLookUpPeople || query.trim().length < 2) {
        setPeople([]);
        return;
      }

      const result = await adminApi.users.list({ q: query, per_page: 5 });

      if (!cancelled && result.ok) setPeople(result.data.data);
    }, 200);

    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [query, mayLookUpPeople]);

  const commands = React.useMemo<Command[]>(() => {
    const navigation: Command[] = visibleSections(can)
      .flatMap((section) => section.items)
      .map((item) => ({
        id: `nav:${item.href}`,
        label: item.label,
        hint: item.description,
        icon: item.icon,
        group: "Go to" as const,
        run: () => router.push(item.href),
      }));

    const persons: Command[] = people.map((person) => ({
      id: `user:${person.id}`,
      label: person.display_name,
      hint: person.email,
      icon: UserIcon,
      group: "People" as const,
      run: () => router.push(`/admin/users/${person.id}`),
    }));

    const actions: Command[] = [
      {
        id: "theme",
        label: resolvedTheme === "dark" ? "Switch to light" : "Switch to dark",
        icon: resolvedTheme === "dark" ? Sun : Moon,
        group: "Actions",
        run: () => setTheme(resolvedTheme === "dark" ? "light" : "dark"),
      },
      {
        id: "signout",
        label: "Sign out",
        icon: LogOut,
        group: "Actions",
        run: () => void signOut(),
      },
    ];

    const needle = query.trim().toLowerCase();

    const matches = (command: Command) =>
      needle === "" ||
      command.label.toLowerCase().includes(needle) ||
      command.hint?.toLowerCase().includes(needle) === true;

    // People are never filtered locally — the server already matched them, and
    // re-filtering on the display name would drop an exact email match.
    return [...navigation.filter(matches), ...persons, ...actions.filter(matches)];
  }, [can, people, query, resolvedTheme, router, setTheme, signOut]);

  function runAt(position: number) {
    commands[position]?.run();
    onOpenChange(false);
  }

  function onKeyDown(event: React.KeyboardEvent) {
    if (event.key === "ArrowDown") {
      event.preventDefault();
      setIndex((current) => Math.min(current + 1, commands.length - 1));
    } else if (event.key === "ArrowUp") {
      event.preventDefault();
      setIndex((current) => Math.max(current - 1, 0));
    } else if (event.key === "Enter") {
      event.preventDefault();
      runAt(index);
    } else if (event.key === "Escape") {
      onOpenChange(false);
    }
  }

  let cursor = -1;

  return (
    <div className="fixed inset-0 z-[80] flex items-start justify-center px-4 pt-[12vh]">
      <button
        type="button"
        aria-label="Close search"
        onClick={() => onOpenChange(false)}
        className="animate-fade-in absolute inset-0 bg-[oklch(0.15_0.02_258/0.55)] backdrop-blur-[2px]"
      />

      <div
        role="dialog"
        aria-modal="true"
        aria-label="Admin command palette"
        className="relative w-full max-w-xl overflow-hidden rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-[var(--app-surface)] shadow-[var(--shadow-popover)]"
      >
        <div className="flex items-center gap-3 border-b border-[var(--color-border-subtle)] px-4">
          <Search className="size-4 text-[var(--color-foreground-subtle)]" aria-hidden="true" />
          <input
            autoFocus
            value={query}
            onChange={(event) => {
              setQuery(event.target.value);
              // Reset the highlight in the same transition as the query. An effect
              // would leave one render where the old index points into new results.
              setIndex(0);
            }}
            onKeyDown={onKeyDown}
            placeholder={
              mayLookUpPeople ? "Search screens, or find a person by email…" : "Search screens…"
            }
            aria-label="Search"
            className="h-12 flex-1 bg-transparent text-sm text-[var(--color-foreground)] outline-none placeholder:text-[var(--color-foreground-subtle)]"
          />
          <kbd className="rounded border border-[var(--color-border)] px-1.5 font-mono text-[0.625rem] text-[var(--color-foreground-subtle)]">
            esc
          </kbd>
        </div>

        <div className="scrollbar-slim max-h-[22rem] overflow-y-auto p-2">
          {commands.length === 0 && (
            <p className="px-3 py-8 text-center text-sm text-[var(--color-foreground-subtle)]">
              Nothing matches “{query}”.
            </p>
          )}

          {(["Go to", "People", "Actions"] as const).map((group) => {
            const inGroup = commands.filter((command) => command.group === group);

            if (inGroup.length === 0) return null;

            return (
              <div key={group} className="mb-1 last:mb-0">
                <p className="px-3 py-1.5 font-mono text-[0.625rem] font-medium uppercase tracking-[0.14em] text-[var(--color-foreground-subtle)]">
                  {group}
                </p>

                {inGroup.map((command) => {
                  cursor += 1;
                  const position = cursor;
                  const active = position === index;
                  const Icon = command.icon;

                  return (
                    <button
                      key={command.id}
                      type="button"
                      onMouseEnter={() => setIndex(position)}
                      onClick={() => runAt(position)}
                      className={cn(
                        "flex w-full items-center gap-3 rounded-[var(--radius-md)] px-3 py-2 text-left text-sm transition-colors",
                        active
                          ? "bg-[var(--color-surface-sunken)] text-[var(--color-foreground)]"
                          : "text-[var(--color-foreground-muted)]",
                      )}
                    >
                      <Icon className="size-4 shrink-0" aria-hidden="true" />
                      <span className="min-w-0 flex-1">
                        <span className="block truncate">{command.label}</span>
                        {command.hint && (
                          <span className="block truncate text-xs text-[var(--color-foreground-subtle)]">
                            {command.hint}
                          </span>
                        )}
                      </span>
                      {active && (
                        <CornerDownLeft
                          className="size-3.5 text-[var(--color-foreground-subtle)]"
                          aria-hidden="true"
                        />
                      )}
                    </button>
                  );
                })}
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
