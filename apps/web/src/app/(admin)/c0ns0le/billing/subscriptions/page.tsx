import type { Metadata } from "next";

import { RequireStaff } from "@/components/admin/require-staff";
import { BillingSubscriptionsScreen } from "@/components/admin/screens/billing-subscriptions-screen";

export const metadata: Metadata = { title: "Subscriptions" };

export default function AdminSubscriptionsPage() {
  return (
    <RequireStaff permissions={["subscriptions.view_any"]}>
      <BillingSubscriptionsScreen />
    </RequireStaff>
  );
}
