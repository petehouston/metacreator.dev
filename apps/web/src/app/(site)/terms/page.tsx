import type { Metadata } from "next";

import { LegalPage } from "@/components/legal/legal-page";
import { siteConfig } from "@/config/site";

export const metadata: Metadata = {
  title: "Terms of Service",
  description: `The terms that govern your use of ${siteConfig.name}.`,
  alternates: { canonical: "/terms" },
  robots: { index: true, follow: true },
};

export default function TermsPage() {
  return (
    <LegalPage
      title="Terms of Service"
      updatedAt="25 August 2026"
      intro={
        <p>
          These terms govern your use of {siteConfig.name}. They are written to be readable;
          where something is genuinely a legal obligation we say so plainly rather than
          burying it. By using the service you agree to them.
        </p>
      }
      sections={[
        {
          id: "the-service",
          heading: "1. The service",
          body: (
            <>
              <p>
                {siteConfig.name} provides web-based tools for analysing and planning social
                media content. Some tools are free to use without an account, some require a
                free account, and some require an active paid subscription. We may change
                which tools sit in which tier, and we will not remove a tool you are actively
                paying for without notice and a comparable replacement or a refund.
              </p>
              <p>
                We are not affiliated with, endorsed by, or sponsored by YouTube, Google,
                Meta, Instagram, TikTok, X, or LinkedIn. All trademarks belong to their
                respective owners.
              </p>
            </>
          ),
        },
        {
          id: "accounts",
          heading: "2. Your account",
          body: (
            <>
              <p>
                You must be at least 16 years old, provide an accurate email address, and keep
                your credentials secure. You are responsible for activity under your account.
              </p>
              <p>
                <strong>Your email address cannot be changed</strong> once an account is
                created — it is the account&apos;s identity. If you genuinely need a different
                address, contact support and we will perform an audited transfer.
              </p>
            </>
          ),
        },
        {
          id: "acceptable-use",
          heading: "3. Acceptable use",
          body: (
            <>
              <p>You agree not to:</p>
              <ul>
                <li>Use the service to violate any platform&apos;s terms of service.</li>
                <li>
                  Attempt to circumvent access tiers, rate limits or quotas, including by
                  creating multiple accounts.
                </li>
                <li>
                  Resell, white-label or provide automated access to our tools without a
                  written agreement.
                </li>
                <li>
                  Upload content you do not have the rights to, or content that is unlawful.
                </li>
                <li>
                  Probe, scan or disrupt the service. Good-faith security research is
                  welcome — see our security page for how to report findings.
                </li>
              </ul>
              <p>
                We may suspend an account that breaches these terms. Where the breach is
                accidental, we will tell you what happened and give you a chance to fix it.
              </p>
            </>
          ),
        },
        {
          id: "payment",
          heading: "4. Payment, renewal and refunds",
          body: (
            <>
              <p>
                Subscriptions renew automatically at the interval you chose until cancelled.
                You can cancel at any time from your billing page; access continues to the end
                of the period you have already paid for, and we do not pro-rate partial
                periods.
              </p>
              <p>
                The 7-day pass is a one-off payment. It does not renew and nothing is charged
                again.
              </p>
              <p>
                If a tool did not work and we could not fix it, email us within 14 days and we
                will refund you. We would rather refund a frustrated customer than argue.
              </p>
            </>
          ),
        },
        {
          id: "your-content",
          heading: "5. Your content",
          body: (
            <p>
              You keep all rights to everything you put into the service. We use it only to
              produce the result you asked for, and to operate and secure the service. We do
              not sell it, and we do not use it to train models. See the{" "}
              <a href="/privacy">privacy policy</a> for retention periods.
            </p>
          ),
        },
        {
          id: "availability",
          heading: "6. Availability and warranties",
          body: (
            <p>
              We work hard to keep the service fast and available, but it is provided
              &ldquo;as is&rdquo; without warranties of any kind. Tool outputs are estimates
              and guidance, not guarantees of performance — benchmarks in particular are
              medians drawn from published studies, and your niche may differ substantially.
            </p>
          ),
        },
        {
          id: "liability",
          heading: "7. Limitation of liability",
          body: (
            <p>
              To the maximum extent permitted by law, our total liability for any claim
              relating to the service is limited to the amount you paid us in the twelve
              months before the claim arose. We are not liable for indirect or consequential
              losses, including lost revenue or lost followers.
            </p>
          ),
        },
        {
          id: "changes",
          heading: "8. Changes to these terms",
          body: (
            <p>
              We may update these terms. For material changes we will email account holders at
              least 14 days before they take effect, and the date at the top of this page will
              change. Continuing to use the service after that means you accept the new terms.
            </p>
          ),
        },
      ]}
    />
  );
}
