import type { Metadata } from "next";

import { LegalPage } from "@/components/legal/legal-page";
import { siteConfig } from "@/config/site";

export const metadata: Metadata = {
  title: "Privacy Policy",
  description:
    "What we collect, what we deliberately do not collect, how long we keep it, and how to get it all back or deleted.",
  alternates: { canonical: "/privacy" },
};

export default function PrivacyPage() {
  return (
    <LegalPage
      title="Privacy Policy"
      updatedAt="25 August 2026"
      intro={
        <p>
          The short version: we collect as little as we can get away with, we do not store
          your IP address, we do not sell anything to anyone, and you can export or delete
          everything yourself from your dashboard. The rest of this page is the detail.
        </p>
      }
      sections={[
        {
          id: "what-we-collect",
          heading: "1. What we collect",
          body: (
            <>
              <p>
                <strong>If you have an account:</strong> your email address, display name,
                optional avatar, locale and timezone, and your subscription and invoice
                records.
              </p>
              <p>
                <strong>When you use a tool:</strong> which tool, when, whether it succeeded,
                how long it took, and a one-way hash of your input. The input itself is not
                stored unless the tool explicitly says so and you agree.
              </p>
              <p>
                <strong>Rather than your IP address:</strong> a rotating daily HMAC of your IP
                and browser user-agent. This lets us count unique visitors for one day and
                enforce fair-use limits. It cannot be reversed, and it is useless the next
                day. <strong>We do not write your IP address to our database.</strong>
              </p>
              <p>
                <strong>If you upload a file:</strong> the file, in private storage, served
                only through short-lived signed links, deleted automatically after 30 days.
              </p>
            </>
          ),
        },
        {
          id: "what-we-dont",
          heading: "2. What we deliberately do not do",
          body: (
            <ul>
              <li>We do not sell or rent personal data. Ever.</li>
              <li>We do not use your content to train machine-learning models.</li>
              <li>We do not request write access to any social account.</li>
              <li>
                We do not load third-party analytics or advertising scripts before you consent
                to them.
              </li>
              <li>We do not build advertising profiles.</li>
            </ul>
          ),
        },
        {
          id: "cookies",
          heading: "3. Cookies and tracking",
          body: (
            <>
              <p>
                <strong>Necessary cookies</strong> keep you signed in and protect forms against
                cross-site request forgery. These cannot be turned off without breaking the
                site, and they set no third-party data.
              </p>
              <p>
                <strong>Analytics and marketing tags</strong> — where enabled — load only after
                you accept them in the consent banner. Declining costs you nothing; every tool
                works identically either way.
              </p>
            </>
          ),
        },
        {
          id: "processors",
          heading: "4. Who else touches your data",
          body: (
            <>
              <p>We use a small number of processors, each for one specific job:</p>
              <ul>
                <li>
                  <strong>Stripe</strong> — payments. Card details go directly to Stripe and
                  never reach our servers.
                </li>
                <li>
                  <strong>Mailgun</strong> — transactional email such as receipts and password
                  resets.
                </li>
                <li>
                  <strong>DigitalOcean</strong> — hosting and file storage.
                </li>
                <li>
                  <strong>Sentry</strong> — error reporting, with passwords, tokens and card
                  data scrubbed before anything is sent.
                </li>
                <li>
                  <strong>Your newsletter provider</strong> — only if you subscribe, and only
                  after you confirm.
                </li>
              </ul>
            </>
          ),
        },
        {
          id: "retention",
          heading: "5. How long we keep things",
          body: (
            <ul>
              <li>Tool run records: 90 days, then aggregated into anonymous statistics.</li>
              <li>Uploaded files and generated artifacts: 30 days.</li>
              <li>Support tickets: 2 years after closure.</li>
              <li>
                Invoices: 7 years, because tax law requires it. If you delete your account,
                these are kept with the personal fields redacted.
              </li>
              <li>
                A deleted account: 30 days of recoverability, then permanently purged.
              </li>
            </ul>
          ),
        },
        {
          id: "your-rights",
          heading: "6. Your rights",
          body: (
            <>
              <p>
                You can access, correct, export or delete your data. Export and deletion are
                self-serve from <strong>Dashboard → Privacy</strong> — no email to us, no
                waiting, no retention-offer gauntlet.
              </p>
              <p>
                If you are in the EEA or UK you also have the right to object to processing and
                to complain to your supervisory authority. If you are in California, you have
                the right to know, delete, and opt out of sale — we do not sell data, so the
                last one is already satisfied.
              </p>
            </>
          ),
        },
        {
          id: "security",
          heading: "7. Security",
          body: (
            <p>
              Everything is encrypted in transit. Passwords are hashed with Argon2id. Access to
              production data is limited and audited. If a breach affects your personal data we
              will tell you and the relevant authority within 72 hours of becoming aware of it.
              To report a vulnerability, email{" "}
              <a href="mailto:security@metacreator.dev">security@metacreator.dev</a>.
            </p>
          ),
        },
        {
          id: "contact",
          heading: "8. Contact",
          body: (
            <p>
              Questions about this policy, or about data we hold on you, go to{" "}
              <a href={`mailto:${siteConfig.supportEmail}`}>{siteConfig.supportEmail}</a>.
            </p>
          ),
        },
      ]}
    />
  );
}
