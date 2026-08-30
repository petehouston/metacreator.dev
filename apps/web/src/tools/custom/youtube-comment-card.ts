/**
 * How a YouTube comment is drawn.
 *
 * All of it happens on a canvas in the visitor's own browser, which is the whole
 * reason this tool has a custom UI: an avatar someone drops in never leaves the
 * device, so there is nothing to upload, nothing to store and nothing to delete.
 * The layout below is a transcription of YouTube's desktop comment — the 40px
 * avatar, the handle and age on one line, the 14px body, the action row — scaled up
 * 2× so the exported image is sharp when it lands in a thumbnail.
 */

export type CommentTheme = "light" | "dark";

export type Reaction = "neutral" | "like" | "dislike";

export type AgoUnit = "seconds" | "minutes" | "hours" | "days" | "weeks" | "months" | "years";

export const AGO_UNITS: AgoUnit[] = [
  "seconds",
  "minutes",
  "hours",
  "days",
  "weeks",
  "months",
  "years",
];

export interface CommentCard {
  username: string;
  content: string;
  time: number;
  unit: AgoUnit;
  likes: number;
  reaction: Reaction;
  creatorLiked: boolean;
  theme: CommentTheme;
  transparent: boolean;
  /** Already-decoded images. Held in memory only — never uploaded, never persisted. */
  avatar: CanvasImageSource | null;
  creatorAvatar: CanvasImageSource | null;
}

interface Palette {
  bg: string;
  fg: string;
  muted: string;
  icon: string;
  heart: string;
  placeholder: string;
}

/** YouTube's own two comment palettes, sampled from the desktop watch page. */
export const PALETTES: Record<CommentTheme, Palette> = {
  light: {
    bg: "#FFFFFF",
    fg: "#0F0F0F",
    muted: "#606060",
    icon: "#0F0F0F",
    heart: "#FF0000",
    placeholder: "#909090",
  },
  dark: {
    bg: "#0F0F0F",
    fg: "#F1F1F1",
    muted: "#AAAAAA",
    icon: "#F1F1F1",
    heart: "#FF0000",
    placeholder: "#717171",
  },
};

const WIDTH = 1000;
const PAD = 40;
const AVATAR = 80;
const GUTTER = 24;
const TEXT_X = PAD + AVATAR + GUTTER;
const LINE_HEIGHT = 42;

const FONT_STACK = 'Roboto, "DM Sans", "Helvetica Neue", Arial, sans-serif';

/** Material's thumb, in a 22×20 box. Drawn as a path so no icon font has to exist. */
const THUMB_PATH =
  "M1 9h4v11H1V9zm6.5 11h8.3c1 0 1.9-.6 2.2-1.5l2.5-6a2.4 2.4 0 0 0-2.2-3.3h-5l.8-3.8a1.8 1.8 0 0 0-3.4-1.1L7.5 8.6V20z";

/** The creator heart, in an 18×17.5 box. */
const HEART_PATH =
  "M10 17.5 8.55 16.2C3.4 11.6 0 8.6 0 4.9 0 2.1 2.2 0 5 0c1.6 0 3.1.7 4 1.9C9.9.7 11.4 0 13 0c2.8 0 5 2.1 5 4.9 0 3.7-3.4 6.7-8.55 11.3L10 17.5z";

/**
 * "5 hours ago", "1 hour ago".
 *
 * YouTube singularises the unit at one and never says "1 hours", so neither does
 * this — it is the detail that gives a mock-up away fastest.
 */
export function relativeAge(time: number, unit: AgoUnit): string {
  const value = Math.max(1, Math.floor(time) || 1);

  return `${value} ${value === 1 ? unit.replace(/s$/, "") : unit} ago`;
}

/**
 * YouTube's like count: 999, then 1K, 5.2K, 1.4M.
 *
 * One decimal, and a trailing `.0` is dropped rather than printed — YouTube writes
 * "5K", never "5.0K".
 */
export function compactCount(value: number): string {
  const count = Math.max(0, Math.floor(value) || 0);

  if (count >= 1_000_000) return `${trimZero(count / 1_000_000)}M`;
  if (count >= 1_000) return `${trimZero(count / 1_000)}K`;

  return String(count);
}

function trimZero(value: number): string {
  return value.toFixed(1).replace(/\.0$/, "");
}

/** With or without the @ typed, the card always shows exactly one. */
export function normaliseHandle(username: string): string {
  return `@${username.trim().replace(/^@+/, "")}`;
}

/**
 * Greedy word wrap against real glyph widths.
 *
 * `measure` is passed in rather than a canvas context so the wrap can be tested
 * without a canvas, and so the estimate is never an estimate: it is whatever the
 * font actually measures at the size it will be drawn.
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
      // character instead of being allowed to run off the edge of the card.
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

/**
 * Paint the card and return the size it took, in CSS pixels.
 *
 * The canvas is resized to fit the comment: a two-line comment should not export
 * with a field of empty background under it. `scale` is the export multiplier —
 * everything below is expressed at 1× and the context is scaled once, so the
 * layout maths never has to know about pixel density.
 */
export function drawComment(
  canvas: HTMLCanvasElement,
  card: CommentCard,
  scale = 2,
): { width: number; height: number } {
  const context = canvas.getContext("2d");

  if (!context) return { width: 0, height: 0 };

  const palette = PALETTES[card.theme];
  const bodyWidth = WIDTH - TEXT_X - PAD;

  // Measure before sizing: the height depends on how many lines the body wraps to,
  // and resizing a canvas clears it, so this pass has to happen first.
  context.font = `400 30px ${FONT_STACK}`;
  const lines = wrapText(card.content, bodyWidth, (text) => context.measureText(text).width);

  const authorBaseline = PAD + 28;
  const firstBodyBaseline = authorBaseline + 46;
  const actionCentre = firstBodyBaseline + (lines.length - 1) * LINE_HEIGHT + 40;
  const height = Math.max(actionCentre + 32 + PAD, PAD * 2 + AVATAR);

  canvas.width = Math.round(WIDTH * scale);
  canvas.height = Math.round(height * scale);
  // The intrinsic size is the export size; the CSS size keeps the preview at the
  // width its container gives it, whatever the device pixel ratio is.
  canvas.style.aspectRatio = `${WIDTH} / ${height}`;

  context.setTransform(scale, 0, 0, scale, 0, 0);
  context.clearRect(0, 0, WIDTH, height);

  if (!card.transparent) {
    context.fillStyle = palette.bg;
    context.fillRect(0, 0, WIDTH, height);
  }

  drawAvatar(context, card, palette, PAD + AVATAR / 2, PAD + AVATAR / 2, AVATAR / 2);

  // ── Author row: handle, then the age in muted grey on the same baseline. ──
  const handle = normaliseHandle(card.username);

  context.textBaseline = "alphabetic";
  context.font = `500 26px ${FONT_STACK}`;
  context.fillStyle = palette.fg;
  context.fillText(handle, TEXT_X, authorBaseline);

  const handleWidth = context.measureText(handle).width;

  context.font = `400 24px ${FONT_STACK}`;
  context.fillStyle = palette.muted;
  context.fillText(relativeAge(card.time, card.unit), TEXT_X + handleWidth + 14, authorBaseline);

  // ── Body ──────────────────────────────────────────────────────────────────
  context.font = `400 30px ${FONT_STACK}`;
  context.fillStyle = palette.fg;

  lines.forEach((line, index) => {
    context.fillText(line, TEXT_X, firstBodyBaseline + index * LINE_HEIGHT);
  });

  drawActions(context, card, palette, TEXT_X, actionCentre);

  return { width: WIDTH, height };
}

/** The dropped avatar, cropped to the circle — or YouTube's grey initial when there is none. */
function drawAvatar(
  context: CanvasRenderingContext2D,
  card: CommentCard,
  palette: Palette,
  cx: number,
  cy: number,
  radius: number,
) {
  context.save();
  context.beginPath();
  context.arc(cx, cy, radius, 0, Math.PI * 2);
  context.closePath();
  context.clip();

  if (card.avatar) {
    // `cover`, not `fill`: a square crop of a portrait avatar is what every app
    // does, and stretching a face is instantly noticeable.
    const source = imageSize(card.avatar);
    const ratio = Math.max((radius * 2) / source.width, (radius * 2) / source.height);
    const width = source.width * ratio;
    const height = source.height * ratio;

    context.drawImage(card.avatar, cx - width / 2, cy - height / 2, width, height);
  } else {
    context.fillStyle = palette.placeholder;
    context.fillRect(cx - radius, cy - radius, radius * 2, radius * 2);

    const initial = normaliseHandle(card.username).slice(1, 2).toUpperCase() || "?";

    context.fillStyle = "#FFFFFF";
    context.font = `500 ${Math.round(radius * 0.9)}px ${FONT_STACK}`;
    context.textAlign = "center";
    context.textBaseline = "middle";
    context.fillText(initial, cx, cy + 1);
  }

  context.restore();
  context.textAlign = "left";
  context.textBaseline = "alphabetic";
}

/** Like, count, dislike, Reply — and the creator's heart when the creator liked it. */
function drawActions(
  context: CanvasRenderingContext2D,
  card: CommentCard,
  palette: Palette,
  x: number,
  centre: number,
) {
  let cursor = x;

  drawIcon(context, THUMB_PATH, cursor, centre, palette.icon, card.reaction === "like", false);
  cursor += 40;

  if (card.likes > 0) {
    context.font = `400 24px ${FONT_STACK}`;
    context.fillStyle = palette.muted;
    context.textBaseline = "middle";
    context.fillText(compactCount(card.likes), cursor, centre + 1);
    cursor += context.measureText(compactCount(card.likes)).width + 28;
    context.textBaseline = "alphabetic";
  } else {
    cursor += 20;
  }

  drawIcon(context, THUMB_PATH, cursor, centre, palette.icon, card.reaction === "dislike", true);
  cursor += 56;

  context.font = `500 24px ${FONT_STACK}`;
  context.fillStyle = palette.muted;
  context.textBaseline = "middle";
  context.fillText("Reply", cursor, centre + 1);
  cursor += context.measureText("Reply").width + 34;
  context.textBaseline = "alphabetic";

  if (card.creatorLiked) {
    drawCreatorHeart(context, card, palette, cursor + 16, centre);
  }
}

/**
 * The creator's avatar with a red heart on its corner — what YouTube stamps into
 * the action row when the channel owner likes a comment.
 */
function drawCreatorHeart(
  context: CanvasRenderingContext2D,
  card: CommentCard,
  palette: Palette,
  cx: number,
  cy: number,
) {
  const radius = 16;

  context.save();
  context.beginPath();
  context.arc(cx, cy, radius, 0, Math.PI * 2);
  context.closePath();
  context.clip();

  if (card.creatorAvatar) {
    const source = imageSize(card.creatorAvatar);
    const ratio = Math.max((radius * 2) / source.width, (radius * 2) / source.height);

    context.drawImage(
      card.creatorAvatar,
      cx - (source.width * ratio) / 2,
      cy - (source.height * ratio) / 2,
      source.width * ratio,
      source.height * ratio,
    );
  } else {
    context.fillStyle = palette.placeholder;
    context.fillRect(cx - radius, cy - radius, radius * 2, radius * 2);
  }

  context.restore();

  // The heart sits half off the avatar's bottom-right, with a ring in the card's
  // own background colour so it reads as a badge rather than a smudge.
  const heartX = cx + radius - 6;
  const heartY = cy + radius - 6;

  context.save();
  context.beginPath();
  context.arc(heartX, heartY, 11, 0, Math.PI * 2);
  context.closePath();
  // Always the theme's own background, even on a transparent card: the ring is
  // what stops the heart reading as a smudge on the avatar under it.
  context.fillStyle = palette.bg;
  context.fill();
  context.restore();

  drawPath(context, HEART_PATH, heartX - 7, heartY - 7, 14 / 18, palette.heart, null);
}

/** A thumb, filled when the button is pressed and outlined when it is not. */
function drawIcon(
  context: CanvasRenderingContext2D,
  path: string,
  x: number,
  centre: number,
  color: string,
  pressed: boolean,
  flipped: boolean,
) {
  const size = 28;
  const scale = size / 22;

  context.save();

  if (flipped) {
    context.translate(x + size, centre + size / 2);
    context.rotate(Math.PI);
    context.translate(0, -1);
  } else {
    context.translate(x, centre - size / 2);
  }

  context.scale(scale, scale);
  context.beginPath();

  const shape = new Path2D(path);

  if (pressed) {
    context.fillStyle = color;
    context.fill(shape);
  }

  context.strokeStyle = color;
  context.lineWidth = 1.6;
  context.lineJoin = "round";
  context.stroke(shape);
  context.restore();
}

function drawPath(
  context: CanvasRenderingContext2D,
  path: string,
  x: number,
  y: number,
  scale: number,
  fill: string | null,
  stroke: string | null,
) {
  context.save();
  context.translate(x, y);
  context.scale(scale, scale);

  const shape = new Path2D(path);

  if (fill) {
    context.fillStyle = fill;
    context.fill(shape);
  }

  if (stroke) {
    context.strokeStyle = stroke;
    context.stroke(shape);
  }

  context.restore();
}

/** `CanvasImageSource` is a union; only some members carry width/height directly. */
function imageSize(source: CanvasImageSource): { width: number; height: number } {
  if (source instanceof HTMLImageElement) {
    return { width: source.naturalWidth || source.width, height: source.naturalHeight || source.height };
  }

  if (typeof ImageBitmap !== "undefined" && source instanceof ImageBitmap) {
    return { width: source.width, height: source.height };
  }

  const candidate = source as { width?: number; height?: number };

  return { width: candidate.width ?? 1, height: candidate.height ?? 1 };
}
