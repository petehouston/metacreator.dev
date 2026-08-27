import type { Metadata } from "next";

import { ActivityScreen } from "@/components/admin/screens/activity-screen";
import { RequireStaff } from "@/components/admin/require-staff";

export const metadata: Metadata = { title: "Audit log" };

export default function AdminActivityPage() {
  return (
    <RequireStaff permissions={["activity_log.view"]}>
      <ActivityScreen />
    </RequireStaff>
  );
}
