import type { Metadata } from "next";

import { RequireStaff } from "@/components/admin/require-staff";
import { RoleEditorScreen } from "@/components/admin/screens/role-editor-screen";

export const metadata: Metadata = { title: "Edit role" };

export default async function AdminEditRolePage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  return (
    <RequireStaff permissions={["roles.view_any"]}>
      <RoleEditorScreen id={Number(id)} />
    </RequireStaff>
  );
}
