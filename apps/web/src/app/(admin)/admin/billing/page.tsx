import type { Metadata } from "next";

import { BillingScreen } from "@/components/admin/screens/billing-screen";
import { RequireStaff } from "@/components/admin/require-staff";

export const metadata: Metadata = { title: "Billing" };

export default function AdminBillingPage() {
  return (
    <RequireStaff permissions={["invoices.view_any", "subscriptions.view_any", "plans.view_any"]}>
      <BillingScreen />
    </RequireStaff>
  );
}
