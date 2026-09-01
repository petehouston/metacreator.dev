import type { Metadata } from "next";

import { RequireStaff } from "@/components/admin/require-staff";
import { ToolEditorScreen } from "@/components/admin/screens/tool-editor-screen";

export const metadata: Metadata = { title: "Edit tool" };

export default async function AdminEditToolPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;

  return (
    <RequireStaff permissions={["tools.view", "tools.view_any"]}>
      <ToolEditorScreen slug={slug} />
    </RequireStaff>
  );
}
