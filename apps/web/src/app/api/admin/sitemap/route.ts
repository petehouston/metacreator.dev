import { revalidatePath, revalidateTag } from "next/cache";
import { NextResponse, type NextRequest } from "next/server";

import { buildSitemapReport } from "@/lib/admin/sitemap-report";
import type { AuthUser } from "@/lib/types";

/**
 * The admin's window onto `/sitemap.xml`.
 *
 * This is the one admin screen the Laravel API cannot serve, because the sitemap is
 * not its content: it is a cached route of this app, assembled from several API
 * calls, and the only place that knows what is currently *rendered* is the renderer.
 * So it gets a route handler here — with the same permission the rest of the
 * Platform section is behind, re-checked against the API rather than trusted from
 * the client.
 *
 * - `GET` reports what is being served and how far behind it is.
 * - `POST` expires the caches behind it and re-renders it now.
 */

const INTERNAL_URL = process.env.API_INTERNAL_URL ?? "http://localhost:8080";

/** Same permission as Settings — this changes what search engines are told. */
const PERMISSION = "settings.view";

/**
 * The data caches `sitemapEntries()` reads.
 *
 * Re-rendering the sitemap without expiring these would re-render it from exactly
 * the data it already had, which looks like a working button and changes nothing.
 */
const DATA_TAGS = [
  "tools",
  "tool-categories",
  "posts",
  "post-categories",
  "changelog",
  "settings",
];

export async function GET(request: NextRequest) {
  const denied = await guard(request);
  if (denied) return denied;

  return report(request);
}

export async function POST(request: NextRequest) {
  const denied = await guard(request);
  if (denied) return denied;

  // `{ expire: 0 }` rather than the `"max"` the publish webhook uses: stale-while-
  // revalidate is right for a visitor who wants a page instantly, and wrong for an
  // admin who pressed a button precisely because they do not trust what is cached.
  for (const tag of DATA_TAGS) revalidateTag(tag, { expire: 0 });

  revalidatePath("/sitemap.xml");

  // Marking the route stale does not render it — a request does, and until one
  // arrives the old file is still what a crawler would get. So we make that
  // request ourselves before reporting, rather than telling the admin it is done
  // and leaving the work to whoever shows up next.
  await fetch(new URL("/sitemap.xml", origin(request)), { cache: "no-store" }).catch(
    () => null,
  );

  // Built after the warm-up, and read back from the served file either way: if the
  // re-render has not landed yet the report says so rather than claiming success.
  return report(request);
}

async function report(request: NextRequest) {
  try {
    return NextResponse.json({ data: await buildSitemapReport(origin(request)) });
  } catch (error) {
    return NextResponse.json(
      {
        error: {
          code: "sitemap.unavailable",
          message:
            error instanceof Error
              ? `We could not read the sitemap: ${error.message}`
              : "We could not read the sitemap.",
          status: 502,
        },
      },
      { status: 502 },
    );
  }
}

/**
 * Who is asking, according to the API rather than according to them.
 *
 * The session cookie is forwarded to the same `/auth/session` endpoint the browser
 * uses, so this route inherits the API's answer instead of holding a second opinion
 * about who is staff. Returns a response when the answer is no, `null` when it is
 * yes — so callers read as `const denied = await guard(...); if (denied) return denied;`.
 */
async function guard(request: NextRequest): Promise<NextResponse | null> {
  const cookie = request.headers.get("cookie");

  const user = cookie === null ? null : await session(cookie, origin(request));

  if (user === null) {
    return NextResponse.json(
      { error: { code: "auth.unauthenticated", message: "Sign in to continue.", status: 401 } },
      { status: 401 },
    );
  }

  if (!user.permissions.includes(PERMISSION)) {
    return NextResponse.json(
      {
        error: {
          code: "auth.forbidden",
          message: "Your role does not include the sitemap.",
          status: 403,
        },
      },
      { status: 403 },
    );
  }

  return null;
}

async function session(cookie: string, from: string): Promise<AuthUser | null> {
  try {
    const response = await fetch(new URL("/api/v1/auth/session", INTERNAL_URL), {
      headers: {
        Accept: "application/json",
        Cookie: cookie,
        // Sanctum only reads the session cookie for a request it can see came from
        // a stateful frontend, and it decides that from `Origin`. A server-to-server
        // fetch has none of its own, so the browser's is passed along — which is
        // accurate: this call exists only to answer a request that arrived there.
        Origin: from,
      },
      cache: "no-store",
    });

    if (!response.ok) return null;

    const payload = (await response.json()) as { data: AuthUser | null };

    // A guest is a 200 with `null` here, not a 401 — see the API's SessionController.
    return payload.data;
  } catch {
    return null;
  }
}

/**
 * Where to fetch our own sitemap from.
 *
 * The incoming request's origin, not `siteConfig.url`: on a preview deployment or
 * behind a proxy those differ, and reporting on the canonical host from a machine
 * that is not it would describe someone else's cache.
 */
function origin(request: NextRequest): string {
  return request.nextUrl.origin;
}
