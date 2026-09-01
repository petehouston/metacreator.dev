import type { Metadata } from "next";
import { notFound } from "next/navigation";

import { BillingPanel } from "@/components/app/billing-panel";
import { AppPageHeader } from "@/components/app/page-header";
import { contactEmail, siteFeatures } from "@/lib/site-settings";

export const metadata: Metadata = { title: "Plan & billing" };

/**
 * Gone, not empty, when billing is off. There is no plan to be on, no invoice to
 * read and no card to change, and the sidebar has already dropped the link — a
 * screen still reachable by typing the URL would be the one place left in the app
 * that talks about paying.
 */
export default async function BillingPage() {
  const { billingEnabled } = await siteFeatures();

  if (!billingEnabled) notFound();

  const email = await contactEmail();

  return (
    <>
      <AppPageHeader
        eyebrow="Account"
        title="Plan & billing"
        description="What you are on, what it includes, and how much of it you have used."
      />
      <BillingPanel contactEmail={email} />
    </>
  );
}
