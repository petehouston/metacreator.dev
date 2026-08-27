"use client";

import { ArrowRight, Check, Clock, CreditCard, Minus, Receipt, Zap } from "lucide-react";
import Link from "next/link";
import * as React from "react";

import { planLabel, useEntitlements } from "@/components/app/entitlements-provider";
import { SectionCard } from "@/components/app/section-card";
import { StatTile, StatTileSkeleton } from "@/components/app/stat-tile";
import { FormAlert } from "@/components/auth/form-alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { siteConfig } from "@/config/site";

/**
 * Plan, limits and usage — everything the entitlement service knows, shown plainly.
 *
 * Deliberately does not invent a billing history: payments are not wired up yet
 * ([11 — Billing](docs/11-billing.md)), and a screen that shows an empty invoice
 * table implies one exists and is empty, which is a worse lie than saying so.
 */
export function BillingPanel() {
  const { entitlements, loading, error } = useEntitlements();

  if (error) return <FormAlert>{error}</FormAlert>;

  if (loading || !entitlements) {
    return (
      <div className="grid gap-3 sm:grid-cols-3">
        <StatTileSkeleton />
        <StatTileSkeleton />
        <StatTileSkeleton />
      </div>
    );
  }

  const { usage, limits, is_paid: isPaid } = entitlements;
  const ratio = usage.limit > 0 ? usage.used / usage.limit : 0;

  return (
    <div className="flex flex-col gap-6">
      <section className="app-card relative overflow-hidden p-5">
        <span
          aria-hidden="true"
          className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-[var(--color-primary)] via-[var(--color-accent)] to-[var(--color-ember)]"
        />

        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p className="eyebrow mb-2">Current plan</p>

            <h2 className="flex items-center gap-2.5 text-xl font-bold tracking-[-0.02em] text-[var(--color-foreground)]">
              {planLabel(entitlements.plan)}
              <Badge variant={isPaid ? "success" : "neutral"} size="md">
                {entitlements.status}
              </Badge>
            </h2>

            <p className="mt-1.5 text-sm text-[var(--color-foreground-muted)]">
              {describeTerm(entitlements)}
            </p>
          </div>

          <div className="flex flex-wrap gap-2">
            <Button asChild variant={isPaid ? "secondary" : "primary"}>
              <Link href="/pricing">
                {isPaid ? "Compare plans" : "Upgrade"}
                <ArrowRight className="size-4" aria-hidden="true" />
              </Link>
            </Button>
          </div>
        </div>
      </section>

      <section aria-labelledby="limits-heading">
        <h2 id="limits-heading" className="sr-only">
          Limits and usage
        </h2>

        <div className="grid gap-3 sm:grid-cols-3">
          <StatTile
            label="Runs today"
            value={`${usage.used} / ${usage.limit}`}
            icon={Zap}
            progress={ratio}
            tone={ratio >= 1 ? "danger" : ratio >= 0.75 ? "warning" : "primary"}
            hint={`${usage.remaining} left · resets ${new Date(usage.resets_at).toLocaleTimeString(
              undefined,
              { hour: "numeric", minute: "2-digit" },
            )}`}
          />

          <StatTile
            label="History kept"
            value={limits.history_days === null ? "Unlimited" : `${limits.history_days} days`}
            icon={Clock}
            tone={limits.history_days === null ? "accent" : "neutral"}
            hint="How far back run history reaches"
          />

          <StatTile
            label="Tool access"
            value={
              entitlements.tool_access.default_tier === "premium"
                ? "All tools"
                : entitlements.tool_access.default_tier === "account"
                  ? "Free + account"
                  : "Free tools"
            }
            icon={CreditCard}
            tone={entitlements.tool_access.default_tier === "premium" ? "accent" : "neutral"}
            hint={
              entitlements.tool_access.grants.length > 0
                ? `${entitlements.tool_access.grants.length} individual grant${
                    entitlements.tool_access.grants.length === 1 ? "" : "s"
                  }`
                : "Set by your plan"
            }
          />
        </div>
      </section>

      <SectionCard
        title="What your plan includes"
        description="Everything enforced server-side, so this is the real list."
        bodyClassName="p-0"
      >
        <ul className="divide-y divide-[var(--color-border-subtle)]">
          <FeatureRow
            included
            label="Free and account tools"
            detail="Every tool that does not cost us API quota"
          />
          <FeatureRow
            included={entitlements.tool_access.default_tier === "premium"}
            label="Premium tools"
            detail="Bulk operations, AI-backed analysis, media generation"
          />
          <FeatureRow
            included={limits.export}
            label="Exports"
            detail="Download results as CSV, JSON or a bundle"
          />
          <FeatureRow
            included={limits.history_days === null}
            label="Unlimited history"
            detail={
              limits.history_days === null
                ? "Results are kept indefinitely"
                : `Results are kept ${limits.history_days} days`
            }
          />
          <FeatureRow
            included={limits.priority_support}
            label="Priority support"
            detail="Your messages go to the front of the queue"
          />
          <FeatureRow
            included
            label={`${limits.runs_per_day} runs per day`}
            detail="Resets at midnight in your timezone"
          />
        </ul>
      </SectionCard>

      <SectionCard title="Payments and invoices" description="How billing is handled today.">
        <div className="flex flex-col gap-3">
          <p className="flex items-start gap-2.5 text-sm leading-relaxed text-[var(--color-foreground-muted)]">
            <Receipt
              className="mt-0.5 size-4 shrink-0 text-[var(--color-foreground-subtle)]"
              aria-hidden="true"
            />
            Self-serve invoice history and card management are not available in the app yet.
            Checkout runs from the pricing page, and receipts are emailed to you when a payment
            is taken.
          </p>

          <p className="text-sm text-[var(--color-foreground-muted)]">
            Need a copy of an invoice, a VAT number added, or a refund?{" "}
            <a
              href={`mailto:${siteConfig.supportEmail}`}
              className="font-medium text-[var(--color-primary)] hover:underline"
            >
              {siteConfig.supportEmail}
            </a>{" "}
            — we answer within one working day.
          </p>
        </div>
      </SectionCard>
    </div>
  );
}

function FeatureRow({
  included,
  label,
  detail,
}: {
  included: boolean;
  label: string;
  detail: string;
}) {
  return (
    <li className="flex items-start gap-3 px-4 py-3">
      <span
        className={
          included
            ? "mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-[var(--color-accent-surface)] text-[var(--color-accent)]"
            : "mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-[var(--color-surface-sunken)] text-[var(--color-foreground-subtle)]"
        }
      >
        {included ? (
          <Check className="size-3" aria-hidden="true" />
        ) : (
          <Minus className="size-3" aria-hidden="true" />
        )}
      </span>

      <span className="min-w-0">
        <span
          className={
            included
              ? "block text-sm font-medium text-[var(--color-foreground)]"
              : "block text-sm font-medium text-[var(--color-foreground-subtle)]"
          }
        >
          {label}
        </span>
        <span className="block text-xs text-[var(--color-foreground-subtle)]">{detail}</span>
      </span>

      <span className="sr-only">{included ? "Included" : "Not included"}</span>
    </li>
  );
}

function describeTerm(entitlements: {
  is_paid: boolean;
  cancels_at: string | null;
  renews_at: string | null;
  expires_at: string | null;
}): string {
  if (!entitlements.is_paid) {
    return "Free forever. Upgrade any time — the 7-day pass does not renew.";
  }

  if (entitlements.cancels_at) {
    return `Cancels on ${formatDate(entitlements.cancels_at)}. You keep everything until then.`;
  }

  if (entitlements.renews_at) return `Renews on ${formatDate(entitlements.renews_at)}.`;
  if (entitlements.expires_at) return `Access ends on ${formatDate(entitlements.expires_at)}.`;

  return "Active.";
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString(undefined, {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
}
