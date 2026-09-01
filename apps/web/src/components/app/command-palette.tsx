"use client";

import { CornerDownLeft, LogOut, Moon, Search, Sun, Wrench } from "lucide-react";
import { useRouter } from "next/navigation";
import { useTheme } from "next-themes";
import * as React from "react";

import { useSession } from "@/components/auth/session-provider";
import { navItemsFor } from "@/components/app/nav-items";
import { useBillingEnabled } from "@/components/site/features-provider";
import { apiFetch } from "@/lib/http";
import type { Paginated, ToolSummary } from "@/lib/types";
import { cn } from "@/lib/utils";

/**
 * ⌘K: navigation, tool search and the two account actions people hunt for.
 *
 * Tools are searched against the live catalog rather than a bundled index — the
 * catalog is 78 tools and growing, and shipping a stale copy of it to every visitor
 * is how a search box starts lying. The request is debounced and every in-flight
 * one is aborted when the query moves on, so typing fast costs one result, not six.
 */

interface Command {
  id: string;
  label: string;
  hint?: string;
  icon: React.ComponentType<{ className?: string }>;
  group: "Go to" | "Tools" | "Actions";
  run: () => void;
}

export function CommandPalette({
  open,
  onOpenChange,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  // Mounted only while open, so the query, the results and the selection reset by
  // unmounting rather than by an effect that clears them on the way back in.
  if (!open) return null;

  return <CommandPaletteDialog onOpenChange={onOpenChange} />;
}

function CommandPaletteDialog({ onOpenChange }: { onOpenChange: (open: boolean) => void }) {
  const router = useRouter();
  const { setTheme, resolvedTheme } = useTheme();
  const { signOut } = useSession();
  const billingEnabled = useBillingEnabled();

  const [query, setQuery] = React.useState("");
  const [tools, setTools] = React.useState<ToolSummary[]>([]);
  const [index, setIndex] = React.useState(0);

  const listRef = React.useRef<HTMLDivElement>(null);

  React.useEffect(() => {
    const controller = new AbortController();
    const timer = setTimeout(async () => {
      const result = await apiFetch<Paginated<ToolSummary>>("/catalog/tools", {
        searchParams: { q: query || undefined, per_page: query ? 6 : 4 },
        signal: controller.signal,
      });

      if (result.ok) setTools(result.data.data);
    }, 180);

    return () => {
      clearTimeout(timer);
      controller.abort();
    };
  }, [query]);

  const commands = React.useMemo<Command[]>(() => {
    const navigation: Command[] = navItemsFor(billingEnabled).map((item) => ({
      id: `nav:${item.href}`,
      label: item.label,
      hint: item.description,
      icon: item.icon,
      group: "Go to",
      run: () => router.push(item.href),
    }));

    const toolCommands: Command[] = tools.map((tool) => ({
      id: `tool:${tool.slug}`,
      label: tool.name,
      hint: tool.tagline ?? tool.category?.name ?? undefined,
      icon: Wrench,
      group: "Tools",
      run: () => router.push(`/tools/${tool.slug}`),
    }));

    const actions: Command[] = [
      {
        id: "action:theme",
        label: resolvedTheme === "dark" ? "Switch to light theme" : "Switch to dark theme",
        icon: resolvedTheme === "dark" ? Sun : Moon,
        group: "Actions",
        run: () => setTheme(resolvedTheme === "dark" ? "light" : "dark"),
      },
      {
        id: "action:signout",
        label: "Sign out",
        icon: LogOut,
        group: "Actions",
        run: () => void signOut(),
      },
    ];

    const needle = query.trim().toLowerCase();

    // Tools are already filtered by the API; navigation and actions are a fixed,
    // tiny list, so a substring match here is both enough and instant.
    const matches = (command: Command) =>
      command.group === "Tools" ||
      needle === "" ||
      command.label.toLowerCase().includes(needle) ||
      command.hint?.toLowerCase().includes(needle) === true;

    return [...navigation, ...toolCommands, ...actions].filter(matches);
  }, [tools, query, router, resolvedTheme, setTheme, signOut, billingEnabled]);

  // The result list shrinks as the query narrows, so the stored index can point
  // past the end. Clamping on read keeps the highlight valid without a correcting
  // render pass.
  const selected = Math.min(index, Math.max(0, commands.length - 1));

  React.useEffect(() => {
    function onKeyDown(event: KeyboardEvent) {
      if (event.key === "Escape") {
        event.preventDefault();
        onOpenChange(false);
        return;
      }

      if (event.key === "ArrowDown" || (event.key === "n" && event.ctrlKey)) {
        event.preventDefault();
        setIndex((current) => (current + 1) % Math.max(1, commands.length));
        return;
      }

      if (event.key === "ArrowUp" || (event.key === "p" && event.ctrlKey)) {
        event.preventDefault();
        setIndex((current) => (current - 1 + commands.length) % Math.max(1, commands.length));
        return;
      }

      if (event.key === "Enter") {
        event.preventDefault();
        const command = commands[Math.min(index, Math.max(0, commands.length - 1))];

        if (command) {
          onOpenChange(false);
          command.run();
        }
      }
    }

    document.addEventListener("keydown", onKeyDown);
    return () => document.removeEventListener("keydown", onKeyDown);
  }, [commands, index, onOpenChange]);

  // Keep the highlighted row in view when the selection is driven by the keyboard.
  React.useEffect(() => {
    listRef.current
      ?.querySelector('[data-selected="true"]')
      ?.scrollIntoView({ block: "nearest" });
  }, [selected]);

  let lastGroup: Command["group"] | null = null;

  return (
    <div
      className="fixed inset-0 z-[100] flex items-start justify-center px-4 pt-[12vh]"
      role="presentation"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget) onOpenChange(false);
      }}
    >
      <div
        aria-hidden="true"
        className="absolute inset-0 bg-[oklch(0.15_0.02_258/0.45)] backdrop-blur-sm"
      />

      <div
        role="dialog"
        aria-modal="true"
        aria-label="Command palette"
        className="animate-pop-in relative flex max-h-[26rem] w-full max-w-[34rem] flex-col overflow-hidden rounded-[var(--radius-xl)] border border-[var(--color-border)] bg-[var(--app-surface)] shadow-[var(--shadow-popover)]"
      >
        <div className="flex items-center gap-3 border-b border-[var(--color-border-subtle)] px-4">
          <Search className="size-4 shrink-0 text-[var(--color-foreground-subtle)]" aria-hidden="true" />

          <input
            /* The dialog exists to be typed into; focusing anything else would
               make ⌘K a two-step action. */
            autoFocus
            type="text"
            value={query}
            onChange={(event) => {
              setQuery(event.target.value);
              setIndex(0);
            }}
            placeholder="Search tools, jump to a screen…"
            aria-label="Search tools and screens"
            className="h-12 flex-1 bg-transparent text-sm text-[var(--color-foreground)] outline-none placeholder:text-[var(--color-foreground-subtle)]"
          />

          <kbd className="hidden rounded border border-[var(--color-border)] px-1.5 py-0.5 font-mono text-[0.625rem] text-[var(--color-foreground-subtle)] sm:block">
            ESC
          </kbd>
        </div>

        <div ref={listRef} className="scrollbar-slim flex-1 overflow-y-auto py-2">
          {commands.length === 0 && (
            <p className="px-4 py-8 text-center text-sm text-[var(--color-foreground-subtle)]">
              Nothing matches “{query}”.
            </p>
          )}

          {commands.map((command, position) => {
            const Icon = command.icon;
            const showGroup = command.group !== lastGroup;
            lastGroup = command.group;

            return (
              <React.Fragment key={command.id}>
                {showGroup && (
                  <p className="px-4 pb-1 pt-3 font-mono text-[0.625rem] font-medium uppercase tracking-[0.14em] text-[var(--color-foreground-subtle)]">
                    {command.group}
                  </p>
                )}

                <button
                  type="button"
                  data-selected={position === selected}
                  onMouseMove={() => setIndex(position)}
                  onClick={() => {
                    onOpenChange(false);
                    command.run();
                  }}
                  className={cn(
                    "flex w-full items-center gap-3 px-4 py-2 text-left transition-colors",
                    position === selected
                      ? "bg-[var(--color-primary-subtle)] text-[var(--color-foreground)]"
                      : "text-[var(--color-foreground-muted)]",
                  )}
                >
                  <Icon className="size-4 shrink-0" aria-hidden="true" />

                  <span className="min-w-0 flex-1">
                    <span className="block truncate text-sm font-medium text-[var(--color-foreground)]">
                      {command.label}
                    </span>
                    {command.hint && (
                      <span className="block truncate text-xs text-[var(--color-foreground-subtle)]">
                        {command.hint}
                      </span>
                    )}
                  </span>

                  {position === selected && (
                    <CornerDownLeft
                      className="size-3.5 shrink-0 text-[var(--color-foreground-subtle)]"
                      aria-hidden="true"
                    />
                  )}
                </button>
              </React.Fragment>
            );
          })}
        </div>
      </div>
    </div>
  );
}
