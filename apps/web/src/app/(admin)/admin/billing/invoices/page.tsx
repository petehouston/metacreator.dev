import type { Metadata } from "next";

import { RequireStaff } from "@/components/admin/require-staff";
import { BillingInvoicesScreen } from "@/components/admin/screens/billing-invoices-screen";

export const metadata: Metadata = { title: "Invoices" };

export default function AdminInvoicesPage() {
  return (
    <RequireStaff permissions={["invoices.view_any"]}>
      <BillingInvoicesScreen />
    </RequireStaff>
  );
}
