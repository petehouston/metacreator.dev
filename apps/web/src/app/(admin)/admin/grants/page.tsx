import type { Metadata } from "next";

import { RequireStaff } from "@/components/admin/require-staff";
import { GrantsScreen } from "@/components/admin/screens/grants-screen";

export const metadata: Metadata = { title: "Tool grants" };

export default function AdminGrantsPage() {
  return (
    <RequireStaff permissions={["tool_grants.view_any"]}>
      <GrantsScreen />
    </RequireStaff>
  );
}
