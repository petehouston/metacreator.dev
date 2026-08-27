import type { Metadata } from "next";
import Link from "next/link";

import { LegalPage } from "@/components/legal/legal-page";

export const metadata: Metadata = {
  title: "Cookie Policy",
  description:
    "Every cookie MetaCreator.Dev sets, what it does, how long it lasts, and how to refuse the optional ones.",
  alternates: { canonical: "/cookies" },
};

export default function CookiesPage() {
  return (
    <LegalPage
      title="Cookie Policy"
      updatedAt="26 August 2026"
      intro={
        <p>
          We use as few cookies as a site like this can. Nothing is set for advertising,
          nothing is shared with a data broker, and the free tools work with every optional
          cookie refused. This page lists all of them by name.
        </p>
      }
      sections={[
        {
          id: "strictly-necessary",
          heading: "1. Strictly necessary",
          body: (
            <>
              <p>
                These make the site function. They cannot be switched off, and under the
                ePrivacy rules they do not require consent.
              </p>
              <ul>
                <li>
                  <strong>metacreator_session</strong> — keeps you signed in. Expires after
                  seven days of inactivity, or immediately when you sign out.
                </li>
                <li>
                  <strong>XSRF-TOKEN</strong> — proves a form submission came from our own
                  pages and not from someone else&apos;s. Expires with the session.
                </li>
                <li>
                  <strong>mc_theme</strong> — remembers whether you chose light or dark.
                  Expires after one year. Contains the word &quot;light&quot; or
                  &quot;dark&quot; and nothing else.
                </li>
              </ul>
            </>
          ),
        },
        {
          id: "analytics",
          heading: "2. Analytics",
          body: (
            <>
              <p>
                We measure which pages and tools people use so we know what to build next.
                These are only set if you accept them.
              </p>
              <ul>
                <li>
                  <strong>Google Analytics 4</strong> (<code>_ga</code>, <code>_ga_*</code>)
                  — aggregate traffic and tool usage. IP anonymisation is on. Up to two
                  years.
                </li>
              </ul>
              <p>
                Our own internal tool analytics do <strong>not</strong> use a cookie. They
                key on a rotating daily hash of your IP and user-agent, which cannot be
                reversed and is useless the following day — see the{" "}
                <Link href="/privacy">Privacy Policy</Link>.
              </p>
            </>
          ),
        },
        {
          id: "marketing",
          heading: "3. Marketing",
          body: (
            <>
              <p>
                Set only if you accept them, and only used to measure whether an ad we paid
                for actually worked.
              </p>
              <ul>
                <li>
                  <strong>Meta Pixel</strong> (<code>_fbp</code>) — conversion measurement.
                  Up to three months.
                </li>
              </ul>
              <p>
                If you never see an ad of ours, you will never meet these. We do not set
                them for organic visitors.
              </p>
            </>
          ),
        },
        {
          id: "your-choices",
          heading: "4. Changing your mind",
          body: (
            <>
              <p>
                Use the <strong>Cookie settings</strong> link in the footer to change your
                choice at any time. Withdrawing consent removes the optional cookies on your
                next page load.
              </p>
              <p>
                Your browser can also block or delete cookies for this site directly. Doing
                so will sign you out and reset your theme preference, but every free tool
                will keep working.
              </p>
              <p>
                We honour <strong>Global Privacy Control</strong>. If your browser sends the
                GPC signal we treat it as a refusal of the analytics and marketing
                categories, and never ask again.
              </p>
            </>
          ),
        },
        {
          id: "changes",
          heading: "5. Changes to this policy",
          body: (
            <p>
              If we add a cookie, it appears on this page before it is set, and the
              &quot;last updated&quot; date above changes. Adding anything to the analytics
              or marketing categories re-prompts everyone for consent rather than assuming
              an old answer still applies.
            </p>
          ),
        },
      ]}
    />
  );
}
