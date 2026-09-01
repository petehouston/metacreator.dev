import type { Metadata } from "next";

import { RequireStaff } from "@/components/admin/require-staff";
import { UsersScreen } from "@/components/admin/screens/users-screen";

export const metadata: Metadata = { title: "Users" };

export default function AdminUsersPage() {
  return (
    <RequireStaff permissions={["users.view_any"]}>
      <UsersScreen />
    </RequireStaff>
  );
}
