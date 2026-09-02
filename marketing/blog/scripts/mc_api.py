"""Minimal client for the MetaCreator admin API.

Auth is Sanctum's stateful cookie session, exactly as the browser does it
(docs/21): the session cookie identifies you, and every unsafe method must echo
the XSRF-TOKEN cookie back in an `X-XSRF-TOKEN` header, URL-decoded.

The cookie is a live admin credential. It is read from the environment or from a
gitignored file - never from a tracked file, and never passed on a command line
where it would land in shell history:

    export MC_COOKIE="$(cat marketing/blog/.cookie)"   # .cookie is gitignored

Requests are stdlib only, so this runs anywhere with python3 and no install step.
"""
from __future__ import annotations

import json
import os
import sys
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

SITE = os.environ.get("MC_SITE", "https://metacreator.dev")
BASE = f"{SITE}/api/v1"
USER_AGENT = (
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36"
)


class ApiError(RuntimeError):
    def __init__(self, status: int, body: str, url: str):
        self.status, self.body, self.url = status, body, url
        super().__init__(f"{status} {url}\n{body[:1200]}")


def _cookie() -> str:
    raw = os.environ.get("MC_COOKIE", "").strip()
    if not raw:
        path = Path(__file__).resolve().parent.parent / ".cookie"
        if path.exists():
            raw = path.read_text().strip()
    if not raw:
        sys.exit(
            "No admin cookie. Set MC_COOKIE, or put the cookie header in "
            "marketing/blog/.cookie (gitignored). See scripts/README.md."
        )
    return raw


def _xsrf(cookie: str) -> str:
    for part in cookie.split(";"):
        name, _, value = part.strip().partition("=")
        if name == "XSRF-TOKEN":
            return urllib.parse.unquote(value)
    return ""


def request(method: str, path: str, payload: dict | None = None, params: dict | None = None) -> dict:
    cookie = _cookie()
    url = f"{BASE}{path}"
    if params:
        url += "?" + urllib.parse.urlencode(params)
    body = json.dumps(payload).encode() if payload is not None else None
    req = urllib.request.Request(url, data=body, method=method.upper())
    req.add_header("Accept", "application/json")
    req.add_header("Cookie", cookie)
    # Sanctum's stateful guard checks the origin of the request, and the CSRF
    # middleware checks the token. Both have to look like the first-party app.
    # Sanctum's stateful guard matches the Origin against SANCTUM_STATEFUL_DOMAINS,
    # which on a local stack is the frontend (:3000) rather than the API (:8080).
    # MC_ORIGIN overrides it; production's origin is the site itself.
    origin = os.environ.get("MC_ORIGIN", SITE)
    req.add_header("Referer", f"{origin}/")
    req.add_header("Origin", origin)
    req.add_header("X-Requested-With", "XMLHttpRequest")
    # The edge in front of production rejects urllib's default User-Agent outright
    # (Cloudflare 1010). This is our own site and our own session; the header just
    # has to look like the browser the session was created in.
    req.add_header("User-Agent", os.environ.get("MC_USER_AGENT", USER_AGENT))
    if body is not None:
        req.add_header("Content-Type", "application/json")
    token = _xsrf(cookie)
    if token:
        req.add_header("X-XSRF-TOKEN", token)
    try:
        with urllib.request.urlopen(req) as response:
            raw = response.read().decode()
            return json.loads(raw) if raw else {}
    except urllib.error.HTTPError as exc:
        detail = exc.read().decode()
        if exc.code in (401, 419):
            detail += (
                "\n\nHint: 401/419 means the session cookie expired or the XSRF token "
                "no longer matches. Sign in at /c0ns0le again and refresh MC_COOKIE."
            )
        raise ApiError(exc.code, detail, url) from None


def get(path: str, **params) -> dict:
    return request("GET", path, params=params or None)


def post(path: str, payload: dict) -> dict:
    return request("POST", path, payload)


def patch(path: str, payload: dict) -> dict:
    return request("PATCH", path, payload)


def paged(path: str, **params) -> list[dict]:
    """Walks every page of a listing endpoint and returns the rows."""
    rows: list[dict] = []
    page = 1
    while True:
        body = get(path, page=page, per_page=100, **params)
        rows.extend(body.get("data", []))
        meta = body.get("meta", {}).get("page", {})
        if page >= int(meta.get("last_page", 1) or 1):
            return rows
        page += 1


def upload(path: str, file_path: str, fields: dict | None = None) -> dict:
    """multipart/form-data POST - the media endpoint takes a real file, not JSON."""
    import mimetypes
    import uuid

    boundary = f"----mc{uuid.uuid4().hex}"
    name = Path(file_path).name
    mime = mimetypes.guess_type(name)[0] or "application/octet-stream"
    parts: list[bytes] = []
    for key, value in (fields or {}).items():
        parts.append(
            f"--{boundary}\r\nContent-Disposition: form-data; name=\"{key}\"\r\n\r\n{value}\r\n".encode()
        )
    parts.append(
        f"--{boundary}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"{name}\"\r\n"
        f"Content-Type: {mime}\r\n\r\n".encode()
    )
    parts.append(Path(file_path).read_bytes())
    parts.append(f"\r\n--{boundary}--\r\n".encode())
    body = b"".join(parts)

    cookie = _cookie()
    req = urllib.request.Request(f"{BASE}{path}", data=body, method="POST")
    req.add_header("Accept", "application/json")
    req.add_header("Cookie", cookie)
    # Sanctum's stateful guard matches the Origin against SANCTUM_STATEFUL_DOMAINS,
    # which on a local stack is the frontend (:3000) rather than the API (:8080).
    # MC_ORIGIN overrides it; production's origin is the site itself.
    origin = os.environ.get("MC_ORIGIN", SITE)
    req.add_header("Referer", f"{origin}/")
    req.add_header("Origin", origin)
    req.add_header("X-Requested-With", "XMLHttpRequest")
    req.add_header("User-Agent", os.environ.get("MC_USER_AGENT", USER_AGENT))
    req.add_header("Content-Type", f"multipart/form-data; boundary={boundary}")
    token = _xsrf(cookie)
    if token:
        req.add_header("X-XSRF-TOKEN", token)
    try:
        with urllib.request.urlopen(req) as response:
            raw = response.read().decode()
            return json.loads(raw) if raw else {}
    except urllib.error.HTTPError as exc:
        raise ApiError(exc.code, exc.read().decode(), f"{BASE}{path}") from None


def whoami() -> dict:
    return get("/auth/session").get("data", {})
