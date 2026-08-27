import type { Metadata } from "next";

import { Overview } from "@/components/app/overview";
import { AppPageHeader } from "@/components/app/page-header";

export const metadata: Metadata = { title: "Overview" };

export default function DashboardPage() {
  return (
    <>
      <AppPageHeader
        eyebrow="Workspace"
        title="Overview"
        description="Your plan, today's usage and where you left off."
      />
      <Overview />
    </>
  );
}
