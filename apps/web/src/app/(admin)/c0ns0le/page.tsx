import type { Metadata } from "next";

import { OverviewScreen } from "@/components/admin/screens/overview-screen";
import { RequireStaff } from "@/components/admin/require-staff";

export const metadata: Metadata = { title: "Overview" };

export default function AdminOverviewPage() {
  return (
    <RequireStaff permissions={["analytics.view"]}>
      <OverviewScreen />
    </RequireStaff>
  );
}
