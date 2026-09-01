import type { Metadata } from "next";

import { RequireStaff } from "@/components/admin/require-staff";
import { BillingPlansScreen } from "@/components/admin/screens/billing-plans-screen";

export const metadata: Metadata = { title: "Plans" };

export default function AdminPlansPage() {
  return (
    <RequireStaff permissions={["plans.view_any"]}>
      <BillingPlansScreen />
    </RequireStaff>
  );
}
