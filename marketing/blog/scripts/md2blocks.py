"""Compiles a post file into the portable block JSON the API stores (docs/09).

A post file is Markdown with a JSON header:

    ---
    { "id": "YT-02", "slug": "...", "title": "...", ... }
    ---
    ## A heading

    A paragraph with **bold**, *italic*, `code` and [a link](/tools/x).

Only the 14 block types that actually render today are emitted; the five that
depend on the media library (gallery, video, audio, gif, newsletter) are not, so
nothing here can produce a labelled placeholder on the live site.

Directives, all line-anchored:

    [[tool:youtube-tag-extractor]]        toolCard - the internal-linking engine
    [[button:Try it|/tools/x]]            button
    [[embed:youtube|https://...]]         embed
    [[divider]] / [[divider:dots]]        divider
    :::tip Title / ... / :::              callout (info|tip|warning|danger)
    :::faq / Q: ... / A: ... / :::        faq  - emits FAQPage JSON-LD
    ![alt](url "caption")                 image (a public URL; no upload path yet)
"""
from __future__ import annotations

import html
import json
import re
from pathlib import Path

BLOCK_TYPES = {
    "paragraph", "heading", "list", "quote", "image", "embed", "code",
    "html", "callout", "divider", "table", "button", "toolCard", "faq",
}
CALLOUT_TONES = {"info", "tip", "warning", "danger"}


# ── front matter ─────────────────────────────────────────────────────────────

def parse_post_file(path: Path) -> tuple[dict, str]:
    text = path.read_text()
    if not text.startswith("---"):
        raise ValueError(f"{path}: missing the JSON header")
    _, header, body = text.split("---", 2)
    try:
        meta = json.loads(header)
    except json.JSONDecodeError as exc:
        raise ValueError(f"{path}: header is not valid JSON - {exc}") from None
    return meta, body.strip("\n")


# ── inline marks ─────────────────────────────────────────────────────────────

def inline(text: str) -> str:
    """Markdown marks to the narrow HTML the richtext purifier profile keeps."""
    out = html.escape(text, quote=False)
    out = re.sub(r"`([^`]+)`", lambda m: f"<code>{m.group(1)}</code>", out)
    out = re.sub(r"\[([^\]]+)\]\(([^)\s]+)\)", r'<a href="\2">\1</a>', out)
    out = re.sub(r"\*\*([^*]+)\*\*", r"<strong>\1</strong>", out)
    out = re.sub(r"(?<![\w*])\*([^*\n]+)\*(?![\w*])", r"<em>\1</em>", out)
    out = re.sub(r"~~([^~]+)~~", r"<s>\1</s>", out)
    out = re.sub(r"==([^=]+)==", r"<mark>\1</mark>", out)
    return out.strip()


def _block(kind: str, data: dict) -> dict:
    assert kind in BLOCK_TYPES, kind
    return {"type": kind, "data": data}


# ── the compiler ─────────────────────────────────────────────────────────────

def compile_markdown(body: str) -> list[dict]:
    lines = body.split("\n")
    blocks: list[dict] = []
    i = 0
    paragraph: list[str] = []

    def flush() -> None:
        nonlocal paragraph
        if paragraph:
            joined = " ".join(line.strip() for line in paragraph).strip()
            if joined:
                blocks.append(_block("paragraph", {"html": f"<p>{inline(joined)}</p>"}))
            paragraph = []

    while i < len(lines):
        line = lines[i]
        stripped = line.strip()

        if not stripped:
            flush(); i += 1; continue

        # fenced code
        if stripped.startswith("```"):
            flush()
            language = stripped[3:].strip() or "text"
            i += 1
            code: list[str] = []
            while i < len(lines) and not lines[i].strip().startswith("```"):
                code.append(lines[i]); i += 1
            i += 1
            blocks.append(_block("code", {"language": language, "code": "\n".join(code), "filename": ""}))
            continue

        # callout / faq fences
        if stripped.startswith(":::"):
            flush()
            head = stripped[3:].strip()
            i += 1
            inner: list[str] = []
            while i < len(lines) and lines[i].strip() != ":::":
                inner.append(lines[i]); i += 1
            i += 1
            blocks.append(_fence(head, inner))
            continue

        # headings
        if re.match(r"^#{2,4}\s", stripped):
            flush()
            level = len(stripped) - len(stripped.lstrip("#"))
            blocks.append(_block("heading", {"level": min(4, max(2, level)),
                                             "text": stripped.lstrip("#").strip()}))
            i += 1; continue

        # directives
        directive = _directive(stripped)
        if directive is not None:
            flush(); blocks.append(directive); i += 1; continue

        # image on its own line
        image = re.match(r'^!\[([^\]]*)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)$', stripped)
        if image:
            flush()
            blocks.append(_block("image", {
                "url": image.group(2), "alt": image.group(1),
                "caption": inline(image.group(3) or ""), "size": "wide",
            }))
            i += 1; continue

        # thematic break
        if re.fullmatch(r"(-{3,}|\*{3,})", stripped):
            flush(); blocks.append(_block("divider", {"style": "plain"})); i += 1; continue

        # blockquote
        if stripped.startswith(">"):
            flush()
            quoted, cite = [], ""
            while i < len(lines) and lines[i].strip().startswith(">"):
                content = lines[i].strip().lstrip(">").strip()
                if content.startswith("--"):
                    cite = content.lstrip("-").strip()
                else:
                    quoted.append(content)
                i += 1
            blocks.append(_block("quote", {"text": inline(" ".join(quoted)),
                                           "cite": cite, "variant": "default"}))
            continue

        # tables
        if stripped.startswith("|") and stripped.endswith("|"):
            flush()
            rows: list[list[str]] = []
            while i < len(lines) and lines[i].strip().startswith("|"):
                cells = [c.strip() for c in lines[i].strip().strip("|").split("|")]
                if not all(re.fullmatch(r":?-{2,}:?", c) for c in cells):
                    rows.append([inline(c) for c in cells])
                i += 1
            blocks.append(_block("table", {"has_header": True, "rows": rows}))
            continue

        # lists
        bullet = re.match(r"^([-*]|\d+\.)\s+(.*)$", stripped)
        if bullet:
            flush()
            style = "unordered" if bullet.group(1) in "-*" else "ordered"
            items: list[dict] = []
            while i < len(lines):
                item = re.match(r"^([-*]|\d+\.)\s+(.*)$", lines[i].strip())
                if not item:
                    break
                text = item.group(2)
                checked = False
                task = re.match(r"^\[([ xX])\]\s+(.*)$", text)
                if task:
                    style, checked, text = "checklist", task.group(1).lower() == "x", task.group(2)
                items.append({"html": inline(text), "checked": checked})
                i += 1
            blocks.append(_block("list", {"style": style, "items": items}))
            continue

        paragraph.append(line)
        i += 1

    flush()
    return blocks


def _fence(head: str, inner: list[str]) -> dict:
    word, _, title = head.partition(" ")
    if word == "faq":
        items, question, answer = [], None, []
        for line in inner + ["Q:"]:
            text = line.strip()
            if text.startswith("Q:"):
                if question is not None:
                    items.append({"question": question, "answer": f"<p>{inline(' '.join(answer))}</p>"})
                question, answer = text[2:].strip(), []
            elif text.startswith("A:"):
                answer.append(text[2:].strip())
            elif text:
                answer.append(text)
        return _block("faq", {"items": items})

    tone = word if word in CALLOUT_TONES else "info"
    text = " ".join(line.strip() for line in inner if line.strip())
    return _block("callout", {"tone": tone, "title": title.strip(),
                              "html": f"<p>{inline(text)}</p>"})


def _directive(line: str) -> dict | None:
    tool = re.fullmatch(r"\[\[tool:([a-z0-9-]+)\]\]", line)
    if tool:
        return _block("toolCard", {"toolSlug": tool.group(1)})

    button = re.fullmatch(r"\[\[button:([^|\]]+)\|([^\]]+)\]\]", line)
    if button:
        return _block("button", {"label": button.group(1).strip(),
                                 "href": button.group(2).strip(), "variant": "primary"})

    embed = re.fullmatch(r"\[\[embed:([a-z]+)\|([^\]|]+)(?:\|([\d:]+))?\]\]", line)
    if embed:
        return _block("embed", {"provider": embed.group(1), "url": embed.group(2).strip(),
                                "aspect": embed.group(3) or "16:9", "caption": ""})

    divider = re.fullmatch(r"\[\[divider(?::(plain|dots|asterism))?\]\]", line)
    if divider:
        return _block("divider", {"style": divider.group(1) or "plain"})

    return None


def compile_post(path: Path) -> tuple[dict, dict]:
    """Returns (meta, block document ready for the API)."""
    meta, body = parse_post_file(path)
    return meta, {"version": 1, "blocks": compile_markdown(body)}


def tool_slugs_in(document: dict) -> list[str]:
    return [b["data"]["toolSlug"] for b in document["blocks"] if b["type"] == "toolCard"]


def plain_text(document: dict) -> str:
    out: list[str] = []
    for block in document["blocks"]:
        data = block["data"]
        for key in ("html", "text", "title"):
            if isinstance(data.get(key), str):
                out.append(re.sub(r"<[^>]+>", " ", data[key]))
        for item in data.get("items", []) or []:
            for key in ("html", "question", "answer"):
                if isinstance(item.get(key), str):
                    out.append(re.sub(r"<[^>]+>", " ", item[key]))
        for row in data.get("rows", []) or []:
            out.extend(re.sub(r"<[^>]+>", " ", c) for c in row)
    return html.unescape(re.sub(r"\s+", " ", " ".join(out))).strip()


if __name__ == "__main__":
    import sys
    meta, document = compile_post(Path(sys.argv[1]))
    print(json.dumps({"meta": meta, "blocks": document}, indent=1))
