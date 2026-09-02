#!/usr/bin/env python3
"""Generates a 1200x630 featured image for every planned post.

Why generated rather than stock: a stock photo of a person holding a phone says
nothing about "where a YouTube title gets cut off", every competitor uses the same
twelve of them, and a library that is free today can change its terms tomorrow.
Generated cards are on-brand, consistent, licence-free, and sized exactly for the
Open Graph slot - a share of any post looks like it came from the same publication.

The palette is the product's own "Signal" system (docs/17): cobalt brand, one accent
per category, on the deep slate-navy ink ground so dark mode is not a hole.

    ../.venv/bin/python gen_images.py              # every planned post
    ../.venv/bin/python gen_images.py --slug x     # one
    ../.venv/bin/python gen_images.py --force      # redraw existing

Swapping in a photo later: drop a 1200x630 file at assets/featured/<slug>.png and
publish.py uploads that instead. Each brief carries a stock search suggestion for
anyone who prefers photography.
"""
from __future__ import annotations

import argparse
import math
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

from _common import ROOT, categories, posts

W, H = 1200, 630
INK = (10, 16, 28)
INK_2 = (17, 26, 43)
PAPER = (246, 248, 252)
MUTED = (150, 163, 186)

FONTS = {
    "bold": "/System/Library/Fonts/Supplemental/Arial Bold.ttf",
    "regular": "/System/Library/Fonts/Supplemental/Arial.ttf",
    "black": "/System/Library/Fonts/Supplemental/Arial Black.ttf",
}


def font(kind: str, size: int) -> ImageFont.FreeTypeFont:
    return ImageFont.truetype(FONTS[kind], size)


def hex_rgb(value: str) -> tuple[int, int, int]:
    value = value.lstrip("#")
    return tuple(int(value[i:i + 2], 16) for i in (0, 2, 4))  # type: ignore[return-value]


def mix(a: tuple[int, int, int], b: tuple[int, int, int], t: float) -> tuple[int, int, int]:
    return tuple(round(x + (y - x) * t) for x, y in zip(a, b))  # type: ignore[return-value]


def background(accent: tuple[int, int, int], seed: int) -> Image.Image:
    """Ink ground, a corner wash in the category accent, and a faint grid."""
    image = Image.new("RGB", (W, H), INK)
    draw = ImageDraw.Draw(image)

    # Vertical ground gradient, drawn as rows - cheap and invisible as banding at
    # this contrast.
    for y in range(H):
        draw.line([(0, y), (W, y)], fill=mix(INK, INK_2, y / H))

    # Accent wash from the top-right, falling off radially.
    wash = Image.new("RGB", (W, H), INK)
    wash_draw = ImageDraw.Draw(wash)
    cx, cy = W * 0.82, H * 0.12
    for radius in range(760, 0, -12):
        t = 1 - radius / 760
        wash_draw.ellipse(
            [cx - radius, cy - radius * 0.85, cx + radius, cy + radius * 0.85],
            fill=mix(INK, accent, t ** 2 * 0.55),
        )
    image = Image.blend(image, wash, 0.85)

    draw = ImageDraw.Draw(image, "RGBA")
    # Grid: the instrument reference from the design system, not decoration.
    for x in range(0, W, 40):
        draw.line([(x, 0), (x, H)], fill=(255, 255, 255, 8))
    for y in range(0, H, 40):
        draw.line([(0, y), (W, y)], fill=(255, 255, 255, 8))

    # A per-post arc so two posts in one category are not the same picture.
    angle = (seed % 360) * math.pi / 180
    ox, oy = W * 0.78 + math.cos(angle) * 90, H * 0.30 + math.sin(angle) * 70
    for i, r in enumerate((250, 190, 130)):
        draw.ellipse([ox - r, oy - r, ox + r, oy + r],
                     outline=(*accent, 70 - i * 15), width=2)
    return image


def wrap(draw: ImageDraw.ImageDraw, text: str, f: ImageFont.FreeTypeFont, width: int) -> list[str]:
    lines: list[str] = []
    line = ""
    for word in text.split():
        candidate = f"{line} {word}".strip()
        if draw.textlength(candidate, font=f) <= width:
            line = candidate
        else:
            if line:
                lines.append(line)
            line = word
    if line:
        lines.append(line)
    return lines


def card(title: str, category: str, accent_hex: str, kicker: str, seed: int) -> Image.Image:
    accent = hex_rgb(accent_hex)
    image = background(accent, seed)
    draw = ImageDraw.Draw(image, "RGBA")

    left, top = 76, 96

    # Category chip
    chip_font = font("bold", 22)
    label = category.upper()
    text_w = draw.textlength(label, font=chip_font)
    draw.rounded_rectangle([left, top, left + text_w + 44, top + 46], radius=23,
                           fill=(*accent, 46), outline=(*accent, 170), width=2)
    draw.text((left + 22, top + 11), label, font=chip_font, fill=mix(accent, PAPER, 0.45))

    # Title - shrink until it fits three lines
    y = top + 92
    for size in (66, 60, 54, 48, 44):
        title_font = font("black", size)
        lines = wrap(draw, title, title_font, W - left * 2 - 40)
        if len(lines) <= 3:
            break
    for line in lines[:3]:
        draw.text((left, y), line, font=title_font, fill=PAPER)
        y += int(size * 1.22)

    # Kicker
    if kicker:
        draw.text((left, y + 14), kicker, font=font("regular", 26), fill=MUTED)

    # Footer rule and wordmark
    draw.line([(left, H - 92), (W - left, H - 92)], fill=(255, 255, 255, 28), width=2)
    draw.text((left, H - 68), "metacreator", font=font("bold", 28), fill=PAPER)
    mark_w = draw.textlength("metacreator", font=font("bold", 28))
    draw.text((left + mark_w, H - 68), ".dev", font=font("bold", 28), fill=accent)
    right = "Free creator tools"
    draw.text((W - left - draw.textlength(right, font=font("regular", 24)), H - 66),
              right, font=font("regular", 24), fill=MUTED)
    return image


KICKERS = {
    "howto": "Step by step",
    "explainer": "What it means",
    "troubleshooting": "Ordered causes",
    "benchmarks": "Numbers and method",
    "comparison": "Side by side",
    "templates": "Copy and fill in",
    "pillar": "The complete guide",
}


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--slug")
    parser.add_argument("--force", action="store_true")
    args = parser.parse_args()

    CATS = categories()
    out = ROOT / "assets" / "featured"
    out.mkdir(parents=True, exist_ok=True)
    made = 0
    for p in posts():
        if args.slug and p["slug"] != args.slug:
            continue
        path = out / f"{p['slug']}.png"
        if path.exists() and not args.force:
            continue
        category = CATS[p["category"]]
        image = card(
            title=p["title"],
            category=category["name"],
            accent_hex=category["accent_color"],
            kicker=KICKERS.get(p["archetype"], ""),
            seed=sum(ord(c) for c in p["slug"]),
        )
        image.save(path, "PNG", optimize=True)
        made += 1
    print(f"generated {made} images in assets/featured/")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
