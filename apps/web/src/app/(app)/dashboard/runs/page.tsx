import type { Metadata } from "next";

import { AppPageHeader } from "@/components/app/page-header";
import { RunHistory } from "@/components/app/run-history";

export const metadata: Metadata = { title: "Run history" };

export default function RunsPage() {
  return (
    <>
      <AppPageHeader
        eyebrow="Workspace"
        title="Run history"
        description="Every tool you've run, newest first. Open a row for its result, timings and run ID."
      />
      <RunHistory />
    </>
  );
}
