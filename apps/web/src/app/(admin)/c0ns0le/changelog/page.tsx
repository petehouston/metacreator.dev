import type { Metadata } from "next";

import { RequireStaff } from "@/components/admin/require-staff";
import { ChangelogScreen } from "@/components/admin/screens/changelog-screen";

export const metadata: Metadata = { title: "Changelog" };

export default function AdminChangelogPage() {
  return (
    <RequireStaff permissions={["changelog.view_any"]}>
      <ChangelogScreen />
    </RequireStaff>
  );
}
