/**
 * Admin API contract types.
 *
 * Kept apart from `lib/types.ts` because the admin surface is a different
 * contract with a different audience: the public types describe what a visitor
 * may see, and mixing the two makes it easy to render a staff-only field on a
 * marketing page by autocomplete.
 */

import type { BlockDocument, ChangelogItem } from "@/lib/types";

export type Trend = "up" | "down" | "flat";
export type MetricFormat = "number" | "currency" | "percent";

export interface SeriesPoint {
  date: string;
  value: number;
}

export interface Metric {
  key: string;
  label: string;
  value: number;
  previous: number | null;
  change_percent: number | null;
  trend: Trend;
  higher_is_better: boolean;
  format: MetricFormat;
  hint: string | null;
  series: SeriesPoint[];
}

export interface PeriodInfo {
  days: number;
  label: string;
  start: string;
  end: string;
}

export interface VolumePoint {
  date: string;
  runs: number;
  failed: number;
}

export interface FunnelStep {
  step: string;
  label: string;
  count: number;
  retention: number | null;
}

export interface AccessReasonSlice {
  reason: string;
  runs: number;
  share: number;
}

export interface ToolAnalyticsRow {
  id: string;
  slug: string;
  name: string;
  tier: string;
  status: string;
  is_visible: boolean;
  category: { slug: string; name: string } | null;
  runs: number;
  unique_actors: number;
  succeeded: number;
  failed: number;
  failure_rate: number;
  cache_hit_rate: number;
  p50_duration_ms: number;
  p95_duration_ms: number;
  comped_runs: number;
  views: number;
  starts: number;
  paywall_hits: number;
  account_walls: number;
  quota_walls: number;
  start_rate: number | null;
}

export interface ToolErrorRow {
  code: string;
  count: number;
  tools: string[];
}

/**
 * One actor's usage — an account, or an anonymous visitor identified by the daily
 * IP+user-agent hash. A headline "runs" number cannot tell healthy breadth from
 * one script; this is the panel that can.
 */
export interface ToolActorRow {
  type: "user" | "visitor";
  label: string;
  email: string | null;
  runs: number;
  tools: number;
  failed: number;
  /** Percent of all runs in the window. */
  share: number;
  last_run_at: string | null;
}

export interface ToolActors {
  rows: ToolActorRow[];
  totals: {
    /** Every run in the window, across everyone. */
    runs: number;
    actors: number;
    accounts: number;
    visitors: number;
    runs_per_actor: number;
  };
}

export interface Overview {
  period: PeriodInfo;
  periods: number[];
  metrics: Metric[];
  volume: VolumePoint[];
  funnel: FunnelStep[];
  access_reasons: AccessReasonSlice[];
  top_tools: ToolAnalyticsRow[];
  top_errors: ToolErrorRow[];
}

export interface ToolAnalytics {
  period: PeriodInfo;
  periods: number[];
  as_of: string | null;
  totals: {
    runs: number;
    failed: number;
    failure_rate: number;
    cache_hit_rate: number;
    views: number;
    paywall_hits: number;
  };
  rows: ToolAnalyticsRow[];
  volume: VolumePoint[];
  access_reasons: AccessReasonSlice[];
  top_errors: ToolErrorRow[];
  actors: ToolActors;
}

export interface ContentAnalyticsRow {
  slug: string;
  title: string;
  status: string;
  published_at: string | null;
  views: number;
  reads: number;
  read_through: number;
  tool_clicks: number;
  newsletter_signups: number;
}

// ── People ───────────────────────────────────────────────────────────────────

export interface AdminUser {
  id: string;
  email: string;
  display_name: string;
  initials: string;
  status: string;
  is_staff: boolean;
  roles: string[];
  email_verified: boolean;
  has_password: boolean;
  marketing_opt_in: boolean;
  locale: string;
  timezone: string;
  last_seen_at: string | null;
  deletion_requested_at: string | null;
  deleted_at: string | null;
  created_at: string | null;
  runs_count?: number;
  tickets_count?: number;
  subscription?: {
    plan: string | null;
    status: string;
    renews_at: string | null;
    cancels_at: string | null;
  } | null;
  grants?: {
    id: number;
    tool: { slug: string | null; name: string | null };
    reason: string | null;
    expires_at: string | null;
    is_active: boolean;
  }[];
  invoices?: {
    number: string | null;
    status: string;
    total: number;
    currency: string;
    issued_at: string | null;
    hosted_url: string | null;
  }[];
}

export interface AdminRole {
  id: number;
  name: string;
  description: string | null;
  is_system: boolean;
  is_super_admin: boolean;
  users_count?: number;
  permissions: string[];
}

export interface PermissionCatalogResource {
  resource: string;
  label: string;
  permissions: { name: string; action: string }[];
}

export interface PermissionCatalog {
  resources: PermissionCatalogResource[];
  groups: Record<string, string[]>;
  admin_exclusions: string[];
}

// ── Tools ────────────────────────────────────────────────────────────────────

export interface AdminTool {
  id: string;
  key: string;
  slug: string;
  name: string;
  tagline: string | null;
  description: string | null;
  tier: string;
  status: string;
  is_visible: boolean;
  is_featured: boolean;
  version: number;
  sort_order: number;
  platforms: string[];
  category: { id: number; slug: string; name: string } | null;
  config: Record<string, unknown> | null;
  /**
   * This tool's own run caps, keyed by window. A window it defers on is absent, so
   * an empty object means "the tier's allowance applies unchanged".
   */
  run_limits: Partial<Record<"daily" | "weekly" | "monthly", number>>;
  seo?: SeoOverrides | null;
  stats: {
    runs: number;
    avg_duration_ms: number;
    success_rate: number;
    grants?: number;
  };
  published_at: string | null;
  updated_at: string | null;
}

export interface ToolGrant {
  id: number;
  reason: string | null;
  is_active: boolean;
  expires_at: string | null;
  created_at: string | null;
  user: {
    id: string;
    email: string;
    display_name: string;
    initials: string;
  } | null;
  tool: { slug: string; name: string; tier: string } | null;
  granted_by: string | null;
}

// ── Content ──────────────────────────────────────────────────────────────────

export interface AdminPost {
  id: string;
  slug: string;
  title: string;
  excerpt: string | null;
  status: string;
  status_label: string;
  is_featured: boolean;
  word_count: number;
  reading_minutes: number;
  view_count: number;
  version: number;
  author: { id: string; display_name: string; initials: string } | null;
  category: { id: number; slug: string; name: string } | null;
  /** Secondary categories. The primary one above owns the URL and the breadcrumb. */
  categories?: { id: number; slug: string; name: string }[];
  tags: { id: number; slug: string; name: string }[];
  published_at: string | null;
  scheduled_for: string | null;
  deleted_at: string | null;
  updated_at: string | null;
  allowed_transitions: string[];
}

/**
 * SEO overrides as an admin form holds them — the same shape for every entity that
 * has them (posts, tools), because the fields are the same and a per-entity copy is
 * how the two drift.
 */
export interface SeoOverrides {
  title?: string | null;
  description?: string | null;
  canonical_url?: string | null;
  robots?: string | null;
  focus_keyword?: string | null;
  og_title?: string | null;
  og_description?: string | null;
  og_media_id?: number | null;
  /** Read-only: resolved from `og_media_id` so the form can show a thumbnail. */
  og_image_url?: string | null;
  twitter_card?: string | null;
  schema_type?: string | null;
}

export type PostSeo = SeoOverrides;

export interface AdminPostDetail {
  post: AdminPost;
  /**
   * The same `BlockDocument` the public renderer consumes.
   *
   * Deliberately not a looser admin-only shape: the editor and the article are
   * rendered from one document, and a second type for "the editing copy" is how
   * the two drift until WYSIWYG stops being true.
   */
  blocks: BlockDocument;
  seo: PostSeo | null;
  featured_media: AdminMedia | null;
  revisions: {
    id: number;
    title: string;
    is_autosave: boolean;
    created_at: string | null;
  }[];
}

export interface AdminMedia {
  id: string;
  /** The integer key, for the foreign keys a public id cannot fill. */
  numeric_id: number;
  filename: string;
  mime_type: string;
  kind: string;
  size: number;
  width: number | null;
  height: number | null;
  alt_text: string | null;
  caption: string | null;
  title: string | null;
  credit: string | null;
  is_decorative: boolean;
  usage_count: number;
  url: string | null;
  uploaded_by?: { display_name: string } | null;
  created_at: string | null;
}

/**
 * A category or a tag.
 *
 * `id` is present here and absent from the public equivalents on purpose: the
 * admin writes `posts.category_id` and the tag pivot, both numeric, so a picker
 * that only knew slugs could render a choice it could not save.
 */
export interface Taxonomy {
  id: number;
  slug: string;
  name: string;
  description?: string | null;
  accent_color?: string | null;
  sort_order?: number | null;
  posts_count: number;
  tools_count?: number;
}

// ── Commerce ─────────────────────────────────────────────────────────────────

export interface AdminPlan {
  id: number;
  key: string;
  name: string;
  tagline: string | null;
  billing_mode: string;
  interval: string | null;
  interval_count: number;
  amount: number;
  currency: string;
  duration_days: number | null;
  features: string[];
  limits: Record<string, unknown>;
  is_active: boolean;
  is_highlighted: boolean;
  sort_order: number;
  stripe_price_id: string | null;
  /** The plan's identifier at each gateway, keyed by provider. */
  gateway_ids: Record<string, string | null>;
  /** Every subscription ever, not just live ones — what makes a plan undeletable. */
  total_subscriptions: number;
  active_subscriptions?: number;
}

export interface AdminSubscription {
  id: number;
  status: string;
  is_active: boolean;
  is_cancelling: boolean;
  user: { id: string; display_name: string; email: string } | null;
  plan: {
    key: string;
    name: string;
    amount: number;
    currency: string;
    interval: string | null;
  } | null;
  current_period_end: string | null;
  trial_ends_at: string | null;
  cancel_at: string | null;
  ends_at: string | null;
  cancellation_reason: string | null;
  created_at: string | null;
}

/** The card, wallet or bank a payment came from. Never a full number. */
export interface InvoicePaymentMethod {
  type: string | null;
  brand: string | null;
  last4: string | null;
}

export interface AdminInvoice {
  id: number;
  number: string | null;
  status: string;
  /** Which provider took the money: stripe | paypal | braintree. */
  gateway: string;
  subtotal: number;
  tax: number;
  total: number;
  amount_refunded: number;
  /** Total minus refunded — what the business actually kept. */
  net_total: number;
  currency: string;
  payment_method: InvoicePaymentMethod | null;
  user: { id: string; display_name: string; email: string } | null;
  issued_at: string | null;
  paid_at: string | null;
  refunded_at: string | null;
  hosted_url?: string | null;
  pdf_url?: string | null;
}

export interface AdminInvoiceLine {
  id: number;
  description: string;
  quantity: number;
  unit_amount: number;
  amount: number;
}

/**
 * One invoice, complete. The extra fields over {@link AdminInvoice} are the ones a
 * detail page exists for: what was billed, against which subscription, on what
 * card, under which transaction at the gateway, and why money went back.
 *
 * The gateway references are absent rather than null for an actor without
 * `invoices.view` — the API omits them, and the screen must not print "—" as if
 * the payment had no transaction behind it.
 */
export interface AdminInvoiceDetail extends AdminInvoice {
  billing_name: string | null;
  billing_email: string | null;
  period_start: string | null;
  period_end: string | null;
  lines: AdminInvoiceLine[];
  subscription: {
    id: number;
    status: string;
    current_period_end: string | null;
    cancellation_reason: string | null;
  } | null;
  plan: {
    id: number;
    key: string;
    name: string;
    amount: number;
    currency: string;
    interval: string | null;
    billing_mode: string;
  } | null;
  refund: {
    amount: number;
    is_partial: boolean;
    refunded_at: string | null;
    reason: string | null;
    reference?: string | null;
  } | null;
  transaction_id?: string | null;
  transaction_url?: string | null;
}

/** Everything on the billing report, in one payload. */
export interface BillingReport {
  period: PeriodInfo;
  periods: number[];
  currency: string;
  metrics: Metric[];
  revenue_series: SeriesPoint[];
  subscription_series: { date: string; new: number; cancelled: number }[];
  by_plan: {
    id: number;
    key: string;
    name: string;
    interval: string | null;
    billing_mode: string;
    is_active: boolean;
    revenue: number;
    invoices: number;
    active_subscriptions: number;
    share: number;
  }[];
  by_gateway: {
    gateway: string;
    revenue: number;
    invoices: number;
    share: number;
  }[];
  by_status: { status: string; invoices: number; total: number }[];
  top_customers: {
    id: string;
    email: string;
    display_name: string;
    revenue: number;
    invoices: number;
  }[];
  recent_refunds: {
    id: number;
    number: string | null;
    email: string | null;
    amount: number;
    currency: string;
    refunded_at: string | null;
    reason: string | null;
  }[];
}

// ── Support & platform ───────────────────────────────────────────────────────

export interface AdminTicket {
  id: string;
  reference: string;
  subject: string;
  category: string;
  status: string;
  status_label: string;
  priority: string;
  is_overdue: boolean;
  requester: {
    id: string;
    display_name: string;
    email: string;
    initials: string;
  } | null;
  assignee: { id: string; display_name: string; initials: string } | null;
  messages_count?: number;
  messages?: {
    id: number;
    body: string;
    author_type: string;
    is_internal_note: boolean;
    author: { display_name: string; initials: string } | null;
    created_at: string | null;
  }[];
  first_response_at: string | null;
  resolved_at: string | null;
  due_at: string | null;
  last_activity_at: string | null;
  created_at: string | null;
}

export interface ContactMessage {
  id: number;
  name: string;
  email: string;
  subject: string | null;
  message: string;
  topic: string;
  handled_at: string | null;
  handled_by?: string | null;
  created_at: string | null;
}

export interface NewsletterSubscriber {
  id: number;
  email: string;
  name: string | null;
  status: string;
  source: string | null;
  tags: string[];
  provider: string | null;
  sync_status: string;
  sync_error: string | null;
  confirmed_at: string | null;
  unsubscribed_at: string | null;
  created_at: string | null;
}

export interface SettingItem {
  key: string;
  type: string;
  group: string;
  is_public: boolean;
  is_secret: boolean;
  description: string | null;
  value: unknown;
  is_set: boolean | null;
}

export interface SettingGroup {
  group: string;
  can_update: boolean;
  settings: SettingItem[];
}

export interface SettingsPayload {
  groups: SettingGroup[];
  permissions: Record<string, boolean>;
}

export interface ActivityEntry {
  id: number;
  log_name: string | null;
  event: string | null;
  description: string;
  causer: { display_name: string; initials: string; email: string } | null;
  subject: { type: string; id: number | null } | null;
  changes: Record<string, { from: unknown; to: unknown }> | null;
  created_at: string | null;
}

// ── Changelog ────────────────────────────────────────────────────────────────

/**
 * A release as the admin list and editor hold it.
 *
 * `is_live` is computed by the API rather than derived here from status and date.
 * That derivation is exactly the one an editor gets wrong — a `published` release
 * dated next Tuesday is not live — so it is answered once, server-side.
 */
export interface AdminChangelogRelease {
  id: string;
  slug: string;
  version: string | null;
  title: string;
  summary: string | null;
  status: string;
  status_label: string;
  is_live: boolean;
  is_major: boolean;
  released_at: string | null;
  items: ChangelogItem[];
  items_count: number;
  author: { id: string; display_name: string } | null;
  updated_at: string | null;
}
