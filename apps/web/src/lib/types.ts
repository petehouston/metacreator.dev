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

/**
 * The catalog's two orderings that are not derivable from a card.
 *
 * Both arrive as slug lists alongside the page rather than as a field on each
 * tool: the catalog filters and sorts in the browser, so it needs the whole
 * ranking once instead of a request per sort change.
 */
export interface TrendingRanking {
  /** The window, in days, an admin has configured. */
  days: number;
  minimum_runs: number;
  /** Slugs, most active first. */
  slugs: string[];
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
   * Presentation hint, ignored by validation: overrides the control `kindOf`
   * would otherwise infer from `maxLength`. `"text"` forces a single line on a
   * generously sized field; `"textarea"` forces a box on a short one, which a
   * comment field wants even at 300 characters.
   */
  "x-control"?: "text" | "textarea";
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
  /** Absent for a guest: "not saved" and "nobody to save it for" are different. */
  is_favorite?: boolean;
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
  /** The input this run was given. Detail view only, and members only. */
  input?: Record<string, unknown> | null;
  /**
   * Whether there is a stored result to open. Distinguishes "this run kept
   * nothing" from "this run produced nothing", which look identical otherwise.
   */
  has_stored_result?: boolean;
  error?: { code: string; message: string };
  meta: {
    cache_hit: boolean;
    duration_ms: number | null;
    access_reason: string | null;
  };
  created_at: string;
}

/**
 * What the API says when an actor has spent an allowance.
 *
 * Carries both ways out — the tier above, and when this one resets — because
 * those are the only two things the person can do about it. Budgets are counted
 * over a day, a week and a month at once, so it also names *which* one ran out:
 * telling someone who hit a monthly ceiling to come back tomorrow just sends them
 * back into the same wall.
 */
export type QuotaWindow = "daily" | "weekly" | "monthly";

export interface QuotaExceededDetails {
  limit: number;
  /** Which budget ran out. "Come back tomorrow" is only true of the daily one. */
  window: QuotaWindow;
  window_label: string;
  resets_at: string;
  upgrade_available: boolean;
  tier: ToolTier | null;
  next_tier: ToolTier | null;
  /** Null when the tier above is itself unlimited — see `next_tier_unlimited`. */
  next_tier_limit: number | null;
  next_tier_unlimited: boolean;
  upgrade_action: "register" | "subscribe" | null;
}

export interface Entitlements {
  /**
   * Whether the site sells anything at all. False collapses the ladder: `plan` is
   * `free`, `is_paid` is false regardless of any dormant subscription, and every
   * Pro tool is gated at `account`.
   */
  billing_enabled: boolean;
  plan: string;
  status: string;
  is_paid: boolean;
  renews_at: string | null;
  expires_at: string | null;
  cancels_at: string | null;
  limits: {
    /** -1 means unlimited. */
    runs_per_day: number;
    /** The same allowance for every window. -1 means that window is not counted. */
    runs: Record<QuotaWindow, number>;
    history_days: number | null;
    export: boolean;
    priority_support: boolean;
  };
  /** Which rung of the ladder this actor is on. */
  access_tier: ToolTier;
  /** Every rung's allowance per window, so a wall can say what the next is worth. */
  tier_limits: Record<ToolTier, Record<QuotaWindow, number>>;
  /**
   * The headline figures describe the **binding** window — the counted one with the
   * least left — rather than always the day, so a site capped monthly does not show
   * a meter reading "unlimited". `windows` has the full breakdown.
   */
  usage: {
    limit: number;
    used: number;
    /** Null when unlimited — "unlimited minus four" is not a quantity. */
    remaining: number | null;
    unlimited: boolean;
    window: QuotaWindow;
    tier: ToolTier;
    resets_at: string;
    windows: Record<
      QuotaWindow,
      {
        limit: number;
        used: number;
        remaining: number | null;
        unlimited: boolean;
        label: string;
        resets_at: string;
      }
    >;
  };
  tool_access: {
    default_tier: ToolTier;
    /** The top rung the catalog currently has — `account` while billing is off. */
    highest_tier: ToolTier;
    grants: string[];
  };
}

export interface Paginated<T> {
  data: T[];
  meta: {
    page: {
      current: number;
      per_page: number;
      total: number;
      last_page: number;
    };
    access?: Record<string, boolean>;
    trending?: TrendingRanking;
    /** The caller's own saved slugs. Empty for a guest. */
    favorites?: string[];
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

export type NotificationGroup = "security" | "billing" | "tools" | "support" | "product" | "staff";

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

// ── Changelog ────────────────────────────────────────────────────────────────

/**
 * The change types the API knows about, as string literals rather than an enum:
 * the API is the source of truth and ships the catalog with every `meta` call, so
 * a type added server-side widens this union in one line and nothing else breaks.
 */
export type ChangeTypeValue =
  | "added"
  | "improved"
  | "fixed"
  | "deprecated"
  | "removed"
  | "security";

/** The tone the API asks the UI to paint a change type. */
export type ChangeTone = "success" | "info" | "warning" | "danger" | "muted";

export interface ChangelogItem {
  id: number;
  type: ChangeTypeValue;
  type_label: string;
  tone: ChangeTone;
  title: string;
  description: string | null;
}

export interface ChangelogRelease {
  id: string;
  slug: string;
  version: string | null;
  title: string;
  summary: string | null;
  is_major: boolean;
  released_at: string | null;
  items: ChangelogItem[];
  /** Pre-counted per type, so the summary line costs no client-side grouping. */
  counts?: Partial<Record<ChangeTypeValue, number>>;
}

export interface ChangeTypeOption {
  value: ChangeTypeValue;
  label: string;
  hint: string;
  tone: ChangeTone;
  /** Canonical reading order, decided by the API. Lower sorts first. */
  weight: number;
}

export interface ChangelogMeta {
  types: ChangeTypeOption[];
  /** Only the years that actually have published releases in them. */
  years: { year: number; total: number }[];
}

// ── Top rankings ─────────────────────────────────────────────────────────────

export type RankingPlatformValue =
  | "youtube"
  | "instagram"
  | "tiktok"
  | "x"
  | "facebook"
  | "twitch"
  | "bluesky";

/**
 * How to read the number on `metric`.
 *
 * The API stores what the source published rather than a normalised count — "515"
 * under a header reading "(millions)" — so the unit travels with the page and the
 * client does the arithmetic once, at render.
 */
export type MetricUnit = "exact" | "thousands" | "millions" | "billions";

/** The resolved SEO block a public page renders its metadata from. */
export interface PageSeo {
  title: string | null;
  description: string | null;
  canonical_url: string | null;
  robots: string | null;
  focus_keyword: string | null;
  og_title: string | null;
  og_description: string | null;
  og_image_url: string | null;
  twitter_card: string | null;
  schema_type: string | null;
}

export interface TopRankingEntry {
  rank: number;
  name: string;
  handle: string | null;
  owner: string | null;
  profile_url: string | null;
  metric: number | null;
  secondary_metric: number | null;
  country: string | null;
  category: string | null;
  language: string | null;
  description: string | null;
  /**
   * Null means "draw the monogram". The API has already ruled out links that are
   * unresolved *or* past their signature expiry, so this is one branch here rather
   * than a date comparison in the component.
   */
  avatar_url: string | null;
  initials: string;
}

export interface TopRankingPage {
  slug: string;
  title: string;
  platform: RankingPlatformValue;
  platform_label: string;
  /** An oklch triple (`L C H`) the page drops straight into `oklch(...)`. */
  platform_accent: string;
  /** "channel", "account", "page" — for headings that read naturally per network. */
  noun: string;
  metric_label: string;
  metric_unit: MetricUnit;
  secondary_metric_label: string | null;
  secondary_metric_unit: MetricUnit | null;
  intro: string | null;
  /**
   * Stored overrides with the page's own title and intro filled in behind them.
   *
   * Already resolved server-side, so `generateMetadata` reads one value per field
   * rather than re-deriving a fallback chain the API also has an opinion about.
   */
  seo: PageSeo | null;
  source_page: string;
  source_url: string;
  synced_at: string | null;
  entries_count: number;
  /** Only on the detail response; the index omits it. */
  entries?: TopRankingEntry[];
}
