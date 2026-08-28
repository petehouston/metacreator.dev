import type { Metadata } from "next";
import { notFound } from "next/navigation";

import { RequireStaff } from "@/components/admin/require-staff";
import { BillingPlanEditorScreen } from "@/components/admin/screens/billing-plan-editor-screen";

export const metadata: Metadata = { title: "Plan" };

export default async function AdminPlanPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;

  // `/plans/new` has its own route, so anything non-numeric reaching here is a
  // mistyped URL rather than a plan — 404 instead of requesting `/plans/NaN`.
  if (!/^\d+$/.test(id)) notFound();

  return (
    <RequireStaff permissions={["plans.view_any"]}>
      <BillingPlanEditorScreen id={Number(id)} />
    </RequireStaff>
  );
}
