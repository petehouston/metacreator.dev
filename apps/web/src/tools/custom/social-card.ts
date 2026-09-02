/**
 * How each platform's mock-up card is drawn, on a canvas, in the visitor's browser.
 *
 * The API has a runner per platform that draws the same cards as SVG, and that is
 * the headless implementation of record (docs/08). This is the one people actually
 * use, and it exists in the browser for two reasons that do not apply on the
 * server: an avatar somebody drops in never leaves the device, so there is nothing
 * for us to upload, store or promise to delete; and the preview has to be live,
 * because nobody sets a like count, a theme and a device width blind and then
 * submits a form to find out what they got.
 *
 * The layouts below are transcriptions of each app's own card, drawn at roughly 2×
 * so an export lands sharp in a slide or a thumbnail. Keeping them close to the
 * PHP runners is deliberate: the two are meant to produce the same picture, and
 * where they drift the runner is the definition.
 *
 * Every card here is a **mock-up, not evidence**. Nothing draws a verification
 * badge, and the tool page says so on every export.
 */

export type CardPlatform = "facebook" | "instagram" | "x-reply" | "pinterest" | "tiktok";

/** Form values, exactly as the schema-driven form holds them. */
export type CardValues = Record<string, string | number | boolean>;

export interface CardImages {
  /** Decoded in the page and held in memory only — never uploaded, never persisted. */
  avatar: CanvasImageSource | null;
}

interface Palette {
  bg: string;
  fg: string;
  muted: string;
  border: string;
  accent: string;
  frame: string;
  chip: string;
}

const FONT = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';

/**
 * Each platform's own palette, sampled from the app.
 *
 * These mirror the `THEMES` constants in the matching PHP runner. A colour that
 * disagrees between the two is a bug in whichever was edited last.
 */
const PALETTES: Record<CardPlatform, Record<string, Palette>> = {
  facebook: {
    light: { bg: "#FFFFFF", fg: "#050505", muted: "#65676B", border: "#CED0D4", accent: "#0866FF", frame: "#F0F2F5", chip: "#F0F2F5" },
    dark: { bg: "#242526", fg: "#E4E6EB", muted: "#B0B3B8", border: "#3E4042", accent: "#2E89FF", frame: "#3A3B3C", chip: "#3A3B3C" },
  },
  instagram: {
    light: { bg: "#FFFFFF", fg: "#000000", muted: "#737373", border: "#DBDBDB", accent: "#00376B", frame: "#EFEFEF", chip: "#FAFAFA" },
    dark: { bg: "#000000", fg: "#F5F5F5", muted: "#A8A8A8", border: "#262626", accent: "#E0F1FF", frame: "#1A1A1A", chip: "#121212" },
  },
  "x-reply": {
    light: { bg: "#FFFFFF", fg: "#0F1419", muted: "#536471", border: "#EFF3F4", accent: "#1D9BF0", frame: "#F7F9F9", chip: "#EFF3F4" },
    dim: { bg: "#15202B", fg: "#F7F9F9", muted: "#8B98A5", border: "#38444D", accent: "#1D9BF0", frame: "#1E2732", chip: "#38444D" },
    dark: { bg: "#000000", fg: "#E7E9EA", muted: "#71767B", border: "#2F3336", accent: "#1D9BF0", frame: "#16181C", chip: "#2F3336" },
  },
  pinterest: {
    light: { bg: "#FFFFFF", fg: "#111111", muted: "#767676", border: "#E9E9E9", accent: "#0074E8", frame: "#EFEFEF", chip: "#E60023" },
    dark: { bg: "#111111", fg: "#F5F5F5", muted: "#B5B5B5", border: "#2A2A2A", accent: "#7FB9FF", frame: "#1E1E1E", chip: "#E60023" },
  },
  tiktok: {
    dark: { bg: "#121212", fg: "#FFFFFF", muted: "#8A8B91", border: "#2A2A2C", accent: "#20D5EC", frame: "#1E1E20", chip: "#2A2A2C" },
    light: { bg: "#FFFFFF", fg: "#161823", muted: "#86878B", border: "#E3E3E4", accent: "#00B4CC", frame: "#F1F1F2", chip: "#F1F1F2" },
  },
};

/** Card width per platform and device, matching the runners' `WIDTHS`. */
const WIDTHS: Record<CardPlatform, { desktop: number; mobile: number }> = {
  facebook: { desktop: 1120, mobile: 780 },
  instagram: { desktop: 940, mobile: 780 },
  "x-reply": { desktop: 1100, mobile: 800 },
  pinterest: { desktop: 900, mobile: 760 },
  tiktok: { desktop: 1000, mobile: 820 },
};

/** Where Instagram cuts a caption in the feed. */
const CAPTION_FOLD = 125;

/** Where Pinterest cuts a Pin title in the closeup. */
const TITLE_FOLD = 40;

/* ── Public API ───────────────────────────────────────────────────────────── */

export function widthFor(platform: CardPlatform, device: string): number {
  const widths = WIDTHS[platform];

  return device === "mobile" ? widths.mobile : widths.desktop;
}

export function paletteFor(platform: CardPlatform, theme: string): Palette {
  const themes = PALETTES[platform];

  return themes[theme] ?? Object.values(themes)[0];
}

/**
 * Paint the card and return the size it took, in CSS pixels.
 *
 * The canvas is resized to fit: a two-line post should not export with a field of
 * empty background under it. `scale` is the export multiplier — the layout below
 * is written at 1× and the context is scaled once, so none of the arithmetic has
 * to know about pixel density.
 */
export function drawCard(
  canvas: HTMLCanvasElement,
  platform: CardPlatform,
  values: CardValues,
  images: CardImages,
  options: { scale?: number; transparent?: boolean } = {},
): { width: number; height: number } {
  const context = canvas.getContext("2d");

  if (!context) return { width: 0, height: 0 };

  const scale = options.scale ?? 2;
  const palette = paletteFor(platform, str(values.theme));
  const width = widthFor(platform, str(values.device));

  // Two passes: the height depends on how the text wraps, and resizing a canvas
  // clears it — so the layout has to be measured before the canvas is sized.
  const layout = LAYOUTS[platform];
  const height = layout(context, { width, palette, values, images, measureOnly: true });

  canvas.width = Math.round(width * scale);
  canvas.height = Math.round(height * scale);
  canvas.style.aspectRatio = `${width} / ${height}`;

  context.setTransform(scale, 0, 0, scale, 0, 0);
  context.clearRect(0, 0, width, height);

  if (!options.transparent) {
    context.fillStyle = palette.bg;
    context.fillRect(0, 0, width, height);
  }

  layout(context, { width, palette, values, images, measureOnly: false });

  return { width, height };
}

/* ── Drawing helpers ──────────────────────────────────────────────────────── */

interface Ctx {
  width: number;
  palette: Palette;
  values: CardValues;
  images: CardImages;
  /** On a measuring pass nothing is painted; only the height is computed. */
  measureOnly: boolean;
}

function str(value: unknown, fallback = ""): string {
  return typeof value === "string" ? value : typeof value === "number" ? String(value) : fallback;
}

function num(value: unknown): number {
  const parsed = typeof value === "number" ? value : Number(value);

  return Number.isFinite(parsed) ? Math.max(0, Math.floor(parsed)) : 0;
}

function bool(value: unknown): boolean {
  return value === true || value === "true";
}

/**
 * A count as a feed abbreviates it: 999, then 1K, 5.2K, 1.4M.
 *
 * One decimal with a trailing `.0` dropped — every one of these platforms writes
 * "5K", never "5.0K", and that is the detail that gives a mock-up away fastest.
 */
export function compact(value: number): string {
  const count = Math.max(0, Math.floor(value) || 0);

  if (count >= 1_000_000) return `${trimZero(count / 1_000_000)}M`;
  if (count >= 1_000) return `${trimZero(count / 1_000)}K`;

  return String(count);
}

function trimZero(value: number): string {
  return value.toFixed(1).replace(/\.0$/, "");
}

export function handle(value: string): string {
  return `@${value.trim().replace(/^@+/, "")}`;
}

/**
 * Greedy word wrap against real glyph widths.
 *
 * `measure` is passed in rather than the context so the wrap can be unit-tested
 * without a canvas, and so the width is never an estimate: it is whatever the font
 * actually measures at the size it will be drawn.
 */
export function wrapText(
  text: string,
  maxWidth: number,
  measure: (text: string) => number,
): string[] {
  const lines: string[] = [];

  for (const paragraph of text.split(/\r?\n/)) {
    if (paragraph.trim() === "") {
      lines.push("");
      continue;
    }

    let current = "";

    for (const word of paragraph.trim().split(/\s+/)) {
      const candidate = current === "" ? word : `${current} ${word}`;

      if (measure(candidate) <= maxWidth) {
        current = candidate;
        continue;
      }

      if (current !== "") lines.push(current);

      // A single word wider than the line — a URL, usually — is broken by
      // character rather than allowed to run off the edge of the card.
      let rest = word;

      while (measure(rest) > maxWidth && rest.length > 1) {
        let cut = rest.length;

        while (cut > 1 && measure(rest.slice(0, cut)) > maxWidth) cut -= 1;

        lines.push(rest.slice(0, cut));
        rest = rest.slice(cut);
      }

      current = rest;
    }

    if (current !== "") lines.push(current);
  }

  return lines.length === 0 ? [""] : lines;
}

function wrap(
  context: CanvasRenderingContext2D,
  text: string,
  maxWidth: number,
  font: string,
): string[] {
  context.font = font;

  return wrapText(text, maxWidth, (value) => context.measureText(value).width);
}

/** Draw wrapped lines, colouring links, mentions and hashtags the way apps do. */
function drawLines(
  context: CanvasRenderingContext2D,
  lines: string[],
  x: number,
  y: number,
  lineHeight: number,
  font: string,
  color: string,
  accent?: string,
) {
  context.font = font;
  context.textBaseline = "alphabetic";

  lines.forEach((line, index) => {
    const baseline = y + index * lineHeight;

    if (!accent) {
      context.fillStyle = color;
      context.fillText(line, x, baseline);

      return;
    }

    let cursor = x;

    for (const part of line.split(/(\s+)/)) {
      context.fillStyle = /^(https?:\/\/|www\.|@\w|#\w)/.test(part) ? accent : color;
      context.fillText(part, cursor, baseline);
      cursor += context.measureText(part).width;
    }
  });
}

function text(
  context: CanvasRenderingContext2D,
  value: string,
  x: number,
  y: number,
  font: string,
  color: string,
  align: CanvasTextAlign = "left",
) {
  context.font = font;
  context.fillStyle = color;
  context.textAlign = align;
  context.textBaseline = "alphabetic";
  context.fillText(value, x, y);
  context.textAlign = "left";
}

/** The dropped avatar cropped to a circle, or the name's initials on a flat disc. */
function avatar(
  context: CanvasRenderingContext2D,
  image: CanvasImageSource | null,
  name: string,
  cx: number,
  cy: number,
  radius: number,
  disc: string,
  ink: string,
) {
  context.save();
  context.beginPath();
  context.arc(cx, cy, radius, 0, Math.PI * 2);
  context.closePath();
  context.clip();

  if (image) {
    // `cover`, not `fill`: every app square-crops an avatar, and a stretched face
    // is instantly noticeable.
    const source = imageSize(image);
    const ratio = Math.max((radius * 2) / source.width, (radius * 2) / source.height);

    context.drawImage(
      image,
      cx - (source.width * ratio) / 2,
      cy - (source.height * ratio) / 2,
      source.width * ratio,
      source.height * ratio,
    );
  } else {
    context.fillStyle = disc;
    context.fillRect(cx - radius, cy - radius, radius * 2, radius * 2);

    context.fillStyle = ink;
    context.font = `700 ${Math.round(radius * 0.85)}px ${FONT}`;
    context.textAlign = "center";
    context.textBaseline = "middle";
    context.fillText(initials(name), cx, cy + 1);
  }

  context.restore();
  context.textAlign = "left";
  context.textBaseline = "alphabetic";
}

export function initials(name: string): string {
  const words = name.trim().replace(/^@+/, "").split(/\s+/).filter(Boolean);

  if (words.length === 0) return "?";

  const first = words[0].slice(0, 1).toUpperCase();

  return words.length === 1 ? first : first + words[words.length - 1].slice(0, 1).toUpperCase();
}

function roundRect(
  context: CanvasRenderingContext2D,
  x: number,
  y: number,
  width: number,
  height: number,
  radius: number,
  fill: string,
) {
  context.beginPath();
  context.moveTo(x + radius, y);
  context.arcTo(x + width, y, x + width, y + height, radius);
  context.arcTo(x + width, y + height, x, y + height, radius);
  context.arcTo(x, y + height, x, y, radius);
  context.arcTo(x, y, x + width, y, radius);
  context.closePath();
  context.fillStyle = fill;
  context.fill();
}

/** The marked frame that stands in for the photo. See the tools' own FAQ for why. */
function placeholder(
  context: CanvasRenderingContext2D,
  x: number,
  y: number,
  width: number,
  height: number,
  palette: Palette,
  label: string,
  radius = 0,
) {
  if (radius > 0) {
    roundRect(context, x, y, width, height, radius, palette.frame);
  } else {
    context.fillStyle = palette.frame;
    context.fillRect(x, y, width, height);
  }

  text(context, label, x + width / 2, y + height / 2, `400 26px ${FONT}`, palette.muted, "center");
}

function imageSize(source: CanvasImageSource): { width: number; height: number } {
  if (typeof HTMLImageElement !== "undefined" && source instanceof HTMLImageElement) {
    return { width: source.naturalWidth || source.width, height: source.naturalHeight || source.height };
  }

  const candidate = source as { width?: number; height?: number };

  return { width: candidate.width ?? 1, height: candidate.height ?? 1 };
}

/* ── Layouts ──────────────────────────────────────────────────────────────── */

type Layout = (context: CanvasRenderingContext2D, ctx: Ctx) => number;

const LAYOUTS: Record<CardPlatform, Layout> = {
  facebook: drawFacebook,
  instagram: drawInstagram,
  "x-reply": drawXReply,
  pinterest: drawPinterest,
  tiktok: drawTikTok,
};

function drawFacebook(context: CanvasRenderingContext2D, ctx: Ctx): number {
  const { width, palette, values, images, measureOnly } = ctx;
  const pad = 36;
  const bodySize = width > 900 ? 30 : 32;
  const lineHeight = bodySize + 12;
  const name = str(values.name) || "Page name";

  const lines = wrap(context, str(values.text) || "Your post will appear here.", width - pad * 2, `400 ${bodySize}px ${FONT}`);

  const bodyTop = pad + 118;
  let y = bodyTop + (lines.length - 1) * lineHeight + 34;

  const reactions = num(values.reactions);
  const comments = num(values.comments);
  const shares = num(values.shares);
  const hasCounts = reactions > 0 || comments > 0 || shares > 0;

  if (hasCounts) y += 44;

  y += 16;
  const rule = y;

  y += 42;
  const height = y + pad;

  if (measureOnly) return height;

  avatar(context, images.avatar, name, pad + 34, pad + 34, 34, palette.accent, palette.bg);

  const textX = pad + 88;
  const audience = str(values.audience, "public");

  text(context, name, textX, pad + 26, `600 30px ${FONT}`, palette.fg);
  text(
    context,
    `${str(values.timestamp, "2h")}  ·  ${audience === "friends" ? "Friends" : audience === "private" ? "Only me" : "Public"}`,
    textX,
    pad + 58,
    `400 24px ${FONT}`,
    palette.muted,
  );

  drawLines(context, lines, pad, bodyTop, lineHeight, `400 ${bodySize}px ${FONT}`, palette.fg, palette.accent);

  if (hasCounts) {
    const countsY = bodyTop + (lines.length - 1) * lineHeight + 34;

    // Facebook puts reactions on the left and comments/shares on the right; that
    // asymmetry is one of the things the eye recognises.
    context.beginPath();
    context.arc(pad + 16, countsY + 8, 16, 0, Math.PI * 2);
    context.fillStyle = palette.accent;
    context.fill();

    context.beginPath();
    context.arc(pad + 44, countsY + 8, 16, 0, Math.PI * 2);
    context.fillStyle = "#F3425F";
    context.fill();
    context.lineWidth = 3;
    context.strokeStyle = palette.bg;
    context.stroke();

    if (reactions > 0) {
      text(context, compact(reactions), pad + 76, countsY + 18, `400 24px ${FONT}`, palette.muted);
    }

    const right = [
      comments > 0 ? `${compact(comments)} comment${comments === 1 ? "" : "s"}` : "",
      shares > 0 ? `${compact(shares)} share${shares === 1 ? "" : "s"}` : "",
    ].filter(Boolean);

    if (right.length > 0) {
      text(context, right.join("  "), width - pad, countsY + 18, `400 24px ${FONT}`, palette.muted, "right");
    }
  }

  context.beginPath();
  context.moveTo(pad, rule);
  context.lineTo(width - pad, rule);
  context.strokeStyle = palette.border;
  context.lineWidth = 2;
  context.stroke();

  const third = Math.floor(width / 3);

  ["Like", "Comment", "Share"].forEach((label, index) => {
    text(context, label, third * index + third / 2, rule + 42, `600 26px ${FONT}`, palette.muted, "center");
  });

  return height;
}

function drawInstagram(context: CanvasRenderingContext2D, ctx: Ctx): number {
  const { width, palette, values, images, measureOnly } = ctx;
  const pad = 28;
  const header = 108;
  const shape = str(values.shape, "square");
  const ratio = shape === "portrait" ? 1.25 : shape === "landscape" ? 0.5625 : 1;
  const mediaHeight = Math.round(width * ratio);

  const username = (str(values.username) || "username").replace(/^@+/, "");
  const caption = str(values.caption) || "Your caption will appear here.";
  const visible = caption.slice(0, CAPTION_FOLD);
  const hidden = caption.slice(CAPTION_FOLD);
  const inner = width - pad * 2;
  const font = `400 28px ${FONT}`;

  const visibleLines = wrap(context, visible, inner, font);
  const hiddenLines = hidden === "" ? [] : wrap(context, hidden, inner, font);

  const likes = num(values.likes);
  const comments = num(values.comments);

  let y = header + mediaHeight + 52;

  if (likes > 0) y += 46;

  y += 44;
  const captionTop = y;

  y += 40 + (visibleLines.length - 1) * 40;
  if (hiddenLines.length > 0) y += 40 + (hiddenLines.length - 1) * 40;
  if (comments > 0) y += 46;
  y += 42;

  const height = y + pad;

  if (measureOnly) return height;

  const location = str(values.location).trim();

  avatar(context, images.avatar, username, pad + 32, 54, 32, palette.accent, palette.bg);
  text(context, username, pad + 82, location === "" ? 64 : 48, `600 28px ${FONT}`, palette.fg);

  if (location !== "") {
    text(context, location, pad + 82, 78, `400 24px ${FONT}`, palette.muted);
  }

  placeholder(
    context,
    0,
    header,
    width,
    mediaHeight,
    palette,
    `${shape.charAt(0).toUpperCase()}${shape.slice(1)} · your photo goes here`,
  );

  const actionsY = header + mediaHeight + 52;

  text(context, "Like   Comment   Share", pad, actionsY, `600 25px ${FONT}`, palette.fg);
  text(context, "Save", width - pad, actionsY, `600 25px ${FONT}`, palette.fg, "right");

  let cursor = actionsY;

  if (likes > 0) {
    cursor += 46;
    text(context, `${compact(likes)} like${likes === 1 ? "" : "s"}`, pad, cursor, `600 27px ${FONT}`, palette.fg);
  }

  text(context, username, pad, captionTop, `600 28px ${FONT}`, palette.fg);
  drawLines(context, visibleLines, pad, captionTop + 40, 40, font, palette.fg, palette.accent);

  cursor = captionTop + 40 + (visibleLines.length - 1) * 40;

  if (hiddenLines.length > 0) {
    // Greyed in place rather than dropped: seeing which sentence gets cut is the
    // entire point of drawing the fold.
    context.globalAlpha = 0.55;
    drawLines(context, hiddenLines, pad, cursor + 40, 40, font, palette.muted);
    context.globalAlpha = 1;
    cursor += 40 + (hiddenLines.length - 1) * 40;
  }

  if (comments > 0) {
    cursor += 46;
    text(context, `View all ${compact(comments)} comments`, pad, cursor, `400 24px ${FONT}`, palette.muted);
  }

  text(context, str(values.timestamp, "2 hours ago"), pad, cursor + 42, `400 21px ${FONT}`, palette.muted);

  return height;
}

function drawXReply(context: CanvasRenderingContext2D, ctx: Ctx): number {
  const { width, palette, values, images, measureOnly } = ctx;
  const pad = 40;
  const radius = 32;
  const textX = pad + radius * 2 + 24;
  const inner = width - textX - pad;
  const size = width > 900 ? 30 : 32;
  const lineHeight = size + 12;
  const font = `400 ${size}px ${FONT}`;

  const parentName = str(values.parent_name) || "Original poster";
  const replyName = str(values.reply_name) || "Replier";
  const parentHandle = handle(str(values.parent_handle) || "someone");
  const replyHandle = handle(str(values.reply_handle) || "someone_else");

  const parentLines = wrap(context, str(values.parent_text) || "The original post.", inner, font);
  const replyLines = wrap(context, str(values.reply_text) || "The reply.", inner, font);

  const parentTop = pad + 30;
  const parentBodyTop = parentTop + 56;
  const parentBottom = parentBodyTop + (parentLines.length - 1) * lineHeight + 28;
  const replyTop = parentBottom + 52;
  const replyBodyTop = replyTop + 56;

  let y = replyBodyTop + (replyLines.length - 1) * lineHeight;

  const metrics = [
    num(values.replies) > 0 ? `${compact(num(values.replies))}  Replies` : "",
    num(values.reposts) > 0 ? `${compact(num(values.reposts))}  Reposts` : "",
    num(values.likes) > 0 ? `${compact(num(values.likes))}  Likes` : "",
  ].filter(Boolean);

  if (metrics.length > 0) y += 34 + 44;

  const height = y + pad;

  if (measureOnly) return height;

  // The thread line X draws from the parent's avatar down to the reply's.
  context.beginPath();
  context.moveTo(pad + radius, parentTop + radius + 12);
  context.lineTo(pad + radius, replyTop - radius + 4);
  context.strokeStyle = palette.border;
  context.lineWidth = 4;
  context.stroke();

  avatar(context, null, parentName, pad + radius, parentTop + 4, radius, palette.accent, palette.bg);
  authorRow(context, palette, textX, parentTop, parentName, parentHandle, str(values.parent_timestamp, "4h"));
  drawLines(context, parentLines, textX, parentBodyTop, lineHeight, font, palette.fg, palette.accent);

  context.font = `400 26px ${FONT}`;
  context.fillStyle = palette.muted;
  context.fillText("Replying to ", textX, parentBottom + 22);
  context.fillStyle = palette.accent;
  context.fillText(parentHandle, textX + context.measureText("Replying to ").width, parentBottom + 22);

  avatar(context, images.avatar, replyName, pad + radius, replyTop + 4, radius, palette.accent, palette.bg);
  authorRow(context, palette, textX, replyTop, replyName, replyHandle, str(values.reply_timestamp, "3h"));
  drawLines(context, replyLines, textX, replyBodyTop, lineHeight, font, palette.fg, palette.accent);

  if (metrics.length > 0) {
    const ruleY = replyBodyTop + (replyLines.length - 1) * lineHeight + 34;

    context.beginPath();
    context.moveTo(textX, ruleY);
    context.lineTo(width - pad, ruleY);
    context.strokeStyle = palette.border;
    context.lineWidth = 2;
    context.stroke();

    text(context, metrics.join("     "), textX, ruleY + 44, `400 26px ${FONT}`, palette.muted);
  }

  return height;
}

/** Name, handle and timestamp on one baseline, measured rather than estimated. */
function authorRow(
  context: CanvasRenderingContext2D,
  palette: Palette,
  x: number,
  y: number,
  name: string,
  userHandle: string,
  stamp: string,
) {
  context.font = `700 30px ${FONT}`;
  context.fillStyle = palette.fg;
  context.textBaseline = "alphabetic";
  context.fillText(name, x, y);

  let cursor = x + context.measureText(name).width + 14;

  context.font = `400 26px ${FONT}`;
  context.fillStyle = palette.muted;
  context.fillText(userHandle, cursor, y);

  if (stamp.trim() !== "") {
    cursor += context.measureText(userHandle).width + 14;
    context.fillText(`· ${stamp}`, cursor, y);
  }
}

function drawPinterest(context: CanvasRenderingContext2D, ctx: Ctx): number {
  const { width, palette, values, images, measureOnly } = ctx;
  const pad = 32;
  const inner = width - pad * 2;
  const shape = str(values.shape, "standard");
  const ratio = shape === "square" ? 1 : shape === "long" ? 2.1 : 1.5;
  const mediaHeight = Math.round(inner * ratio);

  const rawTitle = str(values.title) || "Your Pin title";
  const title = rawTitle.length > TITLE_FOLD ? `${rawTitle.slice(0, TITLE_FOLD)}…` : rawTitle;
  const description = str(values.description).trim();
  const source = str(values.source_url).trim();
  const account = str(values.account) || "Your account";

  const titleLines = wrap(context, title, inner, `700 36px ${FONT}`);
  const descriptionLines =
    description === "" ? [] : wrap(context, description, inner, `400 27px ${FONT}`).slice(0, 4);

  let y = pad + mediaHeight + 62;
  const titleTop = y;

  y += (titleLines.length - 1) * 48;
  if (descriptionLines.length > 0) y += 52 + (descriptionLines.length - 1) * 38;
  if (source !== "") y += 50;
  y += 72;

  const height = y + 52;

  if (measureOnly) return height;

  placeholder(context, pad, pad, inner, mediaHeight, palette, shapeLabel(shape), 32);

  // Pinterest's Save button, top right of the image.
  roundRect(context, width - pad - 132, pad + 24, 108, 56, 28, "#E60023");
  text(context, "Save", width - pad - 78, pad + 60, `700 26px ${FONT}`, "#FFFFFF", "center");

  drawLines(context, titleLines, pad, titleTop, 48, `700 36px ${FONT}`, palette.fg);

  let cursor = titleTop + (titleLines.length - 1) * 48;

  if (descriptionLines.length > 0) {
    cursor += 52;
    drawLines(context, descriptionLines, pad, cursor, 38, `400 27px ${FONT}`, palette.fg, palette.accent);
    cursor += (descriptionLines.length - 1) * 38;
  }

  if (source !== "") {
    cursor += 50;
    // Pinterest shows the bare host and nothing else — no path, no parameters.
    text(context, domainOf(source), pad, cursor, `400 24px ${FONT}`, palette.muted);
  }

  cursor += 72;

  avatar(context, images.avatar, account, pad + 28, cursor - 10, 28, "#E60023", "#FFFFFF");
  text(context, account, pad + 76, cursor - 16, `600 27px ${FONT}`, palette.fg);

  const saves = num(values.saves);

  text(
    context,
    saves > 0 ? `${compact(saves)} saves` : "Saved by nobody yet",
    pad + 76,
    cursor + 14,
    `400 24px ${FONT}`,
    palette.muted,
  );

  return height;
}

function shapeLabel(shape: string): string {
  if (shape === "square") return "1:1 · your Pin image goes here";
  if (shape === "long") return "1:2.1 · your Pin image goes here";

  return "2:3 · your Pin image goes here";
}

export function domainOf(value: string): string {
  try {
    const url = new URL(value.includes("://") ? value : `https://${value}`);

    return url.hostname.replace(/^www\./i, "").toLowerCase();
  } catch {
    return value;
  }
}

function drawTikTok(context: CanvasRenderingContext2D, ctx: Ctx): number {
  const { width, palette, values, images, measureOnly } = ctx;
  const pad = 36;
  const radius = 38;
  const textX = pad + radius * 2 + 22;
  // The heart column on the right is reserved before the body wraps, or a long
  // comment runs underneath it.
  const inner = width - textX - pad - 90;

  const username = (str(values.username) || "username").replace(/^@+/, "");
  const userHandle = handle(username);
  const content = str(values.content) || "Your comment will appear here.";
  const lines = wrap(context, content, inner, `400 30px ${FONT}`);

  const pinned = bool(values.pinned);
  let y = pad + 30 + (pinned ? 36 : 0);
  const handleY = y;

  y += 46 + (lines.length - 1) * 42 + 46;

  if (bool(values.liked_by_creator)) y += 40;

  const height = Math.max(y + pad, pad * 2 + radius * 2);

  if (measureOnly) return height;

  avatar(context, images.avatar, username, pad + radius, pad + radius, radius, palette.accent, palette.bg);

  if (pinned) {
    text(context, "Pinned", textX, handleY - 36, `700 21px ${FONT}`, palette.accent);
  }

  text(context, userHandle, textX, handleY, `600 26px ${FONT}`, palette.muted);

  if (bool(values.is_creator)) {
    // The chip TikTok stamps on the video author's own comments — not a heart,
    // which is YouTube's convention and the fastest tell there is.
    context.font = `600 26px ${FONT}`;
    const chipX = textX + context.measureText(userHandle).width + 16;

    roundRect(context, chipX, handleY - 24, 118, 34, 8, palette.chip);
    text(context, "Creator", chipX + 59, handleY - 1, `600 21px ${FONT}`, palette.muted, "center");
  }

  drawLines(context, lines, textX, handleY + 46, 42, `400 30px ${FONT}`, palette.fg, palette.accent);

  const metaY = handleY + 46 + (lines.length - 1) * 42 + 46;
  const replies = num(values.replies);
  const meta = [tiktokAge(str(values.age, "3h")), "Reply"];

  if (replies > 0) meta.push(`View ${compact(replies)} repl${replies === 1 ? "y" : "ies"}`);

  text(context, meta.join("     "), textX, metaY, `400 24px ${FONT}`, palette.muted);

  if (bool(values.liked_by_creator)) {
    text(context, "♥ Liked by creator", textX, metaY + 40, `400 24px ${FONT}`, palette.muted);
  }

  text(context, "♥", width - pad, pad + 44, `400 34px ${FONT}`, palette.muted, "right");

  const likes = num(values.likes);

  if (likes > 0) {
    text(context, compact(likes), width - pad, pad + 82, `400 24px ${FONT}`, palette.muted, "right");
  }

  return height;
}

/**
 * TikTok writes "3h", never "3 hours ago".
 *
 * Anything unrecognised passes through unchanged — somebody typing an actual date
 * knows what they are doing.
 */
export function tiktokAge(value: string): string {
  const spelled = value.match(/^(\d+)\s*(second|minute|hour|day|week)s?\b/i);

  if (spelled) return spelled[1] + spelled[2][0].toLowerCase();

  const short = value.match(/^(\d+)\s*([smhdw])$/i);

  if (short) return short[1] + short[2].toLowerCase();

  return value.trim() === "" ? "3h" : value;
}
