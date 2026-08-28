import type { Metadata } from "next";

import { RequireStaff } from "@/components/admin/require-staff";
import { RoleEditorScreen } from "@/components/admin/screens/role-editor-screen";

export const metadata: Metadata = { title: "New role" };

export default function AdminNewRolePage() {
  return (
    <RequireStaff permissions={["roles.manage"]}>
      <RoleEditorScreen />
    </RequireStaff>
  );
}
