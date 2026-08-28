import { Check, Sparkles } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { siteConfig } from "@/config/site";

export const metadata: Metadata = {
  title: "Pricing — Free Tools, $9 Weekly Pass, or Pro",
  description:
    "Most MetaCreator tools are free. Pro unlocks every premium tool for $19/month or $180/year, and a $9 seven-day pass covers one-off projects.",
  alternates: { canonical: "/pricing" },
};

const PLANS = [
  {
    key: "free",
    name: "Free",
    price: "$0",
    cadence: "forever",
    description: "Everything most creators need, with no card and no commitment.",
    features: [
      "Every free tool, no account needed",
      "Account tools with a free sign-up",
      "50 runs a day",
      "7 days of run history",
    ],
    cta: { href: "/register", label: "Create a free account" },
    variant: "secondary" as const,
  },
  {
    key: "pass_7d",
    name: "7-day pass",
    price: "$9",
    cadence: "one-off",
    description: "Pro for a week. Nothing renews, nothing to cancel.",
    features: [
      "Every premium tool for 7 days",
      "300 runs a day",
      "Full exports and downloads",
      "No card kept on file",
    ],
    cta: { href: "/register?plan=pass_7d", label: "Get a pass" },
    variant: "secondary" as const,
  },
  {
    key: "pro_yearly",
    name: "Pro",
    price: "$15",
    cadence: "per month, billed yearly",
    note: "or $19 billed monthly",
    description: "For creators and small teams doing this professionally.",
    features: [
      "Every tool, including premium",
      "1,000 runs a day",
      "Unlimited run history",
      "Exports, bulk operations and media kits",
      "Early access to new tools",
      "Priority support",
    ],
    cta: { href: "/register?plan=pro_yearly", label: "Start with Pro" },
    variant: "primary" as const,
    highlighted: true,
  },
];

const COMPARISON = [
  { feature: "Free tools", free: true, pass: true, pro: true },
  { feature: "Account-only tools", free: "With an account", pass: true, pro: true },
  { feature: "Premium tools", free: false, pass: true, pro: true },
  { feature: "Runs per day", free: "50", pass: "300", pro: "1,000" },
  { feature: "Run history", free: "7 days", pass: "During the pass", pro: "Unlimited" },
  { feature: "Exports and downloads", free: false, pass: true, pro: true },
  { feature: "Media kit generator", free: false, pass: true, pro: true },
  { feature: "Priority support", free: false, pass: false, pro: true },
];

export default function PricingPage() {
  return (
    <div className="mx-auto w-full max-w-[75rem] px-4 py-14 sm:px-6 lg:py-20">
      <header className="mx-auto flex max-w-2xl flex-col items-center gap-4 text-center">
        <Badge variant="brand" size="md">
          <Sparkles className="size-3" />
          Half the catalog is free, forever
        </Badge>

        <h1 className="text-display-lg text-balance">Pay only when a tool earns it</h1>

        <p className="text-body-lg text-[var(--color-foreground-muted)]">
          There is no trial to remember to cancel and no feature held hostage behind a
          demo call. Start free, and upgrade the day a premium tool saves you an hour.
        </p>
      </header>

      <div className="mt-12 grid gap-5 lg:grid-cols-3">
        {PLANS.map((plan) => (
          <div
            key={plan.key}
            className={`panel relative flex flex-col gap-6 overflow-hidden p-7 ${
              plan.highlighted ? "shadow-[var(--shadow-glow)]" : ""
            }`}
          >
            {plan.highlighted && (
              <>
                <span
                  aria-hidden="true"
                  className="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-r from-[var(--color-primary)] to-[var(--color-accent)]"
                />
                <Badge variant="brand" size="md" className="absolute right-5 top-5">
                  Best value
                </Badge>
              </>
            )}

            <div className="flex flex-col gap-2">
              <h2 className="text-heading-3">{plan.name}</h2>

              <p className="flex items-baseline gap-2">
                <span className="tabular text-display-lg">{plan.price}</span>
                <span className="font-mono text-[0.6875rem] uppercase tracking-[0.1em] text-[var(--color-foreground-subtle)]">
                  {plan.cadence}
                </span>
              </p>

              {plan.note && (
                <p className="text-xs text-[var(--color-foreground-subtle)]">{plan.note}</p>
              )}

              <p className="text-sm text-[var(--color-foreground-muted)]">{plan.description}</p>
            </div>

            <ul className="flex flex-1 flex-col gap-3">
              {plan.features.map((feature) => (
                <li key={feature} className="flex items-start gap-2.5 text-sm">
                  <Check className="mt-0.5 size-4 shrink-0 text-[var(--color-accent)]" />
                  <span className="text-[var(--color-foreground-muted)]">{feature}</span>
                </li>
              ))}
            </ul>

            <Button asChild variant={plan.variant} size="lg">
              <Link href={plan.cta.href}>{plan.cta.label}</Link>
            </Button>
          </div>
        ))}
      </div>

      <section aria-labelledby="comparison" className="mt-16">
        <p className="eyebrow">Side by side</p>
        <h2 id="comparison" className="mt-3 text-heading-2">
          What you get on each plan
        </h2>

        <div className="panel mt-6 overflow-x-auto">
          <table className="w-full min-w-[40rem] text-sm">
            <thead className="bg-[var(--color-surface-sunken)]">
              <tr>
                <th scope="col" className="px-5 py-3 text-left">
                  <span className="eyebrow">Feature</span>
                </th>
                {["Free", "7-day pass", "Pro"].map((column) => (
                  <th key={column} scope="col" className="px-5 py-3 text-left">
                    <span className="eyebrow">{column}</span>
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {COMPARISON.map((row) => (
                <tr key={row.feature} className="border-t border-[var(--color-border-subtle)]">
                  <th scope="row" className="px-5 py-3 text-left font-medium">
                    {row.feature}
                  </th>
                  {[row.free, row.pass, row.pro].map((value, index) => (
                    <td key={index} className="px-5 py-3 text-[var(--color-foreground-muted)]">
                      <Availability value={value} />
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      <p className="mt-10 text-center text-sm text-[var(--color-foreground-subtle)]">
        Prices in USD, excluding any tax your country requires. Cancel any time from your
        billing page.{" "}
        <Link href="/contact" className="text-[var(--color-primary)] hover:underline">
          Questions? Ask us.
        </Link>
      </p>

      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{
          __html: JSON.stringify({
            "@context": "https://schema.org",
            "@type": "Product",
            name: `${siteConfig.name} Pro`,
            description: "Premium creator tools for social media growth and analysis.",
            offers: {
              "@type": "AggregateOffer",
              priceCurrency: "USD",
              lowPrice: "9.00",
              highPrice: "180.00",
              offerCount: 3,
            },
          }),
        }}
      />
    </div>
  );
}

function Availability({ value }: { value: boolean | string }) {
  if (value === true) {
    return (
      <>
        <Check className="size-4 text-[var(--color-accent)]" aria-hidden="true" />
        <span className="sr-only">Included</span>
      </>
    );
  }

  if (value === false) {
    return (
      <>
        <span aria-hidden="true" className="text-[var(--color-foreground-subtle)]">
          —
        </span>
        <span className="sr-only">Not included</span>
      </>
    );
  }

  return <>{value}</>;
}
