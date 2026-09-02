#!/usr/bin/env python3
"""Signs in with email and password and writes a cookie file the other scripts read.

Local stacks only. Production uses a browser session cookie, because a password on a
command line is a password in a shell history.

    MC_SITE=http://localhost:8080 python3 login.py admin@metacreator.dev password
    MC_SITE=http://localhost:8080 MC_ORIGIN=http://localhost:3000 \
      MC_COOKIE="$(cat ../.cookie.local)" python3 publish.py ../posts/*.md --apply

Cookies are parsed straight from the Set-Cookie headers rather than through
http.cookiejar: the jar normalises values in a way that breaks Laravel's
percent-encoded XSRF token, which then fails CSRF validation with a 419.
"""
from __future__ import annotations

import json
import os
import sys
import urllib.parse
import urllib.request
from pathlib import Path

import mc_api


def collect(response, jar: dict[str, str]) -> None:
    for header in response.headers.get_all("Set-Cookie") or []:
        name, _, rest = header.partition("=")
        jar[name.strip()] = rest.split(";", 1)[0]


def main() -> int:
    if len(sys.argv) != 3:
        print(__doc__)
        return 2
    email, password = sys.argv[1], sys.argv[2]
    site = mc_api.SITE
    if "localhost" not in site and "127.0.0.1" not in site:
        print(f"Refusing to send a password to {site}. This is for local stacks only.")
        return 2

    # The origin has to be one of SANCTUM_STATEFUL_DOMAINS, which on the local stack
    # is the frontend on :3000 rather than the API on :8080.
    origin = os.environ.get("MC_ORIGIN", "http://localhost:3000")
    base = {
        "Accept": "application/json",
        "Referer": f"{origin}/",
        "Origin": origin,
        "X-Requested-With": "XMLHttpRequest",
        "User-Agent": mc_api.USER_AGENT,
    }
    jar: dict[str, str] = {}

    with urllib.request.urlopen(
        urllib.request.Request(f"{site}/sanctum/csrf-cookie", headers=base)
    ) as response:
        collect(response, jar)

    token = urllib.parse.unquote(jar.get("XSRF-TOKEN", ""))
    headers = {
        **base,
        "Content-Type": "application/json",
        "X-XSRF-TOKEN": token,
        "Cookie": "; ".join(f"{k}={v}" for k, v in jar.items()),
    }
    request = urllib.request.Request(
        f"{site}/api/v1/auth/login",
        data=json.dumps({"email": email, "password": password}).encode(),
        method="POST", headers=headers,
    )
    with urllib.request.urlopen(request) as response:
        collect(response, jar)
        response.read()

    out = Path(__file__).resolve().parent.parent / ".cookie.local"
    out.write_text("; ".join(f"{k}={v}" for k, v in jar.items()) + "\n")
    print(f"signed in as {email}; cookie written to {out.name}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
