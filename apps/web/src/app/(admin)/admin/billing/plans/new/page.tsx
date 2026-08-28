import type { Metadata } from "next";

import { RequireStaff } from "@/components/admin/require-staff";
import { BillingPlanEditorScreen } from "@/components/admin/screens/billing-plan-editor-screen";

export const metadata: Metadata = { title: "New plan" };

export default function AdminNewPlanPage() {
  return (
    <RequireStaff permissions={["plans.create"]}>
      <BillingPlanEditorScreen />
    </RequireStaff>
  );
}
