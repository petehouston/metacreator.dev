import type { Metadata } from "next";

import { AppPageHeader } from "@/components/app/page-header";
import { SupportPanel } from "@/components/app/support-panel";
import { contactEmail } from "@/lib/site-settings";

export const metadata: Metadata = { title: "Help & support" };

export default async function SupportPage() {
  const email = await contactEmail();

  return (
    <>
      <AppPageHeader
        eyebrow="Help"
        title="Help & support"
        description="Answers to the questions we get most, and how to reach a human."
      />
      <SupportPanel contactEmail={email} />
    </>
  );
}
