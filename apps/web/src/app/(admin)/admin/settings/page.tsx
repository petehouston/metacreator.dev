import type { Metadata } from "next";

import { RequireStaff } from "@/components/admin/require-staff";
import { SettingsScreen } from "@/components/admin/screens/settings-screen";

export const metadata: Metadata = { title: "Settings" };

export default function AdminSettingsPage() {
  return (
    <RequireStaff permissions={["settings.view"]}>
      <SettingsScreen />
    </RequireStaff>
  );
}
