import { Mail, MessageSquare, ShieldAlert } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";

import { ContactForm } from "@/components/site/contact-form";
import { siteConfig } from "@/config/site";
import { contactEmail } from "@/lib/site-settings";

export const metadata: Metadata = {
  title: "Contact Us",
  description:
    "Get in touch about a tool request, a bug, billing, or a security report. We answer everything.",
  alternates: { canonical: "/contact" },
};

/** The three routes in, given whichever address Settings currently publishes. */
const channelsFor = (email: string) => [
  {
    icon: MessageSquare,
    title: "Support",
    body: "Already have an account? Opening a ticket from your dashboard attaches the context we need, so it is the fastest route.",
    href: "/dashboard/support",
    label: "Open a ticket",
  },
  {
    icon: Mail,
    title: "Email",
    body: "For anything else, including partnerships and press.",
    href: `mailto:${email}`,
    label: email,
  },
  {
    icon: ShieldAlert,
    title: "Security",
    body: "Found a vulnerability? Report it privately and we will respond within one working day. We do not pursue good-faith researchers.",
    href: `mailto:${email}`,
    label: email,
  },
];

export default async function ContactPage() {
  const channels = channelsFor(await contactEmail());

  return (
    <div className="mx-auto w-full max-w-[75rem] px-4 py-14 sm:px-6 lg:py-20">
      <header className="flex max-w-2xl flex-col gap-3">
        <h1 className="text-display-lg text-balance">Get in touch</h1>
        <p className="text-body-lg text-[var(--color-foreground-muted)]">
          Tool requests are especially welcome — a surprising amount of the catalog started
          as somebody&apos;s email.
        </p>
      </header>

      <div className="mt-12 grid gap-10 lg:grid-cols-[1.2fr_1fr] lg:items-start">
        <div className="rounded-[var(--radius-xl)] border border-[var(--color-border)] bg-[var(--color-surface)] p-6 sm:p-8">
          <ContactForm />
        </div>

        <div className="flex flex-col gap-4">
          {channels.map((channel) => {
            const Icon = channel.icon;

            return (
              <div
                key={channel.title}
                className="flex flex-col gap-2 panel p-5"
              >
                <span className="flex size-9 items-center justify-center rounded-[var(--radius-md)] bg-[var(--color-primary-subtle)] text-[var(--color-primary)]">
                  <Icon className="size-4" />
                </span>

                <h2 className="text-heading-3">{channel.title}</h2>
                <p className="text-sm leading-relaxed text-[var(--color-foreground-muted)]">
                  {channel.body}
                </p>

                <Link
                  href={channel.href}
                  className="text-sm font-medium text-[var(--color-primary)] hover:underline"
                >
                  {channel.label} →
                </Link>
              </div>
            );
          })}
        </div>
      </div>

      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{
          __html: JSON.stringify({
            "@context": "https://schema.org",
            "@type": "ContactPage",
            name: "Contact MetaCreator.Dev",
            url: `${siteConfig.url}/contact`,
          }),
        }}
      />
    </div>
  );
}
