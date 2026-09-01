"use client";

/**
 * The browser's single door to the API.
 *
 * Everything the client sends goes through here so that three things are true
 * everywhere rather than per-call-site: the session cookie travels with the request,
 * Sanctum's CSRF token is primed before any mutation, and a failure — network or
 * envelope — arrives as a value rather than a thrown exception. Callers that have to
 * remember to try/catch eventually forget, and the user is left with a spinner.
 */

import type { ApiError } from "./types";

const PUBLIC_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8080";

export type ApiResult<T> = { ok: true; data: T } | { ok: false; error: ApiFailure };

export interface ApiFailure extends ApiError {
  /** Field → messages, present when `code` is `validation.failed`. */
  fieldErrors?: Record<string, string[]>;
}

export const NETWORK_FAILURE: ApiFailure = {
  code: "network.unreachable",
  message:
    "We couldn't reach the server. Check your connection and try again — nothing was lost.",
  status: 0,
};

let csrfPrimed = false;

export function readCookie(name: string): string | null {
  const match = document.cookie.match(new RegExp(`(^| )${name}=([^;]+)`));
  return match ? decodeURIComponent(match[2]) : null;
}

/**
 * Sanctum requires the XSRF cookie before any mutating request.
 *
 * A failure here is not fatal on its own — the request that follows reports the real
 * problem with a far better message than "couldn't fetch a cookie".
 */
export async function primeCsrf(): Promise<void> {
  if (csrfPrimed && readCookie("XSRF-TOKEN")) return;

  try {
    await fetch(`${PUBLIC_URL}/sanctum/csrf-cookie`, { credentials: "include" });
    csrfPrimed = true;
  } catch {
    csrfPrimed = false;
  }
}

interface ApiRequestInit {
  method?: "GET" | "POST" | "PATCH" | "PUT" | "DELETE";
  /** JSON body. Use `formData` instead for file uploads. */
  body?: unknown;
  formData?: FormData;
  signal?: AbortSignal;
  searchParams?: Record<string, string | number | boolean | undefined>;
}

export async function apiFetch<T>(
  path: string,
  init: ApiRequestInit = {},
): Promise<ApiResult<T>> {
  const method = init.method ?? "GET";

  if (method !== "GET") {
    await primeCsrf();
  }

  const url = new URL(`/api/v1${path}`, PUBLIC_URL);

  for (const [key, value] of Object.entries(init.searchParams ?? {})) {
    if (value !== undefined && value !== "") {
      url.searchParams.set(key, String(value));
    }
  }

  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-XSRF-TOKEN": readCookie("XSRF-TOKEN") ?? "",
  };

  // FormData must set its own multipart boundary; setting Content-Type by hand
  // produces a body the server cannot parse.
  if (init.body !== undefined) {
    headers["Content-Type"] = "application/json";
  }

  let response: Response;

  try {
    response = await fetch(url, {
      method,
      credentials: "include",
      headers,
      body: init.formData ?? (init.body !== undefined ? JSON.stringify(init.body) : undefined),
      signal: init.signal,
    });
  } catch {
    return { ok: false, error: NETWORK_FAILURE };
  }

  // 204 and other empty successes must not be run through JSON.parse.
  const payload =
    response.status === 204 ? null : await response.json().catch(() => null);

  if (!response.ok) {
    const error = payload?.error ?? {};

    return {
      ok: false,
      error: {
        code: error.code ?? "server.error",
        message: error.message ?? "Something went wrong. Please try again.",
        status: response.status,
        details: error.details,
        request_id: error.request_id,
        fieldErrors: error.code === "validation.failed" ? error.details : undefined,
      },
    };
  }

  return { ok: true, data: payload as T };
}

/** Convenience for endpoints whose useful payload is the `data` key. */
export async function apiData<T>(
  path: string,
  init: ApiRequestInit = {},
): Promise<ApiResult<T>> {
  const result = await apiFetch<{ data: T } | null>(path, init);

  if (!result.ok) return result;

  // A 204 arrives as `null`, not as an envelope — every DELETE in the admin API
  // answers that way. Reaching straight for `.data` threw a TypeError *after* the
  // row was already gone, which left the screen showing a spinner over a record
  // that no longer existed.
  return { ok: true, data: (result.data?.data ?? null) as T };
}
