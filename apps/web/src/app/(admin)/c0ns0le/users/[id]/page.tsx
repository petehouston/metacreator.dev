import type { Metadata } from "next";

import { RequireStaff } from "@/components/admin/require-staff";
import { UserDetailScreen } from "@/components/admin/screens/user-detail-screen";

export const metadata: Metadata = { title: "User" };

export default async function AdminUserPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  return (
    <RequireStaff permissions={["users.view"]}>
      <UserDetailScreen id={id} />
    </RequireStaff>
  );
}
