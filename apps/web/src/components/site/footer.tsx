import Link from "next/link";

import { Logo } from "@/components/site/logo";
import { NewsletterForm } from "@/components/site/newsletter-form";
import { footerNavFor, siteConfig } from "@/config/site";
import { siteFeatures } from "@/lib/site-settings";

export async function SiteFooter() {
  // A server component, so it reads the switch directly rather than through the
  // client context — the footer is rendered once per request and never re-renders.
  const { billingEnabled, changelogEnabled } = await siteFeatures();

  return (
    <footer className="relative mt-24 border-t border-[var(--color-border)]">
      {/* The rail again, closing the page the same way the header opens it. */}
      <span
        aria-hidden="true"
        className="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-[var(--color-primary)] to-transparent"
      />

      <div className="mx-auto w-full max-w-[80rem] px-4 py-14 sm:px-6">
        <div className="grid gap-12 lg:grid-cols-[1.4fr_2.6fr]">
          <div className="flex flex-col gap-4">
            <Logo />
            <p className="max-w-sm text-sm leading-relaxed text-[var(--color-foreground-muted)]">
              {siteConfig.description}
            </p>
            <NewsletterForm source="footer" compact />
          </div>

          {/* Sitewide links to the money pages — the least glamorous, highest-leverage
              part of the internal linking strategy (see docs/16). */}
          <nav aria-label="Footer" className="grid grid-cols-2 gap-8 sm:grid-cols-4">
            {footerNavFor(billingEnabled, changelogEnabled).map((group) => (
              <div key={group.title} className="flex flex-col gap-3">
                <h2 className="eyebrow">{group.title}</h2>
                <ul className="flex flex-col gap-2.5">
                  {group.links.map((link) => (
                    <li key={link.href}>
                      <Link
                        href={link.href}
                        className="text-sm text-[var(--color-foreground-muted)] transition-colors hover:text-[var(--color-foreground)]"
                      >
                        {link.label}
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </nav>
        </div>

        <div className="mt-12 flex flex-col items-center justify-between gap-4 border-t border-[var(--color-border-subtle)] pt-6 sm:flex-row">
          <p className="font-mono text-[0.6875rem] text-[var(--color-foreground-subtle)]">
            © {new Date().getFullYear()} {siteConfig.name}. All rights reserved.
          </p>
          <p className="font-mono text-[0.6875rem] text-[var(--color-foreground-subtle)]">
            Not affiliated with any social network. All trademarks belong to their owners.
          </p>
        </div>
      </div>
    </footer>
  );
}
