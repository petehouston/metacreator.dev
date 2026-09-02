import { revalidatePath, revalidateTag } from "next/cache";
import { NextResponse } from "next/server";

/**
 * On-demand ISR invalidation, called by the API when content changes.
 *
 * This is what makes aggressive caching safe: pages stay static until the CMS says
 * otherwise, rather than being re-fetched on a timer and hoping.
 */
export async function POST(request: Request) {
  const secret = request.headers.get("x-revalidate-secret");
  const expected = process.env.REVALIDATE_SECRET;

  // A missing secret in the environment must fail closed. An open revalidation
  // endpoint is a free cache-stampede button for anyone who finds it.
  if (!expected || secret !== expected) {
    return NextResponse.json(
      { error: { code: "auth.forbidden", message: "Invalid revalidation secret." } },
      { status: 403 },
    );
  }

  const body = (await request.json().catch(() => ({}))) as {
    tags?: string[];
    paths?: string[];
  };

  const tags = body.tags ?? [];
  const paths = body.paths ?? [];

  // `{ expire: 0 }` rather than a stale-while-revalidate profile. The two differ on
  // exactly the case this endpoint exists for: with stale-while-revalidate the
  // first visitor after a publish still gets the *old* page while the new one
  // renders behind them, so an editor who saves and reloads sees no change and
  // concludes publishing is broken. Expiring outright costs that one visitor a
  // render and makes the update immediate, which is the trade this route wants.
  for (const tag of tags) revalidateTag(tag, { expire: 0 });
  for (const path of paths) revalidatePath(path);

  return NextResponse.json({
    data: { revalidated: { tags, paths }, at: new Date().toISOString() },
  });
}
