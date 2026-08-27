"use client";

import { LayoutDashboard, LogOut, Settings, Shield, Sparkles } from "lucide-react";
import Link from "next/link";
import * as React from "react";

import { useSession } from "@/components/auth/session-provider";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

/**
 * Signed-out: the two conversion buttons. Signed-in: an avatar menu.
 *
 * Rendered client-side, so `loading` gets a neutral placeholder rather than the
 * signed-out buttons — flashing "Sign in" at someone who is already signed in reads
 * as a bug every time.
 */
export function UserMenu() {
  const { user, loading, signOut } = useSession();
  const [open, setOpen] = React.useState(false);
  const containerRef = React.useRef<HTMLDivElement>(null);

  React.useEffect(() => {
    if (!open) return;

    function onPointerDown(event: MouseEvent) {
      if (!containerRef.current?.contains(event.target as Node)) {
        setOpen(false);
      }
    }

    function onKeyDown(event: KeyboardEvent) {
      if (event.key === "Escape") setOpen(false);
    }

    document.addEventListener("mousedown", onPointerDown);
    document.addEventListener("keydown", onKeyDown);

    return () => {
      document.removeEventListener("mousedown", onPointerDown);
      document.removeEventListener("keydown", onKeyDown);
    };
  }, [open]);

  if (loading) {
    return <div className="size-9 rounded-full bg-[var(--color-surface-sunken)]" aria-hidden="true" />;
  }

  if (!user) {
    return (
      <>
        <Button asChild variant="ghost" size="sm" className="hidden sm:inline-flex">
          <Link href="/login">Sign in</Link>
        </Button>

        <Button asChild size="sm">
          <Link href="/register">Get started free</Link>
        </Button>
      </>
    );
  }

  return (
    <div className="relative" ref={containerRef}>
      <button
        type="button"
        onClick={() => setOpen((value) => !value)}
        aria-expanded={open}
        aria-haspopup="menu"
        aria-label="Account menu"
        className="flex size-9 items-center justify-center overflow-hidden rounded-full border border-[var(--color-border)] bg-[var(--color-surface-raised)] text-xs font-semibold text-[var(--color-foreground)] transition-colors hover:border-[var(--color-border-strong)]"
      >
        {user.avatar_url ? (
          /* Avatars come from a storage host that is configurable per environment,
             so next/image would need every possible bucket allow-listed. At 36px the
             optimiser buys nothing anyway. */
          // eslint-disable-next-line @next/next/no-img-element
          <img src={user.avatar_url} alt="" className="size-full object-cover" />
        ) : (
          user.initials
        )}
      </button>

      {open && (
        <div
          role="menu"
          className={cn(
            "absolute right-0 top-full z-50 mt-2 w-60 overflow-hidden rounded-[var(--radius-md)]",
            "border border-[var(--color-border)] bg-[var(--color-surface)] shadow-[var(--shadow-popover,0_12px_32px_rgba(0,0,0,0.14))]",
          )}
        >
          <div className="border-b border-[var(--color-border-subtle)] px-4 py-3">
            <p className="truncate text-sm font-semibold text-[var(--color-foreground)]">
              {user.display_name}
            </p>
            <p className="truncate text-xs text-[var(--color-foreground-subtle)]">{user.email}</p>
          </div>

          <nav className="py-1">
            <MenuLink href="/dashboard" icon={LayoutDashboard} onNavigate={() => setOpen(false)}>
              Dashboard
            </MenuLink>
            <MenuLink href="/dashboard/runs" icon={Sparkles} onNavigate={() => setOpen(false)}>
              Run history
            </MenuLink>
            <MenuLink href="/dashboard/settings" icon={Settings} onNavigate={() => setOpen(false)}>
              Settings
            </MenuLink>

            {user.is_staff && (
              <MenuLink href="/admin" icon={Shield} onNavigate={() => setOpen(false)}>
                Admin
              </MenuLink>
            )}
          </nav>

          <div className="border-t border-[var(--color-border-subtle)] py-1">
            <button
              type="button"
              role="menuitem"
              onClick={() => {
                setOpen(false);
                void signOut();
              }}
              className="flex w-full items-center gap-2.5 px-4 py-2 text-sm text-[var(--color-foreground-muted)] transition-colors hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-foreground)]"
            >
              <LogOut className="size-4" aria-hidden="true" />
              Sign out
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

function MenuLink({
  href,
  icon: Icon,
  children,
  onNavigate,
}: {
  href: string;
  icon: React.ComponentType<{ className?: string }>;
  children: React.ReactNode;
  onNavigate: () => void;
}) {
  return (
    <Link
      href={href}
      role="menuitem"
      onClick={onNavigate}
      className="flex items-center gap-2.5 px-4 py-2 text-sm text-[var(--color-foreground-muted)] transition-colors hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-foreground)]"
    >
      <Icon className="size-4" aria-hidden="true" />
      {children}
    </Link>
  );
}
