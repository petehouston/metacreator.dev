# Brand assets

The mark is a **tapered wand throwing four sparks** — the tool, and what it makes.
The rod runs cobalt at the handle to emerald at the tip; the sparks are emerald.
Below ~32px the mark goes flat and the two smallest sparks are grown, which is an
optical correction, not a second logo.

Every file in this folder is **generated**. Do not edit them by hand — change
`scripts/brand-mark.mjs` (geometry + palette) and re-run:

```
node scripts/generate-icons.mjs
```

That also rewrites `src/app/favicon.ico` and `apps/api/public/favicon.ico`, so the
two frameworks never serve different marks.

| File | Used by |
| --- | --- |
| `favicon.svg`, `favicon-{16,32,48,64,96,128,256}x*.png` | browser tabs, bookmarks |
| `../../src/app/favicon.ico` (16/32/48) | legacy browsers, Windows shortcuts |
| `apple-touch-icon{,-152,-167}.png` | iOS / iPadOS Home Screen |
| `icon-{192,512}.png` | PWA install, Android launcher (`purpose: any`) |
| `icon-maskable-{192,512}.png` | Android adaptive icons (`purpose: maskable`) |
| `mstile-150.png` | Windows Start tile |
| `logo-mark.svg`, `logo-mark-flat.svg` | in-product mark; flat is the Safari pinned-tab `mask-icon` |
| `logo-lockup-{light,dark}.svg` + `@1–3x.png` | headers, README, press |
| `../../src/lib/brand-mark.generated.ts` | `<Logo>` / `<LogoMark>` (paths, painted in theme tokens) and the OG card (data URI) |

The lockup SVGs use live text in DM Sans / JetBrains Mono with system fallbacks.
The PNG lockups were rasterised on a machine without DM Sans installed, so their
wordmark falls back to Helvetica — install DM Sans and re-run the script before
using them anywhere the exact wordmark matters.
