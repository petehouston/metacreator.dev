import type { Metadata } from "next";

import { BillingPanel } from "@/components/app/billing-panel";
import { AppPageHeader } from "@/components/app/page-header";

export const metadata: Metadata = { title: "Plan & billing" };

export default function BillingPage() {
  return (
    <>
      <AppPageHeader
        eyebrow="Account"
        title="Plan & billing"
        description="What you are on, what it includes, and how much of it you have used."
      />
      <BillingPanel />
    </>
  );
}
