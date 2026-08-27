import type { Metadata } from "next";
import Link from "next/link";

import { LegalPage } from "@/components/legal/legal-page";

export const metadata: Metadata = {
  title: "Security",
  description:
    "How MetaCreator.Dev protects accounts and data: password hashing, session handling, encryption, uploads, and how to report a vulnerability.",
  alternates: { canonical: "/security" },
};

export default function SecurityPage() {
  return (
    <LegalPage
      title="Security"
      updatedAt="26 August 2026"
      intro={
        <p>
          This page describes what we actually do, not what we aspire to. If something here
          stops being true, this page changes with it. To report a vulnerability, skip to{" "}
          <Link href="#reporting">reporting a vulnerability</Link>.
        </p>
      }
      sections={[
        {
          id: "accounts",
          heading: "1. Accounts and sign-in",
          body: (
            <ul>
              <li>
                Passwords are hashed with <strong>Argon2id</strong>, the current password
                hashing competition winner. We never store, log or email a password, and we
                cannot tell you what yours is.
              </li>
              <li>
                Sign-in is throttled by <strong>email address and IP address separately</strong>,
                so someone spraying one account cannot lock its owner out.
              </li>
              <li>
                Magic links are single-use, expire in 15 minutes, and are stored hashed. A
                stolen database backup does not yield working links.
              </li>
              <li>
                Changing a password <strong>invalidates every other session</strong>. Sign-in
                and password-reset responses are deliberately identical whether or not the
                address exists, so neither confirms an account to a stranger.
              </li>
              <li>
                Sensitive actions require re-entering your password even when you are already
                signed in — a borrowed unlocked browser is not enough to take an account over.
              </li>
              <li>
                Your dashboard lists every active session with its device and location, and
                you can revoke any of them individually.
              </li>
            </ul>
          ),
        },
        {
          id: "sessions",
          heading: "2. Sessions and the browser",
          body: (
            <ul>
              <li>
                Authentication uses <strong>HttpOnly, Secure, SameSite cookies</strong>, not
                tokens in local storage. A cross-site scripting bug cannot read a cookie
                JavaScript is not allowed to see.
              </li>
              <li>Every state-changing request carries a CSRF token bound to the session.</li>
              <li>
                A strict <strong>Content Security Policy</strong> restricts where scripts,
                frames and images may come from. Article embeds are limited to a named list
                of providers.
              </li>
              <li>
                HTTPS is enforced with HSTS. There is no unencrypted route into the
                application.
              </li>
            </ul>
          ),
        },
        {
          id: "data",
          heading: "3. Data handling",
          body: (
            <ul>
              <li>
                Data is encrypted in transit (TLS 1.2+) and at rest. Third-party API
                credentials are additionally encrypted at the application layer, so they are
                unreadable in a database dump.
              </li>
              <li>
                <strong>We do not store your IP address.</strong> Rate limiting and unique
                counts use a daily-rotating HMAC that cannot be reversed and is useless the
                next day.
              </li>
              <li>
                Tool inputs are not retained unless a tool says so and you agree. What we keep
                by default is which tool ran, when, whether it succeeded and how long it took.
              </li>
              <li>
                Uploads go to private storage, are served only through short-lived signed
                URLs, and are deleted automatically after 30 days.
              </li>
              <li>
                Database backups are encrypted and tested by restoring them — an untested
                backup is not a backup.
              </li>
            </ul>
          ),
        },
        {
          id: "content",
          heading: "4. User-submitted content",
          body: (
            <p>
              Anything that will be rendered as markup — article content, custom HTML blocks —
              is sanitised against an allow-list when it is saved <strong>and again when it
              is rendered</strong>. Sanitising twice matters: the rules can be tightened after
              something was already stored under looser ones, and stored content is treated as
              untrusted regardless of who submitted it. Scripts, event handlers and inline
              styles are removed, and iframes are restricted to named providers.
            </p>
          ),
        },
        {
          id: "engineering",
          heading: "5. How we build",
          body: (
            <ul>
              <li>
                Dependencies are scanned for known vulnerabilities on every build, and the
                build fails on a high-severity advisory.
              </li>
              <li>
                Static analysis and automated tests run on every change. Nothing reaches
                production without passing both.
              </li>
              <li>
                Administrative access uses granular, role-based permissions — each action
                requires its own permission rather than a blanket &quot;admin&quot; flag — and
                privileged actions are written to an audit log.
              </li>
              <li>Production access requires multi-factor authentication.</li>
            </ul>
          ),
        },
        {
          id: "reporting",
          heading: "6. Reporting a vulnerability",
          body: (
            <>
              <p>
                Email <strong>security@metacreator.dev</strong> with enough detail to
                reproduce the issue. We aim to acknowledge within{" "}
                <strong>two business days</strong> and to tell you our assessment and expected
                fix timeline within ten.
              </p>
              <p>
                We will not pursue legal action against anyone who reports in good faith,
                stays within their own test account, does not access or modify other people&apos;s
                data, does not degrade the service, and gives us a reasonable window to fix
                the issue before publishing.
              </p>
              <p>
                We do not currently run a paid bounty programme, but we credit every reporter
                who wants to be named.
              </p>
            </>
          ),
        },
      ]}
    />
  );
}
