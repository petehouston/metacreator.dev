import type { Metadata } from "next";

import { RequireStaff } from "@/components/admin/require-staff";
import { RolesScreen } from "@/components/admin/screens/roles-screen";

export const metadata: Metadata = { title: "Roles & permissions" };

export default function AdminRolesPage() {
  return (
    <RequireStaff permissions={["roles.view_any"]}>
      <RolesScreen />
    </RequireStaff>
  );
}
