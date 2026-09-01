"use client";

import {
  ArrowRight,
  ArrowUpRight,
  Bell,
  CalendarClock,
  CheckCircle2,
  Clock,
  Mail,
  Sparkles,
  Wrench,
  Zap,
} from "lucide-react";
import Link from "next/link";
import * as React from "react";

import { EmptyState } from "@/components/app/empty-state";
import { planLabel, useEntitlements } from "@/components/app/entitlements-provider";
import { RunStatusBadge } from "@/components/app/run-status-badge";
import { SectionCard } from "@/components/app/section-card";
import { StatTile, StatTileSkeleton } from "@/components/app/stat-tile";
import { FormAlert } from "@/components/auth/form-alert";
import { useSession } from "@/components/auth/session-provider";
import { useBillingEnabled } from "@/components/site/features-provider";
import { Button } from "@/components/ui/button";
import { TierBadge } from "@/components/ui/badge";
import { authApi } from "@/lib/auth-api";
import { apiFetch } from "@/lib/http";
import type { NotificationItem, Paginated, ToolRun, ToolSummary } from "@/lib/types";
import { relativeTime } from "@/lib/utils";

/**
 * The signed-in home.
 *
 * Ordered by what someone actually opens this screen to find out: am I about to hit
 * a limit, what did I run last, and what can I run next. The upgrade prompt sits
 * *after* all three — it is the answer to a question the numbers have already
 * raised, not an interstitial.
 */
/** How each budget window reads as a stat-tile label. */
const RUN_PERIOD: Record<string, string> = {
  daily: "today",
  weekly: "this week",
  monthly: "this month",
};

export function Overview() {
  const { user } = useSession();
  const { entitlements, loading: entitlementsLoading } = useEntitlements();
  const billingEnabled = useBillingEnabled();

  const [runs, setRuns] = React.useState<ToolRun[]>([]);
  const [notices, setNotices] = React.useState<NotificationItem[]>([]);
  const [suggested, setSuggested] = React.useState<ToolSummary[]>([]);
  const [loading, setLoading] = React.useState(true);

  React.useEffect(() => {
    let cancelled = false;

    void (async () => {
      const [runsResult, noticesResult, toolsResult] = await Promise.all([
        apiFetch<Paginated<ToolRun>>("/account/tool-runs", {
          searchParams: { per_page: 6 },
        }),
        apiFetch<Paginated<NotificationItem>>("/notifications", {
          searchParams: { per_page: 3, "filter[unread]": 1 },
        }),
        apiFetch<Paginated<ToolSummary>>("/catalog/tools", {
          searchParams: { per_page: 6, "filter[featured]": 1 },
        }),
      ]);

      if (cancelled) return;

      if (runsResult.ok) setRuns(runsResult.data.data);
      if (noticesResult.ok) setNotices(noticesResult.data.data);
      if (toolsResult.ok) setSuggested(toolsResult.data.data);

      setLoading(false);
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  const usage = entitlements?.usage;
  const ratio = usage && usage.limit > 0 ? usage.used / usage.limit : 0;

  // Computed from the window the API returned rather than requested separately: a
  // headline figure is not worth a second round trip.
  const succeeded = runs.filter((run) => run.status === "succeeded").length;
  const successRate = runs.length > 0 ? Math.round((succeeded / runs.length) * 100) : null;

  const timings = runs
    .map((run) => run.meta.duration_ms)
    .filter((value): value is number => typeof value === "number");
  const medianDuration = timings.length > 0 ? median(timings) : null;

  return (
    <div className="flex flex-col gap-6">
      {user && !user.email_verified && <VerifyEmailNotice />}

      <section aria-labelledby="usage-heading">
        <h2 id="usage-heading" className="sr-only">
          Plan and usage
        </h2>

        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          {entitlementsLoading || !entitlements ? (
            <>
              <StatTileSkeleton />
              <StatTileSkeleton />
              <StatTileSkeleton />
              <StatTileSkeleton />
            </>
          ) : (
            <>
              {/* With billing off there is no plan to be on, and a tile reading
                  "Free" would imply a paid one exists to move to. What the tile
                  was really answering — what can I run — is said directly. */}
              <StatTile
                label={billingEnabled ? "Plan" : "Access"}
                value={billingEnabled ? planLabel(entitlements.plan) : "All tools"}
                icon={Sparkles}
                tone={!billingEnabled || entitlements.is_paid ? "accent" : "neutral"}
                hint={
                  !billingEnabled
                    ? "Every tool in the catalog"
                    : entitlements.is_paid
                      ? entitlements.renews_at
                        ? `Renews ${formatDate(entitlements.renews_at)}`
                        : "Active"
                      : "Free tools and account tools"
                }
              />

              {/* The window shown is whichever budget has the least left, not always
                  the day — see QuotaService::status(). */}
              <StatTile
                label={`Runs ${usage ? (RUN_PERIOD[usage.window] ?? RUN_PERIOD.daily) : RUN_PERIOD.daily}`}
                value={`${usage?.used ?? 0} / ${usage?.limit ?? 0}`}
                icon={Zap}
                progress={ratio}
                tone={ratio >= 1 ? "danger" : ratio >= 0.75 ? "warning" : "primary"}
                hint={
                  usage
                    ? `${usage.remaining} left · resets ${
                        usage.window === "daily"
                          ? formatTime(usage.resets_at)
                          : formatDate(usage.resets_at)
                      }`
                    : undefined
                }
              />

              <StatTile
                label="Success rate"
                value={successRate === null ? "—" : `${successRate}%`}
                icon={CheckCircle2}
                tone="accent"
                hint={
                  runs.length > 0
                    ? `Across your last ${runs.length} run${runs.length === 1 ? "" : "s"}`
                    : "No runs yet"
                }
              />

              <StatTile
                label="Typical run"
                value={medianDuration === null ? "—" : `${formatDuration(medianDuration)}`}
                icon={Clock}
                hint={
                  entitlements.limits.history_days === null
                    ? "History kept forever"
                    : `History kept ${entitlements.limits.history_days} days`
                }
              />
            </>
          )}
        </div>
      </section>

      <div className="grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <div className="flex min-w-0 flex-col gap-6">
          <SectionCard
            title="Recent runs"
            description="Your latest results, newest first."
            action={
              <Link
                href="/dashboard/runs"
                className="inline-flex items-center gap-1 text-xs font-semibold text-[var(--color-primary)] hover:underline"
              >
                View all
                <ArrowRight className="size-3" aria-hidden="true" />
              </Link>
            }
            bodyClassName={runs.length > 0 ? "p-0" : undefined}
          >
            {loading ? (
              <RowSkeletons />
            ) : runs.length === 0 ? (
              <EmptyState
                icon={Sparkles}
                title="No runs yet"
                description="Pick a tool, run it once, and everything you produce lands here."
                action={
                  <Button asChild size="sm">
                    <Link href="/tools">Browse tools</Link>
                  </Button>
                }
              />
            ) : (
              <ul className="divide-y divide-[var(--color-border-subtle)]">
                {runs.map((run) => (
                  <li key={run.id}>
                    <Link
                      href={run.tool ? `/tools/${run.tool.slug}` : "/dashboard/runs"}
                      className="flex items-center justify-between gap-4 px-4 py-3 transition-colors hover:bg-[var(--color-surface-sunken)]"
                    >
                      <span className="min-w-0">
                        <span className="block truncate text-sm font-medium text-[var(--color-foreground)]">
                          {run.tool?.name ?? "Tool run"}
                        </span>
                        <span className="block text-xs text-[var(--color-foreground-subtle)]">
                          {relativeTime(run.created_at)}
                          {run.meta.duration_ms !== null &&
                            ` · ${formatDuration(run.meta.duration_ms)}`}
                          {run.meta.cache_hit && " · cached"}
                        </span>
                      </span>

                      <RunStatusBadge status={run.status} />
                    </Link>
                  </li>
                ))}
              </ul>
            )}
          </SectionCard>

          <SectionCard
            title="Start something"
            description="Featured tools, ready to run."
            action={
              <Link
                href="/tools"
                className="inline-flex items-center gap-1 text-xs font-semibold text-[var(--color-primary)] hover:underline"
              >
                All tools
                <ArrowRight className="size-3" aria-hidden="true" />
              </Link>
            }
          >
            {loading ? (
              <div className="grid gap-3 sm:grid-cols-2">
                <div className="h-20 animate-pulse rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)]" />
                <div className="h-20 animate-pulse rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)]" />
              </div>
            ) : suggested.length === 0 ? (
              <EmptyState
                icon={Wrench}
                title="The catalog is quiet"
                description="No featured tools right now — the full catalog is still there."
                action={
                  <Button asChild size="sm" variant="secondary">
                    <Link href="/tools">Open the catalog</Link>
                  </Button>
                }
              />
            ) : (
              <ul className="grid gap-3 sm:grid-cols-2">
                {suggested.map((tool) => (
                  <li key={tool.slug}>
                    <Link
                      href={`/tools/${tool.slug}`}
                      className="app-card app-card-interactive group flex h-full flex-col gap-1.5 p-3.5"
                    >
                      <span className="flex items-center justify-between gap-2">
                        <TierBadge tier={tool.tier} />
                        <ArrowUpRight
                          className="size-3.5 text-[var(--color-foreground-subtle)] transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5"
                          aria-hidden="true"
                        />
                      </span>

                      <span className="text-sm font-semibold text-[var(--color-foreground)]">
                        {tool.name}
                      </span>

                      {tool.tagline && (
                        <span className="line-clamp-2 text-xs leading-relaxed text-[var(--color-foreground-muted)]">
                          {tool.tagline}
                        </span>
                      )}
                    </Link>
                  </li>
                ))}
              </ul>
            )}
          </SectionCard>
        </div>

        <div className="flex min-w-0 flex-col gap-6">
          <SectionCard
            title="Needs your attention"
            description="Unread notifications."
            action={
              <Link
                href="/dashboard/notifications"
                className="inline-flex items-center gap-1 text-xs font-semibold text-[var(--color-primary)] hover:underline"
              >
                All
                <ArrowRight className="size-3" aria-hidden="true" />
              </Link>
            }
            bodyClassName={notices.length > 0 ? "p-0" : undefined}
          >
            {notices.length === 0 ? (
              <p className="py-6 text-center text-sm text-[var(--color-foreground-subtle)]">
                <Bell className="mx-auto mb-2 size-5 opacity-60" aria-hidden="true" />
                You are all caught up.
              </p>
            ) : (
              <ul className="divide-y divide-[var(--color-border-subtle)]">
                {notices.map((notice) => (
                  <li key={notice.id} className="px-4 py-3">
                    <p className="flex items-start gap-2 text-sm font-medium text-[var(--color-foreground)]">
                      <span
                        aria-hidden="true"
                        className="mt-1.5 size-1.5 shrink-0 rounded-full bg-[var(--color-primary)]"
                      />
                      {notice.title}
                    </p>

                    <p className="mt-0.5 pl-3.5 text-xs leading-relaxed text-[var(--color-foreground-muted)]">
                      {notice.body}
                    </p>

                    {notice.action && (
                      <Link
                        href={notice.action.url}
                        className="mt-1 inline-block pl-3.5 text-xs font-semibold text-[var(--color-primary)] hover:underline"
                      >
                        {notice.action.label}
                      </Link>
                    )}
                  </li>
                ))}
              </ul>
            )}
          </SectionCard>

          {/* No upsell where there is nothing to sell. */}
          {billingEnabled && entitlements && !entitlements.is_paid && <UpgradeCard />}

          {billingEnabled && entitlements?.is_paid && entitlements.renews_at && (
            <SectionCard title="Subscription" description="What happens next.">
              <p className="flex items-center gap-2 text-sm text-[var(--color-foreground-muted)]">
                <CalendarClock className="size-4 text-[var(--color-accent)]" aria-hidden="true" />
                {entitlements.cancels_at
                  ? `Ends ${formatDate(entitlements.cancels_at)}`
                  : `Renews ${formatDate(entitlements.renews_at)}`}
              </p>

              <Button asChild variant="secondary" size="sm" className="mt-3">
                <Link href="/dashboard/billing">Manage plan</Link>
              </Button>
            </SectionCard>
          )}
        </div>
      </div>
    </div>
  );
}

function UpgradeCard() {
  return (
    <section className="app-card relative overflow-hidden p-5">
      {/* One gradient, once, on the single card that is asking for something. */}
      <span
        aria-hidden="true"
        className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-[var(--color-primary)] via-[var(--color-accent)] to-[var(--color-ember)]"
      />

      <p className="eyebrow mb-2">Upgrade</p>

      <h2 className="text-base font-semibold text-[var(--color-foreground)]">
        Unlock every premium tool
      </h2>

      <p className="mt-1.5 text-sm leading-relaxed text-[var(--color-foreground-muted)]">
        Higher daily limits, unlimited history and exports. Start with a $9 seven-day pass — no
        subscription needed.
      </p>

      <Button asChild className="mt-4 w-full">
        <Link href="/pricing">
          See plans
          <ArrowRight className="size-4" aria-hidden="true" />
        </Link>
      </Button>
    </section>
  );
}

function VerifyEmailNotice() {
  const [sent, setSent] = React.useState(false);
  const [pending, setPending] = React.useState(false);

  return (
    <FormAlert tone="success">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <span className="flex items-center gap-2">
          <Mail className="size-4" aria-hidden="true" />
          Confirm your email so receipts and security alerts reach you.
        </span>

        <Button
          size="sm"
          variant="secondary"
          loading={pending}
          disabled={sent}
          onClick={async () => {
            setPending(true);
            const result = await authApi.resendVerification();
            setPending(false);
            if (result.ok) setSent(true);
          }}
        >
          {sent ? "Link sent" : "Resend link"}
        </Button>
      </div>
    </FormAlert>
  );
}

function RowSkeletons() {
  return (
    <div className="flex flex-col gap-2" aria-hidden="true">
      {[0, 1, 2].map((row) => (
        <div
          key={row}
          className="h-10 animate-pulse rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)]"
        />
      ))}
    </div>
  );
}

function median(values: number[]): number {
  const sorted = [...values].sort((a, b) => a - b);
  const middle = Math.floor(sorted.length / 2);

  return sorted.length % 2 === 0 ? (sorted[middle - 1] + sorted[middle]) / 2 : sorted[middle];
}

export function formatDuration(ms: number): string {
  return ms >= 1000 ? `${(ms / 1000).toFixed(ms >= 10_000 ? 0 : 1)}s` : `${Math.round(ms)}ms`;
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString(undefined, {
    month: "short",
    day: "numeric",
  });
}

function formatTime(iso: string): string {
  return new Date(iso).toLocaleTimeString(undefined, {
    hour: "numeric",
    minute: "2-digit",
  });
}
