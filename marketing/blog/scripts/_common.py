"""Shared loading + path helpers for the marketing/blog toolchain.

Everything here reads the plan; nothing here talks to the network. The API client
lives in mc_api.py so a validation run can never accidentally write to production.
"""
from __future__ import annotations

import json
import os
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SITE = os.environ.get("MC_SITE", "https://metacreator.dev")


def load(name: str) -> dict:
    return json.loads((ROOT / name).read_text())


def posts() -> list[dict]:
    return load("plan/posts.json")["posts"]


def clusters() -> dict[str, dict]:
    return {c["id"]: c for c in load("plan/clusters.json")["clusters"]}


def categories() -> dict[str, dict]:
    return {c["slug"]: c for c in load("taxonomy/categories.json")["categories"]}


def tags() -> dict[str, dict]:
    return {t["slug"]: t for t in load("taxonomy/tags.json")["tags"]}


def tools() -> dict[str, dict]:
    return {t["slug"]: t for t in load("plan/tools-snapshot.json")}


def slugify(text: str) -> str:
    return re.sub(r"[^a-z0-9]+", "-", text.lower()).strip("-")


# Google truncates by pixel width, not characters (docs/16). This is the same
# cheap approximation the admin SERP preview uses: an average-width table for
# Arial at 20px for titles / 14px for descriptions is overkill here, so we weight
# narrow characters at 0.5 and wide ones at 1.5 and call the result "px units".
_NARROW = set("iljtfr.,:;'|!I ")
_WIDE = set("mwMW@")


def pixel_width(text: str, size: float) -> float:
    units = sum(0.5 if c in _NARROW else 1.5 if c in _WIDE else 1.0 for c in text)
    return units * size
