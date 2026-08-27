import { NextResponse } from "next/server";

/**
 * Liveness plus a shallow dependency check.
 *
 * Reports `degraded` rather than failing when the API is unreachable: the frontend
 * itself is still serving cached pages, and a hard failure here would take a
 * partially-working site out of the load balancer entirely.
 */
export const dynamic = "force-dynamic";

export async function GET() {
  const apiUrl = process.env.API_INTERNAL_URL ?? "http://localhost:8080";
  let apiReachable = false;

  try {
    const response = await fetch(`${apiUrl}/up`, {
      signal: AbortSignal.timeout(2000),
      cache: "no-store",
    });
    apiReachable = response.ok;
  } catch {
    apiReachable = false;
  }

  return NextResponse.json(
    {
      status: apiReachable ? "ok" : "degraded",
      build: process.env.NEXT_PUBLIC_BUILD_ID ?? "dev",
      checks: { api: apiReachable ? "ok" : "unreachable" },
      timestamp: new Date().toISOString(),
    },
    { status: 200 },
  );
}
