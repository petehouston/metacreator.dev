import type { Metadata } from "next";

import { RequireStaff } from "@/components/admin/require-staff";
import { AnalyticsScreen } from "@/components/admin/screens/analytics-screen";

export const metadata: Metadata = { title: "Analytics" };

export default function AdminAnalyticsPage() {
  return (
    <RequireStaff permissions={["analytics.view", "tool_analytics.view"]}>
      <AnalyticsScreen />
    </RequireStaff>
  );
}
