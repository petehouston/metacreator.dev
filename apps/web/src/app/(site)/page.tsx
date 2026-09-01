import { ArrowRight, ArrowUpRight, Check } from "lucide-react";
import Link from "next/link";
import { Suspense } from "react";

import { HeroTool } from "@/components/site/hero-tool";
import { NewsletterForm } from "@/components/site/newsletter-form";
import { ToolCard, ToolCardSkeleton } from "@/components/tools/tool-card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { platforms, siteConfig } from "@/config/site";
import { api } from "@/lib/api";
import { siteFeatures } from "@/lib/site-settings";

export const metadata = {
  title: `${siteConfig.name} — ${siteConfig.tagline}`,
  description: siteConfig.description,
  alternates: { canonical: "/" },
};

/**
 * The answer to "is it really free?" is different when there is nothing to sell,
 * and this list is also the source of the FAQPage structured data — leaving the
 * Pro sentence in would publish a plan the site does not offer.
 */
const FREE_FAQ_ANSWER =
  "Yes. Every tool in the catalog is free to use right now. Most run straight away; a few of the heavier ones ask you to sign in — also free — so daily limits stay fair for everyone.";

function faqsFor(billingEnabled: boolean) {
  return billingEnabled
    ? FAQS
    : FAQS.map((faq) =>
        faq.q === "Is it really free?" ? { ...faq, a: FREE_FAQ_ANSWER } : faq,
      );
}

const FAQS = [
  {
    q: "Is it really free?",
    a: "Yes. Most of the catalog runs straight away with nothing to sign up for. A few of the heavier tools ask for a free account so daily limits stay fair, and an account also saves your run history. Pro unlocks the tools that cost us real money to run.",
  },
  {
    q: "Do I need to connect my social accounts?",
    a: "No. Nothing here asks for access to your profiles, and we never post on your behalf. Every tool works from the links, text and files you hand it.",
  },
  {
    q: "What happens to what I paste in?",
    a: "Inputs are hashed, not stored, unless a tool explicitly says otherwise and you agree. We do not record your IP address. Files you upload are private and deleted automatically.",
  },
  {
    q: "Which networks do you support?",
    a: "All of the big ones — YouTube, Instagram, TikTok, X, Facebook, LinkedIn, Threads, Pinterest — and plenty of tools are network-agnostic, so they work anywhere you post. New networks get added as creators ask for them.",
  },
  {
    q: "Can I cancel any time?",
    a: "Yes, in two clicks from your billing page. Your access runs to the end of the period you already paid for. There is also a $9 seven-day pass if you only need Pro for one project.",
  },
];

/**
 * The landing page.
 *
 * Built as an editorial spread rather than a SaaS template: a mono voice for
 * anything measured, and one working tool above the fold instead of a screenshot
 * of one. The signature repeats deliberately — the bracket rule on
 * every section eyebrow, the two-colour spine on every card, and exactly one
 * gradient headline on the page.
 */
export default async function HomePage() {
  const { billingEnabled } = await siteFeatures();
  const faqs = faqsFor(billingEnabled);

  return (
    <>
      <Hero billingEnabled={billingEnabled} />
      <Ticker />
      <ProofStrip />
      <Suspense fallback={<ToolGridFallback />}>
        <FeaturedTools />
      </Suspense>
      <HowItWorks />
      <Outcomes />
      {/* The whole section is an offer, so with billing off it is not softened — it
          is absent, and the page reads as a product that is simply free. */}
      {billingEnabled && <PricingTeaser />}
      <Faq faqs={faqs} />
      <FinalCta />

      {/* FAQPage structured data — the FAQ above is the visible source of truth for
          it, which is what keeps the markup honest and eligible for rich results. */}
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{
          __html: JSON.stringify({
            "@context": "https://schema.org",
            "@type": "FAQPage",
            mainEntity: faqs.map((faq) => ({
              "@type": "Question",
              name: faq.q,
              acceptedAnswer: { "@type": "Answer", text: faq.a },
            })),
          }),
        }}
      />
    </>
  );
}

function Hero({ billingEnabled }: { billingEnabled: boolean }) {
  return (
    <section className="relative overflow-hidden">
      <div aria-hidden="true" className="pointer-events-none absolute inset-0 bg-grid opacity-70" />

      <div className="relative mx-auto grid w-full max-w-[80rem] gap-14 px-4 pb-16 pt-14 sm:px-6 lg:grid-cols-[1.05fr_1fr] lg:items-center lg:pb-24 lg:pt-20">
        <div className="flex flex-col items-start gap-7">
          <Badge variant="brand" size="md" className="font-mono tracking-[0.08em]">
            60+ tools · every major network · free to use
          </Badge>

          <h1 className="text-heading-1 text-balance sm:text-display-lg lg:text-display-xl">
            Every <span className="text-gradient">creator</span> tool.
            <br className="hidden sm:block" />{" "}
            <span className="marker-underline">One tab.</span>
          </h1>

          <p className="max-w-xl text-body-lg text-[var(--color-foreground-muted)]">
            Everything you need to analyze, plan and grow — across YouTube, Instagram,
            TikTok, X, Facebook, LinkedIn, Threads, Pinterest and wherever you post
            next. One fast, private workspace instead of twenty tabs. Start with the
            tool on the right, right now.
          </p>

          <div className="flex flex-wrap items-center gap-3">
            <Button asChild size="xl">
              <Link href="/tools">
                Browse the tools
                <ArrowRight />
              </Link>
            </Button>

            {billingEnabled && (
              <Button asChild variant="secondary" size="xl">
                <Link href="/pricing">See pricing</Link>
              </Button>
            )}
          </div>

          <ul className="flex flex-wrap items-center gap-x-5 gap-y-2 font-mono text-[0.6875rem] uppercase tracking-[0.1em] text-[var(--color-foreground-subtle)]">
            {["No connected accounts", "Private by default", "Every network"].map((item) => (
              <li key={item} className="flex items-center gap-1.5">
                <Check className="size-3.5 text-[var(--color-accent)]" />
                {item}
              </li>
            ))}
          </ul>
        </div>

        {/* A working tool, not a screenshot. The visitor gets something useful before
            we ask for anything at all. */}
        <div className="relative">
          <div
            aria-hidden="true"
            className="pointer-events-none absolute -inset-6 rounded-[var(--radius-xl)] bg-aurora blur-2xl"
          />
          <div className="relative">
            <HeroTool />
          </div>
        </div>
      </div>
    </section>
  );
}

/**
 * The ticker.
 *
 * Purely a piece of typography, and the one place the catalog's own vocabulary is
 * on show — "SPLIT · SCORE · RESIZE" tells you what the site is for faster than a
 * paragraph does.
 */
function Ticker() {
  const words = [
    "Analyze",
    "Generate",
    "Split",
    "Resize",
    "Benchmark",
    "Score",
    "Compare",
    "Download",
    "Translate",
    "Schedule",
    "Audit",
    "Convert",
  ];

  return (
    <div
      aria-hidden="true"
      className="marquee-mask relative overflow-hidden border-y border-[var(--color-border-subtle)] py-3.5"
    >
      {/* Two identical groups, each at least a viewport wide: the animation slides
          exactly one group, so the second is always in place before the first
          leaves and the loop never shows a gap or a jump. */}
      <div className="marquee">
        {[0, 1].map((copy) => (
          <div key={copy} className="marquee-group">
            {words.map((word) => (
              <span
                key={word}
                className="flex shrink-0 items-center gap-8 pr-8 font-mono text-[0.6875rem] uppercase tracking-[0.32em] text-[var(--color-foreground-subtle)]"
              >
                {word}
                <span className="size-1 rounded-full bg-[var(--color-accent)]" />
              </span>
            ))}
          </div>
        ))}
      </div>
    </div>
  );
}

function ProofStrip() {
  const stats = [
    { value: "60+", label: "Tools in the catalog" },
    { value: String(platforms.length), label: "Networks covered" },
    { value: "< 1s", label: "Typical result time" },
    { value: "$0", label: "To use them today" },
  ];

  return (
    <section aria-label="At a glance" className="mx-auto w-full max-w-[80rem] px-4 py-14 sm:px-6">
      <dl className="grid grid-cols-2 gap-px overflow-hidden rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-[var(--color-border)] lg:grid-cols-4">
        {stats.map((stat) => (
          <div
            key={stat.label}
            className="flex flex-col gap-1 bg-[var(--color-surface)] p-6 backdrop-blur-md"
          >
            <dd className="tabular order-1 text-heading-1 text-[var(--color-foreground)]">
              {stat.value}
            </dd>
            <dt className="order-2 font-mono text-[0.6875rem] uppercase tracking-[0.1em] text-[var(--color-foreground-subtle)]">
              {stat.label}
            </dt>
          </div>
        ))}
      </dl>
    </section>
  );
}

async function FeaturedTools() {
  // A failed fetch must not take the landing page down with it — the rest of the
  // page still converts perfectly well without this section.
  const tools = await api.tools
    .list({ per_page: 8 })
    .then((response) => response.data)
    .catch(() => []);

  if (tools.length === 0) return null;

  return (
    <Section
      eyebrow="The catalog"
      title="Small tools that finish the job"
      description="Every tool does one job properly, with instructions and a worked example. Most run on the spot; a few ask for a free account so daily limits stay fair."
      action={{ href: "/tools", label: "See all tools" }}
    >
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {tools.map((tool) => (
          <ToolCard key={tool.slug} tool={tool} />
        ))}
      </div>

      <nav
        aria-label="Tools by platform"
        className="mt-6 flex flex-wrap items-center gap-2 border-t border-[var(--color-border-subtle)] pt-6"
      >
        <span className="eyebrow mr-2">By network</span>
        {platforms.map((platform) => (
          <Link key={platform.key} href={`/tools?platform=${platform.key}`}>
            <Badge
              variant="neutral"
              size="md"
              className="cursor-pointer transition-colors hover:border-[var(--color-border-strong)] hover:text-[var(--color-foreground)]"
            >
              {platform.label}
            </Badge>
          </Link>
        ))}
      </nav>
    </Section>
  );
}

function ToolGridFallback() {
  return (
    <Section eyebrow="The catalog" title="Small tools that finish the job">
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {Array.from({ length: 8 }, (_, index) => (
          <ToolCardSkeleton key={index} />
        ))}
      </div>
    </Section>
  );
}

function HowItWorks() {
  const steps = [
    {
      title: "Pick a tool",
      body: "Search or filter by network. Each tool page tells you exactly what it does and shows a worked example.",
    },
    {
      title: "Run it",
      body: "Paste a link, some text or a file. Results appear in about a second, with a copy button on everything.",
    },
    {
      title: "Act on it",
      body: "Every result comes with the context to make a decision — benchmarks, prioritised fixes, or a file you can use.",
    },
  ];

  return (
    <Section
      eyebrow="How it works"
      title="No onboarding, no setup, no connected accounts"
      description="You are three clicks from a useful answer, and none of them asks for access to your profiles."
    >
      <ol className="grid gap-px overflow-hidden rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-[var(--color-border)] md:grid-cols-3">
        {steps.map((step, index) => (
          <li
            key={step.title}
            className="relative flex flex-col gap-3 bg-[var(--color-surface)] p-7 backdrop-blur-md"
          >
            <span className="tabular font-mono text-[0.6875rem] tracking-[0.2em] text-[var(--color-primary)]">
              {String(index + 1).padStart(2, "0")}
            </span>
            <h3 className="text-heading-3">{step.title}</h3>
            <p className="text-sm leading-relaxed text-[var(--color-foreground-muted)]">
              {step.body}
            </p>
          </li>
        ))}
      </ol>
    </Section>
  );
}

function Outcomes() {
  const outcomes = [
    {
      title: "Grow",
      body: "Benchmark your engagement against accounts your size, find the hashtags you can actually rank for, and see which competitors are pulling ahead.",
      links: [
        { href: "/tools/engagement-rate-calculator", label: "Engagement Rate Calculator" },
        { href: "/tools/hashtag-generator", label: "Hashtag Generator" },
      ],
    },
    {
      title: "Analyze",
      body: "Audit a title before you publish it, score a channel against the competition, and track what changed after you shipped.",
      links: [
        { href: "/tools/headline-analyzer", label: "Headline Analyzer" },
        { href: "/tools/youtube-thumbnail-downloader", label: "Thumbnail Downloader" },
      ],
    },
    {
      title: "Create",
      body: "Turn one long-form idea into a week of posts, fit every caption to every network you publish on, and stop losing hooks to a character limit.",
      links: [
        { href: "/tools/x-thread-splitter", label: "Thread Splitter" },
        { href: "/tools/social-media-character-counter", label: "Character Counter" },
      ],
    },
  ];

  return (
    <Section
      eyebrow="What you get out of it"
      title="Built around what creators actually do all day"
    >
      <div className="flex flex-col">
        {outcomes.map((outcome) => (
          <div
            key={outcome.title}
            className="grid gap-5 border-t border-[var(--color-border-subtle)] py-8 last:border-b md:grid-cols-[14rem_minmax(0,1fr)_16rem] md:items-start md:gap-10"
          >
            <h3 className="text-heading-2 text-[var(--color-foreground)]">{outcome.title}</h3>

            <p className="leading-relaxed text-[var(--color-foreground-muted)]">{outcome.body}</p>

            <ul className="flex flex-col gap-2">
              {outcome.links.map((link) => (
                <li key={link.href}>
                  <Link
                    href={link.href}
                    className="group flex items-center justify-between gap-2 text-sm text-[var(--color-primary)]"
                  >
                    {link.label}
                    <ArrowUpRight className="size-4 transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5" />
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        ))}
      </div>
    </Section>
  );
}

function PricingTeaser() {
  const plans = [
    {
      name: "Free",
      price: "$0",
      cadence: "forever",
      description: "The whole catalog, plus higher limits once you make an account.",
      features: ["Every free tool", "50 runs a day", "7 days of history"],
      cta: { href: "/register", label: "Create an account" },
      highlighted: false,
    },
    {
      name: "Pro",
      price: "$15",
      cadence: "per month, billed yearly",
      description: "Everything, including the tools that cost us real money to run.",
      features: [
        "Every tool, including premium",
        "1,000 runs a day",
        "Unlimited history and exports",
        "Priority support",
      ],
      cta: { href: "/pricing", label: "See plans" },
      highlighted: true,
    },
    {
      name: "7-day pass",
      price: "$9",
      cadence: "one-off",
      description: "Pro for a week. No subscription, no card kept on file.",
      features: ["Every premium tool for 7 days", "300 runs a day", "Nothing to cancel"],
      cta: { href: "/pricing", label: "Get a pass" },
      highlighted: false,
    },
  ];

  return (
    <Section
      eyebrow="Pricing"
      title="Start free. Upgrade only when a tool earns it."
      description="Most people never need to pay. The ones who do are usually running client work — and for them, one media kit pays for a year."
    >
      <div className="grid gap-4 lg:grid-cols-3">
        {plans.map((plan) => (
          <div
            key={plan.name}
            className={`panel relative flex flex-col gap-5 overflow-hidden p-6 ${
              plan.highlighted ? "shadow-[var(--shadow-glow)]" : ""
            }`}
          >
            {plan.highlighted && (
              <span
                aria-hidden="true"
                className="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-r from-[var(--color-primary)] to-[var(--color-accent)]"
              />
            )}

            <div className="flex flex-col gap-1.5">
              <div className="flex items-center justify-between gap-2">
                <h3 className="text-heading-3">{plan.name}</h3>
                {plan.highlighted && <Badge variant="brand">Most popular</Badge>}
              </div>

              <p className="flex items-baseline gap-1.5">
                <span className="tabular text-display-lg">{plan.price}</span>
                <span className="font-mono text-[0.6875rem] uppercase tracking-[0.1em] text-[var(--color-foreground-subtle)]">
                  {plan.cadence}
                </span>
              </p>
              <p className="text-sm text-[var(--color-foreground-muted)]">{plan.description}</p>
            </div>

            <ul className="flex flex-1 flex-col gap-2.5 border-t border-[var(--color-border-subtle)] pt-5">
              {plan.features.map((feature) => (
                <li key={feature} className="flex items-start gap-2 text-sm">
                  <Check className="mt-0.5 size-4 shrink-0 text-[var(--color-accent)]" />
                  <span className="text-[var(--color-foreground-muted)]">{feature}</span>
                </li>
              ))}
            </ul>

            <Button asChild variant={plan.highlighted ? "primary" : "secondary"} size="lg">
              <Link href={plan.cta.href}>{plan.cta.label}</Link>
            </Button>
          </div>
        ))}
      </div>
    </Section>
  );
}

/**
 * The FAQ.
 *
 * A single stacked list rather than the two-column grid it used to be: in a grid,
 * opening one answer stretched its row, so the unopened item beside it grew a band
 * of empty space until you opened that one too. One column per row cannot do that,
 * and it reads better anyway — questions are a sequence, not a matrix.
 *
 * Still plain `<details>`, so it works before hydration and answers stay findable
 * with the browser's own find-in-page.
 */
function Faq({ faqs }: { faqs: typeof FAQS }) {
  return (
    <Section
      eyebrow="Questions"
      title="The things people actually ask"
      description="Still unsure about something? The contact form reaches a person, not a queue."
      action={{ href: "/contact", label: "Ask us anything" }}
    >
      <div className="overflow-hidden rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-[var(--color-surface)] backdrop-blur-md">
        {faqs.map((faq) => (
          <details
            key={faq.q}
            className="group border-b border-[var(--color-border-subtle)] last:border-b-0 open:bg-[var(--color-surface-sunken)]"
          >
            <summary className="flex cursor-pointer list-none items-center gap-4 px-5 py-5 font-medium transition-colors marker:hidden hover:text-[var(--color-primary)] sm:px-7">
              <span className="flex-1 text-balance">{faq.q}</span>

              {/* A plus that becomes a minus: the horizontal bar stays put and the
                  vertical one rotates out of sight, which reads as one control
                  changing state rather than two icons swapping. */}
              <span
                aria-hidden="true"
                className="relative size-5 shrink-0 rounded-full border border-[var(--color-border)] text-[var(--color-foreground-subtle)] transition-colors group-open:border-[var(--color-primary)] group-open:text-[var(--color-primary)]"
              >
                <span className="absolute left-1/2 top-1/2 h-px w-2.5 -translate-x-1/2 -translate-y-1/2 bg-current" />
                <span className="absolute left-1/2 top-1/2 h-2.5 w-px -translate-x-1/2 -translate-y-1/2 bg-current transition-transform duration-200 group-open:rotate-90" />
              </span>
            </summary>

            <p className="px-5 pb-6 pr-12 text-sm leading-relaxed text-[var(--color-foreground-muted)] sm:px-7 sm:pr-16">
              {faq.a}
            </p>
          </details>
        ))}
      </div>
    </Section>
  );
}

function FinalCta() {
  return (
    <section className="mx-auto w-full max-w-[80rem] px-4 py-20 sm:px-6">
      <div className="panel relative overflow-hidden p-10 sm:p-16">
        <div aria-hidden="true" className="pointer-events-none absolute inset-0 bg-aurora" />
        <div
          aria-hidden="true"
          className="pointer-events-none absolute inset-x-0 top-0 h-[3px] bg-gradient-to-r from-transparent via-[var(--color-primary)] to-transparent"
        />

        <div className="relative grid gap-10 lg:grid-cols-[1.2fr_1fr] lg:items-center">
          <div className="flex flex-col items-start gap-5">
            <p className="eyebrow">Last thing</p>

            <h2 className="text-heading-1 text-balance">
              Your next post deserves better than a browser full of half-broken tabs.
            </h2>

            <p className="text-body-lg text-[var(--color-foreground-muted)]">
              Pick a tool and run it. Most of them need nothing but the link you already have.
            </p>

            <Button asChild size="xl">
              <Link href="/tools">
                Browse the tools
                <ArrowRight />
              </Link>
            </Button>
          </div>

          <div className="flex flex-col gap-3 border-t border-[var(--color-border-subtle)] pt-8 lg:border-l lg:border-t-0 lg:pl-10 lg:pt-0">
            <p className="eyebrow">Or just keep in touch</p>
            <NewsletterForm source="landing-final-cta" />
          </div>
        </div>
      </div>
    </section>
  );
}

/**
 * The section frame. The eyebrow — a short mono label above every heading — is what
 * gives the page the rhythm of a document rather than a stack of marketing slabs.
 */
function Section({
  eyebrow,
  title,
  description,
  action,
  children,
}: {
  eyebrow: string;
  title: string;
  description?: string;
  action?: { href: string; label: string };
  children: React.ReactNode;
}) {
  return (
    <section className="mx-auto w-full max-w-[80rem] px-4 py-16 sm:px-6 lg:py-20">
      <div className="mb-9 flex flex-wrap items-end justify-between gap-4">
        <div className="flex max-w-2xl flex-col gap-2.5">
          <p className="eyebrow">{eyebrow}</p>

          <h2 className="text-heading-1 text-balance">{title}</h2>

          {description && (
            <p className="text-[var(--color-foreground-muted)]">{description}</p>
          )}
        </div>

        {action && (
          <Link
            href={action.href}
            className="group flex items-center gap-1.5 text-sm font-medium text-[var(--color-primary)]"
          >
            {action.label}
            <ArrowRight className="size-4 transition-transform group-hover:translate-x-0.5" />
          </Link>
        )}
      </div>

      {children}
    </section>
  );
}
