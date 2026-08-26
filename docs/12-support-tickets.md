# 12 — Support Tickets

## Lifecycle

```
open ──▶ pending (waiting on customer) ──▶ open ──▶ solved ──▶ closed
  └──▶ on_hold (waiting on us/third party) ──┘        └──(reopened within 14d)──▶ open
```

| Status | Meaning | Clock |
| --- | --- | --- |
| `open` | Needs a staff response | SLA running |
| `pending` | Waiting on the customer | SLA paused; auto-solve after 7 days of silence |
| `on_hold` | Blocked internally | SLA paused, flagged in the queue |
| `solved` | Resolved; customer can reopen for 14 days | — |
| `closed` | Final; a new ticket is required after this | — |

## Customer experience

Create a ticket from `/dashboard/support` or from a contextual "Report a problem" affordance on any
tool — the latter attaches the tool key, the run id, the input hash and the error code
automatically, which removes an entire round-trip of "which tool, what did you enter?".

Threads support attachments (10 MB, same allow-list as media), markdown-lite formatting, and email
replies land back in the thread via a Mailgun inbound route with a signed reply token.

## Staff experience

A queue view with saved filters (unassigned, mine, overdue, high priority, by category), keyboard
navigation, and a split pane: thread on the left, customer context on the right — plan, subscription
status, recent tool runs, recent errors, prior tickets. Most tickets are answerable without leaving
the screen.

| Feature | Detail |
| --- | --- |
| Canned responses | Team-shared, variable interpolation, permission-gated |
| Internal notes | `is_internal_note` — never visible to the customer, visually distinct |
| Assignment | Manual or round-robin among agents with `tickets.update` |
| Merge / split | Duplicate threads combine; a multi-issue thread splits |
| Bulk actions | Status, assignee, priority, tag |
| Comped access | Agents with `tool_grants.create` can grant a time-boxed tool grant from the ticket |

## SLA

| Priority | First response | Resolution |
| --- | --- | --- |
| Urgent (billing/outage) | 2 h | 8 h |
| High | 8 h | 24 h |
| Normal | 24 h | 72 h |
| Low | 48 h | 7 d |

Breaches escalate: a queue badge at 80% of target, a notification to the support lead at 100%.

## Notifications

Ticket created → customer confirmation + staff queue notification. Staff reply → customer email and
in-app notification. Customer reply → assigned agent, or the whole queue if unassigned. Solved →
customer notification with a one-click reopen and a 1–5 satisfaction rating.
