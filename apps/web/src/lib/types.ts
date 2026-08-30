/**
 * API contract types.
 *
 * Generated from the OpenAPI spec in CI (`make openapi`); this hand-written copy is
 * the checked-in baseline so the app type-checks on a fresh clone.
 */

export type ToolTier = "free" | "account" | "premium";

export type ResultView =
  | "keyvalue"
  | "table"
  | "list.cards"
  | "list.comments"
  | "text.blocks"
  | "media.gallery"
  | "score.report"
  | "chart.timeseries"
  | "diff.compare"
  | "download.bundle"
  | "preview.social";

export interface TierInfo {
  value: ToolTier;
  label: string;
  description: string;
}

export interface ToolCategory {
  slug: string;
  name: string;
  tagline?: string | null;
  description?: string | null;
  icon?: string | null;
  accent_color?: string | null;
  tool_count?: number;
}

export interface ToolSummary {
  id: string;
  slug: string;
  name: string;
  tagline: string | null;
  tier: TierInfo;
  platforms: string[];
  category?: ToolCategory;
  is_featured: boolean;
  is_deprecated: boolean;
  /** False when an admin has set this tool to no-index. Defaults to true. */
  is_indexable?: boolean;
  stats: { runs: number; avg_duration_ms: number };
}

/** JSON Schema subset the form generator understands. */
export interface JsonSchemaProperty {
  type?: "string" | "integer" | "number" | "boolean" | "array" | "object";
  title?: string;
  description?: string;
  enum?: string[];
  default?: unknown;
  minimum?: number;
  maximum?: number;
  minLength?: number;
  maxLength?: number;
  examples?: unknown[];
  format?: string;
  /**
   * Presentation hint, ignored by validation: forces a single-line control on a
   * field whose `maxLength` would otherwise make it a textarea. See `kindOf`.
   */
  "x-control"?: "text";
}

export interface JsonSchema {
  type: "object";
  required?: string[];
  properties: Record<string, JsonSchemaProperty>;
}

export interface Block {
  id: string;
  type: string;
  data: Record<string, unknown>;
}

export interface BlockDocument {
  version: number;
  blocks: Block[];
}

export interface AccessInfo {
  allowed: boolean;
  reason: string | null;
  error_code: string | null;
  message: string | null;
  required_tier: ToolTier | null;
}

/**
 * Both field-name pairs found in the database. See `faqEntries` in `lib/faq.ts`,
 * which is the only place either shape should be read.
 */
export interface FaqEntry {
  question?: string;
  answer?: string;
  q?: string;
  a?: string;
}

export interface ToolDetail extends Omit<ToolSummary, "stats"> {
  /** The stable registry key (`youtube.comment-generator`). Custom tool UIs are keyed on it. */
  key: string;
  description: string | null;
  version: number;
  input_schema: JsonSchema;
  instructions: BlockDocument | null;
  example: { input: Record<string, unknown>; note?: string } | null;
  faq: FaqEntry[];
  access?: AccessInfo;
  related: ToolSummary[];
  successor?: { slug: string; name: string } | null;
  seo?: SeoMeta;
  stats: { runs: number; avg_duration_ms: number; success_rate: number };
  updated_at: string | null;
}

/**
 * The stored overrides for one entity, exactly as the API holds them.
 *
 * Every field is nullable because every field is optional: null means "fall back",
 * and the fallback chain (entity copy → site template) is resolved at render.
 */
export interface SeoMeta {
  title: string | null;
  description: string | null;
  canonical_url: string | null;
  robots: string;
  focus_keyword: string | null;
  og_title: string | null;
  og_description: string | null;
  og_image_url: string | null;
  twitter_card: string;
  schema_type: string | null;
}

export interface ResultArtifact {
  filename: string;
  mime_type: string;
  size: number;
  url: string | null;
  preview_url: string | null;
  width: number | null;
  height: number | null;
  label: string | null;
}

export interface ToolResult {
  view: ResultView;
  summary: string | null;
  data: Record<string, never> & Record<string, unknown>;
  artifacts: ResultArtifact[];
  warnings: string[];
  meta: Record<string, unknown>;
}

export interface ToolRun {
  id: string;
  status: "queued" | "running" | "succeeded" | "failed" | "cancelled";
  tool?: { slug: string; name: string; version: number };
  result: ToolResult | null;
  error?: { code: string; message: string };
  meta: { cache_hit: boolean; duration_ms: number | null; access_reason: string | null };
  created_at: string;
}

export interface Entitlements {
  plan: string;
  status: string;
  is_paid: boolean;
  renews_at: string | null;
  expires_at: string | null;
  cancels_at: string | null;
  limits: {
    runs_per_day: number;
    history_days: number | null;
    export: boolean;
    priority_support: boolean;
  };
  usage: { limit: number; used: number; remaining: number; resets_at: string };
  tool_access: { default_tier: ToolTier; grants: string[] };
}

export interface Paginated<T> {
  data: T[];
  meta: {
    page: { current: number; per_page: number; total: number; last_page: number };
    access?: Record<string, boolean>;
  };
  links: { first?: string; prev?: string; next?: string; last?: string };
}

export interface ApiError {
  code: string;
  message: string;
  status: number;
  details?: Record<string, unknown>;
  request_id?: string;
}

// ── Account ──────────────────────────────────────────────────────────────────

export interface AuthUser {
  id: string;
  email: string;
  display_name: string;
  initials: string;
  avatar_url: string | null;
  locale: string;
  timezone: string;
  status: string;
  marketing_opt_in: boolean;
  email_verified: boolean;
  has_password: boolean;
  is_staff: boolean;
  roles: string[];
  permissions: string[];
  deletion_requested_at: string | null;
  created_at: string | null;
}

export interface UserDevice {
  id: number;
  label: string;
  location: string | null;
  last_seen_at: string | null;
  created_at: string | null;
  is_current: boolean;
}

export type NotificationGroup =
  | "security"
  | "billing"
  | "tools"
  | "support"
  | "product"
  | "staff";

export interface NotificationItem {
  id: string;
  event: string | null;
  group: NotificationGroup | null;
  icon: string;
  title: string;
  body: string;
  action: { label: string; url: string } | null;
  read_at: string | null;
  created_at: string | null;
}

export interface NotificationPreferenceGroup {
  key: NotificationGroup;
  label: string;
  events: {
    key: string;
    title: string;
    /** `email` is null for events that have no email channel to toggle. */
    channels: { email: boolean | null; in_app: boolean };
  }[];
}

// ── Blog ─────────────────────────────────────────────────────────────────────

export interface PostAuthor {
  name: string;
  avatar_url: string | null;
}

export interface PostCategory {
  slug: string;
  name: string;
  description?: string | null;
  accent_color?: string | null;
  post_count?: number;
}

export interface PostTag {
  slug: string;
  name: string;
  post_count?: number;
}

export interface PostImage {
  url: string;
  alt: string;
  width: number | null;
  height: number | null;
  blur_hash: string | null;
}

export interface PostSummary {
  id: string;
  slug: string;
  title: string;
  excerpt: string | null;
  featured_image?: PostImage | null;
  category?: Pick<PostCategory, "slug" | "name" | "accent_color"> | null;
  author?: PostAuthor;
  tags?: PostTag[];
  is_featured: boolean;
  reading_minutes: number;
  word_count: number;
  published_at: string | null;
}

export interface PostDetail extends PostSummary {
  blocks: Block[];
  seo: {
    title: string | null;
    description: string | null;
    canonical_url: string | null;
    robots: string | null;
    og_title: string | null;
    og_description: string | null;
    og_image_url: string | null;
    twitter_card: string | null;
    schema_type: string | null;
  };
  related: PostSummary[];
}
