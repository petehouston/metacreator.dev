import type { Metadata } from "next";

import { RequireStaff } from "@/components/admin/require-staff";
import { TopRankingsScreen } from "@/components/admin/screens/top-rankings-screen";

export const metadata: Metadata = { title: "Top rankings" };

export default function AdminTopRankingsPage() {
  return (
    <RequireStaff permissions={["top_rankings.view_any"]}>
      <TopRankingsScreen />
    </RequireStaff>
  );
}
