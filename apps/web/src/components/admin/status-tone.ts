/**
 * The one place a status string becomes a colour.
 *
 * Statuses appear on eight screens; deciding their tone at each site is how
 * "past_due" ends up amber in billing and grey in the user detail. The mapping is
 * conservative: anything unrecognised is neutral rather than guessed, so a new
 * status added server-side renders as plain text instead of as a wrong warning.
 */

export type Tone = "neutral" | "success" | "warning" | "danger" | "info" | "muted";

const POST: Record<string, Tone> = {
  published: "success",
  scheduled: "info",
  draft: "muted",
  unpublished: "warning",
  archived: "muted",
  trashed: "danger",
};

const TOOL: Record<string, Tone> = {
  published: "success",
  draft: "muted",
  hidden: "warning",
  deprecated: "danger",
};

const TIER: Record<string, Tone> = {
  free: "success",
  account: "info",
  premium: "warning",
};

const SUBSCRIPTION: Record<string, Tone> = {
  active: "success",
  trialing: "info",
  past_due: "warning",
  canceled: "muted",
  cancelled: "muted",
  incomplete: "warning",
  incomplete_expired: "danger",
  unpaid: "danger",
};

const INVOICE: Record<string, Tone> = {
  paid: "success",
  open: "warning",
  draft: "muted",
  void: "muted",
  uncollectible: "danger",
};

const TICKET: Record<string, Tone> = {
  open: "warning",
  pending: "info",
  on_hold: "muted",
  solved: "success",
  closed: "muted",
};

const PRIORITY: Record<string, Tone> = {
  urgent: "danger",
  high: "warning",
  normal: "neutral",
  low: "muted",
};

const USER: Record<string, Tone> = {
  active: "success",
  suspended: "danger",
  pending: "warning",
};

const SUBSCRIBER: Record<string, Tone> = {
  subscribed: "success",
  pending: "warning",
  unsubscribed: "muted",
  bounced: "danger",
};

const SYNC: Record<string, Tone> = {
  synced: "success",
  pending: "muted",
  failed: "danger",
};

const ACCESS_REASON: Record<string, string> = {
  free: "Free tier",
  account: "Signed in",
  subscription: "Paid subscription",
  grant: "Comped grant",
  admin: "Staff bypass",
};

export const tone = {
  post: (status: string): Tone => POST[status] ?? "neutral",
  tool: (status: string): Tone => TOOL[status] ?? "neutral",
  tier: (tier: string): Tone => TIER[tier] ?? "neutral",
  subscription: (status: string): Tone => SUBSCRIPTION[status] ?? "neutral",
  invoice: (status: string): Tone => INVOICE[status] ?? "neutral",
  ticket: (status: string): Tone => TICKET[status] ?? "neutral",
  priority: (priority: string): Tone => PRIORITY[priority] ?? "neutral",
  user: (status: string): Tone => USER[status] ?? "neutral",
  subscriber: (status: string): Tone => SUBSCRIBER[status] ?? "neutral",
  sync: (status: string): Tone => SYNC[status] ?? "neutral",
};

/** Turn a snake_case enum value into something a person would say. */
export function humanise(value: string): string {
  return value.replace(/_/g, " ").replace(/^\w/, (character) => character.toUpperCase());
}

export function accessReasonLabel(reason: string): string {
  return ACCESS_REASON[reason] ?? humanise(reason);
}

/**
 * The palette charts use for the access-reason split.
 *
 * Comped and staff runs sit at the warm end deliberately — they are the slices an
 * admin is looking for when they open this chart.
 */
export const REASON_COLORS: Record<string, string> = {
  free: "var(--color-brand-300)",
  account: "var(--color-brand-500)",
  subscription: "var(--color-signal-500)",
  grant: "var(--color-ember-400)",
  admin: "var(--color-ember-600)",
};

export function reasonColor(reason: string): string {
  return REASON_COLORS[reason] ?? "var(--color-foreground-subtle)";
}
