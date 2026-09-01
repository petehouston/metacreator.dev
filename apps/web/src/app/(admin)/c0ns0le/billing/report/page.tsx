import type { Metadata } from "next";

import { RequireStaff } from "@/components/admin/require-staff";
import { BillingReportScreen } from "@/components/admin/screens/billing-report-screen";

export const metadata: Metadata = { title: "Billing report" };

export default function AdminBillingReportPage() {
  return (
    <RequireStaff permissions={["invoices.view_any"]}>
      <BillingReportScreen />
    </RequireStaff>
  );
}
