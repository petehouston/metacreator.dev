/**
 * Admin API contract types.
 *
 * Kept apart from `lib/types.ts` because the admin surface is a different
 * contract with a different audience: the public types describe what a visitor
 * may see, and mixing the two makes it easy to render a staff-only field on a
 * marketing page by autocomplete.
 */

import type { BlockDocument } from "@/lib/types";

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
  stats: { runs: number; avg_duration_ms: number; success_rate: number; grants?: number };
  published_at: string | null;
  updated_at: string | null;
}

export interface ToolGrant {
  id: number;
  reason: string | null;
  is_active: boolean;
  expires_at: string | null;
  created_at: string | null;
  user: { id: string; email: string; display_name: string; initials: string } | null;
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
  tags: { id: number; slug: string; name: string }[];
  published_at: string | null;
  scheduled_for: string | null;
  deleted_at: string | null;
  updated_at: string | null;
  allowed_transitions: string[];
}

export interface PostSeo {
  title?: string | null;
  description?: string | null;
  canonical_url?: string | null;
  robots?: string | null;
  focus_keyword?: string | null;
  og_title?: string | null;
  og_description?: string | null;
  og_media_id?: number | null;
  twitter_card?: string | null;
  schema_type?: string | null;
}

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
  revisions: { id: number; title: string; is_autosave: boolean; created_at: string | null }[];
}

export interface AdminMedia {
  id: string;
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
  active_subscriptions?: number;
}

export interface AdminSubscription {
  id: number;
  status: string;
  is_active: boolean;
  is_cancelling: boolean;
  user: { id: string; display_name: string; email: string } | null;
  plan: { key: string; name: string; amount: number; currency: string; interval: string | null } | null;
  current_period_end: string | null;
  trial_ends_at: string | null;
  cancel_at: string | null;
  ends_at: string | null;
  cancellation_reason: string | null;
  created_at: string | null;
}

export interface AdminInvoice {
  id: number;
  number: string | null;
  status: string;
  subtotal: number;
  tax: number;
  total: number;
  amount_refunded: number;
  currency: string;
  user: { id: string; display_name: string; email: string } | null;
  issued_at: string | null;
  paid_at: string | null;
  hosted_url?: string | null;
  pdf_url?: string | null;
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
  requester: { id: string; display_name: string; email: string; initials: string } | null;
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
