"use client";

import { AlertTriangle, CheckCircle2, Info, X } from "lucide-react";
import * as React from "react";

import { Button } from "@/components/ui/button";
import type { ApiFailure } from "@/lib/http";
import { cn } from "@/lib/utils";

/**
 * Telling someone what happened.
 *
 * Every write in the admin either succeeds visibly or fails with the server's own
 * message. Silent success is the worse of the two failure modes: an editor who
 * cannot tell whether a save landed will press the button again.
 */

type ToastTone = "success" | "error" | "info";

interface Toast {
  id: number;
  tone: ToastTone;
  message: string;
}

const ToastContext = React.createContext<{
  notify: (message: string, tone?: ToastTone) => void;
  /** Report an `ApiResult` failure using the server's message and code. */
  reportError: (error: ApiFailure) => void;
} | null>(null);

export function ToastProvider({ children }: { children: React.ReactNode }) {
  const [toasts, setToasts] = React.useState<Toast[]>([]);
  const nextId = React.useRef(0);

  const dismiss = React.useCallback((id: number) => {
    setToasts((current) => current.filter((toast) => toast.id !== id));
  }, []);

  const notify = React.useCallback(
    (message: string, tone: ToastTone = "success") => {
      const id = (nextId.current += 1);

      setToasts((current) => [...current, { id, tone, message }]);

      // Errors stay until dismissed: an error message that disappears before it is
      // read is the same as no error message.
      if (tone !== "error") {
        setTimeout(() => dismiss(id), 4000);
      }
    },
    [dismiss],
  );

  const reportError = React.useCallback(
    (error: ApiFailure) => {
      const fields = error.fieldErrors
        ? Object.values(error.fieldErrors).flat().join(" ")
        : "";

      notify(fields !== "" ? fields : error.message, "error");
    },
    [notify],
  );

  const value = React.useMemo(() => ({ notify, reportError }), [notify, reportError]);

  return (
    <ToastContext.Provider value={value}>
      {children}

      <div
        aria-live="polite"
        aria-atomic="false"
        className="pointer-events-none fixed bottom-4 right-4 z-[90] flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-2"
      >
        {toasts.map((toast) => (
          <ToastRow key={toast.id} toast={toast} onDismiss={() => dismiss(toast.id)} />
        ))}
      </div>
    </ToastContext.Provider>
  );
}

function ToastRow({ toast, onDismiss }: { toast: Toast; onDismiss: () => void }) {
  const Icon =
    toast.tone === "success" ? CheckCircle2 : toast.tone === "error" ? AlertTriangle : Info;

  const color =
    toast.tone === "success"
      ? "var(--color-success)"
      : toast.tone === "error"
        ? "var(--color-danger)"
        : "var(--color-primary)";

  return (
    <div
      role={toast.tone === "error" ? "alert" : "status"}
      className="animate-fade-in pointer-events-auto flex items-start gap-2.5 rounded-[var(--radius-md)] border border-[var(--color-border)] bg-[var(--app-surface)] px-3 py-2.5 shadow-[var(--shadow-popover)]"
    >
      <Icon className="mt-0.5 size-4 shrink-0" style={{ color }} aria-hidden="true" />

      <p className="min-w-0 flex-1 text-sm leading-snug text-[var(--color-foreground)]">
        {toast.message}
      </p>

      <button
        type="button"
        onClick={onDismiss}
        aria-label="Dismiss"
        className="-mr-1 flex size-5 shrink-0 items-center justify-center rounded-full text-[var(--color-foreground-subtle)] transition-colors hover:text-[var(--color-foreground)]"
      >
        <X className="size-3.5" aria-hidden="true" />
      </button>
    </div>
  );
}

export function useToast() {
  const context = React.useContext(ToastContext);

  if (context === null) {
    throw new Error("useToast must be used inside <ToastProvider>.");
  }

  return context;
}

/**
 * Confirmation for anything destructive or outward-facing.
 *
 * The confirm button repeats the *verb*, not "OK": a dialog whose primary action
 * says "Confirm" is a dialog people dismiss without reading.
 */
export function ConfirmDialog({
  open,
  title,
  description,
  confirmLabel,
  destructive = false,
  pending = false,
  onConfirm,
  onCancel,
}: {
  open: boolean;
  title: string;
  description: React.ReactNode;
  confirmLabel: string;
  destructive?: boolean;
  pending?: boolean;
  onConfirm: () => void;
  onCancel: () => void;
}) {
  React.useEffect(() => {
    if (!open) return;

    function onKeyDown(event: KeyboardEvent) {
      if (event.key === "Escape") onCancel();
    }

    document.addEventListener("keydown", onKeyDown);
    return () => document.removeEventListener("keydown", onKeyDown);
  }, [open, onCancel]);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-[85] flex items-center justify-center px-4">
      <button
        type="button"
        aria-label="Cancel"
        onClick={onCancel}
        className="animate-fade-in absolute inset-0 bg-[oklch(0.15_0.02_258/0.55)]"
      />

      <div
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="confirm-title"
        className="relative w-full max-w-md rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-[var(--app-surface)] p-5 shadow-[var(--shadow-popover)]"
      >
        <h2
          id="confirm-title"
          className="text-base font-semibold text-[var(--color-foreground)]"
        >
          {title}
        </h2>

        <div className="mt-2 text-sm leading-relaxed text-[var(--color-foreground-muted)]">
          {description}
        </div>

        <div className="mt-5 flex justify-end gap-2">
          <Button variant="secondary" size="sm" onClick={onCancel} disabled={pending}>
            Cancel
          </Button>
          <Button
            variant={destructive ? "danger" : "primary"}
            size="sm"
            onClick={onConfirm}
            loading={pending}
            autoFocus
          >
            {confirmLabel}
          </Button>
        </div>
      </div>
    </div>
  );
}

/** A right-hand panel for editing one row without leaving the list. */
export function Drawer({
  open,
  title,
  description,
  onClose,
  footer,
  children,
  className,
}: {
  open: boolean;
  title: string;
  description?: React.ReactNode;
  onClose: () => void;
  footer?: React.ReactNode;
  children: React.ReactNode;
  className?: string;
}) {
  React.useEffect(() => {
    if (!open) return;

    function onKeyDown(event: KeyboardEvent) {
      if (event.key === "Escape") onClose();
    }

    document.addEventListener("keydown", onKeyDown);
    return () => document.removeEventListener("keydown", onKeyDown);
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-[75]">
      <button
        type="button"
        aria-label="Close panel"
        onClick={onClose}
        className="animate-fade-in absolute inset-0 bg-[oklch(0.15_0.02_258/0.45)]"
      />

      <div
        role="dialog"
        aria-modal="true"
        aria-label={title}
        className={cn(
          "absolute inset-y-0 right-0 flex w-full max-w-lg flex-col border-l border-[var(--color-border)] bg-[var(--app-surface)] shadow-[var(--shadow-popover)]",
          className,
        )}
      >
        <div className="flex shrink-0 items-start justify-between gap-3 border-b border-[var(--color-border-subtle)] px-5 py-4">
          <div className="min-w-0">
            <h2 className="truncate text-base font-semibold text-[var(--color-foreground)]">
              {title}
            </h2>
            {description && (
              <p className="mt-0.5 text-xs text-[var(--color-foreground-subtle)]">{description}</p>
            )}
          </div>

          <button
            type="button"
            onClick={onClose}
            aria-label="Close"
            className="flex size-8 shrink-0 items-center justify-center rounded-[var(--radius-md)] text-[var(--color-foreground-muted)] transition-colors hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-foreground)]"
          >
            <X className="size-4" aria-hidden="true" />
          </button>
        </div>

        <div className="scrollbar-slim flex-1 overflow-y-auto px-5 py-4">{children}</div>

        {footer && (
          <div className="flex shrink-0 justify-end gap-2 border-t border-[var(--color-border-subtle)] px-5 py-3">
            {footer}
          </div>
        )}
      </div>
    </div>
  );
}
