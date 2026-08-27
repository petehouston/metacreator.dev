"use client";

import type { ToolRun } from "./types";

/**
 * Browser-side API client.
 *
 * Uses the public URL and `credentials: "include"` so the Sanctum session cookie
 * travels with every request. There is no token in JavaScript-readable storage —
 * an XSS should not be able to walk off with a session (see docs/21).
 */
const PUBLIC_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8080";

export interface RunToolFailure {
  code: string;
  message: string;
  status: number;
  details?: Record<string, unknown>;
  /** Field → messages, present when `code` is `validation.failed`. */
  fieldErrors?: Record<string, string[]>;
}

let csrfPrimed = false;

/**
 * Sanctum requires the XSRF cookie before any mutating request.
 *
 * A failure here is not fatal on its own — the run request that follows will report
 * the real problem with a far better message than "couldn't fetch a cookie".
 */
async function primeCsrf(): Promise<void> {
  if (csrfPrimed) return;

  try {
    await fetch(`${PUBLIC_URL}/sanctum/csrf-cookie`, { credentials: "include" });
    csrfPrimed = true;
  } catch {
    csrfPrimed = false;
  }
}

const NETWORK_FAILURE: RunToolFailure = {
  code: "network.unreachable",
  message:
    "We couldn't reach the server. Check your connection and try again — nothing was lost.",
  status: 0,
};

function readCookie(name: string): string | null {
  const match = document.cookie.match(new RegExp(`(^| )${name}=([^;]+)`));
  return match ? decodeURIComponent(match[2]) : null;
}

export async function runTool(
  slug: string,
  input: Record<string, unknown>,
  source?: string,
): Promise<{ ok: true; run: ToolRun } | { ok: false; error: RunToolFailure }> {
  await primeCsrf();

  // A rejected fetch (offline, CORS, DNS, connection refused) must be turned into a
  // normal failure result. Letting it reject leaves the caller's `pending` flag
  // stuck on, and the user staring at a spinner that never stops.
  let response: Response;

  try {
    response = await fetch(`${PUBLIC_URL}/api/v1/tools/${slug}/run`, {
      method: "POST",
      credentials: "include",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-XSRF-TOKEN": readCookie("XSRF-TOKEN") ?? "",
      },
      body: JSON.stringify({ input, source }),
    });
  } catch {
    return { ok: false, error: NETWORK_FAILURE };
  }

  const payload = await response.json().catch(() => null);

  if (!response.ok) {
    const error = payload?.error ?? {};

    return {
      ok: false,
      error: {
        code: error.code ?? "server.error",
        message: error.message ?? "Something went wrong. Please try again.",
        status: response.status,
        details: error.details,
        fieldErrors: error.code === "validation.failed" ? error.details : undefined,
      },
    };
  }

  return { ok: true, run: payload.data as ToolRun };
}

/**
 * Poll an asynchronous run until it reaches a terminal state.
 *
 * Backs off from 600ms to 3s so a slow tool does not generate a request storm.
 */
export async function pollRun(
  runId: string,
  { signal, timeoutMs = 120_000 }: { signal?: AbortSignal; timeoutMs?: number } = {},
): Promise<ToolRun> {
  const startedAt = Date.now();
  let delay = 600;

  for (;;) {
    if (Date.now() - startedAt > timeoutMs) {
      throw new Error("This run took too long. Check your history in a moment.");
    }

    await new Promise((resolve) => setTimeout(resolve, delay));
    delay = Math.min(delay * 1.4, 3000);

    const response = await fetch(`${PUBLIC_URL}/api/v1/tools/runs/${runId}`, {
      credentials: "include",
      headers: { Accept: "application/json" },
      signal,
    });

    if (!response.ok) continue;

    const run = (await response.json()).data as ToolRun;

    if (run.status !== "queued" && run.status !== "running") {
      return run;
    }
  }
}
