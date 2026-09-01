// Shared SVG source for every generated icon. One geometry, one palette:
// change it here and re-run `node scripts/generate-icons.mjs`.
export const C = {
  brand500: '#3d80f7', brand600: '#2667e7', brand400: '#5e9cff',
  signal400: '#50e2a8', signal500: '#13c990', signal300: '#8df3c6',
  ink1000: '#0f131c', ink900: '#1b2130', paper0: '#ffffff',
  fg: '#f4f6fb',
};

/* ── Geometry ───────────────────────────────────────────────────────────────
   The mark is a tapered wand throwing four sparks. Both primitives are built
   from quadratic curves only — no arcs — so there are no sweep-flag surprises
   and every point is derived, not eyeballed.

   Coordinates are chosen so the ink lands on x 2.1–29.9, y 2.8–29.2 of the
   32-unit box: near-equal margins on all four sides, which is what stops an
   asymmetric mark from looking mis-set inside a square icon. */

const f = (p) => `${Math.round(p[0] * 100) / 100} ${Math.round(p[1] * 100) / 100}`;

/**
 * Four-pointed spark. `q` is the waist — the control points sit at q × radius,
 * so 0.2 gives the pinched arms that read as a spark rather than a diamond.
 */
function spark(cx, cy, r, q = 0.2) {
  const pts = [], ctl = [];
  for (let i = 0; i < 4; i++) {
    const a = ((-90 + i * 90) * Math.PI) / 180;
    const b = ((-90 + (i + 0.5) * 90) * Math.PI) / 180;
    pts.push([cx + Math.cos(a) * r, cy + Math.sin(a) * r]);
    ctl.push([cx + Math.cos(b) * r * q, cy + Math.sin(b) * r * q]);
  }
  let d = `M${f(pts[0])}`;
  for (let i = 0; i < 4; i++) d += `Q${f(ctl[i])} ${f(pts[(i + 1) % 4])}`;
  return `${d}Z`;
}

/**
 * Tapered rod from a wide handle to a fine tip, with rounded caps at both ends.
 * The taper is the whole point: an even-width stroke reads as a pencil, and the
 * heavy handle is also what keeps the rod visible once it is 16px wide.
 */
function rod(x1, y1, x2, y2, w1, w2) {
  const dx = x2 - x1, dy = y2 - y1, L = Math.hypot(dx, dy);
  const ux = dx / L, uy = dy / L, nx = -uy, ny = ux, r1 = w1 / 2, r2 = w2 / 2;
  const A = [x1 + nx * r1, y1 + ny * r1], B = [x2 + nx * r2, y2 + ny * r2];
  const Cp = [x2 - nx * r2, y2 - ny * r2], D = [x1 - nx * r1, y1 - ny * r1];
  const tipC = [x2 + ux * r2 * 1.34, y2 + uy * r2 * 1.34];
  const endC = [x1 - ux * r1 * 1.34, y1 - uy * r1 * 1.34];
  return `M${f(A)}L${f(B)}Q${f(tipC)} ${f(Cp)}L${f(D)}Q${f(endC)} ${f(A)}Z`;
}

/** Handle and tip of the rod, and the four sparks it throws, largest first. */
const HANDLE = [3.75, 27.6];
const TIP = [17.15, 14.2];
const SPARKS = [
  [20.05, 11.1, 7],     // the burst at the tip — carries the mark on its own
  [26.05, 18.6, 3.8],
  [24.05, 5.6, 2.8],
  [13.35, 7.1, 2.4],
];

/**
 * The bare path data, so consumers that paint their own colours (the React
 * `<Logo>`, which uses theme tokens rather than fixed hex) can share this exact
 * geometry instead of copying it.
 */
export function markPaths({ small = false } = {}) {
  const tipW = small ? 1.7 : 1.1;
  const grow = small ? [0, 0, 0.7, 0.8] : [0, 0, 0, 0];
  return {
    rod: rod(...HANDLE, ...TIP, 4.6, tipW),
    sparks: SPARKS.map(([cx, cy, r], i) => spark(cx, cy, r + grow[i])),
  };
}

/**
 * The mark. `small` applies optical corrections rather than a different drawing:
 * below ~32px the two least sparks fall under a device pixel and the rod tip
 * disappears, so they are grown and the tip thickened. Same logo, legible.
 */
export function mark({ flat = false, small = false } = {}) {
  const { rod: rodPath, sparks: sparkPaths } = markPaths({ small });
  const rodFill = flat ? C.brand500 : 'url(#gr)';
  const sparkFill = flat ? (small ? C.signal500 : C.signal400) : 'url(#gs)';

  const defs = flat ? '' : `<defs>
    <linearGradient id="gr" x1="${HANDLE[0]}" y1="${HANDLE[1]}" x2="${TIP[0]}" y2="${TIP[1]}" gradientUnits="userSpaceOnUse">
      <stop offset="0" stop-color="${C.brand500}"/><stop offset="1" stop-color="${C.signal400}"/>
    </linearGradient>
    <linearGradient id="gs" x1="13" y1="4" x2="30" y2="22" gradientUnits="userSpaceOnUse">
      <stop offset="0" stop-color="${C.signal300}"/><stop offset="1" stop-color="${C.signal500}"/>
    </linearGradient>
  </defs>`;

  const stick = `<path d="${rodPath}" fill="${rodFill}"/>`;
  const sparks = sparkPaths.map((d) => `<path d="${d}" fill="${sparkFill}"/>`).join('');

  return `${defs}${stick}${sparks}`;
}

/** Standalone mark on transparency — favicon.svg, logo-mark.svg. */
export const markSvg = (small = false) =>
  `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="32" height="32" role="img" aria-label="MetaCreator.Dev">${
    // Below ~32px the two-stop gradients turn to mud across a 4px rod, so the
    // small variant goes flat as well as optically corrected.
    small ? mark({ flat: true, small: true }) : mark({})
  }</svg>`;

/**
 * Mark on the dark ground, for platforms that composite icons onto an unknown
 * background (iOS, Android, Windows tiles) and would otherwise show it on white.
 * `scale` is the mark's share of the canvas — smaller for maskable, whose outer
 * ~20% can be cropped to any shape.
 */
export function tileSvg({ size = 512, scale = 0.66, radius = 0.22, square = false } = {}) {
  const m = size * scale, o = (size - m) / 2, r = square ? 0 : size * radius;
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${size} ${size}" width="${size}" height="${size}">
    <defs><linearGradient id="bg" x1="0" y1="0" x2="${size}" y2="${size}" gradientUnits="userSpaceOnUse">
      <stop offset="0" stop-color="${C.ink900}"/><stop offset="1" stop-color="${C.ink1000}"/>
    </linearGradient></defs>
    <rect width="${size}" height="${size}" rx="${r}" fill="url(#bg)"/>
    <g transform="translate(${o} ${o}) scale(${m / 32})">${mark()}</g>
  </svg>`;
}
