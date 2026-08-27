import type { Metadata } from "next";
import Link from "next/link";

import { siteConfig } from "@/config/site";

export const metadata: Metadata = {
  title: "About MetaCreator.Dev",
  description:
    "Why we built a single, ad-free home for the small tools creators use every day — and how we think about privacy, pricing and platform rules.",
  alternates: { canonical: "/about" },
};

export default function AboutPage() {
  return (
    <div className="mx-auto w-full max-w-[75rem] px-4 py-14 sm:px-6 lg:py-20">
      <div className="prose-measure mx-auto flex flex-col gap-6">
        <p className="eyebrow">Who we are</p>
        <h1 className="text-heading-1 text-balance sm:text-display-lg">About {siteConfig.name}</h1>

        <p className="text-body-lg text-[var(--color-foreground-muted)]">
          Creators run their presence across five or six networks at once, and the tooling
          for it is scattered across dozens of ad-choked single-purpose sites of wildly
          varying quality. We thought that was a silly problem to still have.
        </p>

        <h2 className="text-heading-2">What we&apos;re building</h2>
        <p className="leading-relaxed text-[var(--color-foreground-muted)]">
          One clean, fast home for the small tools creators actually reach for every day.
          Around half the catalog is free with no account at all, because a tool you have to
          sign up for in order to evaluate is not really free. The paid tier covers the tools
          that cost us real money to run — third-party API quota, compute, storage — rather
          than being an artificial wall around something cheap.
        </p>

        <h2 className="text-heading-2">How we think about your data</h2>
        <p className="leading-relaxed text-[var(--color-foreground-muted)]">
          We do not store your IP address. Tool inputs are hashed rather than kept, unless a
          tool explicitly says otherwise and you agree to it. Files you upload go to private
          storage, are served only through short-lived signed links, and are deleted
          automatically. There is no third-party tracking script before you consent to one.
          The details are all in our{" "}
          <Link href="/privacy" className="text-[var(--color-primary)] hover:underline">
            privacy policy
          </Link>
          , written to be read rather than to be survived.
        </p>

        <h2 className="text-heading-2">What we won&apos;t do</h2>
        <ul className="flex flex-col gap-2 pl-5 text-[var(--color-foreground-muted)] marker:text-[var(--color-foreground-subtle)]">
          <li className="list-disc leading-relaxed">
            <strong className="text-[var(--color-foreground)]">Post on your behalf.</strong>{" "}
            No write access to your accounts, ever. That is your name on the internet, not
            ours.
          </li>
          <li className="list-disc leading-relaxed">
            <strong className="text-[var(--color-foreground)]">
              Build tools that break platform rules.
            </strong>{" "}
            If a tool would require scraping a network in violation of its terms, we drop it
            from the roadmap rather than shipping it quietly.
          </li>
          <li className="list-disc leading-relaxed">
            <strong className="text-[var(--color-foreground)]">Sell your data.</strong> Our
            business model is subscriptions. That is the whole thing.
          </li>
          <li className="list-disc leading-relaxed">
            <strong className="text-[var(--color-foreground)]">Run ads.</strong> The reason
            the existing tools are unpleasant is that ads are the only thing paying for them.
          </li>
        </ul>

        <h2 className="text-heading-2">Say hello</h2>
        <p className="leading-relaxed text-[var(--color-foreground-muted)]">
          We pick what to build next largely from what people ask for.{" "}
          <Link href="/contact" className="text-[var(--color-primary)] hover:underline">
            Tell us what is missing
          </Link>{" "}
          — it genuinely moves the roadmap.
        </p>
      </div>
    </div>
  );
}
