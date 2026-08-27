import type { Metadata } from "next";

import { RequireStaff } from "@/components/admin/require-staff";
import { ToolsScreen } from "@/components/admin/screens/tools-screen";

export const metadata: Metadata = { title: "Tools" };

export default function AdminToolsPage() {
  return (
    <RequireStaff permissions={["tools.view_any"]}>
      <ToolsScreen />
    </RequireStaff>
  );
}
