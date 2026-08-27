import type { Metadata } from "next";

import { AppPageHeader } from "@/components/app/page-header";
import { SupportPanel } from "@/components/app/support-panel";

export const metadata: Metadata = { title: "Help & support" };

export default function SupportPage() {
  return (
    <>
      <AppPageHeader
        eyebrow="Help"
        title="Help & support"
        description="Answers to the questions we get most, and how to reach a human."
      />
      <SupportPanel />
    </>
  );
}
