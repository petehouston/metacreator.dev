import Link from "next/link";
import type * as React from "react";

/**
 * Shared shell for legal pages: a readable measure, a visible "last updated" date,
 * and a table of contents. Legal text nobody can navigate is legal text nobody reads.
 */
export function LegalPage({
  title,
  updatedAt,
  intro,
  sections,
}: {
  title: string;
  updatedAt: string;
  intro: React.ReactNode;
  sections: { id: string; heading: string; body: React.ReactNode }[];
}) {
  return (
    <div className="mx-auto w-full max-w-[75rem] px-4 py-14 sm:px-6 lg:py-20">
      <div className="grid gap-12 lg:grid-cols-[minmax(0,1fr)_16rem] lg:items-start">
        <div className="prose-measure flex flex-col gap-6">
          <header className="flex flex-col gap-2">
            <h1 className="text-display-lg text-balance">{title}</h1>
            <p className="text-sm text-[var(--color-foreground-subtle)]">
              Last updated {updatedAt}
            </p>
          </header>

          <div className="text-body-lg text-[var(--color-foreground-muted)]">{intro}</div>

          {sections.map((section) => (
            <section key={section.id} className="flex flex-col gap-3">
              <h2 id={section.id} className="scroll-mt-24 text-heading-2">
                {section.heading}
              </h2>
              <div className="flex flex-col gap-3 leading-relaxed text-[var(--color-foreground-muted)] [&_a]:text-[var(--color-primary)] [&_a]:underline [&_li]:list-disc [&_strong]:font-semibold [&_strong]:text-[var(--color-foreground)] [&_ul]:flex [&_ul]:flex-col [&_ul]:gap-2 [&_ul]:pl-5">
                {section.body}
              </div>
            </section>
          ))}

          <p className="mt-4 border-t border-[var(--color-border-subtle)] pt-6 text-sm text-[var(--color-foreground-subtle)]">
            Questions about this document?{" "}
            <Link href="/contact" className="text-[var(--color-primary)] hover:underline">
              Get in touch
            </Link>
            .
          </p>
        </div>

        <nav aria-label="On this page" className="hidden lg:sticky lg:top-24 lg:block">
          <h2 className="eyebrow">
            On this page
          </h2>
          <ul className="mt-3 flex flex-col gap-2">
            {sections.map((section) => (
              <li key={section.id}>
                <a
                  href={`#${section.id}`}
                  className="text-sm text-[var(--color-foreground-muted)] hover:text-[var(--color-foreground)]"
                >
                  {section.heading}
                </a>
              </li>
            ))}
          </ul>
        </nav>
      </div>
    </div>
  );
}
