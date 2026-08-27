import type { Metadata } from "next";

import { AppPageHeader } from "@/components/app/page-header";
import { ToolBrowser } from "@/components/app/tool-browser";

export const metadata: Metadata = { title: "Tools" };

export default function DashboardToolsPage() {
  return (
    <>
      <AppPageHeader
        eyebrow="Workspace"
        title="Tools"
        description="The whole catalog, filtered by what you can run. Press ⌘K anywhere to search it."
      />
      <ToolBrowser />
    </>
  );
}
