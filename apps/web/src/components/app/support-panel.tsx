"use client";

import { BookOpen, ExternalLink, Mail, MessageSquare, ShieldCheck, Zap } from "lucide-react";
import Link from "next/link";
import * as React from "react";

import { useEntitlements } from "@/components/app/entitlements-provider";
import { SectionCard } from "@/components/app/section-card";
import { useBillingEnabled } from "@/components/site/features-provider";
import { Badge } from "@/components/ui/badge";

/**
 * Two of these five answers describe a paywall. With billing off they would be
 * support copy about a product that no longer exists — and this screen is where
 * someone lands precisely because they are confused, so it is the worst place to
 * leave a stale one.
 */
function faqFor(billingEnabled: boolean) {
  if (billingEnabled) return FAQ;

  return FAQ.filter((entry) => entry.question !== UPGRADE_QUESTION).map((entry) =>
    entry.question === HISTORY_QUESTION
      ? {
          ...entry,
          answer:
            "Indefinitely. Every run you make stays in your history until you remove it — export anything you want a copy of outside the app.",
        }
      : entry,
  );
}

const UPGRADE_QUESTION = "A tool is asking me to upgrade. What does that unlock?";

const HISTORY_QUESTION = "How long are my results kept?";

const FAQ = [
  {
    question: "Why did a tool say I had hit my limit?",
    answer:
      "Every plan has a daily run quota, and it resets at midnight in the timezone set on your profile. Your remaining runs are always on the Overview screen and in the sidebar meter.",
  },
  {
    question: "How long are my results kept?",
    answer:
      "Free accounts keep 7 days of run history; paid plans keep it indefinitely. Older runs are removed, not hidden — export anything you need to keep.",
  },
  {
    question: "A tool is asking me to upgrade. What does that unlock?",
    answer:
      "Premium tools are the ones with a real cost per run — API quota, compute or storage. Plan & billing lists exactly what your current plan includes.",
  },
  {
    question: "Can I change the email on my account?",
    answer:
      "No. The email address is the account identity, so it cannot be edited. If you need to move to a new address, write to us and we will help.",
  },
  {
    question: "Do you post to my social accounts?",
    answer:
      "Never. MetaCreator has no write access to any network — every tool works from data you paste in or from public information.",
  },
];

/**
 * Help, and the honest route to a human.
 *
 * Ticketing ([12](docs/12-support-tickets.md)) is specified but not built, so this
 * does not pretend to open a thread — it answers the five questions support
 * actually receives, and hands over an address for everything else.
 */
/** The address to write to, read from Settings by the page that renders this. */
interface SupportPanelProps {
  contactEmail: string;
}

export function SupportPanel({ contactEmail }: SupportPanelProps) {
  const { entitlements } = useEntitlements();
  const billingEnabled = useBillingEnabled();
  const priority = entitlements?.limits.priority_support === true;
  const faq = faqFor(billingEnabled);

  return (
    <div className="grid gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
      <SectionCard
        title="Common questions"
        description={`The ${faq.length === 5 ? "five" : "four"} we are asked most.`}
        bodyClassName="p-0"
      >
        <ul className="divide-y divide-[var(--color-border-subtle)]">
          {faq.map((entry) => (
            <li key={entry.question}>
              <details className="group px-4 py-3">
                <summary className="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-medium text-[var(--color-foreground)]">
                  {entry.question}
                  <span
                    aria-hidden="true"
                    className="shrink-0 text-[var(--color-foreground-subtle)] transition-transform group-open:rotate-45"
                  >
                    +
                  </span>
                </summary>

                <p className="mt-2 max-w-prose text-sm leading-relaxed text-[var(--color-foreground-muted)]">
                  {entry.answer}
                </p>
              </details>
            </li>
          ))}
        </ul>
      </SectionCard>

      <div className="flex flex-col gap-6">
        <SectionCard title="Talk to us" description="A person reads every message.">
          <div className="flex flex-col gap-3">
            {priority && (
              <Badge variant="success" size="md" className="w-fit">
                <Zap className="size-3" aria-hidden="true" />
                Priority support
              </Badge>
            )}

            <a
              href={`mailto:${contactEmail}`}
              className="flex items-center gap-2.5 rounded-[var(--radius-md)] border border-[var(--color-border)] px-3 py-2.5 text-sm font-medium text-[var(--color-foreground)] transition-colors hover:border-[var(--color-primary)]"
            >
              <Mail className="size-4 text-[var(--color-primary)]" aria-hidden="true" />
              {contactEmail}
            </a>

            <Link
              href="/contact"
              className="flex items-center gap-2.5 rounded-[var(--radius-md)] border border-[var(--color-border)] px-3 py-2.5 text-sm font-medium text-[var(--color-foreground)] transition-colors hover:border-[var(--color-primary)]"
            >
              <MessageSquare className="size-4 text-[var(--color-primary)]" aria-hidden="true" />
              Contact form
            </Link>

            <p className="text-xs leading-relaxed text-[var(--color-foreground-subtle)]">
              {priority
                ? "Your plan puts you at the front of the queue — expect a reply the same working day."
                : "We normally reply within one working day."}
            </p>
          </div>
        </SectionCard>

        <SectionCard title="Read up" description="Guides and policies.">
          <ul className="flex flex-col gap-1">
            <ResourceLink href="/blog" icon={BookOpen}>
              Guides and playbooks
            </ResourceLink>
            <ResourceLink href="/security" icon={ShieldCheck}>
              How we handle your data
            </ResourceLink>
            <ResourceLink href="/terms" icon={ExternalLink}>
              Terms of service
            </ResourceLink>
            <ResourceLink href="/privacy" icon={ExternalLink}>
              Privacy policy
            </ResourceLink>
          </ul>
        </SectionCard>
      </div>
    </div>
  );
}

function ResourceLink({
  href,
  icon: Icon,
  children,
}: {
  href: string;
  icon: React.ComponentType<{ className?: string }>;
  children: React.ReactNode;
}) {
  return (
    <li>
      <Link
        href={href}
        className="flex items-center gap-2.5 rounded-[var(--radius-md)] px-2 py-2 text-sm text-[var(--color-foreground-muted)] transition-colors hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-foreground)]"
      >
        <Icon className="size-4 shrink-0" aria-hidden="true" />
        {children}
      </Link>
    </li>
  );
}
