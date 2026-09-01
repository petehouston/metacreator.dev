"use client";

import { AlarmClock, ArrowLeft, Lock, Send, StickyNote, User } from "lucide-react";
import Link from "next/link";
import * as React from "react";

import { AdminPageHeader, AdminPanel, StatusPill } from "@/components/admin/admin-page";
import { Can } from "@/components/admin/can";
import { useToast } from "@/components/admin/feedback";
import { LoadError } from "@/components/admin/load-error";
import { humanise, tone } from "@/components/admin/status-tone";
import { Button } from "@/components/ui/button";
import { Field, Select } from "@/components/ui/field";
import { adminApi } from "@/lib/admin/api";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import { cn, formatDate, relativeTime } from "@/lib/utils";

/**
 * One conversation.
 *
 * The single most consequential control on this screen is the reply/note toggle —
 * an internal note that leaks to a customer is a real incident — so it is a visible
 * two-state switch that restyles the whole composer, not a checkbox tucked under
 * the send button. When "internal note" is on, the composer turns amber and says
 * who cannot see it.
 */
export function TicketDetailScreen({ id }: { id: string }) {
  const { notify, reportError } = useToast();

  const { data, error, loading, reload } = useAdminResource(() => adminApi.tickets.get(id), [id]);

  const [body, setBody] = React.useState("");
  const [internal, setInternal] = React.useState(false);
  const [sending, setSending] = React.useState(false);

  if (error) return <LoadError error={error} onRetry={reload} />;

  if (loading && !data) {
    return (
      <div className="grid gap-4 lg:grid-cols-3">
        <div className="h-96 animate-pulse rounded-[var(--radius-lg)] bg-[var(--color-surface-sunken)] lg:col-span-2" />
        <div className="h-64 animate-pulse rounded-[var(--radius-lg)] bg-[var(--color-surface-sunken)]" />
      </div>
    );
  }

  if (!data) return null;

  async function send() {
    if (body.trim() === "") return;

    setSending(true);
    const result = await adminApi.tickets.reply(id, { body, is_internal_note: internal });
    setSending(false);

    if (result.ok) {
      setBody("");
      notify(
        internal
          ? "Note added. The customer cannot see it."
          : "Reply sent. The customer has been emailed.",
      );
      reload();
    } else {
      reportError(result.error);
    }
  }

  async function patch(next: Record<string, unknown>) {
    const result = await adminApi.tickets.update(id, next);

    if (result.ok) {
      notify("Ticket updated.");
      reload();
    } else {
      reportError(result.error);
    }
  }

  return (
    <>
      <Button asChild variant="ghost" size="sm" className="mb-3 -ml-2">
        <Link href="/c0ns0le/tickets">
          <ArrowLeft className="size-4" aria-hidden="true" />
          Queue
        </Link>
      </Button>

      <AdminPageHeader
        eyebrow={data.reference}
        title={data.subject}
        description={
          <span className="flex flex-wrap items-center gap-x-3 gap-y-1">
            <span>
              From{" "}
              {data.requester ? (
                <Link
                  href={`/c0ns0le/users/${data.requester.id}`}
                  className="text-[var(--color-primary)] underline-offset-4 hover:underline"
                >
                  {data.requester.display_name}
                </Link>
              ) : (
                "an unknown account"
              )}
            </span>
            <span aria-hidden="true">·</span>
            <span>opened {data.created_at ? relativeTime(data.created_at) : "—"}</span>
          </span>
        }
        actions={
          <>
            <StatusPill label={data.status_label} tone={tone.ticket(data.status)} />
            <StatusPill label={humanise(data.priority)} tone={tone.priority(data.priority)} />
            {data.is_overdue && <StatusPill label="Overdue" tone="danger" />}
          </>
        }
      />

      <div className="grid gap-4 lg:grid-cols-3">
        <div className="flex flex-col gap-4 lg:col-span-2">
          <AdminPanel title="Conversation" description={`${data.messages?.length ?? 0} messages`}>
            <ol className="flex flex-col gap-4">
              {(data.messages ?? []).map((message) => (
                <li
                  key={message.id}
                  className={cn(
                    "rounded-[var(--radius-md)] border p-3",
                    message.is_internal_note
                      ? "border-[var(--color-warning)]/30 bg-[var(--color-warning)]/8"
                      : message.author_type === "user"
                        ? "border-[var(--color-border-subtle)] bg-[var(--color-surface-sunken)]"
                        : "border-[var(--color-primary)]/25 bg-[var(--color-primary-subtle)]/30",
                  )}
                >
                  <div className="mb-1.5 flex flex-wrap items-center gap-2">
                    <span className="text-sm font-medium text-[var(--color-foreground)]">
                      {message.author?.display_name ?? "Customer"}
                    </span>

                    {message.is_internal_note && (
                      <StatusPill label="Internal note" tone="warning" />
                    )}

                    <span className="ml-auto text-xs text-[var(--color-foreground-subtle)]">
                      {message.created_at ? relativeTime(message.created_at) : ""}
                    </span>
                  </div>

                  {/* Plain text, deliberately. A ticket body is untrusted input from
                      a customer; rendering it as HTML would make the support queue
                      the softest XSS target in the product. */}
                  <p className="whitespace-pre-wrap text-sm leading-relaxed text-[var(--color-foreground-muted)]">
                    {message.body}
                  </p>
                </li>
              ))}

              {(data.messages?.length ?? 0) === 0 && (
                <li className="py-6 text-center text-sm text-[var(--color-foreground-subtle)]">
                  No messages on this ticket yet.
                </li>
              )}
            </ol>
          </AdminPanel>

          <Can permission="tickets.reply">
            <div
              className={cn(
                "app-card overflow-hidden transition-colors",
                internal && "border-[var(--color-warning)]/50",
              )}
            >
              <div
                className={cn(
                  "flex items-center gap-1 border-b px-3 py-2 transition-colors",
                  internal
                    ? "border-[var(--color-warning)]/30 bg-[var(--color-warning)]/8"
                    : "border-[var(--color-border-subtle)]",
                )}
              >
                <button
                  type="button"
                  onClick={() => setInternal(false)}
                  aria-pressed={!internal}
                  className={cn(
                    "flex items-center gap-1.5 rounded-[var(--radius-sm)] px-2.5 py-1 text-xs font-medium transition-colors",
                    !internal
                      ? "bg-[var(--color-primary)] text-[var(--color-primary-foreground)]"
                      : "text-[var(--color-foreground-muted)] hover:text-[var(--color-foreground)]",
                  )}
                >
                  <Send className="size-3.5" aria-hidden="true" />
                  Reply to customer
                </button>

                <button
                  type="button"
                  onClick={() => setInternal(true)}
                  aria-pressed={internal}
                  className={cn(
                    "flex items-center gap-1.5 rounded-[var(--radius-sm)] px-2.5 py-1 text-xs font-medium transition-colors",
                    internal
                      ? "bg-[var(--color-warning)] text-[oklch(0.16_0.02_258)]"
                      : "text-[var(--color-foreground-muted)] hover:text-[var(--color-foreground)]",
                  )}
                >
                  <StickyNote className="size-3.5" aria-hidden="true" />
                  Internal note
                </button>

                {internal && (
                  <span className="ml-auto flex items-center gap-1 text-xs font-medium text-[var(--color-warning)]">
                    <Lock className="size-3" aria-hidden="true" />
                    {data.requester?.display_name ?? "The customer"} will not see this
                  </span>
                )}
              </div>

              <textarea
                value={body}
                onChange={(event) => setBody(event.target.value)}
                placeholder={
                  internal
                    ? "A note for whoever picks this up next…"
                    : `Reply to ${data.requester?.display_name ?? "the customer"}…`
                }
                aria-label={internal ? "Internal note" : "Reply to customer"}
                rows={5}
                className="w-full resize-y bg-transparent p-3 text-sm leading-relaxed text-[var(--color-foreground)] outline-none placeholder:text-[var(--color-foreground-subtle)]"
              />

              <div className="flex items-center justify-between gap-2 border-t border-[var(--color-border-subtle)] px-3 py-2">
                <p className="text-xs text-[var(--color-foreground-subtle)]">
                  {internal
                    ? "Notes never leave the admin."
                    : "Sending a reply moves the ticket to “waiting on customer”."}
                </p>

                <Button
                  size="sm"
                  variant={internal ? "secondary" : "primary"}
                  onClick={() => void send()}
                  loading={sending}
                  disabled={body.trim() === ""}
                >
                  {internal ? "Add note" : "Send reply"}
                </Button>
              </div>
            </div>
          </Can>
        </div>

        <div className="flex flex-col gap-4">
          <Can permission="tickets.update">
            <AdminPanel title="Triage">
              <div className="flex flex-col gap-4">
                <Field id="ticket-status" label="Status">
                  {(props) => (
                    <Select
                      {...props}
                      value={data.status}
                      onChange={(event) => void patch({ status: event.target.value })}
                    >
                      <option value="open">Open</option>
                      <option value="pending">Waiting on customer</option>
                      <option value="on_hold">On hold</option>
                      <option value="solved">Solved</option>
                      <option value="closed">Closed</option>
                    </Select>
                  )}
                </Field>

                <Field id="ticket-priority" label="Priority">
                  {(props) => (
                    <Select
                      {...props}
                      value={data.priority}
                      onChange={(event) => void patch({ priority: event.target.value })}
                    >
                      <option value="low">Low</option>
                      <option value="normal">Normal</option>
                      <option value="high">High</option>
                      <option value="urgent">Urgent</option>
                    </Select>
                  )}
                </Field>

                <Can permission="tickets.assign">
                  <Button
                    variant="secondary"
                    size="sm"
                    onClick={() => void patch({ assigned_to: null })}
                    disabled={data.assignee === null}
                  >
                    <User className="size-4" aria-hidden="true" />
                    Unassign
                  </Button>
                </Can>
              </div>
            </AdminPanel>
          </Can>

          <AdminPanel title="Timeline">
            <dl className="flex flex-col gap-3 text-sm">
              <Row label="Opened" value={data.created_at ? formatDate(data.created_at) : "—"} />
              <Row
                label="First response"
                value={
                  data.first_response_at ? relativeTime(data.first_response_at) : "Not yet answered"
                }
                warning={data.first_response_at === null}
              />
              <Row
                label="Due"
                value={data.due_at ? formatDate(data.due_at) : "No SLA set"}
                warning={data.is_overdue}
                icon={data.is_overdue ? AlarmClock : undefined}
              />
              <Row
                label="Resolved"
                value={data.resolved_at ? formatDate(data.resolved_at) : "Not resolved"}
              />
              <Row label="Assigned to" value={data.assignee?.display_name ?? "Nobody"} />
            </dl>
          </AdminPanel>

          {data.requester && (
            <AdminPanel title="Requester">
              <Link
                href={`/c0ns0le/users/${data.requester.id}`}
                className="flex items-center gap-2.5 hover:text-[var(--color-primary)]"
              >
                <span
                  aria-hidden="true"
                  className="flex size-9 shrink-0 items-center justify-center rounded-full bg-[var(--color-primary-subtle)] text-xs font-semibold text-[var(--color-primary)]"
                >
                  {data.requester.initials}
                </span>
                <span className="flex min-w-0 flex-col">
                  <span className="truncate text-sm font-medium text-[var(--color-foreground)]">
                    {data.requester.display_name}
                  </span>
                  <span className="truncate text-xs text-[var(--color-foreground-subtle)]">
                    {data.requester.email}
                  </span>
                </span>
              </Link>
            </AdminPanel>
          )}
        </div>
      </div>
    </>
  );
}

function Row({
  label,
  value,
  warning,
  icon: Icon,
}: {
  label: string;
  value: string;
  warning?: boolean;
  icon?: React.ComponentType<{ className?: string }>;
}) {
  return (
    <div className="flex items-baseline justify-between gap-3">
      <dt className="font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
        {label}
      </dt>
      <dd
        className="flex items-center gap-1 truncate text-right text-sm"
        style={{ color: warning ? "var(--color-warning)" : "var(--color-foreground)" }}
      >
        {Icon && <Icon className="size-3.5" aria-hidden="true" />}
        {value}
      </dd>
    </div>
  );
}
